"""
Document Quality Checker - Accuracy Evaluation
================================================
Generates a large set of synthetic document images with known ground-truth
labels, runs each through the classify() function, and computes standard
ML classification metrics (precision, recall, F1, confusion matrix).

Usage:
    python tests/test_accuracy.py                  # Default: 10 images per category
    python tests/test_accuracy.py --samples 20     # 20 images per category
    python tests/test_accuracy.py --verbose         # Print every sample
    python tests/test_accuracy.py --output custom.json
"""

import argparse
import hashlib
import json
import os
import sys
import time
from collections import defaultdict
from datetime import datetime

import cv2
import numpy as np

sys.path.insert(0, os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "scripts"))
import check_blur

ALL_CLASSES = ["clear", "flagged", "blurry"]


# ---------------------------------------------------------------------------
# Image generators (each returns a numpy BGR image)
# ---------------------------------------------------------------------------

def _base_text_image(width=800, height=600, rng=None):
    """Base image with dense text-like horizontal lines."""
    if rng is None:
        rng = np.random.default_rng()
    img = np.ones((height, width, 3), dtype=np.uint8) * 255
    for y in range(30, height - 30, 18):
        thickness = int(rng.integers(1, 3))
        length = int(rng.integers(width // 4, width - 80))
        x_start = int(rng.integers(40, max(41, width - length - 40)))
        darkness = int(rng.integers(0, 60))
        cv2.line(img, (x_start, y), (x_start + length, y),
                 (darkness, darkness, darkness), thickness)
    cv2.line(img, (40, 20), (width - 40, 20), (0, 0, 0), 2)
    cv2.line(img, (40, height - 20), (width - 40, height - 20), (0, 0, 0), 2)
    return img


def gen_dense_text(rng, w=800, h=600):
    """CLEAR: Dense text document with paragraphs."""
    img = _base_text_image(w, h, rng)
    for _ in range(8):
        x = int(rng.integers(30, max(31, w - 150)))
        y = int(rng.integers(30, max(31, h - 100)))
        bw = int(rng.integers(80, 200))
        bh = int(rng.integers(15, 50))
        cv2.rectangle(img, (x, y), (x + bw, y + bh), (0, 0, 0), 1)
    for _ in range(3):
        x = int(rng.integers(50, max(51, w - 100)))
        y = int(rng.integers(50, max(51, h - 80)))
        bw = int(rng.integers(40, 80))
        bh = int(rng.integers(20, 40))
        shade = int(rng.integers(160, 220))
        cv2.rectangle(img, (x, y), (x + bw, y + bh), (shade, shade, shade), -1)
    return img


def gen_form_table(rng, w=800, h=600):
    """CLEAR: Form with boxes simulating form fields."""
    img = np.ones((h, w, 3), dtype=np.uint8) * 255
    cv2.rectangle(img, (20, 20), (w - 20, h - 20), (0, 0, 0), 2)
    row_h = 45
    for row_y in range(60, h - 60, row_h):
        cv2.line(img, (40, row_y), (w - 40, row_y), (0, 0, 0), 1)
        for col_x in range(60, w - 60, 180):
            length = int(rng.integers(40, 120))
            cv2.line(img, (col_x, row_y + 15), (col_x + length, row_y + 15), (0, 0, 0), 1)
    for x in range(220, w - 60, 180):
        cv2.line(img, (x, 60), (x, h - 60), (0, 0, 0), 1)
    return img


def gen_mixed_content(rng, w=800, h=600):
    """CLEAR: Mixed text + photo-like area."""
    img = gen_dense_text(rng, w, h)
    px, py = int(w * 0.6), 40
    pw, ph = int(w * 0.35), int(h * 0.35)
    patch = rng.integers(20, 80, size=(ph, pw, 3), dtype=np.uint8)
    img[py:py + ph, px:px + pw] = patch
    return img


def gen_high_contrast_print(rng, w=800, h=600):
    """CLEAR: Bold black text on pure white background."""
    img = np.ones((h, w, 3), dtype=np.uint8) * 255
    for y in range(40, h - 40, 22):
        length = int(rng.integers(w // 3, w - 60))
        x = int(rng.integers(30, max(31, w - length - 30)))
        cv2.line(img, (x, y), (x + length, y), (0, 0, 0), 2)
    cv2.rectangle(img, (30, 15), (w - 30, 35), (0, 0, 0), -1)
    cv2.rectangle(img, (30, h - 35), (w - 30, h - 15), (0, 0, 0), -1)
    return img


def gen_multi_font_document(rng, w=800, h=600):
    """CLEAR: Varying line thicknesses and spacings."""
    img = np.ones((h, w, 3), dtype=np.uint8) * 255
    y = 30
    while y < h - 30:
        thickness = int(rng.integers(1, 4))
        gap = int(rng.integers(12, 28))
        length = int(rng.integers(w // 4, w - 60))
        x = int(rng.integers(30, max(31, w - length - 30)))
        gray_val = int(rng.integers(0, 40))
        cv2.line(img, (x, y), (x + length, y), (gray_val, gray_val, gray_val), thickness)
        y += gap
    return img


def gen_slightly_blurred(rng, w=800, h=600):
    """FLAGGED: Slightly blurred readable document."""
    img = gen_dense_text(rng, w, h)
    ksize = int(rng.choice([7, 9, 11, 13, 15]))
    return cv2.GaussianBlur(img, (ksize, ksize), 0)


def gen_low_contrast_readable(rng, w=800, h=600):
    """FLAGGED: Low contrast but still readable."""
    img = gen_dense_text(rng, w, h).astype(np.float64)
    scale = float(rng.uniform(0.35, 0.55))
    shift = float(rng.uniform(80, 110))
    img = np.clip(img * scale + shift, 0, 255).astype(np.uint8)
    return img


def gen_overexposed(rng, w=800, h=600):
    """FLAGGED: Overexposed / washed-out document."""
    img = gen_dense_text(rng, w, h).astype(np.float64)
    img = np.clip(img * 0.3 + 180, 0, 255).astype(np.uint8)
    return img


def gen_sparse_text(rng, w=800, h=600):
    """FLAGGED: Only a few lines on a full page."""
    img = np.ones((h, w, 3), dtype=np.uint8) * 255
    for i in range(int(rng.integers(3, 6))):
        y = int(rng.integers(100, h - 100))
        length = int(rng.integers(100, w // 2))
        x = int(rng.integers(40, max(41, w - length - 40)))
        cv2.line(img, (x, y), (x + length, y), (0, 0, 0), 1)
    return img


def gen_noisy_low_light(rng, w=800, h=600):
    """FLAGGED: Noisy + reduced brightness."""
    img = gen_dense_text(rng, w, h).astype(np.float64)
    img *= float(rng.uniform(0.55, 0.75))
    noise = rng.normal(0, float(rng.uniform(8, 18)), img.shape)
    img = np.clip(img + noise, 0, 255).astype(np.uint8)
    return img


def gen_severely_blurred(rng, w=800, h=600):
    """BLURRY: Heavy Gaussian blur."""
    img = gen_dense_text(rng, w, h)
    k1 = int(rng.choice([31, 41, 51]))
    k2 = int(rng.choice([21, 31]))
    img = cv2.GaussianBlur(img, (k1, k1), 0)
    img = cv2.GaussianBlur(img, (k2, k2), 0)
    return img


def gen_blank_page(rng, w=800, h=600):
    """BLURRY: Pure white or near-white page."""
    base = int(rng.uniform(248, 255))
    return np.ones((h, w, 3), dtype=np.uint8) * base


def gen_motion_blurred(rng, w=800, h=600):
    """BLURRY: Directional / motion blur."""
    img = gen_dense_text(rng, w, h)
    size = int(rng.choice([15, 21, 25, 31]))
    kernel = np.zeros((size, size), dtype=np.float32)
    if rng.random() > 0.5:
        kernel[size // 2, :] = 1.0 / size
    else:
        kernel[:, size // 2] = 1.0 / size
    return cv2.filter2D(img, -1, kernel)


def gen_extreme_underexposed(rng, w=800, h=600):
    """BLURRY: Near-black image (extreme underexposure)."""
    base = int(rng.uniform(0, 18))
    noise = rng.integers(0, 5, (h, w, 3), dtype=np.uint8)
    return np.clip(
        np.full((h, w, 3), base, dtype=np.int16) + noise.astype(np.int16),
        0, 255
    ).astype(np.uint8)


def gen_extreme_overexposed(rng, w=800, h=600):
    """BLURRY: Near-white image (extreme overexposure)."""
    base = int(rng.uniform(242, 255))
    return np.ones((h, w, 3), dtype=np.uint8) * base



# ---------------------------------------------------------------------------
# Category registry
# ---------------------------------------------------------------------------

CATEGORIES = {
    # CLEAR
    "clear_dense_text":        ("clear",   gen_dense_text),
    "clear_form_table":        ("clear",   gen_form_table),
    "clear_mixed_content":     ("clear",   gen_mixed_content),
    "clear_high_contrast":     ("clear",   gen_high_contrast_print),
    "clear_multi_font":        ("clear",   gen_multi_font_document),
    # FLAGGED
    "flagged_slight_blur":     ("flagged", gen_slightly_blurred),
    "flagged_low_contrast":    ("flagged", gen_low_contrast_readable),
    "flagged_overexposed":     ("flagged", gen_overexposed),
    "flagged_sparse_text":     ("flagged", gen_sparse_text),
    "flagged_noisy_low_light": ("flagged", gen_noisy_low_light),
    # BLURRY
    "blurry_severe_blur":      ("blurry",  gen_severely_blurred),
    "blurry_blank_page":       ("blurry",  gen_blank_page),
    "blurry_motion_blur":      ("blurry",  gen_motion_blurred),
    "blurry_underexposed":     ("blurry",  gen_extreme_underexposed),
    "blurry_overexposed":      ("blurry",  gen_extreme_overexposed),
}


# ---------------------------------------------------------------------------
# Metrics helpers (no sklearn dependency)
# ---------------------------------------------------------------------------

def build_confusion_matrix(y_true, y_pred, labels=ALL_CLASSES):
    n = len(labels)
    idx = {c: i for i, c in enumerate(labels)}
    cm = [[0] * n for _ in range(n)]
    for t, p in zip(y_true, y_pred):
        cm[idx[t]][idx[p]] += 1
    return cm


def compute_precision_recall_f1(cm, labels=ALL_CLASSES):
    n = len(labels)
    results = {}
    for i, c in enumerate(labels):
        tp = cm[i][i]
        fp = sum(cm[j][i] for j in range(n)) - tp
        fn = sum(cm[i][j] for j in range(n)) - tp
        support = sum(cm[i])
        prec = tp / (tp + fp) if (tp + fp) else 0.0
        rec = tp / (tp + fn) if (tp + fn) else 0.0
        f1 = 2 * prec * rec / (prec + rec) if (prec + rec) else 0.0
        results[c] = {"precision": prec, "recall": rec, "f1": f1, "support": support}
    return results


def accuracy_score(y_true, y_pred):
    return sum(1 for t, p in zip(y_true, y_pred) if t == p) / len(y_true)



# ---------------------------------------------------------------------------
# Runner
# ---------------------------------------------------------------------------

def run_accuracy(samples_per_category=10, seed=42, verbose=False):
    rng = np.random.default_rng(seed)
    per_sample = []
    y_true = []
    y_pred = []

    for cat_name, (expected_class, gen_fn) in CATEGORIES.items():
        for i in range(samples_per_category):
            img = gen_fn(rng)
            m = check_blur.metrics(img)
            predicted = check_blur.classify(m)
            y_true.append(expected_class)
            y_pred.append(predicted)
            per_sample.append({
                "category": cat_name,
                "expected": expected_class,
                "predicted": predicted,
                "correct": expected_class == predicted,
                "metrics": m,
            })
            if verbose:
                mark = "PASS" if expected_class == predicted else "FAIL"
                print(f"  {mark}  {cat_name:35s}  exp={expected_class:8s}  "
                      f"pred={predicted:8s}  lap={m['laplacian_std']:6.1f}  "
                      f"det={m['detail_ratio']:.4f}  ent={m['entropy']:.2f}")

    cm = build_confusion_matrix(y_true, y_pred)
    prf = compute_precision_recall_f1(cm)
    acc = accuracy_score(y_true, y_pred)
    total = len(y_true)

    macro_p = float(np.mean([prf[c]["precision"] for c in ALL_CLASSES]))
    macro_r = float(np.mean([prf[c]["recall"] for c in ALL_CLASSES]))
    macro_f = float(np.mean([prf[c]["f1"] for c in ALL_CLASSES]))
    w_p = sum(prf[c]["precision"] * prf[c]["support"] for c in ALL_CLASSES) / total
    w_r = sum(prf[c]["recall"] * prf[c]["support"] for c in ALL_CLASSES) / total
    w_f = sum(prf[c]["f1"] * prf[c]["support"] for c in ALL_CLASSES) / total

    return {
        "accuracy": acc,
        "total_samples": total,
        "samples_per_class": {c: prf[c]["support"] for c in ALL_CLASSES},
        "per_class": prf,
        "macro_avg": {"precision": macro_p, "recall": macro_r, "f1": macro_f},
        "weighted_avg": {"precision": float(w_p), "recall": float(w_r), "f1": float(w_f)},
        "confusion_matrix": cm,
        "per_sample": per_sample,
    }


# ---------------------------------------------------------------------------
# Report formatting
# ---------------------------------------------------------------------------

def print_report(results):
    print("=" * 74)
    print("Document Quality Checker -- Accuracy Report")
    print("=" * 74)
    total = results["total_samples"]
    sc = results["samples_per_class"]
    print(f"Samples: {total} | Clear: {sc['clear']} | "
          f"Flagged: {sc['flagged']} | Blurry: {sc['blurry']}")
    correct = int(round(results["accuracy"] * total))
    print(f"Overall Accuracy: {results['accuracy']:.4f}  ({correct}/{total} correct)")
    print()
    print("Per-Class Metrics:")
    print(f"{'':14s} {'Precision':>9s} {'Recall':>9s} {'F1-Score':>9s} {'Support':>9s}")
    print("-" * 52)
    for c in ALL_CLASSES:
        m = results["per_class"][c]
        print(f"  {c:12s} {m['precision']:9.4f} {m['recall']:9.4f} "
              f"{m['f1']:9.4f} {m['support']:9d}")
    print()
    ma = results["macro_avg"]
    wa = results["weighted_avg"]
    print(f"  {'accuracy':12s} {'':>9s} {'':>9s} "
          f"{results['accuracy']:9.4f} {total:9d}")
    print(f"  {'macro avg':12s} {ma['precision']:9.4f} {ma['recall']:9.4f} "
          f"{ma['f1']:9.4f} {total:9d}")
    print(f"  {'weighted avg':12s} {wa['precision']:9.4f} {wa['recall']:9.4f} "
          f"{wa['f1']:9.4f} {total:9d}")
    print()
    print("Confusion Matrix (rows=actual, cols=predicted):")
    header = f"{'':14s}" + "".join(f" {c:>9s}" for c in ALL_CLASSES)
    print(header)
    for i, c in enumerate(ALL_CLASSES):
        row = (f"  {c:12s}" +
               "".join(f" {results['confusion_matrix'][i][j]:9d}"
                       for j in range(len(ALL_CLASSES))))
        print(row)
    print()
    print("Per-Category Breakdown:")
    print(f"  {'Category':35s} {'Expected':>9s} {'Correct':>9s} {'Result':>8s}")
    print("  " + "-" * 63)
    by_cat = defaultdict(lambda: {"correct": 0, "total": 0})
    for s in results["per_sample"]:
        by_cat[s["category"]]["total"] += 1
        if s["correct"]:
            by_cat[s["category"]]["correct"] += 1
    for cat in CATEGORIES:
        info = by_cat[cat]
        expected = CATEGORIES[cat][0]
        pct = info["correct"] / info["total"] * 100 if info["total"] else 0
        mark = "PASS" if pct == 100 else ("WARN" if pct >= 70 else "FAIL")
        print(f"  {cat:35s} {expected:>9s} "
              f"{info['correct']}/{info['total']:>4d} {mark:>8s} ({pct:5.1f}%)")



def save_report(results, output_path, seed):
    thresholds = {
        "detail_severe": 0.012, "lap_severe": 30,
        "local_var_low": 200, "detail_low": 0.04,
        "lap_low": 65, "entropy_low": 2.5,
    }
    thresholds_hash = hashlib.sha256(
        json.dumps(thresholds, sort_keys=True).encode()
    ).hexdigest()[:12]

    report = {
        "report_title": "Document Quality Checker - Accuracy Evaluation",
        "generated_at": datetime.now().isoformat(),
        "configuration": {
            "random_seed": seed,
            "total_samples": results["total_samples"],
            "categories": len(CATEGORIES),
            "thresholds": thresholds,
            "thresholds_hash": thresholds_hash,
        },
        "summary": {
            "overall_accuracy": round(results["accuracy"], 4),
            "correct_count": int(round(results["accuracy"] * results["total_samples"])),
            "total_samples": results["total_samples"],
            "macro_f1": round(results["macro_avg"]["f1"], 4),
            "weighted_f1": round(results["weighted_avg"]["f1"], 4),
        },
        "per_class": {
            c: {k: round(v, 4) if isinstance(v, float) else v
                for k, v in results["per_class"][c].items()}
            for c in ALL_CLASSES
        },
        "macro_avg": {k: round(v, 4) for k, v in results["macro_avg"].items()},
        "weighted_avg": {k: round(v, 4) for k, v in results["weighted_avg"].items()},
        "confusion_matrix": {
            "labels": ALL_CLASSES,
            "matrix": results["confusion_matrix"],
        },
        "per_sample": [
            {"category": s["category"], "expected": s["expected"],
             "predicted": s["predicted"], "correct": s["correct"],
             "metrics": s["metrics"]}
            for s in results["per_sample"]
        ],
        "notes": [
            "Ground truth is based on synthetic generation parameters, not human expert labeling.",
            "Real-world scanned documents may have artifacts not captured by synthetic generation.",
            "The 'flagged' class is the decision boundary and inherently harder to predict.",
            "These metrics represent an upper-bound estimate; real-world accuracy may differ.",
        ],
    }
    os.makedirs(os.path.dirname(output_path) or ".", exist_ok=True)
    with open(output_path, "w", encoding="utf-8") as f:
        json.dump(report, f, indent=2, ensure_ascii=False)


# ---------------------------------------------------------------------------
# CLI
# ---------------------------------------------------------------------------

def main():
    parser = argparse.ArgumentParser(
        description="Accuracy evaluation for document quality checker"
    )
    parser.add_argument("--samples", "-n", type=int, default=10,
                        help="Images per category (default: 10)")
    parser.add_argument("--seed", "-s", type=int, default=42,
                        help="Random seed (default: 42)")
    parser.add_argument("--output", "-o", type=str, default=None,
                        help="JSON report path")
    parser.add_argument("--verbose", "-v", action="store_true",
                        help="Print per-sample metrics")
    args = parser.parse_args()

    default_output = os.path.join(
        os.path.dirname(os.path.abspath(__file__)),
        "reports", "accuracy_report.json"
    )
    output_path = args.output or default_output

    total_imgs = args.samples * len(CATEGORIES)
    print(f"Generating {args.samples} images x {len(CATEGORIES)} categories "
          f"= {total_imgs} total samples...")
    print(f"Random seed: {args.seed}")
    print()

    t0 = time.time()
    results = run_accuracy(
        samples_per_category=args.samples,
        seed=args.seed,
        verbose=args.verbose,
    )
    elapsed = time.time() - t0

    print_report(results)
    print()
    print(f"Elapsed: {elapsed:.1f}s")
    save_report(results, output_path, args.seed)
    print(f"\nReport saved to: {output_path}")

    if results["accuracy"] >= 0.80:
        print("\nResult: ACCEPTABLE (accuracy >= 80%)")
        return 0
    else:
        print("\nResult: NEEDS REVIEW (accuracy < 80%)")
        return 1


if __name__ == "__main__":
    sys.exit(main())

