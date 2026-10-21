from __future__ import annotations

import base64
import io
import time
from collections import defaultdict
from dataclasses import dataclass
from html import escape

import cv2
import numpy as np
from PIL import Image, ImageOps
from reportlab.lib.colors import Color
from reportlab.lib.pagesizes import A4, landscape
from reportlab.pdfgen import canvas
from scipy.ndimage import distance_transform_edt
from skimage.color import deltaE_ciede2000


COMPLEXITY_PRESETS = {
    "detailed": {"area": 0.65, "width": 0.75, "separation": 0.5, "contour": 0.65, "curve": 0.45},
    "balanced": {"area": 1.0, "width": 1.0, "separation": 1.0, "contour": 1.0, "curve": 0.65},
    "simple": {"area": 1.8, "width": 1.4, "separation": 1.5, "contour": 1.5, "curve": 0.75},
    "minimal": {"area": 3.2, "width": 2.0, "separation": 2.2, "contour": 2.3, "curve": 0.85},
}


@dataclass
class Region:
    palette_index: int
    mask: np.ndarray
    area: int
    contour: np.ndarray
    holes: list[np.ndarray]
    label_points: list[tuple[float, float]]


@dataclass
class ProcessingResult:
    preview_png: bytes
    svg: bytes
    pdf: bytes
    palette: list[dict]
    diagnostics: dict


def _resize_for_processing(image: np.ndarray, max_side: int) -> np.ndarray:
    height, width = image.shape[:2]
    scale = min(1.0, max_side / max(height, width))
    if scale == 1.0:
        return image
    return cv2.resize(image, (round(width * scale), round(height * scale)), interpolation=cv2.INTER_AREA)


def _decode_image(data: bytes, max_side: int) -> np.ndarray:
    with Image.open(io.BytesIO(data)) as source:
        source = ImageOps.exif_transpose(source).convert("RGB")
        image = np.asarray(source)
    return _resize_for_processing(image, max_side)


def _quantize_lab(rgb: np.ndarray, color_count: int) -> tuple[np.ndarray, np.ndarray]:
    lab = cv2.cvtColor(rgb, cv2.COLOR_RGB2LAB)
    pixels = lab.reshape(-1, 3).astype(np.float32)
    criteria = (cv2.TERM_CRITERIA_EPS + cv2.TERM_CRITERIA_MAX_ITER, 35, 0.35)
    cv2.setRNGSeed(42)
    _, labels, centers = cv2.kmeans(
        pixels, color_count, None, criteria, 4, cv2.KMEANS_PP_CENTERS
    )
    labels = labels.reshape(lab.shape[:2]).astype(np.int32)
    centers = np.clip(np.rint(centers), 0, 255).astype(np.uint8)
    return labels, centers


def _lab_distance(a: np.ndarray, b: np.ndarray) -> float:
    # OpenCV stores L in 0..255 and offsets a/b by 128. Convert to standard CIE Lab.
    def standard(value: np.ndarray) -> np.ndarray:
        return np.array([value[0] * 100.0 / 255.0, value[1] - 128.0, value[2] - 128.0])

    return float(deltaE_ciede2000(standard(a), standard(b)))


def _smooth_image(rgb: np.ndarray, strength: float) -> np.ndarray:
    if strength <= 0:
        return rgb
    radius = max(1, int(round(strength * 2)))
    diameter = radius * 2 + 1
    return cv2.bilateralFilter(
        rgb,
        d=diameter,
        sigmaColor=20.0 + strength * 15.0,
        sigmaSpace=float(diameter),
    )


