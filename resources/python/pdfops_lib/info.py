import fitz

from .common import open_pdf


def info(payload):
    """Page count and page sizes (as displayed), plus form fields."""
    doc = open_pdf(payload["input"], payload.get("password"))
    fields = []
    for page in doc:
        for widget in page.widgets() or []:
            fields.append({
                "name": widget.field_name,
                "label": widget.field_label or widget.field_name,
                "type": widget.field_type_string.lower(),
                "value": widget.field_value,
                "options": list(widget.choice_values or []),
                "page": page.number + 1,
            })
    return {
        "pages": doc.page_count,
        "sizes": [[round(p.rect.width, 2), round(p.rect.height, 2)] for p in doc],
        "encrypted": doc.is_encrypted,
        "fields": fields,
    }


COMMANDS = {"info": info}
