import json
import logging
import os
import sys
import time

import cv2
import numpy as np

from quality import blur as blur_quality
from quality import ocr as ocr_quality
from quality import page as page_quality
from quality import readability as readability_quality
from quality.thresholds import OCR, PDF, THRESHOLDS_VERSION

LOG_DIR = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "logs")
os.makedirs(LOG_DIR, exist_ok=True)
logging.basicConfig(filename=os.path.join(LOG_DIR, "blur_detection.log"), level=logging.INFO,
                    format="%(asctime)s - %(levelname)s - %(message)s")

MAX_PIXELS = 40_000_000
MAX_SIDE = 12_000

# pdf2image shells out to the Poppler utilities (pdftoppm/pdftocairo). On Windows
# those binaries are not on PATH by default, and the web server keeps the PATH it
# was started with, so probe the usual install locations as well. Set POPPLER_PATH
# to override, or leave everything unset to rely on PATH only.
POPPLER_CANDIDATES = (
    os.environ.get("POPPLER_PATH", ""),
    r"C:\poppler\poppler-26.09.0\Library\bin",
    r"C:\poppler\Library\bin",
    r"C:\Program Files\poppler\Library\bin",
    r"C:\Program Files (x86)\poppler\Library\bin",
)


def poppler_path():
    """Return the directory holding pdftoppm.exe, or None to let pdf2image use PATH."""
    for candidate in POPPLER_CANDIDATES:
        if candidate and os.path.isfile(os.path.join(candidate, "pdftoppm.exe")):
            return candidate
    return None



def emit(status, reason, pages=None, file=None, extra=None):
    payload = {"status": status, "reason": reason, "pages_checked": len(pages or []), "pages": pages or []}
    if file is not None:
        payload["file"] = file
    payload["thresholds_version"] = THRESHOLDS_VERSION
    if isinstance(extra, dict):
        payload.update(extra)
    print(json.dumps(payload, separators=(",", ":")), flush=True)
    # Exit codes: 0=clear, 1=invalid (bad input/format), 2=flagged (needs manual review),
    # 3=blurry or unreadable (cannot be accepted), 4=unavailable (dependency or internal error)
    return {"clear": 0, "flagged": 2, "blurry": 3, "unreadable": 3,
            "invalid": 1, "unavailable": 4}.get(status, 1)


def metrics(image):
    """Measure one page. Compatibility entry point used by the test harness."""
    if image is None or image.size == 0:
        raise ValueError("The file could not be decoded as an image.")
    height, width = image.shape[:2]
    if height > MAX_SIDE or width > MAX_SIDE or height * width > MAX_PIXELS:
        raise ValueError("The image dimensions are too large to process safely.")
    return analyse_page(image, ocr_mode="off")["metrics"]


def _gray(image):
    return image if image.ndim == 2 else cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)


def analyse_page(image, document_type=None, ocr_mode="auto", ocr_engine=None):
    """Measure one page and decide its verdict.

    ``ocr_mode``: ``auto`` (use OCR when installed), ``on`` (require it) or
    ``off`` (skip it).  Returns the flat metrics dictionary expected by the PHP
    layer plus ``status``/``reason`` and the nested ``ocr`` block.

    OCR is the expensive part (~4 s per page on a laptop), so it only runs when
    the cheap page checks did not already decide the verdict.  A page that is
    blurry, blank, too dark or too small is rejected without touching OCR.
    """
    started = time.perf_counter()
    detected = page_quality.detect_page(image)
    gray = _gray(detected["crop"])
    normalized = blur_quality.normalize_scale(gray)
    clean = blur_quality.denoise(normalized)

    values = {"width": int(normalized.shape[1]), "height": int(normalized.shape[0]),
              "frame_width": int(gray.shape[1]), "frame_height": int(gray.shape[0]),
              "page_found": bool(detected["page_found"]),
              "page_bbox": list(detected["bbox"]),
              "page_area_ratio": detected["area_ratio"],
              "page_rectangularity": detected["rectangularity"]}
    values.update(blur_quality.classic_metrics(clean))
    values.update(blur_quality.sharpness_metrics(clean))
    values.update(readability_quality.readability_metrics(normalized, gray))
    measure_seconds = time.perf_counter() - started

    # Cheap verdict first: no point paying for OCR on a page that is already bad.
    status, reason = decide(values, None)
    ocr_values = None
    ocr_seconds = 0.0
    if status in ("clear", "flagged") and ocr_mode != "off":
        engine = ocr_engine if ocr_engine is not None else ocr_quality.resolve_engine()
        if engine is not None or ocr_mode == "on":
            ocr_started = time.perf_counter()
            target = blur_quality.normalize_scale(normalized, OCR["target_side"])
            ocr_values = ocr_quality.recognize(target, document_type, engine=engine)
            ocr_seconds = time.perf_counter() - ocr_started
            status, reason = decide(values, ocr_values)

    values.update({"status": status, "reason": reason, "ocr": ocr_values,
                   "timings": {"measure": round(measure_seconds, 3),
                               "ocr": round(ocr_seconds, 3)}})
    return {"metrics": values, "status": status, "reason": reason, "ocr": ocr_values}


