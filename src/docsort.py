#!/usr/bin/env python3
"""
The text of a document, for sorting it by what it is — "Document sorter" for Nextcloud.

  docsort.py <file> [--pages N] [--lang ro]

prints one JSON object: {"text": "...", "pages": n, "engine": "pdftotext|rapidocr|zip|plain", "chars": n}

PDF:     pdftotext (poppler) when the PDF carries text; otherwise the first pages are rendered
         with pdftoppm and read with RapidOCR (PaddleOCR PP-OCRv4 models on ONNX Runtime).
Images:  RapidOCR.
Office:  the XML inside .docx/.odt/.pptx/.xlsx (no external program).
Text:    read as is.
Nothing is written anywhere except a temporary folder that is removed at the end.
"""
import json
import os
import re
import shutil
import subprocess
import sys
import tempfile
import zipfile

MAX_CHARS = 20000


def run(cmd, timeout=120):
    return subprocess.run(cmd, capture_output=True, timeout=timeout)


def pdf_text(path, pages):
    try:
        r = run(['pdftotext', '-l', str(pages), '-layout', '-enc', 'UTF-8', path, '-'])
        if r.returncode == 0:
            return r.stdout.decode('utf-8', 'replace')
    except (FileNotFoundError, subprocess.TimeoutExpired):
        pass
    return ''


def pdf_render(path, pages, tmp):
    try:
        r = run(['pdftoppm', '-r', '200', '-f', '1', '-l', str(pages), '-png', path, os.path.join(tmp, 'p')])
        if r.returncode != 0:
            return []
    except (FileNotFoundError, subprocess.TimeoutExpired):
        return []
    return sorted(os.path.join(tmp, f) for f in os.listdir(tmp) if f.endswith('.png'))


_engine = None


def ocr(paths):
    global _engine
    if _engine is None:
        from rapidocr_onnxruntime import RapidOCR
        _engine = RapidOCR()
    out = []
    for p in paths:
        try:
            result, _ = _engine(p)
        except Exception as e:  # noqa: BLE001
            sys.stderr.write('ocr failed on %s: %s\n' % (p, e))
            continue
        if not result:
            continue
        # rows top to bottom, then left to right, so phrases stay together
        items = []
        for box, text, conf in result:
            ys = [pt[1] for pt in box]
            xs = [pt[0] for pt in box]
            items.append((min(ys), min(xs), max(ys) - min(ys), text))
        items.sort()
        rows, row, last_y, last_h = [], [], None, 0
        for y, x, h, text in items:
            if last_y is not None and abs(y - last_y) > max(8, 0.6 * max(h, last_h)):
                rows.append(row)
                row = []
            row.append((x, text))
            last_y, last_h = y, h
        if row:
            rows.append(row)
        out.append('\n'.join(' '.join(t for _, t in sorted(r)) for r in rows))
    return '\n'.join(out)


def zip_text(path):
    names = {
        'word/document.xml', 'content.xml', 'xl/sharedStrings.xml',
    }
    try:
        with zipfile.ZipFile(path) as z:
            parts = []
            for n in z.namelist():
                if n in names or n.startswith('ppt/slides/slide') or n.startswith('xl/worksheets/'):
                    parts.append(z.read(n).decode('utf-8', 'replace'))
    except (zipfile.BadZipFile, OSError):
        return ''
    text = ' '.join(parts)
    text = re.sub(r'<[^>]+>', ' ', text)
    return re.sub(r'\s+', ' ', text)


def main():
    args = [a for a in sys.argv[1:] if not a.startswith('--')]
    if not args:
        raise SystemExit(__doc__)
    path = args[0]
    pages = 2
    for i, a in enumerate(sys.argv):
        if a == '--pages' and i + 1 < len(sys.argv):
            pages = max(1, int(sys.argv[i + 1]))
    ext = os.path.splitext(path)[1].lower()
    text, engine = '', 'plain'
    tmp = tempfile.mkdtemp(prefix='docsort-')
    try:
        if ext == '.pdf':
            text = pdf_text(path, pages)
            engine = 'pdftotext'
            if len(re.sub(r'\s+', '', text)) < 80:
                text, engine = ocr(pdf_render(path, pages, tmp)), 'rapidocr'
        elif ext in ('.jpg', '.jpeg', '.png', '.bmp', '.tif', '.tiff', '.webp', '.gif'):
            text, engine = ocr([path]), 'rapidocr'
        elif ext in ('.heic', '.heif'):
            try:
                from PIL import Image
                import pillow_heif  # noqa: F401
                pillow_heif.register_heif_opener()
                out = os.path.join(tmp, 'img.png')
                Image.open(path).convert('RGB').save(out)
                text, engine = ocr([out]), 'rapidocr'
            except Exception as e:  # noqa: BLE001
                sys.stderr.write('heic not readable: %s\n' % e)
        elif ext in ('.docx', '.odt', '.pptx', '.odp', '.xlsx', '.ods', '.dotx'):
            text, engine = zip_text(path), 'zip'
        elif ext in ('.txt', '.md', '.csv', '.rtf', '.eml', '.html', '.htm'):
            with open(path, 'rb') as f:
                text = f.read(MAX_CHARS * 2).decode('utf-8', 'replace')
            if ext in ('.html', '.htm'):
                text = re.sub(r'<[^>]+>', ' ', text)
    finally:
        shutil.rmtree(tmp, ignore_errors=True)
    text = text[:MAX_CHARS]
    print(json.dumps({'text': text, 'pages': pages, 'engine': engine, 'chars': len(text)}, ensure_ascii=False))


if __name__ == '__main__':
    main()
