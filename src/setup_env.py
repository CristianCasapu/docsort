#!/usr/bin/env python3
"""
Installs the document reader into the virtual environment this script runs in — called by
the app from the administration page (or "occ docsort:install"); nothing to do by hand.

    <venv>/bin/python setup_env.py

Packages: RapidOCR (neural text recognition; the ONNX Runtime build for Python < 3.13, the
newer "rapidocr" package after that), pypdfium2 (PDF text and rendering, no poppler needed),
OpenCV headless (no libGL on the server needed), numpy. Only pre-built wheels are used, so no
compiler is needed. Prints what it does, one line at a time.
"""
import importlib
import importlib.metadata as md
import json
import os
import re
import subprocess
import sys

PY = sys.executable


def say(msg):
    print(msg, flush=True)


def pip(*args, check=True):
    cmd = [PY, '-m', 'pip', '--disable-pip-version-check', '--no-input', *args]
    say('$ ' + ' '.join(a for a in cmd[3:]))
    r = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.STDOUT, text=True)
    for line in r.stdout.splitlines():
        if line.strip() and not line.startswith('  '):
            say(line.rstrip())
    if check and r.returncode != 0:
        raise SystemExit('pip failed (%d)' % r.returncode)
    return r.returncode


def deps_of(dist, drop=(), add=()):
    """The requirements of an installed distribution, minus the ones we replace."""
    out = list(add)
    for req in md.requires(dist) or []:
        req = req.split(';')[0].strip()   # no environment markers here (all are unconditional)
        name = re.split(r'[<>=!~\[ ]', req, 1)[0].strip().lower().replace('_', '-')
        if name in drop or not name:
            continue
        out.append(req)
    return out


def main():
    say('Python %d.%d.%d at %s' % (*sys.version_info[:3], PY))
    pip('install', '--upgrade', 'pip', 'wheel', check=False)

    legacy = sys.version_info < (3, 13)
    ocr = 'rapidocr_onnxruntime>=1.3,<2' if legacy else 'rapidocr>=2'
    ocr_dist = 'rapidocr_onnxruntime' if legacy else 'rapidocr'
    say('OCR package: ' + ocr)
    # the OCR package alone first, then its requirements with OpenCV swapped for the headless
    # build (the normal one needs libGL, which servers do not have)
    pip('install', '--only-binary=:all:', '--no-deps', '--upgrade', ocr)
    deps = deps_of(ocr_dist, drop=('opencv-python', 'opencv-contrib-python'),
                   add=('opencv-python-headless>=4.5,<5', 'onnxruntime>=1.7', 'pypdfium2>=4'))
    pip('install', '--only-binary=:all:', '--upgrade', *deps)

    say('== Checking the reader')
    import numpy as np
    import cv2  # noqa: F401
    import pypdfium2  # noqa: F401
    if legacy:
        from rapidocr_onnxruntime import RapidOCR
    else:
        from rapidocr import RapidOCR
    engine = RapidOCR()
    # one small picture with a word on it: the first run of the newer package fetches its
    # models, and that had better happen now and not on somebody's first document
    img = np.full((64, 220, 3), 255, np.uint8)
    cv2.putText(img, 'DOCUMENT', (8, 44), cv2.FONT_HERSHEY_SIMPLEX, 1.2, (0, 0, 0), 2)
    out = engine(img)
    found = out[0] if isinstance(out, tuple) else getattr(out, 'txts', None)
    say('warm-up read: %s' % (json.dumps([t[1] if isinstance(t, (list, tuple)) else t for t in (found or [])]),))
    versions = {d: md.version(d) for d in (ocr_dist, 'onnxruntime', 'opencv-python-headless', 'numpy', 'pypdfium2')}
    say('installed: ' + json.dumps(versions))
    say('== Done')


if __name__ == '__main__':
    main()
