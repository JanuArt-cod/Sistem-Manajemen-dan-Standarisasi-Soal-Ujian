from __future__ import annotations

from dataclasses import dataclass
from typing import Any

from docx.document import Document as DocxDocument
from lxml import etree

from simsoal_docx.models import WarningItem

W_NS = "http://schemas.openxmlformats.org/wordprocessingml/2006/main"
M_NS = "http://schemas.openxmlformats.org/officeDocument/2006/math"
WPS_NS = "http://schemas.microsoft.com/office/word/2010/wordprocessingShape"
V_NS = "urn:schemas-microsoft-com:vml"

NSMAP = {
    "w": W_NS,
    "m": M_NS,
    "wps": WPS_NS,
    "v": V_NS,
}


def _xpath(element: Any, expression: str) -> list[Any]:
    return etree._Element.xpath(element, expression, namespaces=NSMAP)


@dataclass
class DocumentMetrics:
    paragraph_count: int
    non_empty_paragraph_count: int
    table_count: int
    section_count: int
    image_count: int
    equation_count: int
    textbox_count: int
    embedded_object_count: int
    tracked_change_count: int
    comment_count: int
    non_empty_texts: list[str]


def inspect_document(doc: DocxDocument) -> tuple[DocumentMetrics, list[WarningItem]]:
    """Menganalisis struktur internal dokumen .docx sebelum standarisasi."""
    body = doc.element.body
    warnings: list[WarningItem] = []

    non_empty_texts = [p.text for p in doc.paragraphs if p.text and p.text.strip()]
    paragraph_count = len(doc.paragraphs)
    non_empty_paragraph_count = len(non_empty_texts)
    table_count = len(doc.tables)
    section_count = len(doc.sections)

    image_count = len(_xpath(body, ".//w:drawing | .//w:pict"))
    equation_count = len(_xpath(body, ".//m:oMath"))
    textbox_count = len(_xpath(body, ".//w:txbxContent | .//wps:txbx | .//v:textbox"))
    embedded_object_count = len(_xpath(body, ".//w:object | .//w:control"))
    tracked_change_count = len(_xpath(body, ".//w:ins | .//w:del"))
    comment_count = len(_xpath(body, ".//w:commentRangeStart"))

    if non_empty_paragraph_count == 0 and table_count == 0 and image_count == 0:
        warnings.append(
            WarningItem(
                code="EMPTY_DOCUMENT",
                severity="REVIEW_REQUIRED",
                message="Dokumen sumber tidak memiliki teks, tabel, atau gambar pada badan utama.",
                location="body",
            )
        )

    if section_count > 1:
        warnings.append(
            WarningItem(
                code="MULTIPLE_SECTIONS",
                severity="WARNING",
                message=f"Dokumen memiliki {section_count} section. Standar kertas dan margin diterapkan ke seluruh section.",
                location="sections",
            )
        )

    if textbox_count > 0:
        warnings.append(
            WarningItem(
                code="TEXTBOX_OR_FLOATING_SHAPE",
                severity="REVIEW_REQUIRED",
                message=f"Ditemukan {textbox_count} kotak teks (textbox/shape) melayang. Periksa tata letak akhir secara manual.",
                location="body",
            )
        )

    if embedded_object_count > 0:
        warnings.append(
            WarningItem(
                code="EMBEDDED_OLE_OR_CONTROL",
                severity="REVIEW_REQUIRED",
                message=f"Ditemukan {embedded_object_count} objek tersemat (OLE/Equation lama/ActiveX). Pastikan tampil utuh pada hasil cetak.",
                location="body",
            )
        )

    if tracked_change_count > 0:
        warnings.append(
            WarningItem(
                code="TRACKED_CHANGES_PRESENT",
                severity="REVIEW_REQUIRED",
                message=f"Ditemukan {tracked_change_count} jejak revisi (Track Changes) yang belum diterima/ditolak pada dokumen asli.",
                location="body",
            )
        )

    if comment_count > 0:
        warnings.append(
            WarningItem(
                code="DOCUMENT_COMMENTS_PRESENT",
                severity="WARNING",
                message=f"Ditemukan {comment_count} komentar peninjau pada dokumen asli.",
                location="body",
            )
        )

    has_existing_header = any(
        any(p.text.strip() for p in sec.header.paragraphs) or len(sec.header.tables) > 0
        for sec in doc.sections
    )
    if has_existing_header:
        warnings.append(
            WarningItem(
                code="EXISTING_REPEATING_HEADER",
                severity="WARNING",
                message="Dokumen asli memiliki header berulang bawaan Word. Periksa agar tidak bertumpuk dengan kop halaman pertama.",
                location="header",
            )
        )

    metrics = DocumentMetrics(
        paragraph_count=paragraph_count,
        non_empty_paragraph_count=non_empty_paragraph_count,
        table_count=table_count,
        section_count=section_count,
        image_count=image_count,
        equation_count=equation_count,
        textbox_count=textbox_count,
        embedded_object_count=embedded_object_count,
        tracked_change_count=tracked_change_count,
        comment_count=comment_count,
        non_empty_texts=non_empty_texts,
    )

    return metrics, warnings