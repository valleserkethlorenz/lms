"""OCR legibility and light content verification.

The engine is optional.  ``engine_status()`` reports what is available so the
PHP layer can raise a visible warning instead of silently accepting documents
when OCR is missing.  Supported engines, in order of preference:

* ``rapidocr-onnxruntime`` / ``rapidocr`` (pip only, no system binary)
* ``pytesseract`` (requires the Tesseract executable)

Nothing here decides the verdict on its own: ``legibility_verdict`` blocks only
when *no* text can be read, and ``content_flag`` merely downgrades a page to
"flagged" when the expected document-type keywords are missing.
"""

import json
import os
import re

from .thresholds import OCR

_KEYWORD_FILE = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))),
                             "document_keywords.json")

_engine = None
_engine_name = None
_engine_detail = ""
_engine_resolved = False
_keywords = None


def _load_keywords():
    global _keywords
    if _keywords is not None:
        return _keywords
    try:
        with open(_KEYWORD_FILE, "r", encoding="utf-8") as handle:
            payload = json.load(handle)
        _keywords = payload if isinstance(payload, dict) else {}
    except Exception:
        _keywords = {}
    return _keywords


def _normalise(text):
    text = str(text or "").lower()
    text = re.sub(r"[^a-z0-9 ]+", " ", text)
    return re.sub(r"\s+", " ", text).strip()


def expectations_for(document_type):
    """Return the keyword groups expected for a document type name."""
    table = _load_keywords()
    types = table.get("types") or {}
    key = _normalise(document_type)
    groups = []
    label = document_type or ""
    if key:
        for name, entry in types.items():
            candidate = _normalise(name)
            if key == candidate or key in candidate or candidate in key:
                groups = [list(group) for group in (entry.get("required") or [])]
                label = entry.get("label") or document_type
                break
    return {"label": label, "required": groups}


def _thread_limit():
    """Optional cap on ONNX threads (``LMS_DOCUMENT_OCR_THREADS``).

    Limiting each worker to one thread is what makes ``parallel_documents`` pay
    off: by default ONNX already uses every core, so extra processes only fight
    for the same CPUs.
    """
    try:
        value = int(str(os.environ.get("LMS_DOCUMENT_OCR_THREADS", "0")).strip() or 0)
    except ValueError:
        return 0
    return max(0, value)


def _resolve_engine():
    """Import an OCR engine once; remember what failed and why."""
    global _engine, _engine_name, _engine_detail, _engine_resolved
    if _engine_resolved:
        return _engine
    _engine_resolved = True
    threads = _thread_limit()
    options = {"intra_op_num_threads": threads} if threads else {}

    try:
        from rapidocr_onnxruntime import RapidOCR  # type: ignore
        _engine = RapidOCR(**options)
        _engine_name = "rapidocr-onnxruntime"
        _engine_detail = "ready" + (" (%d thread)" % threads if threads else "")
        return _engine
    except Exception as exc:  # pragma: no cover - depends on install
        _engine_detail = "rapidocr-onnxruntime: " + type(exc).__name__

    try:
        from rapidocr import RapidOCR  # type: ignore
        _engine = RapidOCR(**options)
        _engine_name = "rapidocr"
        _engine_detail = "ready" + (" (%d thread)" % threads if threads else "")
        return _engine
    except Exception as exc:  # pragma: no cover
        _engine_detail += "; rapidocr: " + type(exc).__name__

    try:
        import pytesseract  # type: ignore
        pytesseract.get_tesseract_version()
        _engine = pytesseract
        _engine_name = "pytesseract"
        _engine_detail = "ready"
        return _engine
    except Exception as exc:  # pragma: no cover
        _engine_detail += "; pytesseract: " + type(exc).__name__

    _engine = None
    return _engine


def resolve_engine():
    """Public alias for the lazily imported OCR engine (``None`` if unavailable)."""
    return _resolve_engine()


def status_snapshot():
    """Report what is *currently* loaded without importing anything.

    ``engine_status()`` resolves (and therefore loads) the engine, which costs
    about a second.  This snapshot is what the JSON payload uses, so a run with
    OCR disabled never pays that cost.
    """
    return {
        "engine": _engine_name if _engine is not None else None,
        "available": _engine is not None,
        "detail": _engine_detail or "not probed",
    }


def engine_status():
    """Describe OCR availability for the health probe and the PHP layer."""
    engine = _resolve_engine()
    return {
        "engine": _engine_name if engine is not None else None,
        "available": engine is not None,
        "detail": _engine_detail,
    }