def _separate_close_lines(labels: np.ndarray, width: float) -> np.ndarray:
    """Remove narrow ridges/corridors, then assign removed pixels to the nearest region core."""
    kernel_size = int(round(width))
    if kernel_size < 2:
        return labels
    if kernel_size % 2 == 0:
        kernel_size += 1
    kernel = cv2.getStructuringElement(cv2.MORPH_ELLIPSE, (kernel_size, kernel_size))
    core_labels = np.full(labels.shape, -1, dtype=np.int32)
    for palette_index in np.unique(labels):
        mask = (labels == palette_index).astype(np.uint8)
        opened = cv2.morphologyEx(mask, cv2.MORPH_OPEN, kernel)
        core_labels[opened.astype(bool)] = int(palette_index)
    cores = core_labels >= 0
    if not np.any(cores):
        return labels
    _, nearest = distance_transform_edt(~cores, return_indices=True)
    cleaned = core_labels[tuple(nearest)]
    return cleaned.astype(np.int32)


def _merge_small_regions(
    labels: np.ndarray,
    centers: np.ndarray,
    min_size: int,
    min_width: float,
) -> tuple[np.ndarray, int, int]:
    labels = labels.copy()
    small_before = 0
    thin_before = 0
    kernel = np.ones((3, 3), np.uint8)
    color_distances = np.zeros((len(centers), len(centers)), dtype=np.float32)
    for left in range(len(centers)):
        for right in range(left + 1, len(centers)):
            distance = _lab_distance(centers[left], centers[right])
            color_distances[left, right] = color_distances[right, left] = distance

    # Several passes let merged islands disappear without an expensive region graph.
    for pass_index in range(5):
        changed = False
        for palette_index in range(len(centers)):
            binary = (labels == palette_index).astype(np.uint8)
            count, components, stats, _ = cv2.connectedComponentsWithStats(binary, 8)
            for component_id in range(1, count):
                area = int(stats[component_id, cv2.CC_STAT_AREA])
                # Work only inside a padded bounding box. Building a full-size mask for
                # every tiny component makes detailed photographs prohibitively slow.
                x = int(stats[component_id, cv2.CC_STAT_LEFT])
                y = int(stats[component_id, cv2.CC_STAT_TOP])
                width = int(stats[component_id, cv2.CC_STAT_WIDTH])
                height = int(stats[component_id, cv2.CC_STAT_HEIGHT])
                x0, y0 = max(0, x - 1), max(0, y - 1)
                x1, y1 = min(labels.shape[1], x + width + 1), min(labels.shape[0], y + height + 1)
                component_crop = components[y0:y1, x0:x1]
                region = component_crop == component_id
                is_small = area < min_size
                is_thin = False
                if min_width > 0:
                    distance = cv2.distanceTransform(region.astype(np.uint8), cv2.DIST_L2, 3)
                    is_thin = float(distance.max()) * 2.0 < min_width
                if not is_small and not is_thin:
                    continue
                if pass_index == 0:
                    small_before += int(is_small)
                    thin_before += int(is_thin)
                dilated = cv2.dilate(region.astype(np.uint8), kernel, iterations=1).astype(bool)
                label_crop = labels[y0:y1, x0:x1]
                neighbor_values = label_crop[dilated & ~region]
                neighbor_values = neighbor_values[neighbor_values != palette_index]
                if not neighbor_values.size:
                    continue
                candidates, border_counts = np.unique(neighbor_values, return_counts=True)
                # Prefer long borders while keeping the perceptual colour difference small.
                scores = []
                for candidate, border_count in zip(candidates, border_counts):
                    distance = float(color_distances[palette_index, int(candidate)])
                    scores.append(distance - min(8.0, float(border_count) / max(1.0, area) * 4.0))
                label_crop[region] = int(candidates[int(np.argmin(scores))])
                changed = True
        if not changed:
            break
    return labels, small_before, thin_before


def _palette_rgb(centers: np.ndarray) -> np.ndarray:
    return cv2.cvtColor(centers.reshape(1, -1, 3), cv2.COLOR_LAB2RGB).reshape(-1, 3)


