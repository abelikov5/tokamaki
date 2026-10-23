from __future__ import annotations

import base64
import io
import logging
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

logger = logging.getLogger("uvicorn.error")


COMPLEXITY_PRESETS = {
    "detailed": {"area": 0.65, "width": 0.75, "separation": 0.5, "contour": 0.65, "curve": 0.45},
    "balanced": {"area": 1.0, "width": 1.0, "separation": 1.0, "contour": 1.0, "curve": 0.65},
    "simple": {"area": 1.8, "width": 1.4, "separation": 1.5, "contour": 1.5, "curve": 0.75},
    "minimal": {"area": 3.2, "width": 2.0, "separation": 2.2, "contour": 2.3, "curve": 0.85},
}

MAX_LABEL_FONT_SIZE = 13.0
LARGE_LABEL_COLOR = "#707070"
REGULAR_LABEL_COLOR = "#222222"
LABEL_HALO_COLOR = "#FFFFFF"
LABEL_HALO_WIDTH = 3.0


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
    editor_state: "EditorState"


@dataclass
class EditorState:
    labels: np.ndarray
    lab_centers: np.ndarray
    settings: dict


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


def _estimate_color_count(rgb: np.ndarray, max_colors: int = 48) -> int:
    """Estimate a useful palette size while ignoring JPEG noise and edge blends."""
    preview = _resize_for_processing(rgb, 220)
    lab = cv2.cvtColor(preview, cv2.COLOR_RGB2LAB)
    pixels = lab.reshape(-1, 3).astype(np.float32)
    if len(pixels) > 20_000:
        indices = np.linspace(0, len(pixels) - 1, 20_000, dtype=np.int32)
        pixels = pixels[indices]

    unique_colors = np.unique(pixels, axis=0)
    candidate_count = min(32, max_colors, len(unique_colors))
    if candidate_count <= 1:
        return 1

    criteria = (cv2.TERM_CRITERIA_EPS + cv2.TERM_CRITERIA_MAX_ITER, 24, 0.6)
    cv2.setRNGSeed(42)
    _, assignments, centers = cv2.kmeans(
        pixels,
        candidate_count,
        None,
        criteria,
        2,
        cv2.KMEANS_PP_CENTERS,
    )
    counts = np.bincount(assignments.ravel(), minlength=candidate_count)
    minimum_cluster_size = max(10, round(len(pixels) * 0.0075))
    merged_centers: list[np.ndarray] = []
    merged_counts: list[int] = []

    for index in np.argsort(counts)[::-1]:
        count = int(counts[index])
        if count < minimum_cluster_size:
            continue
        center = centers[index].astype(np.float64)
        if merged_centers:
            distances = [_lab_distance(center, existing) for existing in merged_centers]
            nearest = int(np.argmin(distances))
            if distances[nearest] < 10.0:
                total = merged_counts[nearest] + count
                merged_centers[nearest] = (
                    merged_centers[nearest] * merged_counts[nearest] + center * count
                ) / total
                merged_counts[nearest] = total
                continue
        merged_centers.append(center)
        merged_counts.append(count)

    return max(1, min(max_colors, len(merged_centers)))


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


def _label_font_size(region: Region) -> float:
    area_per_label = region.area / max(1, len(region.label_points))
    return max(5.0, min(MAX_LABEL_FONT_SIZE, (area_per_label ** 0.5) * 0.22))


