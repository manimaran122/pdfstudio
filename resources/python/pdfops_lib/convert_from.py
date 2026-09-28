"""Convert from PDF: Word, Excel and Markdown built from the PDF's own text.

All three share one layout pass (_analyze) that turns each page into
reading-order items: paragraphs (with heading level, list kind and bold /
italic spans), tables found by find_tables(), and images. Text inside a
table is left out of the paragraphs so it isn't written twice.
"""
import io
import re
from collections import Counter

import fitz

from .common import UserError, open_pdf

NO_TEXT = "This PDF has no selectable text, so it looks scanned. Run OCR PDF on it first, then convert the result."
BULLETS = "•·◦▪▫●○■□‣⁃∙–—-*"
BULLET_RE = re.compile(r"^\s*[" + re.escape(BULLETS) + r"]\s+")
NUMBER_RE = re.compile(r"^\s*(\d{1,3})[.)]\s+")
DICT_FLAGS = fitz.TEXT_PRESERVE_LIGATURES | fitz.TEXT_PRESERVE_WHITESPACE | fitz.TEXT_PRESERVE_IMAGES


# ---------------------------------------------------------------- analysis

def _style(span):
    font = span["font"].lower()
    bold = bool(span["flags"] & 16) or any(w in font for w in ("bold", "black", "heavy", "semibold"))
    italic = bool(span["flags"] & 2) or "italic" in font or "oblique" in font
    return bold, italic


def _tables(page):
    try:
        found = page.find_tables().tables
    except Exception:  # noqa: BLE001 - table detection is best effort
        return []

    tables = []
    for table in found:
        rows = [[(cell or "").replace("\n", " ").strip() for cell in row] for row in table.extract()]
        if rows and len(rows[0]) >= 2 and any(any(row) for row in rows):
            tables.append({"bbox": fitz.Rect(table.bbox), "rows": rows})
    return tables


def _inside(rect, boxes):
    x, y = (rect.x0 + rect.x1) / 2, (rect.y0 + rect.y1) / 2
    return any(box.x0 - 1 <= x <= box.x1 + 1 and box.y0 - 1 <= y <= box.y1 + 1 for box in boxes)


def _page_lines(page, table_boxes, images):
    """Blocks of line records ({bbox, size, spans}) and image items."""
    blocks, pictures = [], []
    for block in page.get_text("dict", flags=DICT_FLAGS if images else DICT_FLAGS & ~fitz.TEXT_PRESERVE_IMAGES, sort=True)["blocks"]:
        if block["type"] == 1:
            if block["width"] >= 16 and block["height"] >= 16 and not _inside(fitz.Rect(block["bbox"]), table_boxes):
                pictures.append({"type": "image", "y": block["bbox"][1], "block": block})
            continue

        lines = []
        for line in block["lines"]:
            spans = [s for s in line["spans"] if s["text"].strip()]
            if not spans or _inside(fitz.Rect(line["bbox"]), table_boxes):
                continue
            sizes = Counter()
            for s in spans:
                sizes[round(s["size"] * 2) / 2] += len(s["text"].strip())
            lines.append({
                "bbox": fitz.Rect(line["bbox"]),
                "size": sizes.most_common(1)[0][0],
                "spans": [(s["text"], *_style(s)) for s in spans],
            })
        if lines:
            blocks.append(_merge_bullets(lines))
    return blocks, pictures


def _merge_bullets(lines):
    """A bullet drawn as its own glyph run becomes the start of the line beside it."""
    merged = []
    for line in lines:
        prev = merged[-1] if merged else None
        prev_text = "".join(t for t, _, _ in prev["spans"]).strip() if prev else ""
        same_row = prev and abs(prev["bbox"].y1 - line["bbox"].y1) < line["size"] / 2 and prev["bbox"].x1 <= line["bbox"].x0
        if same_row and len(prev_text) == 1 and prev_text in BULLETS:
            prev["spans"] = [(prev_text + " ", False, False), *line["spans"]]
            prev["bbox"] = prev["bbox"] | line["bbox"]
            prev["size"] = line["size"]
            continue
        merged.append(line)
    return merged


def _levels(pages):
    """Body font size, and heading level (1-3) per larger font size."""
    sizes = Counter()
    for blocks in pages:
        for lines in blocks:
            for line in lines:
                sizes[line["size"]] += sum(len(t.strip()) for t, _, _ in line["spans"])
    if not sizes:
        return 0, {}

    body = sizes.most_common(1)[0][0]
    larger = sorted((s for s in sizes if s >= body * 1.15), reverse=True)
    return body, {size: min(rank + 1, 3) for rank, size in enumerate(larger)}


