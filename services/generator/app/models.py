from pydantic import BaseModel


class PaletteEntry(BaseModel):
    number: int
    hex: str
    rgb: tuple[int, int, int]
    pixel_count: int
    percentage: float


class Diagnostics(BaseModel):
    requested_colors: int
    actual_colors: int
    segments: int
    small_regions_before_merge: int
    small_regions_after_merge: int
    thin_regions_before_merge: int
    thin_regions_after_merge: int
    smoothing: float
    min_region_width: float
    contour_smoothing: float
    complexity: str
    line_separation: float
    effective_min_region_size: int
    effective_min_region_width: float
    effective_contour_smoothing: float
    effective_line_separation: float
    boundary_paths: int
    label_density: float
    labels_placed: int
    processing_width: int
    processing_height: int
    processing_ms: int


class ProcessResponse(BaseModel):
    result_id: str
    preview_data_url: str
    svg_data_url: str
    pdf_url: str
    palette: list[PaletteEntry]
    diagnostics: Diagnostics