def _label_fill_color(font_size: float) -> str:
    return LARGE_LABEL_COLOR if font_size >= MAX_LABEL_FONT_SIZE else REGULAR_LABEL_COLOR


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
        f'<defs><clipPath id="scheme-clip"><rect width="{width}" height="{height}"/></clipPath></defs>',
        '<g clip-path="url(#scheme-clip)" fill="none" stroke="#606060" stroke-width="0.65" stroke-linejoin="round" stroke-linecap="round">',
    ]
    for boundary in boundaries:
        parts.append(f'<path d="{escape(_path_for_polyline(boundary, curve_strength))}"/>')
    parts.append('</g><g text-anchor="middle" dominant-baseline="central" font-family="Arial, sans-serif">')
    for region in regions:
        for x, y in region.label_points:
            value = str(palette_numbers[region.palette_index])
            font_size = _label_font_size(region)
            attributes = [
                f'x="{x:.1f}"',
                f'y="{y:.1f}"',
                f'font-size="{font_size:.1f}"',
                f'fill="{_label_fill_color(font_size)}"',
                f'stroke="{LABEL_HALO_COLOR}"',
                f'stroke-width="{LABEL_HALO_WIDTH:.1f}"',
                'stroke-linejoin="round"',
                'paint-order="stroke fill"',
            ]
            parts.append(f'<text {" ".join(attributes)}>{value}</text>')
    frame_inset = 0.325
    frame_width = max(0.0, width - frame_inset * 2)
    frame_height = max(0.0, height - frame_inset * 2)
    parts.append(
        f'</g><rect id="scheme-frame" x="{frame_inset:.3f}" y="{frame_inset:.3f}" '
        f'width="{frame_width:.3f}" height="{frame_height:.3f}" fill="none" stroke="#606060" '
        'stroke-width="0.65" stroke-linejoin="miter" stroke-linecap="square"/></svg>'
    )
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
    pdf.saveState()
    clip = pdf.beginPath()
    clip.rect(ox, oy, draw_w, draw_h)
    pdf.clipPath(clip, stroke=0, fill=0)
    for boundary in boundaries:
        path = _draw_pdf_path(pdf, boundary, scale, ox, oy, height, curve_strength)
        if path:
            pdf.drawPath(path, stroke=1, fill=0)
    pdf.restoreState()
    pdf.setLineJoin(0)
    pdf.setLineCap(0)
    pdf.rect(ox, oy, draw_w, draw_h, stroke=1, fill=0)
    pdf.setFillColorRGB(0.1, 0.1, 0.1)
    for region in regions:
        for x, y in region.label_points:
            area_per_label = region.area / max(1, len(region.label_points))
            size = max(4.0, min(9.0, (area_per_label ** 0.5) * scale * 0.2))
            value = str(palette_numbers[region.palette_index])
            source_font_size = _label_font_size(region)
            text_x = ox + x * scale
            text_y = oy + (height - y) * scale - size / 3
            pdf.setFont("Helvetica", size)
            pdf.saveState()
            pdf.setStrokeColorRGB(1, 1, 1)
            pdf.setLineWidth(LABEL_HALO_WIDTH * scale)
            halo = pdf.beginText()
            halo.setTextOrigin(text_x - pdf.stringWidth(value, "Helvetica", size) / 2, text_y)
            halo.setFont("Helvetica", size)
            halo.setTextRenderMode(1)
            halo.textOut(value)
            pdf.drawText(halo)
            pdf.restoreState()
            if _label_fill_color(source_font_size) == LARGE_LABEL_COLOR:
                pdf.setFillColorRGB(0.44, 0.44, 0.44)
            else:
                pdf.setFillColorRGB(0.13, 0.13, 0.13)
            pdf.drawCentredString(text_x, text_y, value)
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


def _palette_layout(labels: np.ndarray, rgb_centers: np.ndarray) -> tuple[list[int], dict[int, int]]:
    used = sorted(int(index) for index in np.unique(labels))
    # Stable numbering: darkest to lightest, then hue-like RGB order.
    used.sort(key=lambda index: (int(np.sum(rgb_centers[index])), *map(int, rgb_centers[index])))
    return used, {index: number for number, index in enumerate(used, 1)}


def _apply_manual_label_points(regions: list[Region], labels: np.ndarray, settings: dict) -> None:
    for raw_point in settings.get("manual_label_points", []):
        if len(raw_point) != 2:
            continue
        x, y = (int(round(float(value))) for value in raw_point)
        if not (0 <= x < labels.shape[1] and 0 <= y < labels.shape[0]):
            continue
        for region in regions:
            if region.palette_index == int(labels[y, x]) and bool(region.mask[y, x]):
                region.label_points.append((float(x), float(y)))
                break


