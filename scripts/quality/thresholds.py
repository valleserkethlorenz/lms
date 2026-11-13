"""Tunable thresholds for the document quality pipeline.

All pixel metrics are computed on a scale-normalised page crop (see
``quality.blur.normalize_scale``), which is what makes the constants below
comparable between a 2 MP phone shot and a 12 MP scan.  Keeping the numbers in
one module lets ``scripts/check_blur.py``, the tests and the documentation stay
in sync.
"""

THRESHOLDS_VERSION = "2.2.0"

# -- Page detection ---------------------------------------------------------
PAGE = {
    # Frame is searched at this maximum side length (speed only, no accuracy loss).
    "search_side": 1200,
    # A candidate page must cover at least this share of the frame to be cropped to.
    "min_area_ratio": 0.15,
    # Lower bound of area/(bounding box area); rejects ragged non-page shapes.
    "min_rectangularity": 0.55,
    # Above this share the "page" is the whole frame, so cropping adds nothing.
    "max_area_ratio": 0.98,
    # Extra border kept around the detected page so edge text is not clipped.
    "margin": 0.02,
}

# -- Sharpness / blur -------------------------------------------------------
# ``rel_sharpness`` is mean|Laplacian(x)| / mean|Laplacian(Gauss(x, 2.5))| on the
# normalised page: a sharp page loses most of its high-frequency energy when it
# is blurred again (ratio 5+), an already blurred page barely changes (1-2).
# It is contrast- and content-independent, which is what the old absolute
# Laplacian thresholds were missing.
BLUR = {
    "reblur_sigma": 2.5,
    "hard_rel_sharpness": 2.50,   # obvious out-of-focus / smear
    "soft_rel_sharpness": 3.40,
    "hard_rel_sharp_p10": 2.20,   # worst tile: part of the page is unusable
    "soft_rel_sharp_p10": 2.90,
    # Directional detail loss. Text pages are naturally anisotropic, so this
    # only ever produces a "flagged" hint, and only when overall sharpness is
    # already borderline.
    "soft_motion_score": 0.80,
    # Sparse pages cannot be judged on sharpness alone.
    "sparse_ink_ratio": 0.004,
    # Absolute tile Laplacian, reported for dashboards only (never decisive).
    "legacy_reference_laplacian": 65.0,
}

# -- Readability without OCR ------------------------------------------------
READABILITY = {
    "hard_contrast": 0.045,      # std/mean of the page: washed out / very faint
    "soft_contrast": 0.130,
    "blank_contrast": 0.020,     # below this and below blank_ink the page is empty
    "blank_ink": 0.0015,
    "hard_ink_low": 0.0015,      # nothing printed
    "hard_ink_high": 0.450,      # solid black, heavy shadow or covered lens
    # Text height is measured in ORIGINAL page pixels: 240x200 thumbnails yield
    # ~3 px, a 150 DPI A4 scan ~14 px, a phone photo 25-40 px.
    "hard_text_height": 5.0,
    "soft_text_height": 8.0,
    "min_text_components": 5,    # fewer components: text-height test is skipped
}

# -- OCR --------------------------------------------------------------------
OCR = {
    "hard_chars": 30,            # fewer recognised characters: no legible text
    "hard_confidence": 60.0,     # mean word confidence (0-100)
    "soft_chars": 80,
    "soft_confidence": 72.0,
    # OCR is the dominant cost (~4.5 s/page at 1600 px, ~3.9 s at 1280 px) and
    # 1280 px recognised the same 914 characters at the same confidence, so the
    # smaller width is the default.
    "target_side": 1280,
    # Hard cap on OCR passes per document. The checker stops as soon as a page
    # produced a usable result (legible text + expected keywords found), so the
    # usual single-page document costs exactly one pass and only a multi-page
    # document whose first page is inconclusive uses a second.
    "max_pages": 2,
}

# -- PDF (rendered through Poppler) -----------------------------------------
PDF = {
    "dpi": 150,
    # Eligibility documents are 1-2 pages; rendering 10 pages of a long PDF used
    # to cost up to 40 s for pages nobody looks at.
    "max_pages": 3,
}
