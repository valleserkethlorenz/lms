# Blur Detection Feature Documentation

## Overview

The League Management System includes an automated document quality checking system that analyzes uploaded documents (PDFs and images) for blur and readability issues. This helps ensure that submitted documents (IDs, birth certificates, medical certificates, etc.) are legible and meet minimum quality standards.

## System Architecture

### Components

1. **Python Script (`check_blur.py`)**: Core analysis engine using OpenCV
2. **PHP Integration Layer (`documentValidator.php`)**: Bridges PHP and Python
3. **Registration Handler (`registerTeam.php`)**: Processes team registrations with document validation
4. **Organizer Interface (`view_player.php`)**: Displays quality status and allows manual review
5. **Action Endpoint (`leagueAction.php`)**: Handles document review decisions

### Data Flow

```
User Upload → PHP Validation → Python Analysis → Quality Score → Database Storage
                                                      ↓
                                              Organizer Review UI
                                                      ↓
                                              Accept/Reject Decision
```

## Quality Analysis

The checker answers two different questions, in this order:

1. **Can the page be read at all?** (sharpness, blank/faint page, legible text)
2. **Is this the document the league asked for?** (document-type keywords)

It runs in one Python process per submission and measures the *page*, not the
whole frame, at a *normalised* scale.

### Pipeline (`scripts/quality/*`)

| Step | Module | What it does |
|------|--------|--------------|
| 1. Page detection | `quality/page.py` | Otsu threshold → largest bright contour → crop of the page. Falls back to the full frame when nothing convincing is found (`page_found=false`). |
| 2. Scale normalisation | `quality/blur.py` | Page crop resized so its longest side is 1000 px (`INTER_AREA` down, `INTER_CUBIC` up). |
| 3. Denoise | `quality/blur.py` | 3×3 median + 3×3 Gaussian, so sensor noise and JPEG blocking cannot fake detail. |
| 4. Relative sharpness | `quality/blur.py` | `mean|Laplace(x)| / mean|Laplace(Gauss(x, 2.5))|`, per 4×4 tile. Sharp page ≈ 5, blurred ≈ 1.5. |
| 5. Readability | `quality/readability.py` | RMS contrast, ink coverage (Otsu) and median text height measured in **original** pixels. |
| 6. OCR legibility + content | `quality/ocr.py` | RapidOCR (or pytesseract): characters, mean confidence, expected keywords per document type. |
| 7. Decision | `check_blur.py::decide()` | Maps the measurements to `clear` / `flagged` / `blurry` / `unreadable` / `invalid` / `unavailable`. |

### Why relative sharpness instead of `laplacian_std`

`cv2.Laplacian` on the raw upload is not comparable between files: the **same**
photo measured 17.8 / 28.8 / 42.3 / 53.2 depending only on its pixel size, and
noise/sharpening could push a genuinely blurred page above the old `< 30`
threshold. The re-blur ratio has none of those dependencies, and it is what the
`blur` verdict uses now. The absolute Laplacian/entropy values are still stored
in `quality_metrics` for dashboards, but they no longer decide anything.

### Verdicts and thresholds (`scripts/quality/thresholds.py`)

| Verdict | Meaning | Blocks registration? |
|---------|---------|----------------------|
| `clear` | Sharp, readable, expected content found | no |
| `flagged` | Readable but borderline (soft, faint, small print, missing keywords, low OCR confidence) | no — organizer review |
| `blurry` | Out of focus / smeared, or a blank page | **yes** |
| `unreadable` | Text cannot be read (no OCR text, too dark/covered, too small, washed out) | **yes** |
| `invalid` | Not a decodable image/PDF, unsupported format, corrupt | **yes** |
| `unavailable` | Checker/OCR/timeout problem | no, but it is always surfaced as a flag + log entry |

