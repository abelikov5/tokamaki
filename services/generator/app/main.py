from __future__ import annotations

import uuid

from fastapi import FastAPI, File, Form, HTTPException, UploadFile
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import FileResponse
from PIL import UnidentifiedImageError

from app.config import MAX_PROCESSING_SIDE, MAX_UPLOAD_BYTES, RESULTS_DIR
from app.models import ProcessResponse
from app.services.processor import data_url, process_image
from app.services.store import FileResultStore

app = FastAPI(title="Paints by Numbers API", version="0.1.0")
app.add_middleware(
    CORSMiddleware,
    allow_origins=["http://localhost:3000", "http://127.0.0.1:3000"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)
store = FileResultStore(RESULTS_DIR)


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok"}


@app.post("/api/v1/process", response_model=ProcessResponse)
async def process(
    file: UploadFile = File(...),
    colors: int = Form(24),
    min_region_size: int = Form(80),
    smoothing: float = Form(2.0),
    min_region_width: float = Form(4.0),
    contour_smoothing: float = Form(2.0),
    complexity: str = Form("balanced"),
    line_separation: float = Form(2.0),
    label_density: float = Form(1.0),
) -> ProcessResponse:
    if colors not in {16, 24, 36, 48}:
        raise HTTPException(422, "colors must be one of 16, 24, 36 or 48")
    if not 10 <= min_region_size <= 5000:
        raise HTTPException(422, "min_region_size must be between 10 and 5000")
    if not 0 <= smoothing <= 5:
        raise HTTPException(422, "smoothing must be between 0 and 5")
    if not 0 <= min_region_width <= 20:
        raise HTTPException(422, "min_region_width must be between 0 and 20")
    if not 0.25 <= contour_smoothing <= 8:
        raise HTTPException(422, "contour_smoothing must be between 0.25 and 8")
    if complexity not in {"detailed", "balanced", "simple", "minimal"}:
        raise HTTPException(422, "complexity must be detailed, balanced, simple or minimal")
    if not 0 <= line_separation <= 10:
        raise HTTPException(422, "line_separation must be between 0 and 10")
    if not 0.25 <= label_density <= 3:
        raise HTTPException(422, "label_density must be between 0.25 and 3")
    if file.content_type not in {"image/jpeg", "image/png"}:
        raise HTTPException(415, "Only JPG and PNG images are supported")
    content = await file.read(MAX_UPLOAD_BYTES + 1)
    if len(content) > MAX_UPLOAD_BYTES:
        raise HTTPException(413, "Image is larger than 20 MB")
    try:
        result = process_image(
            content,
            colors,
            min_region_size,
            MAX_PROCESSING_SIDE,
            smoothing,
            min_region_width,
            contour_smoothing,
            complexity,
            line_separation,
            label_density,
        )
    except (UnidentifiedImageError, OSError, ValueError) as error:
        raise HTTPException(422, f"Invalid image: {error}") from error
    result_id = str(uuid.uuid4())
    store.save(result_id, result)
    return ProcessResponse(
        result_id=result_id,
        preview_data_url=data_url("image/png", result.preview_png),
        svg_data_url=data_url("image/svg+xml", result.svg),
        pdf_url=f"/api/v1/results/{result_id}/scheme.pdf",
        palette=result.palette,
        diagnostics=result.diagnostics,
    )


@app.get("/api/v1/results/{result_id}/{filename}")
def download_result(result_id: str, filename: str) -> FileResponse:
    allowed = {
        "preview.png": "image/png",
        "scheme.svg": "image/svg+xml",
        "scheme.pdf": "application/pdf",
        "palette.json": "application/json",
        "diagnostics.json": "application/json",
    }
    if filename not in allowed:
        raise HTTPException(404, "Result file not found")
    path = store.path(result_id, filename)
    if path is None:
        raise HTTPException(404, "Result file not found")
    return FileResponse(path, media_type=allowed[filename], filename=filename)
