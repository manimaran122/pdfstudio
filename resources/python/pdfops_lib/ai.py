"""Text extraction and write-back for the AI tools (Summarizer, Translate)."""
import fitz

from .common import UserError, hex_to_rgb, open_pdf, save

# Latin, Greek and Cyrillic. CJK uses PyMuPDF's built-in CJK fonts.
UNICODE_FONT = "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf"
UNICODE_FONT_BOLD = "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf"
CJK_FONTS = {"zh": "china-s", "ja": "japan", "ko": "korea"}


def ai_text(payload):
    """Plain text per page, for documents too large to send as a PDF."""
    doc = open_pdf(payload["input"])
    pages = [page.get_text("text").strip() for page in doc]
    if not any(pages):
        raise UserError("This PDF has no selectable text. Run OCR PDF on it first.")
    return {"pages": pages}


def translate_extract(payload):
    """Text blocks with their position and style, in reading order."""
    doc = open_pdf(payload["input"])
    blocks = []

    for page in doc:
        for block in page.get_text("dict", sort=True)["blocks"]:
            if block.get("type") != 0:
                continue

            lines = []
            sizes, colors, bold = [], [], 0
            for line in block["lines"]:
                text = "".join(span["text"] for span in line["spans"]).strip()
                if text:
                    lines.append(text)
                for span in line["spans"]:
                    if span["text"].strip():
                        sizes.append(span["size"])
                        colors.append(span["color"])
                        bold += bool(span["flags"] & 16)

            text = " ".join(lines).strip()
            if not text or not any(ch.isalpha() for ch in text):
                continue

            blocks.append({
                "page": page.number,
                "bbox": [round(v, 2) for v in block["bbox"]],
                "text": text,
                "size": round(max(set(sizes), key=sizes.count), 2),
                "color": "#%06x" % max(set(colors), key=colors.count),
                "bold": bold * 2 > len(sizes),
            })

    if not blocks:
        raise UserError("This PDF has no selectable text to translate. Run OCR PDF on it first.")

    return {"blocks": blocks}


def translate_apply(payload):
    """Replace each block's text with its translation, in the same box.

    The original text is removed with redactions (images and drawings are
    kept), then the translation is fitted into the block's rectangle,
    shrinking the font until it fits.
    """
    doc = open_pdf(payload["input"])
    blocks = payload["blocks"]
    translations = payload["translations"]
    cjk = CJK_FONTS.get(payload.get("language", "")[:2])

    if len(blocks) != len(translations):
        raise ValueError("translations do not match blocks")

    by_page = {}
    for block, text in zip(blocks, translations):
        by_page.setdefault(block["page"], []).append((block, text))

    for number, items in by_page.items():
        page = doc[number]
        for block, _ in items:
            page.add_redact_annot(fitz.Rect(block["bbox"]))
        page.apply_redactions(images=fitz.PDF_REDACT_IMAGE_NONE)

        for block, text in items:
            rect = fitz.Rect(block["bbox"])
            # Translations often run longer; allow the box to grow downward a little.
            rect.y1 = min(page.rect.height, rect.y1 + block["size"] * 0.6)
            font = {"fontname": cjk} if cjk else {
                "fontname": "dvb" if block["bold"] else "dv",
                "fontfile": UNICODE_FONT_BOLD if block["bold"] else UNICODE_FONT,
            }
            size = block["size"]
            while size >= 4:
                if page.insert_textbox(rect, text, fontsize=size, color=hex_to_rgb(block["color"]), **font) >= 0:
                    break
                size *= 0.9
            else:
                page.insert_textbox(rect, text, fontsize=4, color=hex_to_rgb(block["color"]), **font)

    save(doc, payload["output"], garbage=4)
    return {}


COMMANDS = {
    "ai_text": ai_text,
    "translate_extract": translate_extract,
    "translate_apply": translate_apply,
}
