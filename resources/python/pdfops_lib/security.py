"""PDF Security tools: sign, redact and compare."""
import difflib
import os
import re

import fitz

from .common import UserError, display_rect, hex_to_rgb, open_pdf, save

FONT_FILES = [
    "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf",
    "/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf",
]


def _font_file():
    return next((path for path in FONT_FILES if os.path.isfile(path)), None)


def _plural(count, word):
    return f"{count} {word}" if count == 1 else f"{count} {word}s"


# --- sign -------------------------------------------------------------------

def sign(payload):
    """Stamp signature images and text (printed name, date) onto pages.

    A visual signature only; no certificate is involved."""
    doc = open_pdf(payload["input"])
    assets = payload.get("assets") or {}
    placements = payload.get("placements") or []
    font = _font_file()
    signatures = 0

    for item in placements:
        page_no = int(item.get("page", 1))
        if page_no < 1 or page_no > doc.page_count:
            continue
        page = doc[page_no - 1]
        rect = display_rect(page, item)
        if rect.is_empty:
            continue

        if item.get("kind") == "signature":
            path = assets.get(item.get("asset") or "")
            if not path or not os.path.isfile(path):
                continue
            page.insert_image(rect, filename=path, keep_proportion=True, rotate=page.rotation, overlay=True)
            signatures += 1
        elif item.get("kind") == "text" and (item.get("text") or "").strip():
            _textbox(page, rect, item["text"], float(item.get("size") or 14), hex_to_rgb(item.get("color")), font)

    if not signatures:
        raise UserError("Add your signature to the page first.")

    save(doc, payload["output"])
    return {"signatures": signatures}


def _textbox(page, rect, text, size, color, font):
    kwargs = {"fontname": "stellar", "fontfile": font} if font else {"fontname": "helv"}
    # Shrink the text until it fits the box the user drew.
    while size >= 4:
        shape = page.new_shape()
        left = shape.insert_textbox(rect, text, fontsize=size, color=color, rotate=page.rotation, **kwargs)
        if left >= 0:
            shape.commit()
            return
        size -= 1
    shape = page.new_shape()
    shape.insert_textbox(rect + (0, 0, 0, rect.height * 4), text, fontsize=4, color=color, rotate=page.rotation, **kwargs)
    shape.commit()


# --- redact -----------------------------------------------------------------

PRESETS = {
    "emails": re.compile(r"[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}"),
    # Digit runs that continue past the match (card numbers) are skipped.
    "phones": re.compile(r"(?<![\w+])(?<!\d[ .-])(?:\+?\d{1,3}[ .-]?)?(?:\(\d{2,4}\)[ .-]?|\d{2,4}[ .-])"
                         r"\d{3,4}[ .-]?\d{3,4}(?![ .-]?\d)(?!\w)"),
    "cards": re.compile(r"(?<!\d)(?:\d[ -]?){12,18}\d(?!\d)"),
}


def _luhn(number):
    digits = [int(d) for d in re.sub(r"\D", "", number)]
    if not 13 <= len(digits) <= 19:
        return False
    total = 0
    for i, digit in enumerate(reversed(digits)):
        if i % 2:
            digit *= 2
            if digit > 9:
                digit -= 9
        total += digit
    return total % 10 == 0


def _find(page, term, match_case):
    rects = page.search_for(term)
    if match_case:
        rects = [r for r in rects if term in page.get_textbox(r)]
    return rects


def redact(payload):
    """Black out words, patterns and drawn areas, removing the text beneath."""
    doc = open_pdf(payload["input"])
    terms = [t.strip() for t in (payload.get("terms") or "").splitlines() if t.strip()]
    presets = [name for name in payload.get("presets") or [] if name in PRESETS]
    match_case = bool(payload.get("match_case"))
    areas = [a for a in payload.get("areas") or [] if a.get("kind", "redact") == "redact"]

    total = 0
    pages = set()

    for page in doc:
        rects = []
        for term in terms:
            rects += _find(page, term, match_case)

        if presets:
            text = page.get_text("text")
            for name in presets:
                for match in PRESETS[name].finditer(text):
                    value = match.group(0).strip()
                    if name == "cards" and not _luhn(value):
                        continue
                    rects += page.search_for(value)

        for area in areas:
            if int(area.get("page", 0)) == page.number + 1:
                rect = display_rect(page, area)
                if not rect.is_empty:
                    rects.append(rect)

        # The same text can match several rules; redact each spot once.
        unique = []
        for rect in rects:
            if not any(abs(rect.x0 - u.x0) < 0.5 and abs(rect.y0 - u.y0) < 0.5
                       and abs(rect.x1 - u.x1) < 0.5 and abs(rect.y1 - u.y1) < 0.5 for u in unique):
                unique.append(rect)

        for rect in unique:
            page.add_redact_annot(rect, fill=(0, 0, 0))

        if unique:
            images = getattr(fitz, "PDF_REDACT_IMAGE_PIXELS", None)
            if images is None:
                page.apply_redactions()
            else:
                page.apply_redactions(images=images)
            total += len(unique)
            pages.add(page.number)

    if not total:
        raise UserError("Nothing to redact was found.")

    if payload.get("strip_metadata", True):
        doc.set_metadata({})
        doc.del_xml_metadata()

    save(doc, payload["output"], garbage=4)
    return {"areas": total, "pages": len(pages),
            "summary": f"{_plural(total, 'area')} redacted on {_plural(len(pages), 'page')}."}


# --- compare ----------------------------------------------------------------

GAP = 24
RED = (0.93, 0.26, 0.26)
GREEN = (0.13, 0.70, 0.33)


