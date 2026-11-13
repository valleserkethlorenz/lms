"""Locate the photographed page inside an upload.

Photographs of IDs and certificates usually contain a table, a hand or a wall
around the page.  Those areas are sharp and heavily textured, so measuring the
whole frame (the old behaviour) hides blur on the document itself.  This module
finds the page rectangle with Otsu thresholding plus contour analysis and
returns a crop that the other modules measure in isolation.

The detector is deliberately conservative: when nothing convincing is found it
returns the full frame and reports ``page_found = False`` so callers can still
judge the upload on its overall statistics.
"""

import cv2
import numpy as np

from .thresholds import PAGE


def _page_candidates(gray):
    """Yield ``(area, rectangularity, bbox)`` for page-like bright shapes.

    Only the brightest side is treated as the page: dark text paragraphs and
    photo boxes are holes in that mask, never candidates, which keeps the crop
    from locking onto a block of text.
    """
    blurred = cv2.GaussianBlur(gray, (5, 5), 0)
    kernel = np.ones((5, 5), np.uint8)
    _, mask = cv2.threshold(blurred, 0, 255, cv2.THRESH_BINARY | cv2.THRESH_OTSU)
    mask = cv2.morphologyEx(mask, cv2.MORPH_CLOSE, kernel, iterations=2)
    contours, _ = cv2.findContours(mask, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
    for contour in contours:
        area = float(cv2.contourArea(contour))
        if area <= 0:
            continue
        x, y, width, height = cv2.boundingRect(contour)
        if width <= 1 or height <= 1:
            continue
        yield area, area / float(width * height), (x, y, width, height)


def detect_page(image):
    """Return the page crop plus detection metadata.

    ``image`` is a BGR (or grayscale) array.  The returned ``crop`` is a view of
    the original pixels; no resizing happens here.
    """
    if image is None or image.size == 0:
        raise ValueError("The file could not be decoded as an image.")
    height, width = image.shape[:2]
    gray = image if image.ndim == 2 else cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    # A page is the bright side of the frame.  For dark captures (night photos,
    # heavy shadow) invert first so the same brightest-side rule still applies.
    inverted = float(gray.mean()) < 110.0
    work = (255 - gray) if inverted else gray

    scale = min(1.0, PAGE["search_side"] / float(max(height, width)))
    if scale < 1.0:
        small = cv2.resize(
            work,
            (max(1, int(round(width * scale))), max(1, int(round(height * scale)))),
            interpolation=cv2.INTER_AREA,
        )
    else:
        small = work

    best = None
    for area, rectangularity, bbox in _page_candidates(small):
        if rectangularity < PAGE["min_rectangularity"]:
            continue
        ratio = area / float(small.shape[0] * small.shape[1])
        if ratio < PAGE["min_area_ratio"] or ratio > PAGE["max_area_ratio"]:
            continue
        score = ratio * rectangularity
        if best is None or score > best[0]:
            best = (score, ratio, rectangularity, bbox)

    if best is None:
        return {
            "crop": image,
            "page_found": False,
            "bbox": [0, 0, int(width), int(height)],
            "area_ratio": 1.0,
            "rectangularity": 0.0,
        }

    _, ratio, rectangularity, (x, y, box_w, box_h) = best
    if scale < 1.0:
        x = int(round(x / scale))
        y = int(round(y / scale))
        box_w = int(round(box_w / scale))
        box_h = int(round(box_h / scale))

    margin_x = int(round(box_w * PAGE["margin"]))
    margin_y = int(round(box_h * PAGE["margin"]))
    left = max(0, x - margin_x)
    top = max(0, y - margin_y)
    right = min(width, x + box_w + margin_x)
    bottom = min(height, y + box_h + margin_y)
    if right - left <= 1 or bottom - top <= 1:
        return {
            "crop": image,
            "page_found": False,
            "bbox": [0, 0, int(width), int(height)],
            "area_ratio": 1.0,
            "rectangularity": 0.0,
        }

    return {
        "crop": image[top:bottom, left:right],
        "page_found": True,
        "bbox": [int(left), int(top), int(right - left), int(bottom - top)],
        "area_ratio": round(float(ratio), 4),
        "rectangularity": round(float(rectangularity), 4),
    }
