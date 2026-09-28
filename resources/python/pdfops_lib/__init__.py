"""Command registry. Each module exposes COMMANDS = {name: fn(payload) -> dict}.

Modules load independently, so one that fails to import only disables its
own commands (the error goes to stderr, which the app logs).
"""
import importlib
import pkgutil
import sys
import traceback

from .common import UserError

COMMANDS = {}

for _module in pkgutil.iter_modules(__path__):
    if _module.name == "common":
        continue
    try:
        COMMANDS.update(getattr(importlib.import_module(f"{__name__}.{_module.name}"), "COMMANDS", {}))
    except Exception:  # noqa: BLE001 - report and keep the other modules usable
        print(f"pdfops: could not load module {_module.name}:\n{traceback.format_exc()}", file=sys.stderr)

__all__ = ["COMMANDS", "UserError"]
