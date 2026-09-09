# Document sorter — `docsort`

A **Nextcloud app** that reads the papers you scan or photograph, tells what they are and files
them into folders by kind. Drop scans, photos and PDFs into an inbox folder (`Documente/De
sortat` by default); every quarter of an hour — or when you press *Sort now* — each one is read,
recognised, tagged (`Document: Carte de identitate`) and moved to `Documente/<category>/<kind>/`.

The text of a document is **never stored**: only what it was recognised as, so the same file is
not read twice.

## What it recognises

48 kinds in 13 categories, out of the box: identity (identity card, passport, residence permit),
civil status (birth, marriage, death certificates, divorce), permits and licences (driving,
fishing, hunting, firearm, boat, building), vehicle (registration, insurance, technical
inspection, sale), finance (invoice, receipt, bank statement, tax, payslip), legal (contract,
power of attorney, court, will), property (deed, land registry, rental), health (medical report,
prescription, vaccination, health insurance), education (diploma, transcript, training
certificate), work (employment contract, work certificate, CV), utilities (electricity, gas,
water, telecom), insurance policies, and other (warranty, manual, ticket, letter).

Recognition is plain and readable: every kind has a list of words with weights, in Romanian
(without diacritics) and English; the words found add up and the kind with the highest score
wins if it reaches its minimum. The administrator can edit the rules as JSON in
Administration › Document sorter, try a text against them, or go back to the built-in ones.
A wrong guess is fixed by adding a word.

## How it reads

`src/docsort.py`, run with the Python of the Recognize fork's environment:

- **PDF with text** → `pdftotext` (poppler-utils, GPL-2, called as a separate program);
- **pictures and scanned PDFs** → [RapidOCR](https://github.com/RapidAI/RapidOCR) (Apache-2.0)
  with the PP-OCRv4 models of PaddleOCR (Apache-2.0) on ONNX Runtime (MIT) — the same neural
  reader "Sign up with ID" uses; scanned PDFs are rendered with `pdftoppm` first;
- **office files** (`.docx`, `.odt`, `.pptx`, `.xlsx`, …) → the XML inside, no external program;
- **plain text, Markdown, e-mail, HTML** → as they are.

Nothing is installed on top of the [Recognize fork](https://github.com/CristianCasapu/recognize)'s
environment (`occ recognize:install-insightface` creates it; `occ idregister:install-ocr` adds
RapidOCR).

## Installing the reader

Pictures and PDFs are read by a small Python program. The app installs it by itself:
Administration settings › **Document sorter** › **Install the reader**. A background job builds
a virtual environment in the data directory (`appdata_<id>/docsort/python`) with RapidOCR
(neural text recognition), pypdfium2 (PDF text and rendering) and OpenCV — about 150 MB of
pre-built packages, no compiler, nothing outside the data directory. The only thing the server
needs is Python 3.8 or newer (`python3`); a setup check in Administration › Overview says so
when it is missing.

Office files and plain text are read in PHP and sort without the reader. `occ docsort:install`
does the same installation from a terminal, `occ docsort:install --remove` starts over. An
existing Python with RapidOCR can be pointed at with the app setting `pythonBinary` instead.

## Settings

*Personal settings › Document sorter*: switch it on, inbox folders, destination, what to do with
a recognised document (move / copy / only tag), a folder per category or not, what happens to
documents nobody recognised (stay in the inbox, or an *Unsorted* folder), the language of folder
and tag names, and the list of what was sorted recently.

## Commands

```bash
occ docsort:scan [user] [--dry-run] [--again] [--limit N]   # sort the inbox of everybody (or one person)
occ docsort:classify <file> [--text]                        # what a file on the server would be sorted as
```

## Licence

AGPL-3.0-or-later.

---

## Pe scurt (română)

Aplicație pentru **Nextcloud** care citește actele scanate sau fotografiate, își dă seama ce sunt
(carte de identitate, pașaport, certificat de naștere/căsătorie, permis de conducere/pescuit/
vânătoare, talon auto, factură, chitanță, extras de cont, contract, diplomă, acte medicale,
facturi de utilități …) și le pune în dosare pe categorii și feluri, cu o etichetă pe fiecare.
Citirea: `pdftotext` pentru PDF-uri cu text, RapidOCR (recunoaștere neurală de text, Apache-2.0)
pentru poze și scanări. Textul nu se păstrează niciodată — doar felul documentului. Regulile sunt
liste de cuvinte cu ponderi, în română și engleză, pe care administratorul le poate schimba.