def _build_unlabeled_overlay(width: int, height: int, regions: list[Region]) -> bytes:
    overlay = np.zeros((height, width, 4), dtype=np.uint8)
    for region in regions:
        if region.label_points:
            continue
        overlay[region.mask] = (244, 67, 54, 92)
        contours, _ = cv2.findContours(
            region.mask.astype(np.uint8),
            cv2.RETR_EXTERNAL,
            cv2.CHAIN_APPROX_SIMPLE,
        )
        cv2.drawContours(overlay, contours, -1, (215, 38, 28, 220), 2, lineType=cv2.LINE_AA)
    return _encode_unlabeled_overlay(overlay)


def _encode_unlabeled_overlay(overlay: np.ndarray) -> bytes:
    buffer = io.BytesIO()
    Image.fromarray(overlay, "RGBA").save(buffer, format="PNG", optimize=True)
    return buffer.getvalue()


def build_unlabeled_overlay_from_state(state: EditorState) -> bytes:
    labels = state.labels
    settings = state.settings
    height, width = labels.shape
    overlay = np.zeros((height, width, 4), dtype=np.uint8)
    target_area_per_label = labels.size * (0.012 / settings["label_density"])
    manual_points = [
        (int(round(float(point[0]))), int(round(float(point[1]))))
        for point in settings.get("manual_label_points", [])
        if len(point) == 2
    ]
    for palette_index in sorted(int(value) for value in np.unique(labels)):
        binary = (labels == palette_index).astype(np.uint8)
        count, components, stats, _ = cv2.connectedComponentsWithStats(binary, 8)
        manual_component_ids = {
            int(components[y, x])
            for x, y in manual_points
            if 0 <= x < width and 0 <= y < height and int(labels[y, x]) == palette_index
        }
        for component_id in range(1, count):
            if component_id in manual_component_ids:
                continue
            area = int(stats[component_id, cv2.CC_STAT_AREA])
            x = int(stats[component_id, cv2.CC_STAT_LEFT])
            y = int(stats[component_id, cv2.CC_STAT_TOP])
            component_width = int(stats[component_id, cv2.CC_STAT_WIDTH])
            component_height = int(stats[component_id, cv2.CC_STAT_HEIGHT])
            local_mask = (
                components[y:y + component_height, x:x + component_width] == component_id
            ).astype(np.uint8)
            has_automatic_label = False
            if area >= max(24, settings["effective_min_region_size"] // 2):
                desired_count = min(
                    12,
                    max(1, int(np.ceil(np.sqrt(area / target_area_per_label)))),
                )
                estimated_font_size = max(
                    5.0,
                    min(13.0, np.sqrt(area / desired_count) * 0.22),
                )
                required_radius = max(3.5, estimated_font_size * 0.65 + 1.0)
                padded = np.pad(local_mask, 1, mode="constant", constant_values=0)
                radius = float(cv2.distanceTransform(padded, cv2.DIST_L2, 5).max())
                has_automatic_label = radius >= required_radius
            if has_automatic_label:
                continue
            overlay_crop = overlay[y:y + component_height, x:x + component_width]
            overlay_crop[local_mask.astype(bool)] = (244, 67, 54, 92)
            contours, _ = cv2.findContours(
                local_mask,
                cv2.RETR_EXTERNAL,
                cv2.CHAIN_APPROX_SIMPLE,
            )
            cv2.drawContours(
                overlay_crop,
                contours,
                -1,
                (215, 38, 28, 220),
                2,
                lineType=cv2.LINE_AA,
            )
    return _encode_unlabeled_overlay(overlay)


def _render_editable_result(
    labels: np.ndarray,
    centers: np.ndarray,
    settings: dict,
    started: float,
) -> ProcessingResult:
    stage_started = time.perf_counter()
    logger.info("Рендер: ищем области и размещаем номера...")
    rgb_centers = _palette_rgb(centers)
    used, palette_numbers = _palette_layout(labels, rgb_centers)
    target_area_per_label = labels.size * (0.012 / settings["label_density"])
    regions, small_after, thin_after = _extract_regions(
        labels,
        settings["effective_min_region_size"],
        settings["effective_min_region_width"],
        settings["effective_contour_smoothing"],
        target_area_per_label,
    )
    _apply_manual_label_points(regions, labels, settings)
    logger.info(
        "Рендер: найдено областей %d (%.1f с), строим превью...",
        len(regions), time.perf_counter() - stage_started,
    )

    stage_started = time.perf_counter()
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
    logger.info("Рендер: превью готово (%.1f с), строим контуры...", time.perf_counter() - stage_started)

    stage_started = time.perf_counter()
    height, width = labels.shape
    unlabeled_segments = sum(not region.label_points for region in regions)
    boundaries = _extract_unique_boundaries(labels, settings["effective_contour_smoothing"])
    logger.info(
        "Рендер: контуры готовы, путей %d (%.1f с), строим SVG...",
        len(boundaries), time.perf_counter() - stage_started,
    )
    stage_started = time.perf_counter()
    svg = _build_svg(width, height, regions, palette_numbers, boundaries, settings["curve_strength"])
    logger.info("Рендер: SVG готов (%.1f с), строим PDF...", time.perf_counter() - stage_started)
    stage_started = time.perf_counter()
    pdf = _build_pdf(
        width, height, regions, palette_numbers, palette, boundaries, settings["curve_strength"]
    )
    logger.info("Рендер: PDF готов (%.1f с).", time.perf_counter() - stage_started)
    diagnostics = {
        "requested_colors": settings["requested_colors"],
        "actual_colors": len(used),
        "color_mode": settings.get("color_mode", "manual"),
        "estimated_colors": settings.get("estimated_colors"),
        "segments": len(regions),
        "small_regions_before_merge": settings.get("small_regions_before_merge", 0),
        "small_regions_after_merge": small_after,
        "thin_regions_before_merge": settings.get("thin_regions_before_merge", 0),
        "thin_regions_after_merge": thin_after,
        "smoothing": settings["smoothing"],
        "min_region_width": settings["min_region_width"],
        "contour_smoothing": settings["contour_smoothing"],
        "complexity": settings["complexity"],
        "line_separation": settings["line_separation"],
        "effective_min_region_size": settings["effective_min_region_size"],
        "effective_min_region_width": round(settings["effective_min_region_width"], 2),
        "effective_contour_smoothing": round(settings["effective_contour_smoothing"], 2),
        "effective_line_separation": round(settings["effective_line_separation"], 2),
        "boundary_paths": len(boundaries),
        "label_density": settings["label_density"],
        "labels_placed": sum(len(region.label_points) for region in regions),
        "unlabeled_segments": unlabeled_segments,
        "processing_width": width,
        "processing_height": height,
        "processing_ms": round((time.perf_counter() - started) * 1000),
    }
    state = EditorState(labels.copy(), centers.copy(), settings.copy())
    return ProcessingResult(buffer.getvalue(), svg, pdf, palette, diagnostics, state)


def _stroke_mask(shape: tuple[int, int], points: list[tuple[float, float]], radius: int) -> np.ndarray:
    if not points:
        raise ValueError("Stroke must contain at least one point")
    height, width = shape
    clipped = [
        (
            min(width - 1, max(0, int(round(x)))),
            min(height - 1, max(0, int(round(y)))),
        )
        for x, y in points
    ]
    mask = np.zeros(shape, dtype=np.uint8)
    thickness = max(1, radius * 2)
    if len(clipped) == 1:
        cv2.circle(mask, clipped[0], radius, 1, thickness=-1)
    else:
        for start, end in zip(clipped, clipped[1:]):
            cv2.line(mask, start, end, 1, thickness=thickness, lineType=cv2.LINE_AA)
        cv2.circle(mask, clipped[0], radius, 1, thickness=-1)
        cv2.circle(mask, clipped[-1], radius, 1, thickness=-1)
    return mask.astype(bool)


def _smooth_labels_in_mask(labels: np.ndarray, mask: np.ndarray, kernel_size: int) -> np.ndarray:
    kernel_size = max(3, kernel_size | 1)
    used = [int(value) for value in np.unique(labels)]
    scores = np.stack([
        cv2.boxFilter(
            (labels == index).astype(np.float32),
            ddepth=-1,
            ksize=(kernel_size, kernel_size),
            normalize=False,
            borderType=cv2.BORDER_REPLICATE,
        )
        for index in used
    ])
    majority = np.asarray(used, dtype=np.int32)[np.argmax(scores, axis=0)]
    smoothed = labels.copy()
    smoothed[mask] = majority[mask]
    return smoothed


def _erase_selected_components(
    labels: np.ndarray,
    centers: np.ndarray,
    stroke_mask: np.ndarray,
    target_index: int,
) -> np.ndarray:
    target_mask = (labels == target_index).astype(np.uint8)
    _, components = cv2.connectedComponents(target_mask, 8)
    selected_components = [
        int(component_id)
        for component_id in np.unique(components[stroke_mask & (components > 0)])
        if component_id > 0
    ]
    if not selected_components:
        raise ValueError("The eraser did not touch the selected color")

    edited = labels.copy()
    kernel = np.ones((3, 3), dtype=np.uint8)
    for component_id in selected_components:
        component = components == component_id
        border = cv2.dilate(component.astype(np.uint8), kernel, iterations=1).astype(bool) & ~component
        neighbor_values = edited[border]
        neighbor_values = neighbor_values[neighbor_values != target_index]
        if not neighbor_values.size:
            raise ValueError("Cannot determine a neighboring area for the eraser")
        candidates, border_counts = np.unique(neighbor_values, return_counts=True)
        scores = [
            _lab_distance(centers[target_index], centers[int(candidate)])
            - min(12.0, float(border_count) * 0.35)
            for candidate, border_count in zip(candidates, border_counts)
        ]
        edited[component] = int(candidates[int(np.argmin(scores))])
    return edited


def _erase_stroke_locally(labels: np.ndarray, stroke_mask: np.ndarray) -> np.ndarray:
    if np.all(stroke_mask):
        raise ValueError("The eraser stroke cannot cover the entire image")

    edited = labels.copy()
    component_count, components = cv2.connectedComponents(stroke_mask.astype(np.uint8), 8)
    kernel = np.ones((3, 3), dtype=np.uint8)
    for component_id in range(1, component_count):
        component = components == component_id
        border = cv2.dilate(component.astype(np.uint8), kernel, iterations=1).astype(bool) & ~component
        neighboring_labels = labels[border]
        if not neighboring_labels.size:
            continue
        candidates, counts = np.unique(neighboring_labels, return_counts=True)
        edited[component] = int(candidates[int(np.argmax(counts))])
    return edited


def _merge_unlabeled_components(
    labels: np.ndarray,
    stroke_mask: np.ndarray,
    min_size: int,
    label_density: float,
) -> np.ndarray:
    region_ids = np.full(labels.shape, -1, dtype=np.int32)
    region_masks: list[np.ndarray] = []
    region_palette_indices: list[int] = []
    region_areas: list[int] = []
    unlabeled_region_ids: set[int] = set()
    target_area_per_label = labels.size * (0.012 / label_density)

    for palette_index in sorted(int(value) for value in np.unique(labels)):
        binary = (labels == palette_index).astype(np.uint8)
        count, components, stats, _ = cv2.connectedComponentsWithStats(binary, 8)
        for component_id in range(1, count):
            component = components == component_id
            area = int(stats[component_id, cv2.CC_STAT_AREA])
            region_id = len(region_masks)
            region_ids[component] = region_id
            region_masks.append(component)
            region_palette_indices.append(palette_index)
            region_areas.append(area)

            label_points: list[tuple[float, float]] = []
            if area >= max(24, min_size // 2):
                label_points = _place_label_points(
                    component.astype(np.uint8),
                    area,
                    target_area_per_label,
                )
            if not label_points:
                unlabeled_region_ids.add(region_id)

    touched_region_ids = {
        int(region_id)
        for region_id in np.unique(region_ids[stroke_mask])
        if int(region_id) in unlabeled_region_ids
    }
    edited = labels.copy()
    kernel = np.ones((3, 3), dtype=np.uint8)
    for region_id in sorted(touched_region_ids, key=lambda value: region_areas[value]):
        component = region_masks[region_id]
        border = cv2.dilate(component.astype(np.uint8), kernel, iterations=1).astype(bool) & ~component
        neighboring_ids, border_counts = np.unique(region_ids[border], return_counts=True)
        candidates = [
            (int(neighbor_id), int(border_count))
            for neighbor_id, border_count in zip(neighboring_ids, border_counts)
            if neighbor_id >= 0 and int(neighbor_id) not in touched_region_ids
        ]
        if not candidates:
            candidates = [
                (int(neighbor_id), int(border_count))
                for neighbor_id, border_count in zip(neighboring_ids, border_counts)
                if neighbor_id >= 0 and int(neighbor_id) != region_id
            ]
        if not candidates:
            continue
        target_region_id, _ = max(
            candidates,
            key=lambda candidate: (region_areas[candidate[0]], candidate[1]),
        )
        edited[component] = region_palette_indices[target_region_id]
    return edited


def _apply_edit_operation(
    labels: np.ndarray,
    centers: np.ndarray,
    number_to_index: dict[int, int],
    settings: dict,
    operation: dict,
) -> np.ndarray:
    operation_name = operation["operation"]
    erase_mode = operation.get("erase_mode", "color")
    target_number = operation.get("target_number")
    target_index = number_to_index.get(target_number)
    needs_target = operation_name in {"recolor_region", "unify_background", "paint_stroke"} or (
        operation_name == "erase_stroke" and erase_mode == "color"
    )
    if needs_target:
        if target_index is None:
            raise ValueError("Target palette number is not used in this result")

    if operation_name in {"recolor_region", "unify_background"}:
        x = int(operation.get("x", 0))
        y = int(operation.get("y", 0))
        height, width = labels.shape
        if not (0 <= x < width and 0 <= y < height):
            raise ValueError("Selected point is outside the image")

    if operation_name == "recolor_region":
        source_index = int(labels[y, x])
        source_mask = (labels == source_index).astype(np.uint8)
        _, components = cv2.connectedComponents(source_mask, 8)
        selected_component = int(components[y, x])
        edited = labels.copy()
        edited[components == selected_component] = target_index
        return edited
    if operation_name == "unify_background":
        source_index = int(labels[y, x])
        tolerance = float(operation.get("tolerance", 12.0))
        allowed = [
            index for index in np.unique(labels)
            if _lab_distance(centers[source_index], centers[int(index)]) <= tolerance
        ]
        candidate_mask = np.isin(labels, allowed).astype(np.uint8)
        _, components = cv2.connectedComponents(candidate_mask, 8)
        selected_component = int(components[y, x])
        edited = labels.copy()
        edited[components == selected_component] = target_index
        return edited

    points = operation.get("points") or []
    radius = int(operation.get("radius", 8))
    changed_mask = _stroke_mask(labels.shape, points, radius)
    if operation_name == "paint_stroke":
        edited = labels.copy()
        edited[changed_mask] = target_index
        return edited
    if operation_name == "erase_stroke":
        if erase_mode == "local":
            return _erase_stroke_locally(labels, changed_mask)
        return _erase_selected_components(labels, centers, changed_mask, target_index)
    if operation_name == "smooth_stroke":
        return _smooth_labels_in_mask(labels, changed_mask, max(3, radius // 2))
    if operation_name == "magic_stroke":
        return _merge_unlabeled_components(
            labels,
            changed_mask,
            settings["effective_min_region_size"],
            settings["label_density"],
        )
    raise ValueError("Unknown editor operation")


def edit_result_batch(state: EditorState, operations: list[dict]) -> ProcessingResult:
    if not operations:
        raise ValueError("At least one editor operation is required")
    started = time.perf_counter()
    labels = state.labels.copy()
    settings = state.settings.copy()
    manual_label_points = [list(point) for point in settings.get("manual_label_points", [])]
    rgb_centers = _palette_rgb(state.lab_centers)
    _, palette_numbers = _palette_layout(labels, rgb_centers)
    number_to_index = {number: index for index, number in palette_numbers.items()}
    logger.info("Редактор: применяем %d операций...", len(operations))
    for operation_index, operation in enumerate(operations, start=1):
        logger.info(
            "Редактор: операция %d/%d — %s",
            operation_index, len(operations), operation["operation"],
        )
        if operation["operation"] == "add_label":
            x = int(operation.get("x", 0))
            y = int(operation.get("y", 0))
            height, width = labels.shape
            if not (0 <= x < width and 0 <= y < height):
                raise ValueError("Selected point is outside the image")
            if not any((x - point[0]) ** 2 + (y - point[1]) ** 2 < 4 for point in manual_label_points):
                manual_label_points.append([x, y])
            continue
        labels = _apply_edit_operation(
            labels,
            state.lab_centers,
            number_to_index,
            settings,
            operation,
        )

    settings["manual_label_points"] = manual_label_points
    settings["small_regions_before_merge"] = 0
    settings["thin_regions_before_merge"] = 0
    logger.info("Редактор: операции применены, перестраиваем результат...")
    return _render_editable_result(labels, state.lab_centers, settings, started)


def edit_result(
    state: EditorState,
    operation: str,
    x: int = 0,
    y: int = 0,
    target_number: int | None = None,
    tolerance: float = 12.0,
    points: list[tuple[float, float]] | None = None,
    radius: int = 8,
    erase_mode: str = "color",
) -> ProcessingResult:
    return edit_result_batch(state, [{
        "operation": operation,
        "x": x,
        "y": y,
        "target_number": target_number,
        "tolerance": tolerance,
        "points": points or [],
        "radius": radius,
        "erase_mode": erase_mode,
    }])


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
    auto_colors: bool = False,
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
    resolved_color_count = _estimate_color_count(smoothed_rgb) if auto_colors else color_count
    labels, centers = _quantize_lab(smoothed_rgb, resolved_color_count)
    labels = _separate_close_lines(labels, effective_line_separation)
    labels, small_before, thin_before = _merge_small_regions(
        labels, centers, effective_min_size, effective_min_width
    )

    settings = {
        "requested_colors": resolved_color_count,
        "color_mode": "auto" if auto_colors else "manual",
        "estimated_colors": resolved_color_count if auto_colors else None,
        "small_regions_before_merge": small_before,
        "thin_regions_before_merge": thin_before,
        "smoothing": smoothing,
        "min_region_width": min_region_width,
        "contour_smoothing": contour_smoothing,
        "complexity": complexity,
        "line_separation": line_separation,
        "effective_min_region_size": effective_min_size,
        "effective_min_region_width": round(effective_min_width, 2),
        "effective_contour_smoothing": round(effective_contour_smoothing, 2),
        "effective_line_separation": round(effective_line_separation, 2),
        "label_density": label_density,
        "curve_strength": preset["curve"],
    }
    return _render_editable_result(labels, centers, settings, started)


def data_url(mime: str, content: bytes) -> str:
    return f"data:{mime};base64,{base64.b64encode(content).decode('ascii')}"