Multi-page documents: every page is *measured*, but OCR stops as soon as a page
is legible and contains the expected keywords. A document whose pages are all
unusable is rejected with the first blocking verdict; a document with at least
one usable page (e.g. a blank cover followed by the certificate) is `flagged`
for manual review instead of being rejected outright.

```python
BLUR = {
    "reblur_sigma": 2.5,
    "hard_rel_sharpness": 2.50,   # blurry below this
    "soft_rel_sharpness": 3.40,   # flagged below this
    "hard_rel_sharp_p10": 2.20,   # worst tile: part of the page is unusable
    "soft_rel_sharp_p10": 2.90,
}
READABILITY = {
    "hard_contrast": 0.045,       # washed out
    "soft_contrast": 0.130,
    "hard_text_height": 5.0,      # px in the original upload
    "soft_text_height": 8.0,
    "hard_ink_high": 0.450,       # solid black / covered lens
}
OCR = {"hard_chars": 30, "hard_confidence": 60, "soft_chars": 80, "soft_confidence": 72}
```

Calibration notes (Windows, OpenCV 5.0.0, 1000 px normalised pages):

| Input | `rel_sharpness` | Verdict |
|-------|-----------------|---------|
| A4 page render, sharp | 5.76 | clear |
| same page, Gaussian σ=2 | 4.40 | clear |
| same page, Gaussian σ=4 | 2.91 | flagged |
| same page, Gaussian σ=8 | 1.67 | blurry |
| same page downscaled to 1/3 | 5.09 | clear (scale invariance) |
| 240×200 thumbnail, upscaled | 2.26 | blurry |
| blank / all-black page | 0.00 | blurry |

### Exit Codes

- `0`: Clear
- `1`: Invalid (bad input, missing file, or format error)
- `2`: Flagged (needs manual review)
- `3`: Blurry **or** unreadable (cannot be accepted)
- `4`: Unavailable (dependency or internal error)

## Database Schema

### `tbl_submitted_document`

```sql
CREATE TABLE tbl_submitted_document (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_id INT NOT NULL,
    player_id INT NULL,
    requirement_id INT NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    quality_status ENUM('Clear', 'Flagged', 'Pending') DEFAULT 'Pending',
    quality_metrics JSON NULL,
    review_status ENUM('Pending', 'Accepted', 'Rejected') DEFAULT 'Pending',
    review_remarks TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_team_id (team_id),
    INDEX idx_player_id (player_id),
    INDEX idx_requirement_id (requirement_id)
);
```

### Quality Metrics Storage

The `quality_metrics` JSON column stores raw analysis data for auditing and threshold recalibration:

```json
{
  "status": "flagged",
  "pages_checked": 2,
  "pages": [
    {
      "page": 1,
      "width": 1240,
      "height": 1754,
      "laplacian_std": 45.2,
      "local_variance": 12500.5,
      "detail_ratio": 0.035,
      "entropy": 2.8,
      "page_status": "flagged"
    }
  ]
}
```

## Rate Limiting

Team registration endpoints enforce rate limiting to prevent abuse:

- **Limit**: 5 registration attempts per user per 5-minute window
- **Implementation**: Session-based tracking using `RateLimiter` class
- **Response**: HTTP 429 with retry-after timestamp

## Security Features

1. **CSRF Protection**: All document review actions require valid CSRF tokens
2. **File Validation**: 
   - MIME type checking
   - Extension whitelisting (jpg, jpeg, png, pdf)
   - Size limits (5MB per file)
3. **Access Control**: 
   - Organizers can only review documents in their own leagues
   - Role-based access enforcement (`requireJsonRole([1])`)

## Organizer Workflow

### Viewing Quality Status

On the team registration review page (`view_player.php`):

1. **Automatic Flags Banner**: If any documents were flagged during upload, a warning banner appears at the top listing the affected documents
2. **Quality Badges**: Each document shows a colored badge:
   - Green: passed the automated check (sharp, legible, expected keywords found)
   - Yellow: needs review (soft, faint, small print, missing keywords, checker unavailable)
   - Gray: not checked yet
