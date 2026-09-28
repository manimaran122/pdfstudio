"""Edit PDF tools: rotate, page numbers, watermark, crop, edit, forms.

Boxes from the UI are fractions of the page as displayed (rotation
applied); common.display_rect turns them into unrotated page rects.
Text and images are inserted with rotate=page.rotation so they appear
upright in the displayed orientation.
"""
import fitz

from .common import UserError, display_rect, hex_to_rgb, open_pdf, save

MM = 72 / 25.4


def shown_point(page, x, y):
    """A point on the page as displayed → unrotated page coordinates."""
    return fitz.Point(x, y) * page.derotation_matrix


# --- rotate -----------------------------------------------------------------

def rotate(payload):
    doc = open_pdf(payload["input"])
    rotated = 0
    for page_no, degrees in payload["rotations"]:
        degrees = int(degrees) % 360
        if degrees and 1 <= page_no <= doc.page_count:
            page = doc[page_no - 1]
            page.set_rotation((page.rotation + degrees) % 360)
            rotated += 1
    if not rotated:
        raise UserError("Rotate at least one page.")
    save(doc, payload["output"])
    return {"rotated": rotated}


# --- page numbers -----------------------------------------------------------

def page_numbers(payload):
    doc = open_pdf(payload["input"])
    vertical, _, horizontal = payload["position"].partition("-")
    size = float(payload["size"])
    margin = float(payload["margin"]) * MM
    color = hex_to_rgb(payload.get("color"))
    pages = list(range(1 if payload.get("skipFirst") else 0, doc.page_count))
    if not pages:
        raise UserError("There are no pages left to number.")
    start = int(payload["start"])
    total = start + len(pages) - 1

    for index, page_no in enumerate(pages):
        page = doc[page_no]
        label = payload["format"].replace("{n}", str(start + index)).replace("{total}", str(total))
        shown = page.rect
        width = fitz.get_text_length(label, fontname="helv", fontsize=size)
        x = {
            "left": margin,
            "center": (shown.width - width) / 2,
            "right": shown.width - margin - width,
        }[horizontal]
        # Baseline: cap height below the top margin, or on the bottom margin.
        y = margin + size * 0.8 if vertical == "top" else shown.height - margin
        page.insert_text(
            shown_point(page, x, y), label, fontsize=size, fontname="helv",
            color=color, rotate=page.rotation,
        )

    save(doc, payload["output"])
    return {"numbered": len(pages)}


# --- watermark --------------------------------------------------------------

def _tile_centers(shown, step_x, step_y):
    """Centers of a staggered grid that covers the displayed page."""
    centers = []
    row = 0
    y = step_y / 2
    while y < shown.height + step_y / 2:
        x = (step_x / 2) * (row % 2)
        while x < shown.width + step_x / 2:
            centers.append((x, y))
            x += step_x
        y += step_y
        row += 1
    return centers


def watermark(payload):
    doc = open_pdf(payload["input"])
    opacity = max(0.0, min(1.0, float(payload["opacity"]) / 100))
    overlay = not payload.get("behind")
    tiled = payload.get("position") == "tiled"
    angle = 45 if payload.get("rotation") == "diagonal" else 0

    if payload["type"] == "image":
        image = payload["image"]
        scale = float(payload["scale"]) / 100
        ratio = float(payload["ratio"])  # height / width, after rotation
        for page in doc:
            shown = page.rect
            width = shown.width * scale
            height = width * ratio
            if height > shown.height:
                height = shown.height * scale
                width = height / ratio
            if tiled:
                centers = _tile_centers(shown, width * 1.5, height * 1.8)
            else:
                centers = [(shown.width / 2, shown.height / 2)]
            for cx, cy in centers:
                box = fitz.Rect(cx - width / 2, cy - height / 2, cx + width / 2, cy + height / 2)
                page.insert_image(
                    box * page.derotation_matrix, filename=image, keep_proportion=True,
                    overlay=overlay, rotate=page.rotation,
                )
    else:
        text = payload["text"]
        size = float(payload["size"])
        color = hex_to_rgb(payload.get("color"), (0.5, 0.5, 0.5))
        text_width = fitz.get_text_length(text, fontname="helv", fontsize=size)
        for page in doc:
            shown = page.rect
            if tiled:
                centers = _tile_centers(shown, text_width + size * 2, size * 4)
            else:
                centers = [(shown.width / 2, shown.height / 2)]
            for cx, cy in centers:
                pivot = shown_point(page, cx, cy)
                page.insert_text(
                    shown_point(page, cx - text_width / 2, cy + size * 0.35), text,
                    fontsize=size, fontname="helv", color=color, fill_opacity=opacity,
                    rotate=page.rotation, overlay=overlay,
                    morph=(pivot, fitz.Matrix(angle)) if angle else None,
                )

    save(doc, payload["output"])
    return {}


# --- crop -------------------------------------------------------------------