def _strip_prefix(spans, count):
    out = []
    for text, bold, italic in spans:
        if count > 0:
            cut = min(count, len(text))
            text, count = text[cut:], count - cut
        if text:
            out.append((text, bold, italic))
    return out


def _paragraphs(lines, levels):
    """Group a block's lines into headings, list items and paragraphs."""
    paragraphs = []
    current = None
    for line in lines:
        text = "".join(t for t, _, _ in line["spans"])
        level = levels.get(line["size"], 0) if len(text) <= 200 else 0
        kind, number, spans = "para", None, line["spans"]

        if level:
            kind = "heading"
        elif match := BULLET_RE.match(text):
            kind, spans = "bullet", _strip_prefix(spans, match.end())
        elif match := NUMBER_RE.match(text):
            kind, number, spans = "number", int(match.group(1)), _strip_prefix(spans, match.end())

        if current is not None and _continues(current, line, kind, level):
            _join(current["spans"], spans)
            current["bottom"] = line["bbox"].y1
            continue

        current = {
            "type": "text", "kind": kind, "level": level, "number": number,
            "spans": list(spans), "y": line["bbox"].y0, "bottom": line["bbox"].y1,
            "x0": line["bbox"].x0, "size": line["size"],
        }
        paragraphs.append(current)
    return paragraphs


def _continues(current, line, kind, level):
    """Whether a line carries on the paragraph above it: same kind of text,
    a normal line gap, and (for list items) indented past the marker."""
    if kind not in ("para", "heading") or level != current["level"]:
        return False
    if kind == "heading" and current["kind"] != "heading":
        return False
    if line["bbox"].y0 - current["bottom"] > current["size"] * 0.6:
        return False
    return not (current["kind"] in ("bullet", "number") and line["bbox"].x0 <= current["x0"] + 1)


def _join(spans, more):
    """Append a following line's spans, undoing end-of-line hyphenation."""
    last_text, bold, italic = spans[-1]
    first = more[0][0].lstrip() if more else ""
    if last_text.rstrip().endswith("-") and first[:1].islower():
        spans[-1] = (last_text.rstrip()[:-1], bold, italic)
    elif not last_text.endswith(" "):
        spans[-1] = (last_text + " ", bold, italic)
    spans.extend(more)


def _analyze(path, images=False):
    doc = open_pdf(path)
    raw = []
    for page in doc:
        tables = _tables(page)
        blocks, pictures = _page_lines(page, [t["bbox"] for t in tables], images)
        raw.append((page, blocks, pictures, tables))

    body, levels = _levels([blocks for _, blocks, _, _ in raw])
    has_text = body > 0 or any(tables for *_, tables in raw)
    if not has_text:
        raise UserError(NO_TEXT)

    pages = []
    for page, blocks, pictures, tables in raw:
        items = [p for lines in blocks for p in _paragraphs(lines, levels)]
        items += pictures
        items += [{"type": "table", "y": t["bbox"].y0, "rows": t["rows"]} for t in tables]
        items.sort(key=lambda item: item["y"])
        pages.append(items)
    return doc, body, pages


def _clean(spans):
    """Merge neighbouring spans that share a style and trim the ends."""
    out = []
    for text, bold, italic in spans:
        if out and out[-1][1:] == (bold, italic):
            out[-1] = (out[-1][0] + text, bold, italic)
        else:
            out.append((text, bold, italic))
    if out:
        out[0] = (out[0][0].lstrip(), *out[0][1:])
        out[-1] = (out[-1][0].rstrip(), *out[-1][1:])
    return [s for s in out if s[0]]


# -------------------------------------------------------------------- Word

def _image_bytes(block):
    """PNG or JPEG bytes python-docx can embed, or None."""
    if block["ext"] in ("png", "jpeg", "jpg"):
        return block["image"]
    try:
        pix = fitz.Pixmap(block["image"])
        if pix.n - pix.alpha >= 4:
            pix = fitz.Pixmap(fitz.csRGB, pix)
        return pix.tobytes("png")
    except Exception:  # noqa: BLE001 - skip images MuPDF can't decode
        return None


