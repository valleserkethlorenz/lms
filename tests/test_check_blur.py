"""
CLI Test Harness for check_blur.py
===================================
Generates synthetic test images to verify the blur detection pipeline
classifies documents correctly without requiring real scanned documents.

Usage:
    python tests/test_check_blur.py           # Run all tests
    python tests/test_check_blur.py --verbose  # Show detailed metrics
"""

import argparse
import io
import json
import os
import sys
import tempfile
from contextlib import redirect_stdout

import cv2
import numpy as np

sys.path.insert(0, os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), 'scripts'))
import check_blur


def generate_clear_document(width=800, height=600):
    """Generate a synthetic clear document with text-like patterns."""
    img = np.ones((height, width, 3), dtype=np.uint8) * 255
    # Draw dense text-like horizontal lines with varying darkness
    for y in range(30, height - 30, 18):
        thickness = np.random.randint(1, 3)
        length = np.random.randint(width // 4, width - 80)
        x_start = np.random.randint(40, width - length - 40)
        darkness = np.random.randint(0, 60)
        cv2.line(img, (x_start, y), (x_start + length, y), (darkness, darkness, darkness), thickness)
    # Add paragraph blocks and borders for more tonal variety
    for _ in range(8):
        x = np.random.randint(30, width - 150)
        y = np.random.randint(30, height - 100)
        w = np.random.randint(80, 200)
        h = np.random.randint(15, 50)
        cv2.rectangle(img, (x, y), (x + w, y + h), (0, 0, 0), 1)
    # Add a header line and footer for contrast spread
    cv2.line(img, (40, 20), (width - 40, 20), (0, 0, 0), 2)
    cv2.line(img, (40, height - 20), (width - 40, height - 20), (0, 0, 0), 2)
    # Add some gray shading regions to boost entropy
    for _ in range(3):
        x = np.random.randint(50, width - 100)
        y = np.random.randint(50, height - 80)
        w = np.random.randint(40, 80)
        h = np.random.randint(20, 40)
        shade = np.random.randint(160, 220)
        cv2.rectangle(img, (x, y), (x + w, y + h), (shade, shade, shade), -1)
    return img


def generate_blurry_document(width=800, height=600):
    """Generate a severely blurred document."""
    clear = generate_clear_document(width, height)
    blurry = cv2.GaussianBlur(clear, (51, 51), 0)
    blurry = cv2.GaussianBlur(blurry, (31, 31), 0)
    return blurry


def generate_blank_document(width=800, height=600):
    """Generate a completely blank (white) document."""
    return np.ones((height, width, 3), dtype=np.uint8) * 255


def generate_low_contrast_document(width=800, height=600):
    """Generate a document with moderate low contrast (slightly underexposed)."""
    img = generate_clear_document(width, height)
    # Reduce contrast moderately (40%) to simulate poor lighting
    # This should trigger 'flagged' but not 'blurry'
    img = (img * 0.4 + 90).astype(np.uint8)
    return img


def run_test(name, image, expected_status, verbose=False):
    """Run a single test case and return (passed, metrics)."""
    with tempfile.NamedTemporaryFile(suffix='.jpg', delete=False) as tmp:
        cv2.imwrite(tmp.name, image)
        tmp_path = tmp.name
    try:
        f = io.StringIO()
        with redirect_stdout(f):
            exit_code = check_blur.check_file(tmp_path)
        output = f.getvalue()
        lines = output.strip().split('\n')
        payload = json.loads(lines[-1])
        actual_status = payload['status']
        passed = actual_status == expected_status
        if verbose or not passed:
            print(f"\n{'✓' if passed else '✗'} Test: {name}")
            print(f"  Expected: {expected_status}")
            print(f"  Actual:   {actual_status}")
            print(f"  Reason:   {payload.get('reason', 'N/A')}")
            if 'pages' in payload and payload['pages']:
                page = payload['pages'][0]
                print(f"  Metrics:  laplacian_std={page.get('laplacian_std', 'N/A')}, "
                      f"detail_ratio={page.get('detail_ratio', 'N/A')}, "
                      f"entropy={page.get('entropy', 'N/A')}")
        return passed, payload
    finally:
        os.unlink(tmp_path)


def main():
    parser = argparse.ArgumentParser(description='Test harness for check_blur.py')
    parser.add_argument('--verbose', '-v', action='store_true',
                        help='Show detailed metrics for all tests')
    args = parser.parse_args()
    np.random.seed(42)  # Reproducible results
    print("=" * 70)
    print("Blur Detection Test Harness")
    print("=" * 70)
    tests = [
        ("Clear document", generate_clear_document(), "clear"),
        ("Blurry document", generate_blurry_document(), "blurry"),
        ("Blank document", generate_blank_document(), "blurry"),
        ("Low contrast document", generate_low_contrast_document(), "flagged"),
    ]
    results = []
    for name, image, expected in tests:
        passed, payload = run_test(name, image, expected, args.verbose)
        results.append((name, passed, expected, payload.get('status', 'unknown')))
    print("\n" + "=" * 70)
    print("Test Summary")
    print("=" * 70)
    passed_count = sum(1 for _, passed, _, _ in results if passed)
    total_count = len(results)
    for name, passed, expected, actual in results:
        status = "PASS" if passed else "FAIL"
        print(f"{status}  {name:30s}  expected={expected:10s}  actual={actual:10s}")
    print("\n" + "-" * 70)
    print(f"Total: {passed_count}/{total_count} tests passed")
    if passed_count == total_count:
        print("\nAll tests passed!")
        return 0
    else:
        print(f"\n{total_count - passed_count} test(s) failed")
        return 1


if __name__ == '__main__':
    sys.exit(main())