def decide(values, ocr_values=None):
    """Map measured values to ``(status, reason)``.

    Order matters: a blank page reports "blurry" (nothing captured), then hard
    blur failures, then readability failures, then OCR legibility, and only then
    the soft "flagged" reasons.
    """
    for resolver in (readability_quality.blank_verdict,
                     blur_quality.blur_verdict,
                     readability_quality.readability_verdict,
                     ocr_quality.legibility_verdict):
        status, reason = resolver(values if resolver is not ocr_quality.legibility_verdict else ocr_values)
        if status:
            return status, reason

    content = ocr_quality.content_flag(ocr_values)
    if content:
        return "flagged", content

    soft = [flag for flag in (blur_quality.blur_soft_flag(values),
                              readability_quality.readability_soft_flag(values),
                              ocr_quality.ocr_soft_flag(ocr_values)) if flag]
    if soft:
        return "flagged", " ".join(soft)
    return "clear", "The document passed the preliminary quality check."


def classify(values):
    """Return the verdict for a metrics dictionary.

    Kept for ``tests/test_accuracy.py``.  The production pipeline calls
    :func:`decide` with the OCR block as well; every threshold now lives in
    ``quality/thresholds.py`` instead of being hard-coded here.
    """
    filled = {"sharp_p10": 999.0, "sharp_median": 999.0, "motion_score": 0.0,
              "contrast": 1.0, "ink_ratio": 0.05, "text_height": None}
    filled.update(values or {})
    return decide(filled, (values or {}).get("ocr"))[0]


def load_pages(path):
    ext = os.path.splitext(path)[1].lower()
    if ext in (".jpg", ".jpeg", ".png", ".webp", ".bmp"):
        return [cv2.imread(path)]
    if ext == ".pdf":
        from pdf2image import convert_from_path
        # Only the first pages are inspected: eligibility documents are 1-2 pages
        # and every extra page costs seconds of rendering and OCR.
        options = {"dpi": PDF["dpi"], "first_page": 1, "last_page": PDF["max_pages"], "timeout": 30}
        located = poppler_path()
        if located:
            options["poppler_path"] = located
        images = convert_from_path(path, **options)
        return [cv2.cvtColor(np.array(page), cv2.COLOR_RGB2BGR) for page in images]
    raise ValueError("This document format is not supported.")


def check_file(path, document_type=None, ocr_mode=None, max_ocr_pages=None):
    """Check one document and print exactly one JSON line.

    ``document_type`` is the league's document requirement name (e.g. "Valid
    ID"); it only drives the optional keyword expectations.  ``ocr_mode``
    defaults to ``$LMS_DOCUMENT_OCR`` and then to ``auto``.
    """
    if ocr_mode is None:
        configured = (os.environ.get("LMS_DOCUMENT_OCR") or "auto").strip().lower()
        ocr_mode = configured if configured in ("auto", "on", "off") else "auto"
    try:
        pages = load_pages(path)
        if not pages:
            return emit("invalid", "The document contains no readable pages.", file=path)

        # No engine probe here: it would load the OCR models (~1 s) even when the
        # caller disabled OCR or when every page fails the cheap checks.
        ocr_budget = 0 if ocr_mode == "off" else (
            OCR["max_pages"] if max_ocr_pages is None else max(0, int(max_ocr_pages)))
        ocr_pages = 0
        sufficient = False

        started = time.perf_counter()
        results = []
        statuses = []
        for number, image in enumerate(pages, 1):
            # Stop OCR'ing as soon as a page gave a conclusive result: single-page
            # documents cost one pass, and page 2 is only read when page 1 was
            # illegible or did not contain the expected content.
            use_ocr = ocr_budget > 0 and ocr_pages < ocr_budget and not sufficient
            page = analyse_page(image, document_type, ocr_mode=ocr_mode if use_ocr else "off")
            page["metrics"]["page"] = number
            if page.get("ocr") is not None:
                # Count only pages that were really OCR'd (a page rejected by the
                # cheap checks never reaches the engine).
                ocr_pages += 1
                sufficient = ocr_quality.is_sufficient(page.get("ocr"))
            results.append(page["metrics"])
            statuses.append(page["status"])
        elapsed = time.perf_counter() - started

        extra = {
            "ocr": ocr_quality.status_snapshot(),
            "ocr_pages": ocr_pages,
            "timings": {
                "total": round(elapsed, 3),
                "ocr": round(sum(float(p.get("timings", {}).get("ocr", 0) or 0) for p in results), 3),
            },
        }

        blocking = [status for status in statuses if status in ("blurry", "unreadable")]
        usable = [status for status in statuses if status in ("clear", "flagged")]
        if not usable:
            status = blocking[0] if blocking else "invalid"
            reason = next((page["reason"] for page in results if page["status"] == status),
                          "The document cannot be accepted.")
            return emit(status, reason, results, file=path, extra=extra)
        if "flagged" in usable or len(usable) < len(statuses):
            # A multi-page document is not rejected because one page (a cover, the
            # back side, a blank end page) is unreadable - but it needs a human look.
            reason = next((page["reason"] for page in results if page["status"] != "clear"),
                          "Parts of the document need manual review.")
            return emit("flagged", reason, results, file=path, extra=extra)
        return emit("clear", "The document passed the preliminary quality check.", results, file=path, extra=extra)
    except ImportError as exc:
        logging.exception("Document checker dependency unavailable")
        return emit("unavailable", "The automated checker is temporarily unavailable: " + str(exc), file=path)
    except Exception as exc:
        message = str(exc)
        if "poppler" in message.lower():
            logging.exception("Poppler utilities are unavailable")
            return emit("unavailable", "PDF quality checking needs the Poppler utilities, which are not installed.", file=path)
        logging.exception("Document processing failed")
        return emit("invalid", message[:240], file=path)


