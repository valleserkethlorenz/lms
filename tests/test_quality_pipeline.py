"""
Regression tests for the document quality pipeline (scripts/quality + check_blur).
=================================================================================

Covers the failures that made obviously blurry uploads pass registration:

* the same document must get the same verdict at any upload resolution
* out-of-focus, blank, all-black and illegible-thumbnail pages must be rejected
* OCR legibility and document-type expectations (with an injected fake engine,
  so the tests do not depend on rapidocr/Tesseract being installed)
* the command line contract used by includes/documentValidator.php

Usage:
    python tests/test_quality_pipeline.py            # run all tests
    python tests/test_quality_pipeline.py --verbose  # print measured values
"""

import argparse
import io
import json
import os
import sys
from contextlib import redirect_stdout

import cv2
import numpy as np

SCRIPTS = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "scripts")
sys.path.insert(0, SCRIPTS)
import check_blur  # noqa: E402
from quality import ocr as ocr_quality  # noqa: E402
from quality import page as page_quality  # noqa: E402

VERBOSE = False


def document(width=1600, height=2200, lines=34, text_scale=0.7):
    """A document-like page: dense text lines, a box and a stamp area."""
    image = np.full((height, width, 3), 248, np.uint8)
    rng = np.random.default_rng(7)
    step = max(12, int(height / (lines + 3)))
    for line in range(lines):
        y = step * (line + 1)
        for x in range(int(width * 0.06), int(width * 0.9), int(width * 0.16)):
            word = "".join(chr(int(rng.integers(65, 90))) for _ in range(int(rng.integers(4, 9))))
            cv2.putText(image, word, (x, y), cv2.FONT_HERSHEY_SIMPLEX, text_scale,
                        (25, 25, 25), 2, cv2.LINE_AA)
    cv2.rectangle(image, (int(width * 0.1), int(height * 0.84)),
                  (int(width * 0.45), int(height * 0.95)), (40, 40, 40), 3)
    return image


def photographed(page, pad_ratio=0.18, background=70):
    """The same page photographed on a darker desk (page covers ~64% of frame)."""
    frame_h = int(page.shape[0] * (1 + 2 * pad_ratio))
    frame_w = int(page.shape[1] * (1 + 2 * pad_ratio))
    desk = np.clip(np.full((frame_h, frame_w, 3), float(background))
                   + np.random.default_rng(11).normal(0, 9, (frame_h, frame_w, 3)),
                   0, 255).astype(np.uint8)
    top = int(page.shape[0] * pad_ratio)
    left = int(page.shape[1] * pad_ratio)
    desk[top:top + page.shape[0], left:left + page.shape[1]] = page
    return desk


class FakeEngine:
    """Stands in for rapidocr/pytesseract inside ocr.recognize()."""

    def __init__(self, words):
        self.words = words

    def __call__(self, image):
        return ([[[0, 0], [10, 0], [10, 10], [0, 10]], text, score] for text, score in self.words), 0.0


class CountingEngine:
    """Fake OCR engine that records how many pages it was asked to read."""

    def __init__(self, pages):
        self.pages = pages
        self.calls = 0

    def __call__(self, image):
        words = self.pages[min(self.calls, len(self.pages) - 1)]
        self.calls += 1
        return ([[[0, 0], [10, 0], [10, 10], [0, 10]], text, score] for text, score in words), 0.0


def run_check_file(pages, engine, document_type="Birth Certificate"):
    """Run check_file() with a stubbed page loader and OCR engine.

    Returns ``(payload, engine)`` so tests can assert how many OCR passes ran.
    """
    original_loader = check_blur.load_pages
    original_state = (ocr_quality._engine, ocr_quality._engine_name,
                      ocr_quality._engine_detail, ocr_quality._engine_resolved)
    check_blur.load_pages = lambda path: list(pages)
    ocr_quality._engine = engine
    ocr_quality._engine_name = "fake"
    ocr_quality._engine_detail = "ready"
    ocr_quality._engine_resolved = True
    try:
        buffer = io.StringIO()
        with redirect_stdout(buffer):
            check_blur.check_file("stub.jpg", document_type, ocr_mode="on")
        payload = json.loads(buffer.getvalue().strip().split("\n")[-1])
    finally:
        check_blur.load_pages = original_loader
        (ocr_quality._engine, ocr_quality._engine_name,
         ocr_quality._engine_detail, ocr_quality._engine_resolved) = original_state
    return payload, engine


SUFFICIENT_WORDS = [
    ("REPUBLIC OF THE PHILIPPINES", 96.0),
    ("BIRTH CERTIFICATE", 95.0),
    ("Name: JUAN DELA CRUZ", 94.0),
    ("Date of Birth: 1990-01-01", 93.0),
    ("Registry Number 2026-000123", 92.0),
]
GARBAGE_WORDS = [("...", 18.0), ("###", 12.0)]