def _words(doc):
    words = []
    for page in doc:
        matrix = page.rotation_matrix
        for w in page.get_text("words", sort=True):
            words.append((page.number, fitz.Rect(w[:4]) * matrix, w[4]))
    return words


def _snippet(words, limit=40):
    text = " ".join(w[2] for w in words)
    return text if len(text) <= limit else text[:limit - 1] + "…"


def compare(payload):
    """Word-level diff of two PDFs, as a side-by-side report."""
    old = open_pdf(payload["inputs"][0])
    new = open_pdf(payload["inputs"][1])
    old_words, new_words = _words(old), _words(new)

    matcher = difflib.SequenceMatcher(None, [w[2] for w in old_words], [w[2] for w in new_words], autojunk=False)
    changes = []
    removed = [[] for _ in range(old.page_count)]
    added = [[] for _ in range(new.page_count)]

    for tag, i1, i2, j1, j2 in matcher.get_opcodes():
        if tag == "equal":
            continue
        before, after = old_words[i1:i2], new_words[j1:j2]
        for page, rect, _ in before:
            removed[page].append(rect)
        for page, rect, _ in after:
            added[page].append(rect)
        page = (before or after)[0][0] + 1
        if tag == "replace":
            line = f"p.{page}: ‘{_snippet(before)}’ → ‘{_snippet(after)}’"
        elif tag == "delete":
            line = f"p.{page}: removed ‘{_snippet(before)}’"
        else:
            line = f"p.{page}: added ‘{_snippet(after)}’"
        changes.append(line)

    out = fitz.open()
    font = _font_file()
    _summary_page(out, font, changes, payload.get("names") or ["Original", "Revised"],
                  sum(len(r) for r in removed), sum(len(a) for a in added))

    for index in range(max(old.page_count, new.page_count)):
        left = old[index].rect if index < old.page_count else None
        right = new[index].rect if index < new.page_count else None
        lw, lh = (left.width, left.height) if left else (right.width, right.height)
        rw, rh = (right.width, right.height) if right else (lw, lh)
        page = out.new_page(width=lw + GAP + rw, height=max(lh, rh))
        _side(page, fitz.Rect(0, 0, lw, lh), old, index, removed, RED, font)
        _side(page, fitz.Rect(lw + GAP, 0, lw + GAP + rw, rh), new, index, added, GREEN, font)
        page.draw_line((lw + GAP / 2, 0), (lw + GAP / 2, page.rect.height), color=(0.8, 0.8, 0.8), width=0.5)

    save(out, payload["output"])
    count = len(changes)
    return {
        "changes": count,
        "removed": sum(len(r) for r in removed),
        "added": sum(len(a) for a in added),
        "summary": f"{_plural(count, 'change')} found." if count else "No text differences found.",
    }


def _side(page, box, doc, index, marks, color, font):
    if index >= doc.page_count:
        page.draw_rect(box, color=(0.85, 0.85, 0.85), fill=(0.97, 0.97, 0.97), width=0.5)
        _text(page, box + (0, box.height / 2 - 10, 0, 0), "No page", 12, (0.5, 0.5, 0.5), font, align=1)
        return
    page.show_pdf_page(box, doc, index)
    for rect in marks[index]:
        page.draw_rect(rect + (box.x0 - 1, box.y0 - 1, box.x0 + 1, box.y0 + 1),
                       color=None, fill=color, fill_opacity=0.35, overlay=True)


def _text(page, rect, text, size, color, font, align=0):
    kwargs = {"fontname": "stellar", "fontfile": font} if font else {"fontname": "helv"}
    if not font:
        text = text.replace("→", "->").replace("‘", "'").replace("’", "'").replace("…", "...")
    return page.insert_textbox(rect, text, fontsize=size, color=color, align=align, **kwargs)


def _line(page, point, text, size, color, font):
    kwargs = {"fontname": "stellar", "fontfile": font} if font else {"fontname": "helv"}
    if not font:
        text = text.replace("→", "->").replace("‘", "'").replace("’", "'").replace("…", "...")
    page.insert_text(point, text, fontsize=size, color=color, **kwargs)


def _summary_page(out, font, changes, names, removed, added):
    page = out.new_page(width=612, height=792)
    dark, grey = (0.09, 0.09, 0.11), (0.4, 0.4, 0.45)
    _text(page, fitz.Rect(54, 54, 558, 90), "Comparison report", 20, dark, font)
    _line(page, (54, 104), f"Original: {names[0]}"[:90], 10, grey, font)
    _line(page, (54, 118), f"Revised: {names[1] if len(names) > 1 else ''}"[:90], 10, grey, font)

    if changes:
        headline = f"{_plural(len(changes), 'change')} found: {_plural(removed, 'word')} removed, {_plural(added, 'word')} added."
    else:
        headline = "No text differences found."
    _text(page, fitz.Rect(54, 136, 558, 160), headline, 12, dark, font)

    if changes:
        page.draw_rect(fitz.Rect(54, 166, 64, 176), color=None, fill=RED, fill_opacity=0.35)
        _text(page, fitz.Rect(68, 165, 250, 180), "Removed (left)", 9, grey, font)
        page.draw_rect(fitz.Rect(160, 166, 170, 176), color=None, fill=GREEN, fill_opacity=0.35)
        _text(page, fitz.Rect(174, 165, 350, 180), "Added (right)", 9, grey, font)

    y = 200
    for line in changes[:50]:
        _line(page, (54, y), line, 9, dark, font)
        y += 11.5
    if len(changes) > 50:
        _line(page, (54, y + 4), f"…and {len(changes) - 50} more. See the pages that follow.", 9, grey, font)


COMMANDS = {"sign": sign, "redact": redact, "compare": compare}
