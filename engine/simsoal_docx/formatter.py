from __future__ import annotations

import hashlib
import io
import os
import platform
import zipfile
from pathlib import Path
from typing import Any

import docx
from docx import Document
from docx.document import Document as DocxDocument
from docx.enum.section import WD_ORIENT
from docx.enum.table import WD_ALIGN_VERTICAL, WD_TABLE_ALIGNMENT
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.shared import Mm, Pt
from docx.text.paragraph import Paragraph
from docx.text.run import Run

from simsoal_docx import ENGINE_NAME, __version__
from simsoal_docx.inspector import DocumentMetrics, inspect_document
from simsoal_docx.models import ProcessingConfig, ProcessingReport, WarningItem


def compute_sha256(file_path: Path) -> str:
    hasher = hashlib.sha256()
    with file_path.open("rb") as f:
        for chunk in iter(lambda: f.read(65536), b""):
            hasher.update(chunk)
    return hasher.hexdigest()


def _apply_font_to_run(run: Run, font_family: str, font_size_pt: float) -> None:
    run.font.name = font_family
    run.font.size = Pt(font_size_pt)

    r_pr = run._r.get_or_add_rPr()
    r_fonts = r_pr.get_or_add_rFonts()
    r_fonts.set(qn("w:ascii"), font_family)
    r_fonts.set(qn("w:hAnsi"), font_family)
    r_fonts.set(qn("w:cs"), font_family)
    r_fonts.set(qn("w:eastAsia"), font_family)


def _apply_paragraph_typography(
    paragraph: Paragraph,
    config: ProcessingConfig,
    is_inserted_header: bool = False,
) -> None:
    pf = paragraph.paragraph_format
    pf.line_spacing = config.line_spacing

    if not is_inserted_header:
        if pf.space_before is None:
            pf.space_before = Pt(config.paragraph_spacing_before_pt)
        if pf.space_after is None:
            pf.space_after = Pt(config.paragraph_spacing_after_pt)

    for run in paragraph.runs:
        _apply_font_to_run(run, config.font_family, config.font_size_pt)


def _set_cell_borders(cell: Any, top: str = "none", bottom: str = "none", left: str = "none", right: str = "none", sz: str = "6") -> None:
    tc_pr = cell._tc.get_or_add_tcPr()
    tc_borders = OxmlElement("w:tcBorders")
    for side, val in (("top", top), ("bottom", bottom), ("left", left), ("right", right)):
        el = OxmlElement(f"w:{side}")
        el.set(qn("w:val"), val)
        if val != "none":
            el.set(qn("w:sz"), sz)
            el.set(qn("w:space"), "0")
            el.set(qn("w:color"), "000000")
        tc_borders.append(el)
    tc_pr.append(tc_borders)


def _set_table_borders(table: Any, val: str = "single", sz: str = "4") -> None:
    tbl_pr = table._tbl.tblPr
    tbl_borders = OxmlElement("w:tblBorders")
    for side in ("top", "bottom", "left", "right", "insideH", "insideV"):
        el = OxmlElement(f"w:{side}")
        el.set(qn("w:val"), val)
        if val != "none":
            el.set(qn("w:sz"), sz)
            el.set(qn("w:space"), "0")
            el.set(qn("w:color"), "000000")
        tbl_borders.append(el)
    tbl_pr.append(tbl_borders)


def _set_cell_margins(cell: Any, top_mm: float = 1.5, bottom_mm: float = 1.5, left_mm: float = 2.5, right_mm: float = 2.5) -> None:
    tc_pr = cell._tc.get_or_add_tcPr()
    tc_mar = OxmlElement("w:tcMar")
    for side, mm_val in (("top", top_mm), ("bottom", bottom_mm), ("left", left_mm), ("right", right_mm)):
        el = OxmlElement(f"w:{side}")
        el.set(qn("w:w"), str(int(round(mm_val * 56.7))))
        el.set(qn("w:type"), "dxa")
        tc_mar.append(el)
    tc_pr.append(tc_mar)


