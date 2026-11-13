"""Report what the document checker can do on this machine.

``includes/documentValidator.php`` runs this once, caches the JSON in
``storage/private/cache/python_interpreter.json`` and shows it in the admin
health panel.  Keeping the probe in a file (instead of a long ``python -c``
one-liner) makes it readable and easy to run by hand:

    python scripts/checker_probe.py
"""

import json
import sys

capabilities = {
    "ok": True,
    "python": sys.version.split()[0],
    "cv2": None,
    "numpy": None,
    "ocr": None,
    "ocr_available": False,
    "ocr_detail": "",
}

try:
    import cv2
    capabilities["cv2"] = cv2.__version__
except Exception as exc:  # pragma: no cover - depends on install
    capabilities["ok"] = False
    capabilities["cv2"] = type(exc).__name__

try:
    import numpy
    capabilities["numpy"] = numpy.__version__
except Exception as exc:  # pragma: no cover
    capabilities["ok"] = False
    capabilities["numpy"] = type(exc).__name__

try:
    from quality import ocr
    status = ocr.engine_status()
    capabilities["ocr"] = status["engine"]
    capabilities["ocr_available"] = status["available"]
    capabilities["ocr_detail"] = status["detail"]
except Exception as exc:  # pragma: no cover
    capabilities["ocr_detail"] = type(exc).__name__

print(json.dumps(capabilities), flush=True)
sys.exit(0 if capabilities["ok"] else 1)