def status_of(image, document_type=None, ocr="off", engine=None):
    page = check_blur.analyse_page(image, document_type, ocr_mode=ocr, ocr_engine=engine)
    if VERBOSE:
        values = page["metrics"]
        print("      rel=%.2f p10=%.2f contrast=%.3f ink=%.4f text_h=%s -> %s" % (
            values.get("rel_sharpness", -1), values.get("rel_sharp_p10", -1),
            values.get("contrast", -1), values.get("ink_ratio", -1),
            values.get("text_height"), page["status"]))
    return page["status"]


# --------------------------------------------------------------------------
# Tests
# --------------------------------------------------------------------------

def test_verdict_is_resolution_independent():
    """The bug that started this: identical photo, different pixel sizes.

    A sharp page must never be rejected because of its pixel dimensions, and its
    relative sharpness must stay in a narrow band across sizes.  A very small
    downscale may still be *flagged* (the print really is small), which is fine
    as long as it is not blocked.
    """
    page = document()
    verdicts = {}
    sharpness = {}
    for factor in (0.35, 0.6, 1.0, 1.6):
        scaled = cv2.resize(page, (int(page.shape[1] * factor), int(page.shape[0] * factor)),
                            interpolation=cv2.INTER_AREA if factor < 1 else cv2.INTER_CUBIC)
        analysed = check_blur.analyse_page(scaled, ocr_mode="off")
        verdicts[factor] = analysed["status"]
        sharpness[factor] = analysed["metrics"]["rel_sharpness"]
        assert analysed["status"] not in ("blurry", "unreadable"), \
            "sharp page at %sx was rejected: %s" % (factor, analysed["reason"])
    assert verdicts[1.0] == "clear" and verdicts[1.6] == "clear", verdicts
    assert verdicts[0.6] == verdicts[1.0], verdicts
    lo, hi = min(sharpness.values()), max(sharpness.values())
    assert hi / lo < 2.0, "relative sharpness drifted with resolution: %s" % sharpness
    return {"verdicts": verdicts, "rel_sharpness": sharpness}


def test_blur_is_detected():
    page = document()
    sharp = status_of(page)
    heavy = status_of(cv2.GaussianBlur(page, (0, 0), 8))
    medium = status_of(cv2.GaussianBlur(page, (0, 0), 4))
    assert sharp == "clear", "sharp page: expected clear, got %s" % sharp
    assert heavy == "blurry", "sigma=8 blur: expected blurry, got %s" % heavy
    assert medium in ("flagged", "blurry"), "sigma=4 blur: expected flagged/blurry, got %s" % medium
    return {"sharp": sharp, "sigma4": medium, "sigma8": heavy}


def test_photographed_document_on_desk():
    photo = photographed(document())
    assert status_of(photo) == "clear", "sharp photo of a page should be clear"
    assert status_of(cv2.GaussianBlur(photo, (0, 0), 8)) == "blurry"
    detected = page_quality.detect_page(photo)
    assert detected["page_found"] is True, "page on a desk should be located"
    assert detected["area_ratio"] < 0.95, "crop should exclude most of the desk"
    return {key: detected[key] for key in ("page_found", "area_ratio", "rectangularity")}


def test_blank_and_black_pages_are_rejected():
    page = document()
    assert status_of(np.full_like(page, 255)) == "blurry", "blank page must not pass"
    assert status_of(np.full_like(page, 5)) == "blurry", "all-black page must not pass"


def test_illegible_thumbnails_are_rejected():
    page = document()
    tiny = cv2.resize(page, (240, 200), interpolation=cv2.INTER_AREA)
    upscaled = cv2.resize(tiny, (1000, 833), interpolation=cv2.INTER_CUBIC)
    assert status_of(tiny) in ("blurry", "unreadable"), "240x200 upload must not pass"
    assert status_of(upscaled) in ("blurry", "unreadable"), "upscaled thumbnail must not pass"


def test_full_frame_scan_is_not_cropped():
    detected = page_quality.detect_page(document())
    assert detected["page_found"] is False, "a page filling the frame needs no crop"


def test_legibility_blocks_when_no_text_is_read():
    engine = FakeEngine([("...", 20.0), ("###", 15.0)])
    status = status_of(document(), ocr="on", engine=engine)
    assert status == "unreadable", "unreadable text should block, got %s" % status


