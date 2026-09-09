# Changelog

All notable changes to this project are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project uses
[semantic versioning](https://semver.org/).

## [1.1.0] – 2026-09-10

### Added
- The app installs its own document reader from the administration page: one button builds a
  Python environment in the data directory with RapidOCR, pypdfium2 and OpenCV (pre-built
  wheels only, about 150 MB) and checks it. A setup check in Administration › Overview says
  when it is missing, and the personal page tells people what is not read yet. `occ
  docsort:install` does the same from a terminal; `--remove` starts over.
- PDFs are read with pypdfium2, so poppler (`pdftotext`, `pdftoppm`) is no longer needed;
  it is still used when it is there and pypdfium2 is not.
- Office files and plain text are read in PHP and sort even without the reader.

### Changed
- A file that could not be read only because the reader is missing is not remembered as an
  error: it is read as soon as the reader is installed.
- Either RapidOCR package works (`rapidocr_onnxruntime` up to Python 3.12, `rapidocr` after).

## [1.0.0] – 2026-09-09

First release.

### Added
- An inbox folder whose scans, photos and PDFs are read, recognised and filed by kind into
  `<destination>/<category>/<kind>/`.
- Text is read with `pdftotext` for PDFs that carry text, with RapidOCR (neural text recognition)
  for pictures and scanned pages, and from the XML of office documents.
- 48 kinds of document in 13 categories — identity documents, civil status certificates, permits
  and licences, vehicle papers, invoices and receipts, bank and tax papers, contracts, property,
  health, education, work, utilities, insurance — recognised by keyword lists in Romanian and
  English that the administrator can replace.
- A `Document: <kind>` tag on every recognised file.
- Personal settings: on/off, inbox folder, destination folder, move or copy, group by category,
  what to do with unrecognised files, language of the folder names.
- A background job every 15 minutes for the people who switched it on, plus `occ docsort:scan` and
  `occ docsort:classify`.
- Only the kind of a document is remembered; its text is never stored.
- English and Romanian.
