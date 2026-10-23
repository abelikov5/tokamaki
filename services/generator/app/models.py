from pydantic import BaseModel, Field


class PaletteEntry(BaseModel):
    number: int
    hex: str
    rgb: tuple[int, int, int]
    pixel_count: int
    percentage: float


class Diagnostics(BaseModel):
    requested_colors: int
    actual_colors: int
    color_mode: str = "manual"
    estimated_colors: int | None = None
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
    unlabeled_segments: int
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


class EditOperation(BaseModel):
    operation: str
    x: int = 0
    y: int = 0
    target_number: int | None = None
    erase_mode: str = "color"
    tolerance: float = 12.0
    points: list[tuple[float, float]] = Field(default_factory=list)
    radius: int = 8


class EditRequest(BaseModel):
    operation: str | None = None
    x: int = 0
    y: int = 0
    target_number: int | None = None
    erase_mode: str = "color"
    tolerance: float = 12.0
    points: list[tuple[float, float]] = Field(default_factory=list)
    radius: int = 8
    operations: list[EditOperation] = Field(default_factory=list)
