from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from app.services.processor import process_image


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("image", type=Path)
    parser.add_argument("--colors", type=int, default=24, choices=[16, 24, 36, 48])
    parser.add_argument("--min-region-size", type=int, default=100)
    parser.add_argument("--smoothing", type=float, default=2.0, help="Edge-preserving input smoothing, 0..5")
    parser.add_argument("--min-region-width", type=float, default=4.0, help="Merge regions thinner than this many pixels")
    parser.add_argument("--contour-smoothing", type=float, default=2.0, help="Vector contour simplification, 0.25..8")
    parser.add_argument("--complexity", choices=["detailed", "balanced", "simple", "minimal"], default="balanced")
    parser.add_argument("--line-separation", type=float, default=2.0, help="Remove parallel boundaries closer than this, 0..10")
    parser.add_argument("--label-density", type=float, default=1.0, help="Repeat numbers in large regions, 0.25..3")
    args = parser.parse_args()
    if not 0 <= args.smoothing <= 5:
        parser.error("--smoothing must be between 0 and 5")
    if not 0 <= args.min_region_width <= 20:
        parser.error("--min-region-width must be between 0 and 20")
    if not 0.25 <= args.contour_smoothing <= 8:
        parser.error("--contour-smoothing must be between 0.25 and 8")
    if not 0 <= args.line_separation <= 10:
        parser.error("--line-separation must be between 0 and 10")
    if not 0.25 <= args.label_density <= 3:
        parser.error("--label-density must be between 0.25 and 3")
    result = process_image(
        args.image.read_bytes(),
        args.colors,
        args.min_region_size,
        smoothing=args.smoothing,
        min_region_width=args.min_region_width,
        contour_smoothing=args.contour_smoothing,
        complexity=args.complexity,
        line_separation=args.line_separation,
        label_density=args.label_density,
    )
    output = Path(__file__).resolve().parents[1] / "demo-output"
    output.mkdir(exist_ok=True)
    (output / "preview.png").write_bytes(result.preview_png)
    (output / "scheme.svg").write_bytes(result.svg)
    (output / "scheme.pdf").write_bytes(result.pdf)
    (output / "palette.json").write_text(json.dumps(result.palette, indent=2), encoding="utf-8")
    (output / "diagnostics.json").write_text(json.dumps(result.diagnostics, indent=2), encoding="utf-8")
    print(json.dumps(result.diagnostics, indent=2))
    print(f"Output: {output}")


if __name__ == "__main__":
    main()
