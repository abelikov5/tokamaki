import io

import numpy as np
from fastapi.testclient import TestClient
from PIL import Image

from app.main import app
from app.services.processor import _place_label_points, _separate_close_lines


def sample_png() -> bytes:
    image = np.zeros((180, 240, 3), dtype=np.uint8)
    image[:, :80] = (210, 65, 55)
    image[:, 80:160] = (55, 145, 210)
    image[:, 160:] = (235, 205, 70)
    image[45:135, 75:165] = (65, 175, 90)
    output = io.BytesIO()
    Image.fromarray(image).save(output, "PNG")
    return output.getvalue()


def test_health():
    response = TestClient(app).get("/health")
    assert response.status_code == 200
    assert response.json() == {"status": "ok"}


def test_process_full_flow():
    client = TestClient(app)
    response = client.post(
        "/api/v1/process",
        files={"file": ("sample.png", sample_png(), "image/png")},
        data={
            "colors": "16",
            "min_region_size": "25",
            "smoothing": "2",
            "min_region_width": "4",
            "contour_smoothing": "2",
        },
    )
    assert response.status_code == 200, response.text
    body = response.json()
    assert body["preview_data_url"].startswith("data:image/png;base64,")
    assert body["svg_data_url"].startswith("data:image/svg+xml;base64,")
    assert body["diagnostics"]["segments"] > 0
    assert 1 <= body["diagnostics"]["actual_colors"] <= 16
    assert body["diagnostics"]["thin_regions_after_merge"] == 0
    assert body["diagnostics"]["contour_smoothing"] == 2
    assert body["diagnostics"]["complexity"] == "balanced"
    assert body["diagnostics"]["boundary_paths"] > 0
    assert body["diagnostics"]["labels_placed"] > 0
    pdf = client.get(body["pdf_url"])
    assert pdf.status_code == 200
    assert pdf.content.startswith(b"%PDF")
    svg = client.get(f'/api/v1/results/{body["result_id"]}/scheme.svg')
    assert svg.status_code == 200
    assert b"<svg" in svg.content and b"<text" in svg.content


def test_rejects_wrong_file_type():
    response = TestClient(app).post(
        "/api/v1/process",
        files={"file": ("bad.txt", b"not an image", "text/plain")},
        data={"colors": "24", "min_region_size": "80"},
    )
    assert response.status_code == 415


def test_rejects_invalid_smoothing():
    response = TestClient(app).post(
        "/api/v1/process",
        files={"file": ("sample.png", sample_png(), "image/png")},
        data={"colors": "24", "min_region_size": "80", "smoothing": "9"},
    )
    assert response.status_code == 422


def test_line_separation_removes_one_pixel_ridge():
    labels = np.zeros((30, 30), dtype=np.int32)
    labels[:, 15] = 1
    cleaned = _separate_close_lines(labels, 3)
    assert not np.any(cleaned == 1)


def test_large_edge_region_gets_multiple_inset_labels():
    mask = np.ones((120, 160), dtype=np.uint8)
    points = _place_label_points(mask, int(mask.sum()), target_area_per_label=1200)
    assert len(points) >= 4
    assert all(5 < x < 154 and 5 < y < 114 for x, y in points)