def _configure_page_setup(doc: DocxDocument, config: ProcessingConfig) -> None:
    normal_style = doc.styles["Normal"]
    normal_style.font.name = config.font_family
    normal_style.font.size = Pt(config.font_size_pt)
    r_pr = normal_style.element.get_or_add_rPr()
    r_fonts = r_pr.get_or_add_rFonts()
    r_fonts.set(qn("w:ascii"), config.font_family)
    r_fonts.set(qn("w:hAnsi"), config.font_family)
    r_fonts.set(qn("w:cs"), config.font_family)
    r_fonts.set(qn("w:eastAsia"), config.font_family)

    width_mm = config.paper_width_mm
    height_mm = config.paper_height_mm
    if config.orientation == "LANDSCAPE" and width_mm < height_mm:
        width_mm, height_mm = height_mm, width_mm
    elif config.orientation != "LANDSCAPE" and width_mm > height_mm:
        width_mm, height_mm = height_mm, width_mm

    for section in doc.sections:
        section.orientation = WD_ORIENT.LANDSCAPE if config.orientation == "LANDSCAPE" else WD_ORIENT.PORTRAIT
        section.page_width = Mm(width_mm)
        section.page_height = Mm(height_mm)
        section.top_margin = Mm(config.margin_top_mm)
        section.bottom_margin = Mm(config.margin_bottom_mm)
        section.left_margin = Mm(config.margin_left_mm)
        section.right_margin = Mm(config.margin_right_mm)
        section.different_first_page_header_footer = True