def _place_label_points(
    mask: np.ndarray,
    area: int,
    target_area_per_label: float,
    min_radius: float = 3.5,
) -> list[tuple[float, float]]:
    # Repeats grow sublinearly: an area 4x the threshold gets 2 labels,
    # 9x gets 3, 16x gets 4. This avoids filling backgrounds with numbers.
    desired_count = min(
        12,
        max(1, int(np.ceil(np.sqrt(area / target_area_per_label)))),
    )
    estimated_font_size = max(5.0, min(13.0, np.sqrt(area / desired_count) * 0.22))
    required_radius = max(min_radius, estimated_font_size * 0.65 + 1.0)
    # OpenCV otherwise treats the outside of the image as foreground. Padding
    # forces regions touching the canvas edge to keep labels inside the border.
    padded = np.pad(mask, 1, mode="constant", constant_values=0)
    distance = cv2.distanceTransform(padded, cv2.DIST_L2, 5)[1:-1, 1:-1]
    score = distance.copy()
    suppression_radius = max(12, int(np.sqrt(area / desired_count) * 0.48))
    points: list[tuple[float, float]] = []
    for _ in range(desired_count):
        _, radius, _, point = cv2.minMaxLoc(score)
        if radius < required_radius:
            break
        points.append((float(point[0]), float(point[1])))
        cv2.circle(score, point, suppression_radius, 0, thickness=-1)
    return points


