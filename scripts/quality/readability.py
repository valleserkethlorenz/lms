"""Readability checks that do not need OCR.

These catch the "not blurry but useless" uploads the old blur-only pipeline
accepted: a blank page, a washed-out or solid-black page, and a page whose text
is simply too small to read once normalised.
"""

import cv2
import numpy as np

from .thresholds import READABILITY


def contrast(gray):
    """RMS-style contrast (std / mean) of the page crop, 0.0 - ~1.0."""
    values = gray.astype(np.float64)
    mean = float(values.mean())
    if mean <= 1e-6:
        return 0.0
    return float(values.std() / mean)


def ink_ratio(gray):
    """Share of "printed" pixels, using Otsu so lighting does not matter."""
    _, mask = cv2.threshold(gray, 0, 255, cv2.THRESH_BINARY_INV | cv2.THRESH_OTSU)
    return float(np.count_nonzero(mask)) / float(mask.size)


def text_height(gray):
    """Median height (px) of connected text-like components.

    Returns ``(median_height, component_count)``.  ``median_height`` is ``None``
    when too few components are found for the estimate to mean anything.
    """
    _, mask = cv2.threshold(gray, 0, 255, cv2.THRESH_BINARY_INV | cv2.THRESH_OTSU)
    count, _, stats, _ = cv2.connectedComponentsWithStats(mask, connectivity=8)
    heights = []
    for index in range(1, count):
        x, y, width, height, area = stats[index]
        if height < 4 or width < 2:
            continue
        if width > 6 * height:
            continue  # table rules, separators, underlines
        if height > max(80, int(gray.shape[0] * 0.12)):
            continue  # borders, photo frames, big logos
        if area < 8:
            continue
        heights.append(int(height))
        if len(heights) >= 4000:
            break
    if len(heights) < READABILITY["min_text_components"]:
        return None, len(heights)
    return float(np.median(heights)), len(heights)


def readability_metrics(gray, original_gray=None):
    """Contrast/ink on the normalised page, text height on the original pixels.

    Text height must be measured before normalisation: upscaling a 120x100
    thumbnail to 1000 px would otherwise make 2 px text look 17 px tall.
    """
    source = gray if original_gray is None else original_gray
    median_height, components = text_height(source)
    return {
        "contrast": round(contrast(gray), 4),
        "ink_ratio": round(ink_ratio(gray), 5),
        "text_height": None if median_height is None else round(median_height, 1),
        "text_components": components,
    }


def blank_verdict(values):
    """A page with no contrast and no ink is empty, not merely blurred."""
    if values["contrast"] < READABILITY["blank_contrast"] and values["ink_ratio"] < READABILITY["blank_ink"]:
        return "blurry", "The page appears blank; nothing was captured."
    return None, None


def readability_verdict(values):
    """Hard readability failures (``unreadable``) or ``(None, None)``."""
    if values["ink_ratio"] > READABILITY["hard_ink_high"]:
        return "unreadable", "The page is too dark or covered; the content cannot be read."
    if values["ink_ratio"] < READABILITY["hard_ink_low"] and values["contrast"] < READABILITY["soft_contrast"]:
        return "unreadable", "No printed content could be found on the page."
    if values["text_height"] is not None and values["text_height"] < READABILITY["hard_text_height"]:
        return "unreadable", "The text is too small to read; move closer or upload a better scan."
    if values["contrast"] < READABILITY["hard_contrast"]:
        return "unreadable", "The page is too faint or washed out to read."
    return None, None


def readability_soft_flag(values):
    if values["contrast"] < READABILITY["soft_contrast"]:
        return "The page has low contrast and may be hard to read."
    if values["text_height"] is not None and values["text_height"] < READABILITY["soft_text_height"]:
        return "The text is small; a closer photo or scan is recommended."
    return None