def _insert_standard_school_header(
    doc: DocxDocument,
    anchor: Any,
    config: ProcessingConfig,
    warnings: list[WarningItem],
) -> None:
    """Menyusun kop sekolah + identitas ujian hanya pada bagian awal halaman pertama (D-016)."""
    school = config.school
    document_meta = config.document

    printable_width_mm = max(
        100.0,
        config.paper_width_mm - config.margin_left_mm - config.margin_right_mm,
    )

    school_name = (school.get("name") or "SEKOLAH MENENGAH KEJURUAN").strip()
    foundation_name = (school.get("foundation_name") or "").strip()
    npsn = (school.get("npsn") or "").strip()
    address = (school.get("address") or "").strip()
    phone = (school.get("phone") or "").strip()
    email = (school.get("email") or "").strip()
    website = (school.get("website") or "").strip()

    has_logo = False
    logo_path: Path | None = None
    if config.show_school_logo and config.logo_abs_path:
        candidate = Path(config.logo_abs_path)
        if candidate.is_file():
            logo_path = candidate
            has_logo = True
        else:
            warnings.append(
                WarningItem(
                    code="SCHOOL_LOGO_MISSING",
                    severity="WARNING",
                    message="File logo sekolah tidak ditemukan di penyimpanan privat; kop disusun tanpa logo.",
                    location="first_page_header",
                )
            )

    # 1. Tabel Kop Surat Sekolah (Halaman 1)
    kop_table = doc.add_table(rows=1, cols=2 if has_logo else 1)
    kop_table.alignment = WD_TABLE_ALIGNMENT.CENTER
    kop_table.autofit = False
    _set_table_borders(kop_table, val="none")

    if has_logo and logo_path is not None:
        logo_col_mm = 26.0
        text_col_mm = printable_width_mm - logo_col_mm
        left_cell = kop_table.cell(0, 0)
        right_cell = kop_table.cell(0, 1)
        left_cell.width = Mm(logo_col_mm)
        right_cell.width = Mm(text_col_mm)
        left_cell.vertical_alignment = WD_ALIGN_VERTICAL.CENTER
        right_cell.vertical_alignment = WD_ALIGN_VERTICAL.CENTER
        _set_cell_margins(left_cell, top_mm=1.0, bottom_mm=2.0, left_mm=0.0, right_mm=2.0)
        _set_cell_margins(right_cell, top_mm=1.0, bottom_mm=2.0, left_mm=1.0, right_mm=0.0)
        _set_cell_borders(left_cell, bottom="double", sz="12")
        _set_cell_borders(right_cell, bottom="double", sz="12")

        logo_p = left_cell.paragraphs[0]
        logo_p.alignment = WD_ALIGN_PARAGRAPH.CENTER
        logo_p.paragraph_format.space_before = Pt(0)
        logo_p.paragraph_format.space_after = Pt(0)
        logo_run = logo_p.add_run()
        try:
            logo_run.add_picture(str(logo_path), height=Mm(21.0))
        except Exception:
            warnings.append(
                WarningItem(
                    code="SCHOOL_LOGO_UNREADABLE",
                    severity="WARNING",
                    message="Format gambar logo sekolah tidak dapat disisipkan ke DOCX; kop disusun tanpa logo.",
                    location="first_page_header",
                )
            )

        text_cell = right_cell
    else:
        text_cell = kop_table.cell(0, 0)
        text_cell.width = Mm(printable_width_mm)
        text_cell.vertical_alignment = WD_ALIGN_VERTICAL.CENTER
        _set_cell_margins(text_cell, top_mm=1.0, bottom_mm=2.0, left_mm=0.0, right_mm=0.0)
        _set_cell_borders(text_cell, bottom="double", sz="12")

    p_idx = 0
    if foundation_name:
        p_found = text_cell.paragraphs[0]
        p_found.alignment = WD_ALIGN_PARAGRAPH.CENTER
        p_found.paragraph_format.space_before = Pt(0)
        p_found.paragraph_format.space_after = Pt(1)
        p_found.paragraph_format.line_spacing = 1.0
        r_found = p_found.add_run(foundation_name.upper())
        r_found.bold = True
        _apply_font_to_run(r_found, config.font_family, max(10.0, config.font_size_pt - 1.0))
        p_idx += 1

    p_school = text_cell.paragraphs[0] if p_idx == 0 else text_cell.add_paragraph()
    p_school.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p_school.paragraph_format.space_before = Pt(0)
    p_school.paragraph_format.space_after = Pt(1)
    p_school.paragraph_format.line_spacing = 1.0
    r_school = p_school.add_run(school_name.upper())
    r_school.bold = True
    _apply_font_to_run(r_school, config.font_family, config.font_size_pt + 2.0)

    detail_parts: list[str] = []
    if npsn:
        detail_parts.append(f"NPSN: {npsn}")
    if address:
        detail_parts.append(address)
    if detail_parts:
        p_addr = text_cell.add_paragraph()
        p_addr.alignment = WD_ALIGN_PARAGRAPH.CENTER
        p_addr.paragraph_format.space_before = Pt(0)
        p_addr.paragraph_format.space_after = Pt(1)
        p_addr.paragraph_format.line_spacing = 1.0
        r_addr = p_addr.add_run(" | ".join(detail_parts))
        _apply_font_to_run(r_addr, config.font_family, max(9.0, config.font_size_pt - 2.5))

    contact_parts: list[str] = []
    if phone:
        contact_parts.append(f"Telp. {phone}")
    if email:
        contact_parts.append(f"Email: {email}")
    if website:
        contact_parts.append(f"Web: {website}")
    if contact_parts:
        p_contact = text_cell.add_paragraph()
        p_contact.alignment = WD_ALIGN_PARAGRAPH.CENTER
        p_contact.paragraph_format.space_before = Pt(0)
        p_contact.paragraph_format.space_after = Pt(0)
        p_contact.paragraph_format.line_spacing = 1.0
        r_contact = p_contact.add_run(" • ".join(contact_parts))
        _apply_font_to_run(r_contact, config.font_family, max(9.0, config.font_size_pt - 2.5))

    anchor.addprevious(kop_table._tbl)

    # 2. Judul & Subjudul Ujian
    exam_title = (
        (config.header_title or "").strip()
        or (document_meta.get("period_name") or "").strip()
        or "NASKAH SOAL UJIAN"
    )
    academic_year = (document_meta.get("academic_year") or "").strip()
    exam_subtitle = (config.header_subtitle or "").strip() or (
        f"TAHUN PELAJARAN {academic_year}" if academic_year else ""
    )

    p_title = doc.add_paragraph()
    p_title.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p_title.paragraph_format.space_before = Pt(6)
    p_title.paragraph_format.space_after = Pt(1 if exam_subtitle else 4)
    p_title.paragraph_format.line_spacing = 1.0
    r_title = p_title.add_run(exam_title.upper())
    r_title.bold = True
    _apply_font_to_run(r_title, config.font_family, config.font_size_pt)
    anchor.addprevious(p_title._p)

    if exam_subtitle:
        p_sub = doc.add_paragraph()
        p_sub.alignment = WD_ALIGN_PARAGRAPH.CENTER
        p_sub.paragraph_format.space_before = Pt(0)
        p_sub.paragraph_format.space_after = Pt(4)
        p_sub.paragraph_format.line_spacing = 1.0
        r_sub = p_sub.add_run(exam_subtitle.upper())
        r_sub.bold = True
        _apply_font_to_run(r_sub, config.font_family, config.font_size_pt)
        anchor.addprevious(p_sub._p)

    # 3. Tabel Identitas Ujian
    if config.show_exam_identity_table:
        subject_name = str(document_meta.get("subject_name") or "-")
        class_name = str(document_meta.get("class_name") or "-")
        teacher_name = str(document_meta.get("teacher_name") or "-")
        exam_date = str(document_meta.get("exam_date") or "-")
        duration = document_meta.get("duration_minutes")
        duration_text = f"{duration} Menit" if duration else "-"

        meta_rows = [
            ("Mata Pelajaran", subject_name, "Hari / Tanggal", exam_date),
            ("Kelas / Tingkat", class_name, "Alokasi Waktu", duration_text),
            ("Guru Pengampu", teacher_name, "Kurikulum / Kode", str(document_meta.get("subject_code") or "-")),
        ]

        id_table = doc.add_table(rows=len(meta_rows), cols=4)
        id_table.alignment = WD_TABLE_ALIGNMENT.CENTER
        id_table.autofit = False
        _set_table_borders(id_table, val="single", sz="4")

        col_widths = [
            printable_width_mm * 0.18,
            printable_width_mm * 0.34,
            printable_width_mm * 0.18,
            printable_width_mm * 0.30,
        ]

        for r_idx, (lbl1, val1, lbl2, val2) in enumerate(meta_rows):
            row_values = [(lbl1, True), (f": {val1}", False), (lbl2, True), (f": {val2}", False)]
            for c_idx, (text_val, is_bold) in enumerate(row_values):
                cell = id_table.cell(r_idx, c_idx)
                cell.width = Mm(col_widths[c_idx])
                cell.vertical_alignment = WD_ALIGN_VERTICAL.CENTER
                _set_cell_margins(cell, top_mm=1.0, bottom_mm=1.0, left_mm=2.0, right_mm=2.0)
                cp = cell.paragraphs[0]
                cp.paragraph_format.space_before = Pt(0)
                cp.paragraph_format.space_after = Pt(0)
                cp.paragraph_format.line_spacing = 1.0
                crun = cp.add_run(text_val)
                crun.bold = is_bold
                _apply_font_to_run(crun, config.font_family, max(10.0, config.font_size_pt - 1.0))

        anchor.addprevious(id_table._tbl)

    # 4. Petunjuk Umum
    instruction = (config.instruction_text or "").strip()
    if instruction:
        p_inst = doc.add_paragraph()
        p_inst.paragraph_format.space_before = Pt(4)
        p_inst.paragraph_format.space_after = Pt(6)
        p_inst.paragraph_format.line_spacing = 1.0
        r_inst_lbl = p_inst.add_run("Petunjuk Umum: ")
        r_inst_lbl.bold = True
        r_inst_lbl.italic = True
        _apply_font_to_run(r_inst_lbl, config.font_family, max(10.0, config.font_size_pt - 1.0))

        r_inst_body = p_inst.add_run(instruction)
        r_inst_body.italic = True
        _apply_font_to_run(r_inst_body, config.font_family, max(10.0, config.font_size_pt - 1.0))
        anchor.addprevious(p_inst._p)
    else:
        p_space = doc.add_paragraph()
        p_space.paragraph_format.space_before = Pt(0)
        p_space.paragraph_format.space_after = Pt(4)
        anchor.addprevious(p_space._p)