def pdf_to_docx(payload):
    from docx import Document
    from docx.shared import Inches, Pt

    doc, body, pages = _analyze(payload["input"], images=True)
    word = Document()
    word.styles["Normal"].font.size = Pt(min(max(round(body), 9), 14))

    first = doc[0].rect
    section = word.sections[0]
    section.page_width, section.page_height = Pt(first.width), Pt(first.height)
    margin = Inches(0.75)
    section.left_margin = section.right_margin = section.top_margin = section.bottom_margin = margin
    max_width = section.page_width - 2 * margin

    counts = Counter()
    for index, items in enumerate(pages):
        if index:
            word.add_page_break()
        for item in items:
            if item["type"] == "text":
                spans = _clean(item["spans"])
                if not spans:
                    continue
                if item["kind"] == "heading":
                    paragraph = word.add_heading("", level=item["level"])
                elif item["kind"] == "bullet":
                    paragraph = word.add_paragraph(style="List Bullet")
                elif item["kind"] == "number":
                    paragraph = word.add_paragraph(f"{item['number']}. ", style="List Paragraph")
                else:
                    paragraph = word.add_paragraph()
                for text, bold, italic in spans:
                    run = paragraph.add_run(text)
                    run.bold, run.italic = bold or None, italic or None
                counts["headings" if item["kind"] == "heading" else "paragraphs"] += 1
            elif item["type"] == "image":
                data = _image_bytes(item["block"])
                if data:
                    x0, _, x1, _ = item["block"]["bbox"]
                    word.add_picture(io.BytesIO(data), width=min(Pt(max(x1 - x0, 24)), max_width))
                    counts["images"] += 1
            else:
                rows = item["rows"]
                table = word.add_table(rows=len(rows), cols=len(rows[0]))
                table.style = "Table Grid"
                for r, row in enumerate(rows):
                    for c, value in enumerate(row):
                        cell = table.cell(r, c)
                        cell.text = value
                        if r == 0:
                            for run in cell.paragraphs[0].runs:
                                run.bold = True
                counts["tables"] += 1

    word.save(payload["output"])
    return {"pages": len(pages), **counts}


# ------------------------------------------------------------------- Excel

NUMBER = re.compile(r"^(\d{1,3}(?:,\d{3})+|\d+)?(\.\d+)?$")


def _number(value):
    """(number, format) for numeric-looking text such as "1,250.50",
    "$3.00", "(12)" or "15%"; None to keep the cell as text."""
    s = value.strip().replace("−", "-")
    if not s or len(s) > 30:
        return None
    negative = False
    if s.startswith("(") and s.endswith(")"):
        negative, s = True, s[1:-1].strip()
    if s.startswith("-"):
        negative, s = not negative, s[1:].strip()
    currency = s[0] if s[:1] in "$€£¥" and len(s) > 1 else ""
    s = s[len(currency):].strip()
    percent = s.endswith("%")
    s = s.rstrip("%").strip()

    match = NUMBER.match(s)
    if not s or s == "." or not match:
        return None
    whole, decimals = match.group(1) or "", match.group(2) or ""
    if len(whole) > 1 and whole.startswith("0"):
        return None  # IDs and codes like 00123 stay text

    number = float(s.replace(",", "")) if decimals else int(s.replace(",", ""))
    number = -number if negative else number
    places = "0" * (len(decimals) - 1)
    fmt = None
    if percent:
        number /= 100
        fmt = "0" + ("." + places if places else "") + "%"
    elif currency:
        fmt = f'"{currency}"#,##0' + ("." + places if places else "")
    elif "," in whole:
        fmt = "#,##0" + ("." + places if places else "")
    return number, fmt


def _write_rows(sheet, rows, bold_header):
    from openpyxl.styles import Font

    widths = Counter()
    for r, row in enumerate(rows, start=1):
        for c, value in enumerate(row, start=1):
            if value == "":
                continue
            parsed = _number(value)
            cell = sheet.cell(row=r, column=c, value=parsed[0] if parsed else value)
            if parsed and parsed[1]:
                cell.number_format = parsed[1]
            if bold_header and r == 1:
                cell.font = Font(bold=True)
            widths[c] = max(widths[c], len(value))
    for c, width in widths.items():
        sheet.column_dimensions[sheet.cell(row=1, column=c).column_letter].width = min(max(width + 2, 8), 60)


def _text_rows(page):
    """Every text line as a row, split into cells where words are far apart."""
    words = sorted(page.get_text("words"), key=lambda w: ((w[1] + w[3]) / 2, w[0]))
    rows = []
    for word in words:
        middle, height = (word[1] + word[3]) / 2, word[3] - word[1]
        if rows and abs(rows[-1]["middle"] - middle) < height / 2:
            rows[-1]["words"].append(word)
        else:
            rows.append({"middle": middle, "words": [word]})

    out = []
    for row in rows:
        cells, last = [], None
        for x0, y0, x1, y1, text, *_ in sorted(row["words"], key=lambda w: w[0]):
            if last is None or x0 - last > (y1 - y0) * 0.9:
                cells.append(text)
            else:
                cells[-1] += " " + text
            last = x1
        out.append(cells)
    return out


