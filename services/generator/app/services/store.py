from __future__ import annotations

import json
from pathlib import Path

from app.services.processor import ProcessingResult


class FileResultStore:
    def __init__(self, root: Path):
        self.root = root
        self.root.mkdir(parents=True, exist_ok=True)

    def save(self, result_id: str, result: ProcessingResult) -> None:
        directory = self.root / result_id
        directory.mkdir(parents=True, exist_ok=False)
        (directory / "preview.png").write_bytes(result.preview_png)
        (directory / "scheme.svg").write_bytes(result.svg)
        (directory / "scheme.pdf").write_bytes(result.pdf)
        (directory / "palette.json").write_text(
            json.dumps(result.palette, ensure_ascii=False, indent=2), encoding="utf-8"
        )
        (directory / "diagnostics.json").write_text(
            json.dumps(result.diagnostics, ensure_ascii=False, indent=2), encoding="utf-8"
        )

    def path(self, result_id: str, filename: str) -> Path | None:
        if not result_id.replace("-", "").isalnum():
            return None
        path = self.root / result_id / filename
        return path if path.is_file() else None