def _clone_paragraph_to_doc(
    target_doc: DocxDocument,
    source_para: Paragraph,
    source_doc: DocxDocument,
    anchor: Any,
    config: ProcessingConfig,
) -> None:
    new_p = target_doc.add_paragraph()
    new_p.alignment = source_para.alignment
    new_p.paragraph_format.space_before = source_para.paragraph_format.space_before or Pt(0)
    new_p.paragraph_format.space_after = source_para.paragraph_format.space_after or Pt(2)
    new_p.paragraph_format.line_spacing = source_para.paragraph_format.line_spacing or config.line_spacing

    for run in source_para.runs:
        blips = run._r.xpath(".//a:blip")
        if blips:
            for blip in blips:
                r_embed = blip.get(qn("r:embed"))
                if r_embed and r_embed in source_doc.part.related_parts:
                    img_part = source_doc.part.related_parts[r_embed]
                    img_run = new_p.add_run()
                    img_run.add_picture(io.BytesIO(img_part.blob), height=Mm(20.0))
            continue

        new_run = new_p.add_run(run.text)
        new_run.bold = run.bold
        new_run.italic = run.italic
        new_run.underline = run.underline
        new_run.font.superscript = run.font.superscript
        new_run.font.subscript = run.font.subscript
        font_name = run.font.name or config.font_family
        font_size = run.font.size.pt if run.font.size else config.font_size_pt
        _apply_font_to_run(new_run, font_name, font_size)

    anchor.addprevious(new_p._p)