def _rapidocr_words(output):
    """Normalise output across rapidocr versions into ``(text, score)`` pairs."""
    words = []
    if output is None:
        return words
    for item in output:
        if isinstance(item, (list, tuple)) and len(item) >= 3:
            text, score = item[1], item[2]
        elif isinstance(item, (list, tuple)) and len(item) == 2:
            text, score = item[0], item[1]
        else:
            continue
        if isinstance(text, str) and text.strip():
            try:
                words.append((text.strip(), float(score)))
            except (TypeError, ValueError):
                words.append((text.strip(), 0.0))
    return words


def _recognise_with_rapidocr(engine, image):
    result = engine(image)
    if isinstance(result, tuple) and len(result) == 2:
        result = result[0]
    txts = getattr(result, "txts", None)
    scores = getattr(result, "scores", None)
    if txts is not None and scores is not None:
        return [(str(t).strip(), float(s)) for t, s in zip(txts, scores) if str(t).strip()]
    return _rapidocr_words(result)


def _recognise_with_tesseract(engine, image):
    from pytesseract import Output
    data = engine.image_to_data(image, output_type=Output.DICT)
    words = []
    for text, conf in zip(data.get("text", []), data.get("conf", [])):
        text = str(text or "").strip()
        try:
            score = float(conf)
        except (TypeError, ValueError):
            continue
        if text and score >= 0:
            words.append((text, score))
    return words


def recognize(image, document_type=None, engine=None):
    """Run OCR and return legibility statistics, or ``None`` if unavailable."""
    if engine is None:
        engine = _resolve_engine()
    if engine is None:
        return None
    try:
        if _engine_name == "pytesseract":
            words = _recognise_with_tesseract(engine, image)
        else:
            words = _recognise_with_rapidocr(engine, image)
    except Exception:  # pragma: no cover - engine specific
        return None

    text = " ".join(word for word, _ in words)
    scores = [score for _, score in words]
    # rapidocr reports 0-1 confidences, pytesseract reports 0-100: normalise.
    if scores and max(scores) <= 1.0001:
        scores = [score * 100.0 for score in scores]
    chars = len(re.sub(r"[^0-9A-Za-z]", "", text))
    expectation = expectations_for(document_type)
    normalised = _normalise(text)
    matched, missing = [], []
    for group in expectation["required"]:
        alternatives = [alt for alt in group if str(alt).strip()]
        if not alternatives:
            continue
        hit = next((alt for alt in alternatives if _normalise(alt) in normalised), None)
        if hit:
            matched.append(hit)
        else:
            missing.append(alternatives[0])
    excerpt = re.sub(r"\s+", " ", text).strip()[:500]
    return {
        "engine": _engine_name or "injected",
        "words": len(words),
        "chars": chars,
        "mean_confidence": round(float(sum(scores) / len(scores)), 1) if scores else 0.0,
        "excerpt": excerpt,
        "expected": expectation["label"] if expectation["required"] else None,
        "matched_keywords": matched,
        "missing_keywords": missing,
    }


def is_sufficient(values):
    """True when this page's OCR is good enough to stop reading further pages.

    Used to keep multi-page documents cheap: once a page is legible and every
    expected keyword was found, later pages are only measured (never OCR'd).
    """
    if values is None:
        return False
    if values["chars"] < OCR["soft_chars"] or values["mean_confidence"] < OCR["soft_confidence"]:
        return False
    return not values["missing_keywords"]


def legibility_verdict(values):
    """Block only when the page yields no usable text at all."""
    if values is None:
        return None, None
    if values["chars"] < OCR["hard_chars"] or values["mean_confidence"] < OCR["hard_confidence"]:
        return "unreadable", "No legible text could be recognised on this document."
    return None, None


def content_flag(values):
    """Downgrade to "flagged" when the expected keywords are missing."""
    if values is None or not values["missing_keywords"]:
        return None
    found = ", ".join(values["matched_keywords"]) or "none"
    return ("This may not be the expected document: found " + found +
            " but not " + ", ".join(values["missing_keywords"]) + ".")


def ocr_soft_flag(values):
    if values is None:
        return None
    if values["chars"] < OCR["soft_chars"] or values["mean_confidence"] < OCR["soft_confidence"]:
        return "Only a small amount of text could be read; review the original."
    return None
