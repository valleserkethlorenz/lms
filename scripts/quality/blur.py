"""Scale- and noise-normalised sharpness metrics.

The previous implementation measured ``cv2.Laplacian`` on the raw upload, so the
exact same photo scored 17.8 or 53.2 depending only on its pixel dimensions and
sensor/JPEG noise.  Every metric here is therefore computed on a page crop that
has been resized to a fixed side length and lightly denoised first.
"""

import cv2
import numpy as np

from .thresholds import BLUR

TILES = 4


def normalize_scale(gray, target_side=None):
    """Resize so the longest side equals ``target_side`` (keeps aspect ratio)."""
    target = int(target_side or 1000)
    height, width = gray.shape[:2]
    longest = max(height, width)
    if longest < 1:
        return gray
    scale = target / float(longest)
    if abs(scale - 1.0) < 0.01:
        return gray
    interpolation = cv2.INTER_AREA if scale < 1.0 else cv2.INTER_CUBIC
    return cv2.resize(
        gray,
        (max(1, int(round(width * scale))), max(1, int(round(height * scale)))),
        interpolation=interpolation,
    )


def denoise(gray):
    """Remove sensor noise / JPEG blocking that would fake edge detail."""
    return cv2.GaussianBlur(cv2.medianBlur(gray, 3), (3, 3), 0)


def _tile_means(absolute_laplacian):
    height, width = absolute_laplacian.shape[:2]
    rows = np.linspace(0, height, TILES + 1).astype(int)
    cols = np.linspace(0, width, TILES + 1).astype(int)
    values = []
    for row in range(TILES):
        for col in range(TILES):
            tile = absolute_laplacian[rows[row]:rows[row + 1], cols[col]:cols[col + 1]]
            if tile.size:
                values.append(float(tile.mean()))
    return values


def _motion_score(gray):
    """Directional detail loss: horizontal vs vertical second-derivative energy.

    Motion blur removes detail along the camera-shake direction, so one axis
    keeps far less high-frequency energy than the other.  Text pages are mildly
    anisotropic too, which is why this metric only ever flags (never blocks) by
    default.
    """
    horizontal = float(np.mean(np.abs(cv2.Sobel(gray, cv2.CV_64F, 2, 0, ksize=3))))
    vertical = float(np.mean(np.abs(cv2.Sobel(gray, cv2.CV_64F, 0, 2, ksize=3))))
    total = horizontal + vertical
    if total <= 0:
        return 0.0
    return abs(horizontal - vertical) / total


def _tile_ratios(first, second):
    first_tiles = _tile_means(first)
    second_tiles = _tile_means(second)
    return [a / b if b > 1e-9 else 0.0 for a, b in zip(first_tiles, second_tiles)]


def relative_sharpness(gray):
    """How much high-frequency energy survives a deliberate re-blur.

    A sharp page collapses when blurred again (ratio ~5), an already blurred
    page barely changes (ratio ~1.2).  Because it is a ratio of the same content
    it stays stable across resolution, contrast and how much text is on the page.
    """
    laplacian = np.abs(cv2.Laplacian(gray, cv2.CV_64F))
    reblurred = cv2.GaussianBlur(gray, (0, 0), BLUR["reblur_sigma"])
    reblurred_laplacian = np.abs(cv2.Laplacian(reblurred, cv2.CV_64F))
    baseline = float(reblurred_laplacian.mean())
    if baseline <= 1e-9:
        return 0.0, 0.0
    ratios = _tile_ratios(laplacian, reblurred_laplacian)
    return (round(float(laplacian.mean()) / baseline, 2),
            round(float(np.percentile(ratios, 10)) if ratios else 0.0, 2))


def sharpness_metrics(gray):
    """Tile-based sharpness, relative sharpness and motion score."""
    laplacian = cv2.Laplacian(gray, cv2.CV_64F)
    tiles = _tile_means(np.abs(laplacian))
    if not tiles:
        tiles = [0.0]
    rel_sharpness, rel_sharp_p10 = relative_sharpness(gray)
    return {
        "sharp_median": round(float(np.median(tiles)), 3),
        "sharp_p10": round(float(np.percentile(tiles, 10)), 3),
        "sharp_max": round(float(np.max(tiles)), 3),
        "sharp_min": round(float(np.min(tiles)), 3),
        "rel_sharpness": rel_sharpness,
        "rel_sharp_p10": rel_sharp_p10,
        "motion_score": round(_motion_score(gray), 4),
        "laplacian_std": round(float(np.std(laplacian)), 2),
    }


def classic_metrics(gray):
    """The original four metrics, now computed on the normalised page crop.

    They are kept for reporting, dashboards and threshold recalibration but are
    no longer the values that decide the verdict.
    """
    gray_f = gray.astype(np.float64)
    mean = cv2.blur(gray_f, (9, 9))
    local_var = float(np.mean((gray_f - mean) ** 2))
    edges = cv2.Canny(gray, 100, 200)
    detail = float(np.count_nonzero(edges) / edges.size) if edges.size else 0.0
    hist = cv2.calcHist([gray], [0], None, [256], [0, 256]).flatten()
    hist = hist / max(float(hist.sum()), 1.0)
    hist = hist[hist > 0]
    entropy = float(-(hist * np.log2(hist)).sum()) if hist.size else 0.0
    return {
        "local_variance": round(local_var, 2),
        "detail_ratio": round(detail, 4),
        "entropy": round(entropy, 2),
    }


def blur_verdict(values):
    """Return ``(status, reason)`` or ``(None, None)`` when sharpness is fine."""
    rel = values.get("rel_sharpness")
    rel_p10 = values.get("rel_sharp_p10")
    if rel is None or rel_p10 is None:
        return None, None
    if values.get("ink_ratio", 1.0) < BLUR["sparse_ink_ratio"]:
        # Almost nothing is printed: sharpness cannot be judged, the blank and
        # OCR checks are responsible for this page.
        return None, None
    if rel < BLUR["hard_rel_sharpness"] or rel_p10 < BLUR["hard_rel_sharp_p10"]:
        return "blurry", "The document is out of focus or blurred and cannot be read."
    return None, None


def blur_soft_flag(values):
    """Return a human-readable reason when sharpness only deserves a review."""
    rel = values.get("rel_sharpness")
    rel_p10 = values.get("rel_sharp_p10")
    if rel is not None and rel < BLUR["soft_rel_sharpness"]:
        return "The document looks slightly out of focus."
    if rel_p10 is not None and rel_p10 < BLUR["soft_rel_sharp_p10"]:
        return "Parts of the document are soft and may be hard to read."
    # Directionality only matters when the page is not clearly sharp: thin
    # table rules make almost every document anisotropic.
    if values.get("motion_score", 0.0) > 0.90 and rel_p10 is not None \
            and rel_p10 < BLUR["soft_rel_sharp_p10"] * 1.3:
        return "The document may be motion blurred."
    return None