def test_legible_text_with_matching_type_passes():
    engine = FakeEngine([
        ("Republic of the Philippines", 95.0),
        ("Driver's License", 94.0),
        ("Name: JUAN DELA CRUZ", 93.0),
        ("Date of Birth: 1990-01-01", 92.0),
        ("License No. N01-23-456789", 91.0),
    ])
    status = status_of(document(), document_type="Valid ID", ocr="on", engine=engine)
    assert status == "clear", "legible ID should pass, got %s" % status


def test_wrong_document_type_is_flagged():
    engine = FakeEngine([
        ("Republic of the Philippines", 95.0),
        ("Driver's License", 94.0),
        ("Name: JUAN DELA CRUZ", 93.0),
        ("Date of Birth: 1990-01-01", 92.0),
    ])
    status = status_of(document(), document_type="Birth Certificate", ocr="on", engine=engine)
    assert status == "flagged", "missing document-type keywords should flag, got %s" % status


def test_keyword_table_covers_seeded_document_types():
    for name in ("Birth Certificate", "Medical", "Valid ID", "valid id", "MEDICAL"):
        expectation = ocr_quality.expectations_for(name)
        assert expectation["required"], "no keyword groups configured for %r" % name


def test_single_page_document_runs_exactly_one_ocr_pass():
    """Typical upload: one page, so one OCR pass and nothing more."""
    engine = CountingEngine([SUFFICIENT_WORDS])
    payload, engine = run_check_file([document()], engine)
    assert engine.calls == 1, "single-page document should OCR once, got %d" % engine.calls
    assert payload["ocr_pages"] == 1, payload["ocr_pages"]
    assert payload["status"] in ("clear", "flagged"), payload["status"]


def test_multi_page_document_stops_after_a_conclusive_page():
    """A legible first page with the expected content saves OCR on page 2."""
    engine = CountingEngine([SUFFICIENT_WORDS, SUFFICIENT_WORDS])
    payload, engine = run_check_file([document(), document()], engine)
    assert engine.calls == 1, "should stop after page 1, got %d OCR passes" % engine.calls
    assert payload["pages_checked"] == 2, "both pages must still be measured"


def test_multi_page_document_reads_page_two_when_page_one_is_useless():
    """Cover pages / blank fronts must not hide the real content on page 2."""
    engine = CountingEngine([GARBAGE_WORDS, SUFFICIENT_WORDS])
    payload, engine = run_check_file([document(), document()], engine)
    assert engine.calls == 2, "should continue to page 2, got %d OCR passes" % engine.calls
    assert payload["pages"][1]["ocr"]["chars"] > 30
    # One unreadable page plus one good page is not an automatic rejection.
    assert payload["status"] == "flagged", payload["status"]


def test_multi_page_document_is_rejected_when_no_page_is_usable():
    engine = CountingEngine([GARBAGE_WORDS, GARBAGE_WORDS])
    payload, engine = run_check_file([document(), document()], engine)
    assert engine.calls == 2
    assert payload["status"] == "unreadable", payload["status"]


def test_ocr_is_skipped_entirely_for_a_rejected_page():
    """A blurry page must not pay for OCR at all."""
    engine = CountingEngine([SUFFICIENT_WORDS])
    payload, engine = run_check_file([cv2.GaussianBlur(document(), (0, 0), 8)], engine)
    assert engine.calls == 0, "blurry page should not be OCR'd, got %d calls" % engine.calls
    assert payload["status"] == "blurry"
    assert payload["ocr_pages"] == 0


def test_serve_mode_answers_each_batch():
    """--serve (used by the warm PHP session) answers every file plus a marker.

    The PHP layer keeps one interpreter alive for a whole submission, so the
    protocol must return exactly one payload per requested file and terminate
    each batch with a {"batch_done": true} line, otherwise a warm process would
    hang the registration request.
    """
    engine = CountingEngine([SUFFICIENT_WORDS])
    original_loader = check_blur.load_pages
    original_state = (ocr_quality._engine, ocr_quality._engine_name,
                      ocr_quality._engine_detail, ocr_quality._engine_resolved)
    original_stdin = sys.stdin
    check_blur.load_pages = lambda path: [document()]
    ocr_quality._engine = engine
    ocr_quality._engine_name = "fake"
    ocr_quality._engine_detail = "ready"
    ocr_quality._engine_resolved = True
    try:
        request = json.dumps({"files": [{"path": "one.jpg", "expect": "Birth Certificate"},
                                        {"path": "two.jpg", "expect": "Birth Certificate"}]}) + "\n"
        sys.stdin = io.StringIO(request)
        buffer = io.StringIO()
        with redirect_stdout(buffer):
            check_blur.serve("on", 2)
        lines = [json.loads(line) for line in buffer.getvalue().strip().splitlines()]
    finally:
        check_blur.load_pages = original_loader
        sys.stdin = original_stdin
        (ocr_quality._engine, ocr_quality._engine_name,
         ocr_quality._engine_detail, ocr_quality._engine_resolved) = original_state

    payloads = [line for line in lines if line.get("status")]
    done = [line for line in lines if line.get("batch_done")]
    assert len(payloads) == 2, "expected one payload per file, got %s" % lines
    assert len(done) == 1 and lines[-1].get("batch_done"), "batch_done marker must be last: %s" % lines
    assert {line["file"] for line in payloads} == {"one.jpg", "two.jpg"}
    assert payloads[0]["status"] in ("clear", "flagged")