def _extract_regions(
    labels: np.ndarray,
    min_size: int,
    min_width: float,
    contour_smoothing: float,
    target_area_per_label: float,
) -> tuple[list[Region], int, int]:
    regions: list[Region] = []
    small_after = 0
    thin_after = 0
    for palette_index in sorted(int(value) for value in np.unique(labels)):
        binary = (labels == palette_index).astype(np.uint8)
        count, components, stats, _ = cv2.connectedComponentsWithStats(binary, 8)
        for component_id in range(1, count):
            area = int(stats[component_id, cv2.CC_STAT_AREA])
            mask = (components == component_id).astype(np.uint8)
            contours, hierarchy = cv2.findContours(mask, cv2.RETR_CCOMP, cv2.CHAIN_APPROX_SIMPLE)
            if not contours:
                continue
            outer_indices = [i for i in range(len(contours)) if hierarchy[0][i][3] == -1]
            if not outer_indices:
                continue
            outer_index = max(outer_indices, key=lambda i: cv2.contourArea(contours[i]))
            outer = cv2.approxPolyDP(contours[outer_index], contour_smoothing, True)
            holes = [
                cv2.approxPolyDP(contours[i], contour_smoothing, True)
                for i in range(len(contours))
                if hierarchy[0][i][3] == outer_index
            ]
            label_points: list[tuple[float, float]] = []
            padded = np.pad(mask, 1, mode="constant", constant_values=0)
            distance = cv2.distanceTransform(padded, cv2.DIST_L2, 5)[1:-1, 1:-1]
            radius = float(distance.max())
            if area >= max(24, min_size // 2) and radius >= 2.2:
                label_points = _place_label_points(mask, area, target_area_per_label)
            if area < min_size:
                small_after += 1
            if min_width > 0 and radius * 2.0 < min_width:
                thin_after += 1
            regions.append(Region(palette_index, mask, area, outer, holes, label_points))
    return regions, small_after, thin_after


def _extract_unique_boundaries(labels: np.ndarray, epsilon: float) -> list[np.ndarray]:
    """Trace every palette boundary once on the pixel-edge grid."""
    height, width = labels.shape
    adjacency: dict[tuple[int, int], set[tuple[int, int]]] = defaultdict(set)
    edges: set[tuple[tuple[int, int], tuple[int, int]]] = set()

    def add_edge(left: tuple[int, int], right: tuple[int, int]) -> None:
        edge = (left, right) if left < right else (right, left)
        if edge in edges:
            return
        edges.add(edge)
        adjacency[left].add(right)
        adjacency[right].add(left)

    for y, x in np.argwhere(labels[:, :-1] != labels[:, 1:]):
        add_edge((int(x) + 1, int(y)), (int(x) + 1, int(y) + 1))
    for y, x in np.argwhere(labels[:-1, :] != labels[1:, :]):
        add_edge((int(x), int(y) + 1), (int(x) + 1, int(y) + 1))
    for x in range(width):
        add_edge((x, 0), (x + 1, 0))
        add_edge((x, height), (x + 1, height))
    for y in range(height):
        add_edge((0, y), (0, y + 1))
        add_edge((width, y), (width, y + 1))

    visited: set[tuple[tuple[int, int], tuple[int, int]]] = set()

    def edge_key(left: tuple[int, int], right: tuple[int, int]):
        return (left, right) if left < right else (right, left)

    def walk(start: tuple[int, int], second: tuple[int, int]) -> list[tuple[int, int]]:
        points = [start]
        previous, current = start, second
        visited.add(edge_key(previous, current))
        while True:
            points.append(current)
            if current == start or len(adjacency[current]) != 2:
                break
            candidates = [
                neighbor for neighbor in adjacency[current]
                if neighbor != previous and edge_key(current, neighbor) not in visited
            ]
            if not candidates:
                break
            previous, current = current, candidates[0]
            visited.add(edge_key(previous, current))
        return points

    polylines: list[np.ndarray] = []
    for start, neighbors in adjacency.items():
        if len(neighbors) == 2:
            continue
        for second in neighbors:
            if edge_key(start, second) in visited:
                continue
            points = walk(start, second)
            if len(points) >= 2:
                polylines.append(np.asarray(points, dtype=np.float32))
    for left, right in edges:
        if edge_key(left, right) in visited:
            continue
        points = walk(left, right)
        if len(points) >= 2:
            polylines.append(np.asarray(points, dtype=np.float32))

    simplified: list[np.ndarray] = []
    for points in polylines:
        closed = len(points) > 2 and np.array_equal(points[0], points[-1])
        curve = cv2.approxPolyDP(points.reshape(-1, 1, 2), epsilon, closed).reshape(-1, 2)
        if closed and len(curve) >= 3:
            curve = np.vstack([curve, curve[0]])
        if len(curve) >= 2:
            simplified.append(curve.astype(np.float64))
    return simplified


def _curve_segments(points: np.ndarray, strength: float):
    closed = len(points) > 2 and np.array_equal(points[0], points[-1])
    work = points[:-1] if closed else points
    count = len(work)
    if count < 3:
        return closed, []
    segments = []
    limit = count if closed else count - 1
    for index in range(limit):
        p1 = work[index]
        p2 = work[(index + 1) % count]
        p0 = work[(index - 1) % count] if closed or index > 0 else p1
        p3 = work[(index + 2) % count] if closed or index + 2 < count else p2
        c1 = p1 + (p2 - p0) * (strength / 6.0)
        c2 = p2 - (p3 - p1) * (strength / 6.0)
        segments.append((c1, c2, p2))
    return closed, segments


def _path_for_polyline(points: np.ndarray, curve_strength: float) -> str:
    if len(points) < 2:
        return ""
    commands = [f"M {points[0][0]:.2f} {points[0][1]:.2f}"]
    closed, segments = _curve_segments(points, curve_strength)
    if not segments:
        commands.extend(f"L {point[0]:.2f} {point[1]:.2f}" for point in points[1:])
    else:
        for c1, c2, end in segments:
            commands.append(
                f"C {c1[0]:.2f} {c1[1]:.2f} {c2[0]:.2f} {c2[1]:.2f} {end[0]:.2f} {end[1]:.2f}"
            )
    if closed:
        commands.append("Z")
    return " ".join(commands)


def _build_svg(
    width: int,
    height: int,
    regions: list[Region],
    palette_numbers: dict[int, int],
    boundaries: list[np.ndarray],
    curve_strength: float,
) -> bytes:
    parts = [
        '<?xml version="1.0" encoding="UTF-8"?>',
        f'<svg xmlns="http://www.w3.org/2000/svg" width="{width}" height="{height}" viewBox="0 0 {width} {height}">',
        '<rect width="100%" height="100%" fill="white"/>',
        '<g fill="none" stroke="#606060" stroke-width="0.65" stroke-linejoin="round" stroke-linecap="round">',
    ]
    for boundary in boundaries:
        parts.append(f'<path d="{escape(_path_for_polyline(boundary, curve_strength))}"/>')
    parts.append('</g><g fill="#222" text-anchor="middle" dominant-baseline="central" font-family="Arial, sans-serif">')
    for region in regions:
        for x, y in region.label_points:
            area_per_label = region.area / max(1, len(region.label_points))
            font_size = max(5.0, min(13.0, (area_per_label ** 0.5) * 0.22))
            parts.append(f'<text x="{x:.1f}" y="{y:.1f}" font-size="{font_size:.1f}">{palette_numbers[region.palette_index]}</text>')
    parts.append('</g></svg>')
    return "".join(parts).encode("utf-8")


def _draw_pdf_path(
    pdf: canvas.Canvas,
    points: np.ndarray,
    scale: float,
    ox: float,
    oy: float,
    height: int,
    curve_strength: float,
):
    path = pdf.beginPath()
    if not len(points):
        return None
    path.moveTo(ox + points[0][0] * scale, oy + (height - points[0][1]) * scale)
    closed, segments = _curve_segments(points, curve_strength)
    if not segments:
        for x, y in points[1:]:
            path.lineTo(ox + x * scale, oy + (height - y) * scale)
    else:
        for c1, c2, end in segments:
            path.curveTo(
                ox + c1[0] * scale, oy + (height - c1[1]) * scale,
                ox + c2[0] * scale, oy + (height - c2[1]) * scale,
                ox + end[0] * scale, oy + (height - end[1]) * scale,
            )
    if closed:
        path.close()
    return path


def _build_pdf(
    width: int,
    height: int,
    regions: list[Region],
    palette_numbers: dict[int, int],
    palette: list[dict],
    boundaries: list[np.ndarray],
    curve_strength: float,
) -> bytes:
    output = io.BytesIO()
    page = landscape(A4) if width >= height else A4
    pdf = canvas.Canvas(output, pagesize=page, pageCompression=1)
    margin = 28
    available_w, available_h = page[0] - 2 * margin, page[1] - 2 * margin
    scale = min(available_w / width, available_h / height)
    draw_w, draw_h = width * scale, height * scale
    ox, oy = (page[0] - draw_w) / 2, (page[1] - draw_h) / 2
    pdf.setLineWidth(0.35)
    pdf.setStrokeColor(Color(0.35, 0.35, 0.35))
    pdf.setFillColorRGB(1, 1, 1)
    for boundary in boundaries:
        path = _draw_pdf_path(pdf, boundary, scale, ox, oy, height, curve_strength)
        if path:
            pdf.drawPath(path, stroke=1, fill=0)
    pdf.setFillColorRGB(0.1, 0.1, 0.1)
    for region in regions:
        for x, y in region.label_points:
            area_per_label = region.area / max(1, len(region.label_points))
            size = max(4.0, min(9.0, (area_per_label ** 0.5) * scale * 0.2))
            pdf.setFont("Helvetica", size)
            pdf.drawCentredString(ox + x * scale, oy + (height - y) * scale - size / 3, str(palette_numbers[region.palette_index]))
    pdf.showPage()
    pdf.setFont("Helvetica-Bold", 16)
    pdf.drawString(36, page[1] - 40, "Palette")
    y = page[1] - 70
    for entry in palette:
        if y < 40:
            pdf.showPage()
            y = page[1] - 40
        r, g, b = entry["rgb"]
        pdf.setFillColorRGB(r / 255, g / 255, b / 255)
        pdf.rect(36, y - 10, 22, 16, stroke=1, fill=1)
        pdf.setFillColorRGB(0, 0, 0)
        pdf.setFont("Helvetica", 10)
        pdf.drawString(68, y - 6, f'{entry["number"]}: {entry["hex"]}  ({entry["percentage"]:.1f}%)')
        y -= 23
    pdf.save()
    return output.getvalue()


def process_image(
    data: bytes,
    color_count: int,
    min_region_size: int,
    max_side: int = 1200,
    smoothing: float = 2.0,
    min_region_width: float = 4.0,
    contour_smoothing: float = 2.0,
    complexity: str = "balanced",
    line_separation: float = 2.0,
    label_density: float = 1.0,
) -> ProcessingResult:
    started = time.perf_counter()
    if complexity not in COMPLEXITY_PRESETS:
        raise ValueError(f"Unknown complexity preset: {complexity}")
    if not 0.25 <= label_density <= 3.0:
        raise ValueError("label_density must be between 0.25 and 3")
    preset = COMPLEXITY_PRESETS[complexity]
    effective_min_size = max(1, round(min_region_size * preset["area"]))
    effective_min_width = min_region_width * preset["width"]
    effective_contour_smoothing = contour_smoothing * preset["contour"]
    effective_line_separation = line_separation * preset["separation"]
    rgb = _decode_image(data, max_side)
    smoothed_rgb = _smooth_image(rgb, smoothing)
    labels, centers = _quantize_lab(smoothed_rgb, color_count)
    labels = _separate_close_lines(labels, effective_line_separation)
    labels, small_before, thin_before = _merge_small_regions(
        labels, centers, effective_min_size, effective_min_width
    )

    used = sorted(int(index) for index in np.unique(labels))
    rgb_centers = _palette_rgb(centers)
    # Stable numbering: darkest to lightest, then hue-like RGB order.
    used.sort(key=lambda index: (int(np.sum(rgb_centers[index])), *map(int, rgb_centers[index])))
    palette_numbers = {index: number for number, index in enumerate(used, 1)}
    target_area_per_label = labels.size * (0.012 / label_density)
    regions, small_after, thin_after = _extract_regions(
        labels,
        effective_min_size,
        effective_min_width,
        effective_contour_smoothing,
        target_area_per_label,
    )

    preview = rgb_centers[labels]
    buffer = io.BytesIO()
    Image.fromarray(preview.astype(np.uint8), "RGB").save(buffer, format="PNG", optimize=True)

    total = labels.size
    palette = []
    for index in used:
        color = tuple(int(value) for value in rgb_centers[index])
        pixel_count = int(np.count_nonzero(labels == index))
        palette.append({
            "number": palette_numbers[index],
            "hex": "#" + "".join(f"{value:02X}" for value in color),
            "rgb": color,
            "pixel_count": pixel_count,
            "percentage": round(pixel_count / total * 100, 2),
        })

    height, width = labels.shape
    boundaries = _extract_unique_boundaries(labels, effective_contour_smoothing)
    svg = _build_svg(width, height, regions, palette_numbers, boundaries, preset["curve"])
    pdf = _build_pdf(
        width, height, regions, palette_numbers, palette, boundaries, preset["curve"]
    )
    diagnostics = {
        "requested_colors": color_count,
        "actual_colors": len(used),
        "segments": len(regions),
        "small_regions_before_merge": small_before,
        "small_regions_after_merge": small_after,
        "thin_regions_before_merge": thin_before,
        "thin_regions_after_merge": thin_after,
        "smoothing": smoothing,
        "min_region_width": min_region_width,
        "contour_smoothing": contour_smoothing,
        "complexity": complexity,
        "line_separation": line_separation,
        "effective_min_region_size": effective_min_size,
        "effective_min_region_width": round(effective_min_width, 2),
        "effective_contour_smoothing": round(effective_contour_smoothing, 2),
        "effective_line_separation": round(effective_line_separation, 2),
        "boundary_paths": len(boundaries),
        "label_density": label_density,
        "labels_placed": sum(len(region.label_points) for region in regions),
        "processing_width": width,
        "processing_height": height,
        "processing_ms": round((time.perf_counter() - started) * 1000),
    }
    return ProcessingResult(buffer.getvalue(), svg, pdf, palette, diagnostics)


def data_url(mime: str, content: bytes) -> str:
    return f"data:{mime};base64,{base64.b64encode(content).decode('ascii')}"