def crop(payload):
    doc = open_pdf(payload["input"])
    if payload["mode"] == "area":
        box = payload.get("area")
        if not box:
            raise UserError("Draw the area to keep on a page first.")
        pages = range(doc.page_count) if payload.get("allPages") else [int(box["page"]) - 1]
    else:
        box = None
        pages = range(doc.page_count)

    for page_no in pages:
        page = doc[page_no]
        shown = page.rect
        if box is None:
            m = {side: float(payload["margins"][side]) * MM for side in ("top", "right", "bottom", "left")}
            keep = {
                "x": m["left"] / shown.width,
                "y": m["top"] / shown.height,
                "w": (shown.width - m["left"] - m["right"]) / shown.width,
                "h": (shown.height - m["top"] - m["bottom"]) / shown.height,
            }
        else:
            keep = box
        rect = display_rect(page, keep)
        if keep["w"] <= 0 or keep["h"] <= 0 or rect.width < 36 or rect.height < 36:
            raise UserError(
                "The area to keep is too small." if box else "The margins leave too little of the page."
            )
        # Page coordinates start at the current crop box; set_cropbox wants
        # media box coordinates.
        origin = page.cropbox.tl
        cropbox = (rect + (origin.x, origin.y, origin.x, origin.y)) & page.mediabox
        if cropbox.is_empty:
            raise UserError("The area to keep is outside the page.")
        page.set_cropbox(cropbox)

    save(doc, payload["output"])
    return {"cropped": len(pages)}


# --- edit -------------------------------------------------------------------

def _fit_textbox(page, rect, text, size, color):
    """Insert wrapped text, shrinking the font until it fits the box."""
    while True:
        shape = page.new_shape()
        spare = shape.insert_textbox(rect, text, fontsize=size, fontname="helv", color=color, rotate=page.rotation)
        if spare >= 0 or size <= 4:
            shape.commit()
            return size
        size = max(4, size * 0.9)


def edit(payload):
    doc = open_pdf(payload["input"])
    items = payload["items"]
    if not items:
        raise UserError("Add something to a page first.")

    for item in items:
        page = doc[int(item["page"]) - 1]
        rect = display_rect(page, item)
        kind = item["kind"]

        if kind == "text":
            text = (item.get("text") or "").strip("\n")
            if text:
                _fit_textbox(page, rect, text, float(item.get("size") or 14), hex_to_rgb(item.get("color")))
        elif kind == "rect":
            page.draw_rect(rect, color=hex_to_rgb(item.get("color"), (0.14, 0.22, 0.66)), width=2)
        elif kind == "highlight":
            color = hex_to_rgb(item.get("color"), (0.98, 0.8, 0.08))
            words = page.get_text("words", clip=rect)
            if words:
                annot = page.add_highlight_annot(rect)
                annot.set_colors(stroke=color)
            else:
                annot = page.add_rect_annot(rect)
                annot.set_colors(stroke=color, fill=color)
                annot.set_border(width=0)
                annot.set_opacity(0.4)
            annot.update()
        elif kind == "image":
            path = payload["assets"].get(item.get("asset") or "")
            if not path:
                raise UserError("An image is no longer available. Add it again.")
            page.insert_image(rect, filename=path, keep_proportion=True, rotate=page.rotation)
        elif kind == "note":
            point = shown_point(page, item["x"] * page.rect.width, item["y"] * page.rect.height)
            annot = page.add_text_annot(point, item.get("text") or "", icon="Note")
            annot.set_colors(stroke=hex_to_rgb(item.get("color"), (0.98, 0.8, 0.08)))
            annot.update()

    save(doc, payload["output"])
    return {"items": len(items)}


# --- forms ------------------------------------------------------------------

def _unique(name, taken):
    name = (name or "field").strip() or "field"
    candidate, n = name, 1
    while candidate in taken:
        n += 1
        candidate = f"{name}_{n}"
    taken.add(candidate)
    return candidate


def _checked(value):
    return value not in (None, "", False, "Off", "0", 0)


def forms(payload):
    doc = open_pdf(payload["input"])
    fields = payload.get("fields") or []
    values = payload.get("values") or []
    existing = [(page, widget) for page in doc for widget in (page.widgets() or [])]
    filled = 0

    # Values line up with the fields read after upload (same page/widget order).
    for index, (_, widget) in enumerate(existing):
        if index >= len(fields) or index >= len(values) or fields[index].get("name") != widget.field_name:
            continue
        value, original = values[index], fields[index].get("value")
        kind = widget.field_type

        if kind == fitz.PDF_WIDGET_TYPE_CHECKBOX:
            if _checked(value) == _checked(original):
                continue
            widget.field_value = widget.on_state() if _checked(value) else "Off"
        elif kind == fitz.PDF_WIDGET_TYPE_RADIOBUTTON:
            if value in (None, "") or str(value) == str(original):
                continue
            widget.field_value = str(value) == str(widget.on_state()) or value is True
        elif kind in (fitz.PDF_WIDGET_TYPE_BUTTON, fitz.PDF_WIDGET_TYPE_SIGNATURE):
            continue
        else:
            if str(value or "") == str(original or ""):
                continue
            widget.field_value = str(value or "")
        widget.update()
        filled += 1

    taken = {widget.field_name for _, widget in existing}
    added = 0
    for item in payload.get("add") or []:
        page = doc[int(item["page"]) - 1]
        widget = fitz.Widget()
        widget.rect = display_rect(page, item)
        widget.field_name = _unique(item.get("name"), taken)
        if item["kind"] == "field-checkbox":
            widget.field_type = fitz.PDF_WIDGET_TYPE_CHECKBOX
            widget.field_value = False
        else:
            widget.field_type = fitz.PDF_WIDGET_TYPE_TEXT
            widget.field_value = ""
            widget.text_fontsize = 0  # auto-size to the box
        widget.border_color = (0.6, 0.6, 0.6)
        widget.border_width = 1
        page.add_widget(widget)
        added += 1

    if not existing and not added:
        raise UserError("This PDF has no form fields. Add a field on the page first.")

    save(doc, payload["output"])
    return {"filled": filled, "added": added}


COMMANDS = {
    "rotate": rotate,
    "page_numbers": page_numbers,
    "watermark": watermark,
    "crop": crop,
    "edit": edit,
    "forms": forms,
}
