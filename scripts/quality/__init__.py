"""Document quality analysis helpers used by ``scripts/check_blur.py``.

The package deliberately depends on OpenCV and NumPy only.  The OCR module
imports its engine lazily, so a machine without an OCR engine reports
"OCR unavailable" instead of breaking document checking entirely.

Modules
-------
page         Locate the photographed page inside the frame.
blur         Scale-normalised sharpness, tile statistics and motion blur.
readability  Blank / faint / too-small / too-dark detection without OCR.
ocr          Legibility and content-expectation checks.
thresholds   Every tunable constant in one place (also mirrored in docs).
"""
