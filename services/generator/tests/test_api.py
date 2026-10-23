import base64
import io

import numpy as np
from fastapi.testclient import TestClient
from PIL import Image

from app.main import app
from app.services.processor import (
    Region,
    _build_svg,
    _estimate_color_count,
    _erase_selected_components,
    _erase_stroke_locally,
    _merge_unlabeled_components,
    _place_label_points,
    _separate_close_lines,
)


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
    assert body["diagnostics"]["color_mode"] == "manual"
    assert body["diagnostics"]["estimated_colors"] is None
    assert body["diagnostics"]["thin_regions_after_merge"] == 0
    assert body["diagnostics"]["contour_smoothing"] == 2
    assert body["diagnostics"]["complexity"] == "balanced"
    assert body["diagnostics"]["boundary_paths"] > 0
    assert body["diagnostics"]["labels_placed"] > 0
    assert body["diagnostics"]["unlabeled_segments"] >= 0
    pdf = client.get(body["pdf_url"])
    assert pdf.status_code == 200
    assert pdf.content.startswith(b"%PDF")
    svg = client.get(f'/api/v1/results/{body["result_id"]}/scheme.svg')
    assert svg.status_code == 200
    assert b"<svg" in svg.content and b"<text" in svg.content
    assert b'id="scheme-clip"' in svg.content
    assert b'id="scheme-frame"' in svg.content
    unlabeled = client.get(f'/api/v1/results/{body["result_id"]}/unlabeled.png')
    assert unlabeled.status_code == 200
    assert unlabeled.content.startswith(b"\x89PNG")


def test_svg_uses_gray_for_only_the_largest_labels_and_halos_every_label():
    mask = np.ones((120, 120), dtype=np.uint8)
    regions = [
        Region(0, mask, 10_000, np.empty((0, 1, 2)), [], [(50.0, 50.0)]),
        Region(1, mask, 100, np.empty((0, 1, 2)), [], [(20.0, 20.0)]),
    ]

    svg = _build_svg(120, 120, regions, {0: 4, 1: 12}, [], 0.65)

    assert b'font-size="13.0" fill="#707070"' in svg
    assert b'font-size="5.0" fill="#222222"' in svg
    assert svg.count(b'stroke="#FFFFFF" stroke-width="3.0"') == 2
    assert svg.count(b'paint-order="stroke fill"') == 2


def test_auto_color_mode_detects_flat_illustration_palette():
    client = TestClient(app)
    response = client.post(
        "/api/v1/process",
        files={"file": ("sample.png", sample_png(), "image/png")},
        data={"auto_colors": "true", "min_region_size": "25"},
    )

    assert response.status_code == 200, response.text
    diagnostics = response.json()["diagnostics"]
    assert diagnostics["color_mode"] == "auto"
    assert diagnostics["estimated_colors"] == 4
    assert diagnostics["requested_colors"] == 4
    assert diagnostics["actual_colors"] <= 4


def test_color_estimator_ignores_jpeg_edge_noise():
    colors = np.asarray([
        [24, 54, 93],
        [245, 151, 148],
        [255, 248, 224],
        [235, 190, 74],
        [65, 151, 126],
        [189, 71, 126],
        [114, 92, 146],
        [238, 106, 61],
    ], dtype=np.uint8)
    image = np.repeat(colors[np.newaxis, :, :], 120, axis=0)
    image = np.repeat(image, 80, axis=1)
    buffer = io.BytesIO()
    Image.fromarray(image).save(buffer, "JPEG", quality=86)
    decoded = np.asarray(Image.open(io.BytesIO(buffer.getvalue())).convert("RGB"))

    assert 7 <= _estimate_color_count(decoded) <= 9


def test_editor_can_unify_connected_background_and_rebuild_files():
    client = TestClient(app)
    generated = client.post(
        "/api/v1/process",
        files={"file": ("sample.png", sample_png(), "image/png")},
        data={"colors": "16", "min_region_size": "25"},
    ).json()

    edited = client.post(
        f'/api/v1/results/{generated["result_id"]}/edit',
        json={
            "operation": "unify_background",
            "x": 5,
            "y": 5,
            "target_number": generated["palette"][0]["number"],
            "tolerance": 100,
        },
    )

    assert edited.status_code == 200, edited.text
    body = edited.json()
    assert body["result_id"] != generated["result_id"]
    assert body["diagnostics"]["actual_colors"] == 1
    assert body["diagnostics"]["segments"] == 1
    assert client.get(body["pdf_url"]).content.startswith(b"%PDF")