def _parse_arguments(argv):
    """Parse ``[--expect TYPE] [--ocr=auto|on|off] [--max-ocr-pages=N] PATH...``.

    ``--expect`` applies to the next path, so the PHP layer can hand one batch
    of documents with different requirement types to a single interpreter.
    """
    targets = []
    expectations = {}
    # The PHP layer passes --ocr explicitly; the environment variable lets
    # operators (and the test harness) switch OCR off without changing code.
    default_ocr = (os.environ.get("LMS_DOCUMENT_OCR") or "auto").strip().lower()
    ocr_mode = default_ocr if default_ocr in ("auto", "on", "off") else "auto"
    max_ocr_pages = None
    pending = None
    index = 0
    while index < len(argv):
        argument = argv[index]
        if argument == "--expect":
            index += 1
            pending = argv[index] if index < len(argv) else ""
        elif argument.startswith("--expect="):
            pending = argument.split("=", 1)[1]
        elif argument.startswith("--ocr="):
            value = argument.split("=", 1)[1].lower()
            ocr_mode = value if value in ("auto", "on", "off") else "auto"
        elif argument.startswith("--max-ocr-pages="):
            try:
                max_ocr_pages = int(argument.split("=", 1)[1])
            except ValueError:
                max_ocr_pages = None
        elif argument:
            targets.append(argument)
            expectations[argument] = pending or None
            pending = None
        index += 1
    return targets, expectations, ocr_mode, max_ocr_pages


def serve(ocr_mode="auto", max_ocr_pages=None, out_path=None):
    """Answer JSON requests from stdin, one batch per line.

    ``includes/documentValidator.php`` keeps ONE Python process alive for every
    document of a submission (and for every document a CLI reprocess run
    touches).  Starting the interpreter, importing OpenCV and loading the OCR
    model costs several seconds, so paying it once instead of once per document
    requirement is the cheapest speed-up available: verdicts are produced by the
    exact same :func:`check_file` call.

    Request  (one line on stdin):  {"files": [{"path": "...", "expect": "Valid ID"}, ...]}
    Response (one line per file):  the usual JSON payload
    End of batch:                  {"batch_done": true}

    ``out_path`` sends the response to a file instead of stdout. Windows pipes
    cannot be read without blocking, so the PHP parent polls the file for new
    lines and stdout stays free for the script's own diagnostics.
    """
    if out_path:
        # Line buffered: every payload must be readable by PHP immediately.
        sys.stdout = open(out_path, "w", encoding="utf-8", buffering=1)

    for line in sys.stdin:
        line = line.strip()
        if not line:
            continue
        try:
            request = json.loads(line)
        except ValueError:
            print(json.dumps({"batch_done": True, "error": "invalid request"}), flush=True)
            continue
        entries = request.get("files") if isinstance(request, dict) else None
        mode = (request.get("ocr") if isinstance(request, dict) else None) or ocr_mode
        if mode not in ("auto", "on", "off"):
            mode = ocr_mode
        pages_limit = request.get("max_ocr_pages", max_ocr_pages) if isinstance(request, dict) else max_ocr_pages
        for job in entries or []:
            if isinstance(job, dict):
                path = job.get("path")
                expect = job.get("expect")
            else:
                path, expect = job, None
            if not path:
                continue
            check_file(str(path), expect, ocr_mode=mode, max_ocr_pages=pages_limit)
        print(json.dumps({"batch_done": True}), flush=True)


if __name__ == "__main__":
    serve_out = None
    arguments = []
    for argument in sys.argv[1:]:
        if argument.startswith("--out="):
            serve_out = argument.split("=", 1)[1]
        elif argument != "--serve":
            arguments.append(argument)
    targets, expectations, ocr_mode, max_ocr_pages = _parse_arguments(arguments)
    if "--serve" in sys.argv[1:]:
        serve(ocr_mode, max_ocr_pages, serve_out)
        sys.exit(0)
    if not targets or any(not os.path.isfile(path) for path in targets):
        sys.exit(emit("invalid", "A valid document path is required."))

    # Batch mode: the interpreter (and the expensive OpenCV import) is started once
    # for every document of a submission. One JSON line is printed per file.
    exit_code = 0
    for path in targets:
        exit_code = max(exit_code, check_file(path, expectations.get(path),
                                              ocr_mode=ocr_mode, max_ocr_pages=max_ocr_pages))
    sys.exit(exit_code)