def _insert_custom_docx_header(
    doc: DocxDocument,
    anchor: Any,
    config: ProcessingConfig,
    warnings: list[WarningItem],
) -> None:
    """Menyalin isi kop khusus (.docx) ke bagian atas halaman pertama secara aman."""
    if not config.template_abs_path:
        warnings.append(
            WarningItem(
                code="CUSTOM_TEMPLATE_PATH_MISSING",
                severity="REVIEW_REQUIRED",
                message="Path file template kop khusus tidak tersedia; kop tidak disisipkan.",
                location="first_page_header",
            )
        )
        return

    tpl_path = Path(config.template_abs_path)
    if not tpl_path.is_file():
        warnings.append(
            WarningItem(
                code="CUSTOM_TEMPLATE_NOT_FOUND",
                severity="REVIEW_REQUIRED",
                message="File template kop khusus (.docx) tidak ditemukan di penyimpanan privat.",
                location="first_page_header",
            )
        )
        return

    tpl_doc = Document(str(tpl_path))
    body_children = list(tpl_doc.element.body)
    para_iter = iter(tpl_doc.paragraphs)
    table_iter = iter(tpl_doc.tables)

    for child in body_children:
        tag = child.tag
        if tag.endswith("}p"):
            src_p = next(para_iter, None)
            if src_p is not None:
                _clone_paragraph_to_doc(doc, src_p, tpl_doc, anchor, config)
        elif tag.endswith("}tbl"):
            src_tbl = next(table_iter, None)
            if src_tbl is not None:
                rows_cnt = len(src_tbl.rows)
                cols_cnt = len(src_tbl.columns)
                new_tbl = doc.add_table(rows=rows_cnt, cols=cols_cnt)
                new_tbl.alignment = src_tbl.alignment or WD_TABLE_ALIGNMENT.CENTER
                new_tbl.autofit = src_tbl.autofit

                for r_i, row in enumerate(src_tbl.rows):
                    for c_i, cell in enumerate(row.cells):
                        dst_cell = new_tbl.cell(r_i, c_i)
                        dst_cell.vertical_alignment = cell.vertical_alignment
                        if cell.width:
                            dst_cell.width = cell.width
                        for p_i, cp in enumerate(cell.paragraphs):
                            dst_p = dst_cell.paragraphs[0] if p_i == 0 else dst_cell.add_paragraph()
                            dst_p.alignment = cp.alignment
                            dst_p.paragraph_format.space_before = cp.paragraph_format.space_before or Pt(0)
                            dst_p.paragraph_format.space_after = cp.paragraph_format.space_after or Pt(1)
                            dst_p.paragraph_format.line_spacing = cp.paragraph_format.line_spacing or 1.0
                            for cr in cp.runs:
                                blips = cr._r.xpath(".//a:blip")
                                if blips:
                                    for blip in blips:
                                        r_embed = blip.get(qn("r:embed"))
                                        if r_embed and r_embed in tpl_doc.part.related_parts:
                                            img_part = tpl_doc.part.related_parts[r_embed]
                                            img_run = dst_p.add_run()
                                            img_run.add_picture(io.BytesIO(img_part.blob), height=Mm(20.0))
                                    continue
                                new_cr = dst_p.add_run(cr.text)
                                new_cr.bold = cr.bold
                                new_cr.italic = cr.italic
                                new_cr.underline = cr.underline
                                font_name = cr.font.name or config.font_family
                                font_size = cr.font.size.pt if cr.font.size else config.font_size_pt
                                _apply_font_to_run(new_cr, font_name, font_size)

                anchor.addprevious(new_tbl._tbl)

    p_spacer = doc.add_paragraph()
    p_spacer.paragraph_format.space_before = Pt(0)
    p_spacer.paragraph_format.space_after = Pt(4)
    anchor.addprevious(p_spacer._p)