3. **Metrics Tooltip**: Hover over a badge to see per-page relative sharpness (with the worst tile), contrast, text height and OCR characters/confidence
4. **Review Status**: Shows whether the document has been manually reviewed (Accepted/Rejected)

### Manual Review Process

For flagged documents:

1. Click "View" to open the document in a new tab
2. Review the document quality manually
3. Click the "Review" dropdown button
4. Choose "Accept" or "Reject"
5. For rejections, provide a reason (required)
6. The decision is logged in the audit trail

## Configuration

### Python Discovery

`includes/documentValidator.php` resolves the interpreter in this order:

1. `LMS_PYTHON` environment variable (absolute path to `python.exe`)
2. `where python` / `where python3` (Windows) or `which python` / `which python3`
   — Windows Store aliases inside `\WindowsApps\` are skipped because they only
   print an install message
3. Well-known install locations, e.g. `%LOCALAPPDATA%\Programs\Python\Python*\python.exe`,
   `C:\Python*\python.exe`, `%LOCALAPPDATA%\uv\python\*`

The first candidate that can `import cv2, numpy` wins. The chosen path is logged
to `logs/php_errors.log` as `[DOCUMENT] Using Python interpreter: …`.

**This deployment:** Python 3.12.9 (per-user install) at
`C:\Users\<user>\AppData\Local\Programs\Python\Python312\python.exe`, with
`opencv-python 5.0.0.93`, `numpy 2.5.4`, `pdf2image 1.17.0`, `pillow 13.0.0`.
It is found by the glob rule above, so the web server does not need Python on
its PATH.

### PDF Processing

- **Library**: pdf2image (requires Poppler)
- **Pages Checked**: First 10 pages (most registration documents are ≤5 pages)
- **Timeout**: 30 seconds per document
- **DPI**: 150 (balance between quality and processing time)
- **Poppler lookup**: `POPPLER_PATH` env var → `C:\poppler\poppler-*\Library\bin`
  → `C:\poppler\Library\bin` → `C:\Program Files\poppler\Library\bin` → PATH.
  If Poppler cannot be found the PDF is reported as `unavailable` (accepted and
  flagged for manual review) rather than rejected as invalid.

### Thresholds

Thresholds live in **one** place: `scripts/quality/thresholds.py` (mirrored in
the table above). `check_blur.py` no longer hard-codes any number, so recalibrating
means editing that module and re-running the tests:

```bash
python tests/test_check_blur.py           # 4 canonical cases
python tests/test_quality_pipeline.py     # 13 pipeline/regression cases
```

### OCR engine

Legibility and document-type verification use the first engine available:

1. `rapidocr-onnxruntime` (or `rapidocr`) — pip only, no system binary, ~1-3 s/page
2. `pytesseract` — requires the Tesseract executable

```bash
pip install rapidocr-onnxruntime
```

Expected keywords per document type live in `scripts/document_keywords.json`
(the seeded types are **Birth Certificate**, **Medical**, **Valid ID**). Missing
keywords produce `flagged`, never a rejection — OCR on old or stylised
certificates is not reliable enough to hard-fail on content.

OCR is skipped automatically when no engine is installed; the document is then
judged on sharpness/readability only and the organizer sees an
"automatic check unavailable" flag instead of a silent pass.

### Policy configuration

`config/documentQuality.php` (all optional environment variables):

| Setting | Env var | Default | Effect |
|---------|---------|---------|--------|
| `ocr` | `LMS_DOCUMENT_OCR` | `auto` | `auto` / `on` / `off` |
| `max_ocr_pages` | `LMS_DOCUMENT_OCR_PAGES` | `2` | hard cap; OCR stops after the first conclusive page |
| `block_when_unavailable` | `LMS_DOCUMENT_BLOCK_UNAVAILABLE` | `0` | reject uploads while the checker is down |
| `block_when_flagged` | `LMS_DOCUMENT_BLOCK_FLAGGED` | `0` | reject borderline documents instead of queueing manual review |
| `parallel_documents` | `LMS_DOCUMENT_PARALLEL` | `1` | concurrent checker processes (see Performance) |
| `cache_verdicts` | `LMS_DOCUMENT_CACHE` | `1` | reuse the verdict for byte-identical uploads |
| `cache_ttl_days` | `LMS_DOCUMENT_CACHE_DAYS` | `30` | how long cached verdicts are kept |
| warm process | `LMS_DOCUMENT_SESSION` | `1` | one interpreter per request (`0` = one per batch) |
| `blocking_statuses` | — | `blurry, unreadable, invalid` | verdicts that stop a registration |

### Performance

Measured on the reference machine (Windows, 12 logical cores, OpenCV 5.0.0,
Python 3.12, one 1240×1754 document page):

| Phase | Cost |
|-------|------|
| imread + page detection | 0.016 s |
| normalise, denoise, classic metrics, re-blur sharpness | 0.053 s |
| readability (contrast / ink / text height) | 0.013 s |
| OCR engine load (once per process) | 0.96 s |
| OCR inference @1600 px | 4.57 s |
| OCR inference @1280 px | 3.88 s (same 914 chars, same confidence) |

OCR dominates, so the pipeline is built around *not paying for it*:

1. **Cheap checks first.** A page that fails blur/blank/darkness/size checks is
   rejected before OCR is even imported. Rejected upload: **4.07 s → 0.30 s**
   (measured on the same file), and the OCR model is never loaded.
2. **Short-circuit at file level.** `check_file()` no longer probes the engine
   when the caller passes `--ocr=off`; that alone saves ~1 s per document.
3. **Cheaper OCR settings.** 1280 px instead of 1600 px (−0.7 s/page, identical
   recognition), and OCR stops after the first page that is legible *and*
   contains the expected keywords. A typical one-page document therefore costs
   one OCR pass; page 2 is only read when page 1 was illegible or lacked the
   expected content (hard cap: 2 pages).
4. **Fewer pages rendered from PDFs.** 3 instead of 10, so a long PDF cannot
   spend 40 s rendering pages nobody inspects.
5. **Verdict cache.** A byte-identical upload (same file, document type, OCR
   mode and thresholds version) reuses the previous verdict from
   `storage/private/cache/quality/`. Resubmitting the same photo costs
   **0.01 s instead of 14.9 s** (measured, 4 documents). Bump
   `THRESHOLDS_VERSION` in `scripts/quality/thresholds.py` after recalibration to
   invalidate every cached verdict.
6. **One warm checker process per request.** `scripts/check_blur.py --serve`
   answers JSON lines on stdin, and `includes/documentValidator.php` keeps that
   process (interpreter + OpenCV + loaded OCR model) alive for the whole PHP
   request. A league with two document requirements used to start Python twice;
   a `php scripts/reprocess_quality_metrics.php` run used to start it once *per
   document*. Measured with the real engine on the reference machine, one page
   per batch: **first batch 7.53 s, every following batch 3.69 s**. Responses
   travel through a small file in `storage/private/cache/quality/` because
   Windows anonymous pipes cannot be read without blocking; the parent polls it
   with a deadline and falls back to a one-shot run if the warm process dies.
   `LMS_DOCUMENT_SESSION=0` restores one process per batch.

Resulting wall-clock times for a 4-document submission (all pages legible, OCR
enabled, verdict cache disabled so the analysis is really measured):

| Scenario | Time |
|----------|------|
| Before these changes (v1 checker, 4 pages) | 3.6–7.1 s per document |
| v2, four documents in one requirement group | 14.9 s |
| v2, two requirement groups (one process per group) | 14.9 s |
| v3, same two groups with the warm process | **10.7 s** (−28%) |
| v2/v3, same 4 documents resubmitted (cache) | 0.01 s |
| 3 of 4 documents rejected by the cheap checks | ~0.3 s each + ~5 s for the good one |

`parallel_documents` exists (`LMS_DOCUMENT_PARALLEL`) but defaults to **1** and
should stay there: the ONNX OCR runtime already uses every core, so concurrent
checker processes only oversubscribe the CPU. Re-measured on the reference
machine (4 documents, identical copies, OCR on): **17.2 s in one process vs
39.4 s in four processes**, and capping each worker with
`LMS_DOCUMENT_OCR_THREADS=3` did not help (38.1 s). Shrinking `OCR["target_side"]`
is not a lever either: 1280 px 4.71 s, 1024 px 4.83 s, 896 px 4.68 s — the
recogniser cost is dominated by the text lines it finds, not the image size.

If a submission must never wait for OCR, run the checker out of band: store the
documents with `quality_status = 'Pending'` and process them with
`php scripts/reprocess_quality_metrics.php --limit=N`, which reuses the exact
same pipeline and cache.

### Health check

```bash
php scripts/document_checker_health.php             # interpreter, cv, OCR, Poppler, policy
php scripts/document_checker_health.php --self-test # also analyses tests/fixtures/sample_page.jpg
```

Exit codes: `0` healthy, `1` degraded (documents would be accepted unchecked),
`2` self-test failed. Run it after deploying or after changing Python packages.

## Migration

To add the `quality_metrics` column to an existing database:

```bash
mysql -u your_username -p your_database < database/migrations/031_add_quality_metrics.sql
```

## Troubleshooting

### Python Not Found

**Error**: "Python is not available for automatic document checking"

**Solution**: 
- Verify Python is installed and in PATH
- Check PHP error logs for command output
- Ensure `proc_open` is enabled in `php.ini`

### OpenCV Import Error

**Error**: "OpenCV is not installed"

**Solution**:
```bash
pip install opencv-python numpy
```

For PDF support:
```bash
pip install pdf2image
```

Poppler binaries are required for PDFs (they are **not** a pip package):

```powershell
# Windows: download the release zip, then extract it to C:\poppler
#   https://github.com/oschwartz10612/poppler-windows/releases
# The binaries end up in e.g. C:\poppler\poppler-26.09.0\Library\bin\pdftoppm.exe
# check_blur.py finds that automatically; override with the POPPLER_PATH env var.
```

```bash
# Linux
sudo apt install poppler-utils
```

### High False Positive Rate

If too many clear documents are flagged:

1. Review the `quality_metrics` JSON in the database
2. Identify common metric patterns in flagged documents
3. Adjust thresholds in `check_blur.py` (e.g., increase `detail_ratio` from 0.012 to 0.015)
4. Test with a sample of known-good documents

### Processing Timeout

If documents take too long to analyze:

1. Reduce `last_page` in PDF conversion (currently 10)
2. Lower DPI from 150 to 100
3. Reduce timeout from 30 to 15 seconds

## Future Enhancements

- [ ] Batch reprocessing of existing documents with the v2 pipeline (`scripts/reprocess_quality_metrics.php`)
- [ ] Machine learning model for document classification
- [x] OCR-based text extraction for content validation (RapidOCR / pytesseract)
- [ ] Automated rejection with suggested corrections (already: per-verdict messages)
- [ ] Integration with third-party document verification services

## Audit Trail

All document reviews are logged in `tbl_audit_log`:

```sql
INSERT INTO tbl_audit_log (user_name, user_id, user_role, action, details)
VALUES ('organizer_name', 123, 'Organizer', 'Review Document', 
        'Marked document "birth_cert.pdf" (ID 456) as Accepted for team Phoenix Warriors');
```

## Support

For issues or questions:
- Check PHP error logs: `xampp/apache/logs/error.log`
- Review Python script output in browser console (F12)
- Verify database schema matches migration files
- Test with sample documents in a staging environment

