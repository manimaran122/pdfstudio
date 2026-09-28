"""Helpers shared by the command modules."""
import fitz


class UserError(Exception):
    """A problem to report to the user verbatim."""


def open_pdf(path, password=None):
    try:
        doc = fitz.open(path)
    except Exception as error:  # noqa: BLE001 - PyMuPDF raises bare RuntimeErrors
        raise UserError("This file couldn’t be opened as a PDF.") from error

    if doc.needs_pass and not (password and doc.authenticate(password)):
        raise UserError("This PDF is password protected. Unlock it first.")

    return doc


def save(doc, path, garbage=3):
    doc.save(path, garbage=garbage, deflate=True)


def hex_to_rgb(value, default=(0, 0, 0)):
    value = (value or "").lstrip("#")
    if len(value) != 6:
        return default
    return tuple(int(value[i:i + 2], 16) / 255 for i in (0, 2, 4))


def display_rect(page, box):
    """Convert a box in displayed-page fractions {x, y, w, h} (0..1, as the
    user saw the page on screen, rotation included) into an unrotated PDF
    rect for drawing on the page."""
    shown = page.rect  # page size as displayed (rotation applied)
    rect = fitz.Rect(
        shown.x0 + box["x"] * shown.width,
        shown.y0 + box["y"] * shown.height,
        shown.x0 + (box["x"] + box["w"]) * shown.width,
        shown.y0 + (box["y"] + box["h"]) * shown.height,
    )
    return rect * page.derotation_matrix


def page_list(spec, count):
    """1-based page numbers from a list or "1-3,5" string, validated."""
    if isinstance(spec, list):
        pages = [int(p) for p in spec]
    else:
        pages = []
        for part in str(spec or "").replace(" ", "").split(","):
            if not part:
                continue
            if "-" in part:
                start, _, end = part.partition("-")
                start = int(start) if start else 1
                end = int(end) if end else count
                pages.extend(range(start, end + 1) if start <= end else range(start, end - 1, -1))
            else:
                pages.append(int(part))

    bad = [p for p in pages if p < 1 or p > count]
    if bad:
        raise UserError(f"Page {bad[0]} doesn’t exist; this PDF has {count} pages.")
    return pages
