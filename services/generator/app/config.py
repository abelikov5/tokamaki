import os
from pathlib import Path

MAX_UPLOAD_BYTES = 20 * 1024 * 1024
MAX_PROCESSING_SIDE = max(600, min(1200, int(os.getenv("GENERATOR_MAX_PROCESSING_SIDE", "900"))))
RESULTS_DIR = Path(__file__).resolve().parents[1] / "data" / "results"

