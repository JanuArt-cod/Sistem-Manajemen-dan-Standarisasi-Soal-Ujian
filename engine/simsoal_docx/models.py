from __future__ import annotations

from dataclasses import asdict, dataclass, field
from typing import Any


@dataclass
class WarningItem:
    code: str
    severity: str  # INFO | WARNING | REVIEW_REQUIRED
    message: str
    location: str | None = None

    def to_dict(self) -> dict[str, Any]:
        return asdict(self)


@dataclass
class ProcessingConfig:
    paper_code: str = "F4"
    paper_width_mm: float = 215.0
    paper_height_mm: float = 330.0
    orientation: str = "PORTRAIT"
    margin_top_mm: float = 20.0
    margin_bottom_mm: float = 20.0
    margin_left_mm: float = 25.0
    margin_right_mm: float = 20.0
    font_family: str = "Times New Roman"
    font_size_pt: float = 12.0
    line_spacing: float = 1.0
    paragraph_spacing_before_pt: float = 0.0
    paragraph_spacing_after_pt: float = 4.0
    preserve_original_styles: bool = True
    apply_to_existing_tables: bool = False

    header_mode: str = "STANDARD_SCHOOL"
    first_page_only: bool = True
    show_school_logo: bool = True
    show_exam_identity_table: bool = True
    header_title: str | None = None
    header_subtitle: str | None = None
    instruction_text: str | None = None
    template_abs_path: str | None = None

    school: dict[str, Any] = field(default_factory=dict)
    document: dict[str, Any] = field(default_factory=dict)
    logo_abs_path: str | None = None

    @classmethod
    def from_snapshot(cls, payload: dict[str, Any]) -> ProcessingConfig:
        preset = payload.get("preset") or {}
        template = payload.get("template") or {}
        school = payload.get("school") or {}
        document = payload.get("document") or {}
        runtime = payload.get("runtime") or {}

        return cls(
            paper_code=str(preset.get("paper_code", "F4")),
            paper_width_mm=float(preset.get("paper_width_mm", 215.0)),
            paper_height_mm=float(preset.get("paper_height_mm", 330.0)),
            orientation=str(preset.get("orientation", "PORTRAIT")).upper(),
            margin_top_mm=float(preset.get("margin_top_mm", 20.0)),
            margin_bottom_mm=float(preset.get("margin_bottom_mm", 20.0)),
            margin_left_mm=float(preset.get("margin_left_mm", 25.0)),
            margin_right_mm=float(preset.get("margin_right_mm", 20.0)),
            font_family=str(preset.get("font_family", "Times New Roman")),
            font_size_pt=float(preset.get("font_size_pt", 12.0)),
            line_spacing=float(preset.get("line_spacing", 1.0)),
            paragraph_spacing_before_pt=float(preset.get("paragraph_spacing_before_pt", 0.0)),
            paragraph_spacing_after_pt=float(preset.get("paragraph_spacing_after_pt", 4.0)),
            preserve_original_styles=bool(preset.get("preserve_original_styles", True)),
            apply_to_existing_tables=bool(preset.get("apply_to_existing_tables", False)),
            header_mode=str(template.get("header_mode", "STANDARD_SCHOOL")).upper(),
            first_page_only=True,  # Keputusan D-016 dikunci
            show_school_logo=bool(template.get("show_school_logo", True)),
            show_exam_identity_table=bool(template.get("show_exam_identity_table", True)),
            header_title=template.get("header_title"),
            header_subtitle=template.get("header_subtitle"),
            instruction_text=template.get("instruction_text"),
            template_abs_path=runtime.get("template_abs_path") or template.get("template_abs_path"),
            school=school,
            document=document,
            logo_abs_path=runtime.get("logo_abs_path") or school.get("logo_abs_path"),
        )


@dataclass
class ProcessingReport:
    status: str  # COMPLETED | COMPLETED_WITH_WARNINGS | FAILED
    engine_name: str
    engine_version: str
    python_version: str
    docx_library_version: str
    input_checksum: str | None = None
    output_checksum: str | None = None
    needs_manual_review: bool = False
    metrics: dict[str, Any] = field(default_factory=dict)
    warnings: list[WarningItem] = field(default_factory=list)
    error_message: str | None = None

    def to_dict(self) -> dict[str, Any]:
        return {
            "status": self.status,
            "engine_name": self.engine_name,
            "engine_version": self.engine_version,
            "python_version": self.python_version,
            "docx_library_version": self.docx_library_version,
            "input_checksum": self.input_checksum,
            "output_checksum": self.output_checksum,
            "needs_manual_review": self.needs_manual_review,
            "metrics": self.metrics,
            "warnings": [w.to_dict() for w in self.warnings],
            "error_message": self.error_message,
        }