def _validate_output_docx(
    output_path: Path,
    original_metrics: DocumentMetrics,
) -> tuple[bool, str | None, dict[str, Any]]:
    if not output_path.is_file() or output_path.stat().st_size <= 0:
        return False, "File output .docx tidak terbentuk atau berukuran 0 byte.", {}

    if not zipfile.is_zipfile(output_path):
        return False, "File output bukan arsip ZIP/OOXML yang valid.", {}

    with zipfile.ZipFile(output_path, "r") as zf:
        names = set(zf.namelist())
        for req in ("[Content_Types].xml", "_rels/.rels", "word/document.xml"):
            if req not in names:
                return False, f"Struktur OOXML output tidak lengkap ({req} hilang).", {}

    reopened = Document(str(output_path))
    out_metrics, _ = inspect_document(reopened)

    # Verifikasi invariant: seluruh teks soal non-kosong asli tetap ada secara berurutan
    out_texts = out_metrics.non_empty_texts
    search_cursor = 0
    for expected_text in original_metrics.non_empty_texts:
        found = False
        while search_cursor < len(out_texts):
            if expected_text in out_texts[search_cursor]:
                found = True
                search_cursor += 1
                break
            search_cursor += 1
        if not found:
            return (
                False,
                "Validasi keutuhan konten gagal: sebagian teks naskah asli tidak ditemukan pada hasil output.",
                {},
            )

    if out_metrics.table_count < original_metrics.table_count:
        return False, "Validasi keutuhan konten gagal: jumlah tabel berkurang dari dokumen asli.", {}

    if out_metrics.equation_count != original_metrics.equation_count:
        return False, "Validasi keutuhan konten gagal: jumlah persamaan matematika (OMML) berubah.", {}

    if out_metrics.image_count < original_metrics.image_count:
        return False, "Validasi keutuhan konten gagal: jumlah gambar berkurang dari dokumen asli.", {}

    validation_stats = {
        "output_paragraph_count": out_metrics.paragraph_count,
        "output_table_count": out_metrics.table_count,
        "output_image_count": out_metrics.image_count,
        "output_equation_count": out_metrics.equation_count,
    }
    return True, None, validation_stats