def test_cli_argument_parsing():
    targets, expectations, ocr_mode, max_pages = check_blur._parse_arguments([
        "--expect", "Valid ID", r"C:\docs\a.jpg",
        r"C:\docs\b.pdf",
        "--expect=Medical", r"C:\docs\c.jpg",
        "--ocr=off", "--max-ocr-pages=2",
    ])
    assert targets == [r"C:\docs\a.jpg", r"C:\docs\b.pdf", r"C:\docs\c.jpg"], targets
    assert expectations[r"C:\docs\a.jpg"] == "Valid ID"
    assert expectations[r"C:\docs\b.pdf"] is None
    assert expectations[r"C:\docs\c.jpg"] == "Medical"
    assert ocr_mode == "off" and max_pages == 2


def test_decide_accepts_legacy_metric_dicts():
    legacy = {"laplacian_std": 12.0, "detail_ratio": 0.01, "local_variance": 40.0, "entropy": 1.0}
    assert check_blur.classify(legacy) in ("clear", "flagged", "blurry", "unreadable")


def test_exit_codes_match_statuses():
    codes = {"clear": 0, "invalid": 1, "flagged": 2, "blurry": 3, "unreadable": 3, "unavailable": 4}
    for status, expected in codes.items():
        buffer = io.StringIO()
        with redirect_stdout(buffer):
            code = check_blur.emit(status, "test")
        assert code == expected, "%s should map to exit code %s, got %s" % (status, expected, code)


TESTS = [
    ("verdict survives resizing", test_verdict_is_resolution_independent),
    ("blur is detected", test_blur_is_detected),
    ("page photographed on a desk", test_photographed_document_on_desk),
    ("blank / black pages rejected", test_blank_and_black_pages_are_rejected),
    ("illegible thumbnails rejected", test_illegible_thumbnails_are_rejected),
    ("full-frame scan not cropped", test_full_frame_scan_is_not_cropped),
    ("no legible text blocks", test_legibility_blocks_when_no_text_is_read),
    ("legible matching document passes", test_legible_text_with_matching_type_passes),
    ("wrong document type flagged", test_wrong_document_type_is_flagged),
    ("single page = one OCR pass", test_single_page_document_runs_exactly_one_ocr_pass),
    ("conclusive page stops OCR", test_multi_page_document_stops_after_a_conclusive_page),
    ("page 2 read when page 1 is useless", test_multi_page_document_reads_page_two_when_page_one_is_useless),
    ("all pages unusable is rejected", test_multi_page_document_is_rejected_when_no_page_is_usable),
    ("rejected page skips OCR", test_ocr_is_skipped_entirely_for_a_rejected_page),
    ("keyword table covers types", test_keyword_table_covers_seeded_document_types),
    ("served batch answers every file", test_serve_mode_answers_each_batch),
    ("CLI parsing", test_cli_argument_parsing),
    ("legacy metric dicts", test_decide_accepts_legacy_metric_dicts),
    ("exit codes", test_exit_codes_match_statuses),
]


def main():
    global VERBOSE
    parser = argparse.ArgumentParser(description="Document quality pipeline tests")
    parser.add_argument("--verbose", "-v", action="store_true")
    args = parser.parse_args()
    VERBOSE = args.verbose

    print("=" * 70)
    print("Document Quality Pipeline Tests")
    print("=" * 70)
    failures = 0
    for name, test in TESTS:
        try:
            result = test()
            detail = ""
            if VERBOSE and isinstance(result, dict):
                detail = "  " + str(result)
            print("PASS  %-38s%s" % (name, detail))
        except AssertionError as exc:
            failures += 1
            print("FAIL  %-38s %s" % (name, exc))
        except Exception as exc:  # noqa: BLE001 - report and continue
            failures += 1
            print("ERROR %-38s %s: %s" % (name, type(exc).__name__, exc))
    print("-" * 70)
    print("Total: %d/%d tests passed" % (len(TESTS) - failures, len(TESTS)))
    return 0 if failures == 0 else 1


if __name__ == "__main__":
    sys.exit(main())
