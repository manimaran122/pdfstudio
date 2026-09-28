#!/usr/bin/env python3
"""PyMuPDF-backed PDF operations for the Laravel app.

Usage: pdfops.py <command> <payload.json>

Prints one JSON object to stdout. Exits 2 with {"error": "..."} for
problems the user should see; any other failure exits 1 with details on
stderr (logged, never shown to the user).
"""
import json
import sys
import os

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from pdfops_lib import COMMANDS, UserError  # noqa: E402


def main() -> int:
    if len(sys.argv) != 3 or sys.argv[1] not in COMMANDS:
        print(f"usage: pdfops.py <{'|'.join(sorted(COMMANDS))}> <payload.json>", file=sys.stderr)
        return 1

    with open(sys.argv[2], encoding="utf-8") as handle:
        payload = json.load(handle)

    try:
        result = COMMANDS[sys.argv[1]](payload)
    except UserError as error:
        print(json.dumps({"error": str(error)}))
        return 2

    print(json.dumps(result if result is not None else {}))
    return 0


if __name__ == "__main__":
    sys.exit(main())