def standardize_docx(
    input_path: Path,
    output_path: Path,
    config: ProcessingConfig,
) -> ProcessingReport:
    """Menstandarisasi dokumen .docx ke path output baru tanpa mengubah dokumen asli."""
    base_report = ProcessingReport(
        status="FAILED",
        engine_name=ENGINE_NAME,
        engine_version=__version__,
        python_version=platform.python_version(),
        docx_library_version=docx.__version__,
    )

    if not input_path.is_file():
        base_report.error_message = f"File input tidak ditemukan: {input_path.name}"
        return base_report

    if os.path.abspath(str(input_path)) == os.path.abspath(str(output_path)):
        base_report.error_message = "Path output tidak boleh sama dengan file sumber asli."
        return base_report

    input_checksum = compute_sha256(input_path)
    base_report.input_checksum = input_checksum

    try:
        doc = Document(str(input_path))
    except Exception as exc:
        base_report.error_message = f"Gagal membuka dokumen Word (.docx): {exc}"
        return base_report

    original_metrics, warnings = inspect_document(doc)

    # 1. Standarisasi ukuran kertas F4 & margin halaman
    _configure_page_setup(doc, config)

    # 2. Standarisasi tipografi seluruh paragraf naskah asli tanpa mengubah isi
    for paragraph in doc.paragraphs:
        _apply_paragraph_typography(paragraph, config, is_inserted_header=False)

    if config.apply_to_existing_tables:
        for table in doc.tables:
            for row in table.rows:
                for cell in row.cells:
                    for cp in cell.paragraphs:
                        _apply_paragraph_typography(cp, config, is_inserted_header=False)

    # 3. Sisipkan kop hanya pada halaman pertama (Keputusan D-016)
    body = doc.element.body
    anchor = body[0] if len(body) > 0 else body.sectPr

    if config.header_mode == "STANDARD_SCHOOL":
        _insert_standard_school_header(doc, anchor, config, warnings)
    elif config.header_mode == "CUSTOM_DOCX":
        _insert_custom_docx_header(doc, anchor, config, warnings)
    elif config.header_mode == "NONE":
        pass
    else:
        warnings.append(
            WarningItem(
                code="UNKNOWN_HEADER_MODE",
                severity="WARNING",
                message=f"Mode kop '{config.header_mode}' tidak dikenali; penyisipan kop dilewati.",
                location="first_page_header",
            )
        )

    # 4. Simpan ke path output baru
    output_path.parent.mkdir(parents=True, exist_ok=True)
    doc.save(str(output_path))

    # 5. Validasi pasca-simpan
    is_valid, val_error, out_stats = _validate_output_docx(output_path, original_metrics)
    if not is_valid:
        base_report.error_message = val_error
        base_report.warnings = warnings
        return base_report

    # Pastikan file asli benar-benar tidak tersentuh
    if compute_sha256(input_path) != input_checksum:
        base_report.error_message = "Pelanggaran integritas: checksum file sumber berubah."
        return base_report

    output_checksum = compute_sha256(output_path)
    needs_manual_review = any(w.severity == "REVIEW_REQUIRED" for w in warnings)

    metrics_summary: dict[str, Any] = {
        "input_paragraph_count": original_metrics.paragraph_count,
        "input_non_empty_paragraph_count": original_metrics.non_empty_paragraph_count,
        "input_table_count": original_metrics.table_count,
        "input_image_count": original_metrics.image_count,
        "input_equation_count": original_metrics.equation_count,
        "section_count": original_metrics.section_count,
        "textbox_count": original_metrics.textbox_count,
        "embedded_object_count": original_metrics.embedded_object_count,
        "tracked_change_count": original_metrics.tracked_change_count,
        "comment_count": original_metrics.comment_count,
        "paper_code": config.paper_code,
        "paper_width_mm": config.paper_width_mm,
        "paper_height_mm": config.paper_height_mm,
        "font_family": config.font_family,
        "font_size_pt": config.font_size_pt,
        "header_mode": config.header_mode,
        "first_page_only": True,
        **out_stats,
    }

    status = "COMPLETED_WITH_WARNINGS" if len(warnings) > 0 else "COMPLETED"

    return ProcessingReport(
        status=status,
        engine_name=ENGINE_NAME,
        engine_version=__version__,
        python_version=platform.python_version(),
        docx_library_version=docx.__version__,
        input_checksum=input_checksum,
        output_checksum=output_checksum,
        needs_manual_review=needs_manual_review,
        metrics=metrics_summary,
        warnings=warnings,
        error_message=None,
    )