def test_editor_applies_queued_eraser_and_smoothing_strokes():
    client = TestClient(app)
    generated = client.post(
        "/api/v1/process",
        files={"file": ("sample.png", sample_png(), "image/png")},
        data={"colors": "16", "min_region_size": "25"},
    ).json()

    target = generated["palette"][0]
    preview_bytes = base64.b64decode(generated["preview_data_url"].split(",", 1)[1])
    preview = np.asarray(Image.open(io.BytesIO(preview_bytes)).convert("RGB"))
    target_pixels = np.argwhere(np.all(preview == target["rgb"], axis=2))
    y, x = (int(value) for value in target_pixels[len(target_pixels) // 2])
    response = client.post(
        f'/api/v1/results/{generated["result_id"]}/edit',
        json={
            "operations": [
                {
                    "operation": "erase_stroke",
                    "erase_mode": "color",
                    "target_number": target["number"],
                    "points": [[x, y]],
                    "radius": 3,
                },
                {
                    "operation": "erase_stroke",
                    "erase_mode": "local",
                    "points": [[120, 90], [125, 90]],
                    "radius": 3,
                },
                {
                    "operation": "smooth_stroke",
                    "points": [[70, 80], [95, 95], [120, 92]],
                    "radius": 7,
                },
                {
                    "operation": "magic_stroke",
                    "points": [[40, 40], [45, 45]],
                    "radius": 6,
                },
            ]
        },
    )

    assert response.status_code == 200, response.text
    assert response.json()["result_id"] != generated["result_id"]


def test_editor_adds_and_persists_a_manual_label():
    client = TestClient(app)
    generated = client.post(
        "/api/v1/process",
        files={"file": ("sample.png", sample_png(), "image/png")},
        data={"colors": "16", "min_region_size": "25"},
    ).json()

    first = client.post(
        f'/api/v1/results/{generated["result_id"]}/edit',
        json={"operations": [{"operation": "add_label", "x": 10, "y": 10}]},
    )
    assert first.status_code == 200, first.text
    first_body = first.json()
    assert first_body["diagnostics"]["labels_placed"] == generated["diagnostics"]["labels_placed"] + 1
    first_svg = client.get(f'/api/v1/results/{first_body["result_id"]}/scheme.svg').content
    assert b'x="10.0" y="10.0"' in first_svg

    second = client.post(
        f'/api/v1/results/{first_body["result_id"]}/edit',
        json={"operations": [{"operation": "add_label", "x": 20, "y": 20}]},
    )
    assert second.status_code == 200, second.text
    second_body = second.json()
    second_svg = client.get(f'/api/v1/results/{second_body["result_id"]}/scheme.svg').content
    assert b'x="10.0" y="10.0"' in second_svg
    assert b'x="20.0" y="20.0"' in second_svg


def test_eraser_merges_the_complete_selected_component_into_a_neighbor():
    labels = np.zeros((12, 12), dtype=np.int32)
    labels[3:9, 4:8] = 1
    centers = np.asarray([[128, 128, 128], [180, 128, 128]], dtype=np.uint8)
    stroke_mask = np.zeros_like(labels, dtype=bool)
    stroke_mask[5, 5] = True

    edited = _erase_selected_components(labels, centers, stroke_mask, target_index=1)

    assert not np.any(edited == 1)


def test_local_eraser_only_replaces_pixels_under_the_stroke():
    labels = np.zeros((12, 12), dtype=np.int32)
    labels[3:9, 4:8] = 1
    stroke_mask = labels == 1

    edited = _erase_stroke_locally(labels, stroke_mask)

    assert not np.any(edited == 1)
    assert np.array_equal(edited[~stroke_mask], labels[~stroke_mask])


def test_local_eraser_removes_a_boundary_instead_of_recreating_it():
    labels = np.zeros((14, 14), dtype=np.int32)
    labels[:, 7:] = 1
    stroke_mask = np.zeros_like(labels, dtype=bool)
    stroke_mask[4:10, 5:9] = True

    edited = _erase_stroke_locally(labels, stroke_mask)

    assert np.unique(edited[stroke_mask]).size == 1
    assert np.array_equal(edited[~stroke_mask], labels[~stroke_mask])


def test_magic_brush_merges_an_unlabeled_component_into_the_largest_neighbor():
    labels = np.zeros((20, 20), dtype=np.int32)
    labels[:, 12:] = 2
    labels[8:11, 10:13] = 1
    stroke_mask = np.zeros_like(labels, dtype=bool)
    stroke_mask[9, 11] = True

    edited = _merge_unlabeled_components(labels, stroke_mask, min_size=100, label_density=1)

    assert not np.any(edited == 1)
    assert np.all(edited[8:11, 10:13] == 0)
    assert np.array_equal(edited[~(labels == 1)], labels[~(labels == 1)])


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