def pdf_to_xlsx(payload):
    from openpyxl import Workbook

    doc = open_pdf(payload["input"])
    mode = payload.get("mode", "tables")
    book = Workbook()
    book.remove(book.active)
    tables = rows = 0

    for page in doc:
        name = f"Page {page.number + 1}"
        if mode == "text":
            lines = _text_rows(page)
            if lines:
                _write_rows(book.create_sheet(name), lines, bold_header=False)
                rows += len(lines)
            continue
        for index, table in enumerate(_tables(page)):
            _write_rows(book.create_sheet(name if index == 0 else f"{name} ({index + 1})"), table["rows"], bold_header=True)
            tables += 1
            rows += len(table["rows"])

    if not book.sheetnames:
        if not any(page.get_text("text").strip() for page in doc):
            raise UserError(NO_TEXT)
        raise UserError("No tables were found in this PDF. Choose “Every text line” to export all of its text instead.")

    book.save(payload["output"])
    return {"sheets": len(book.sheetnames), "tables": tables, "rows": rows}


# ---------------------------------------------------------------- Markdown

MD_SPECIAL = re.compile(r"([\\`*_\[\]<>])")


def _md_escape(text):
    return MD_SPECIAL.sub(r"\\\1", text)


def _md_inline(spans):
    out = []
    for text, bold, italic in _clean(spans):
        body = _md_escape(text.strip())
        if not body:
            out.append(text)
            continue
        mark = "***" if bold and italic else "**" if bold else "*" if italic else ""
        lead = text[: len(text) - len(text.lstrip())]
        trail = text[len(text.rstrip()):]
        out.append(f"{lead}{mark}{body}{mark}{trail}")
    return re.sub(r"[ \t]+", " ", "".join(out)).strip()


def _md_table(rows):
    width = max(len(r) for r in rows)
    cell = lambda v: _md_escape(v).replace("|", "\\|")  # noqa: E731
    lines = ["| " + " | ".join(cell(v) for v in row + [""] * (width - len(row))) + " |" for row in rows]
    lines.insert(1, "|" + " --- |" * width)
    return "\n".join(lines)


def pdf_to_markdown(payload):
    _, _, pages = _analyze(payload["input"])
    chunks, counts = [], Counter()

    for index, items in enumerate(pages):
        if index and payload.get("pageBreaks") and chunks:
            chunks.append("---")
        for item in items:
            if item["type"] == "table":
                chunks.append(_md_table(item["rows"]))
                counts["tables"] += 1
                continue
            if item["kind"] == "heading":
                text = _md_escape("".join(t for t, _, _ in item["spans"]))
                text = re.sub(r"\s+", " ", text).strip()
                if text:
                    chunks.append("#" * item["level"] + " " + text)
                    counts["headings"] += 1
                continue
            text = _md_inline(item["spans"])
            if not text:
                continue
            if item["kind"] == "bullet":
                text = "- " + text
            elif item["kind"] == "number":
                text = f"{item['number']}. " + text
            elif re.match(r"^(#|\d+[.)]\s|[-+]\s)", text):
                text = "\\" + text  # plain text that Markdown would read as a heading or list
            # Consecutive list items stay in one list.
            if item["kind"] in ("bullet", "number") and chunks and re.match(r"^(- |\d+\. )", chunks[-1].rsplit("\n", 1)[-1]):
                chunks[-1] += "\n" + text
            else:
                chunks.append(text)

    markdown = "\n\n".join(chunks).strip() + "\n"
    with open(payload["output"], "w", encoding="utf-8") as handle:
        handle.write(markdown)

    limit = int(payload.get("previewChars", 4000))
    preview = markdown
    if len(markdown) > limit:
        cut = markdown.rfind("\n\n", 0, limit)
        preview = markdown[: cut if cut > limit // 2 else limit].rstrip() + "\n\n…"
    return {"pages": len(pages), "preview": preview, "chars": len(markdown), **counts}


COMMANDS = {
    "pdf_to_docx": pdf_to_docx,
    "pdf_to_xlsx": pdf_to_xlsx,
    "pdf_to_markdown": pdf_to_markdown,
}
