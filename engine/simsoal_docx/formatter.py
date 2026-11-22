from __future__ import annotations

import hashlib
import io
import os
import platform
import re
import zipfile
from datetime import date
from pathlib import Path
from typing import Any

import docx
from docx import Document
from docx.document import Document as DocxDocument
from docx.enum.section import WD_ORIENT
from docx.enum.table import WD_ALIGN_VERTICAL, WD_TABLE_ALIGNMENT
from docx.enum.text import WD_ALIGN_PARAGRAPH, WD_TAB_ALIGNMENT
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.shared import Mm, Pt
from docx.text.paragraph import Paragraph
from docx.text.run import Run

from simsoal_docx import ENGINE_NAME, __version__
from simsoal_docx.inspector import DocumentMetrics, inspect_document
from simsoal_docx.models import ProcessingConfig, ProcessingReport, WarningItem

_ARABIC_SCRIPT_RE = re.compile(r"[\u0600-\u06FF\u0750-\u077F\u08A0-\u08FF\uFB50-\uFDFF\uFE70-\uFEFF]")
_LATIN_LETTER_RE = re.compile(r"[A-Za-z]")
_ROMAN_SECTION_RE = re.compile(r"^(I|II|III|IV|V|VI)\.\s+\S")
_NUMBERED_LINE_RE = re.compile(r"^(\d{1,3})[\.\)]\s+\S")
_OPTION_LINE_RE = re.compile(r"^([A-Ea-e])[\.\)]\s+\S")
_TAB_OPTION_SPLIT_RE = re.compile(r"(?:^|\t+)\s*[A-Ea-e][\.\)]\s+")
_PASSAGE_INTRO_RE = re.compile(
    r"^(?:read\s+the\s+(?:text|passage|dialogue|dialog|story|letter|notice|announcement)|"
    r"bacalah\s+(?:teks|bacaan|dialog|percakapan|cerita|kutipan)|"
    r"perhatikan\s+(?:teks|bacaan|dialog|percakapan|kutipan)|"
    r"teks\s+berikut\s+untuk\s+(?:menjawab\s+)?soal)",
    re.IGNORECASE,
)
_ESSAY_KEYWORD_RE = re.compile(
    r"(?:essay|uraian|isian|jawablah\s+pertanyaan)",
    re.IGNORECASE,
)


def _mm_to_dxa(mm_val: float) -> int:
    return int(round(mm_val * 56.6929))


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
    r_fonts.set(qn("w:eastAsia"), font_family)

    # Pertahankan font skrip Arab bawaan pada w:cs jika run berisi huruf Arab
    if run.text and _ARABIC_SCRIPT_RE.search(run.text):
        existing_cs = r_fonts.get(qn("w:cs"))
        if not existing_cs:
            r_fonts.set(qn("w:cs"), font_family)
    else:
        r_fonts.set(qn("w:cs"), font_family)


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


def _set_cell_borders(
    cell: Any,
    top: str = "none",
    bottom: str = "none",
    left: str = "none",
    right: str = "none",
    sz: str = "6",
) -> None:
    tc_pr = cell._tc.get_or_add_tcPr()
    for existing in tc_pr.findall(qn("w:tcBorders")):
        tc_pr.remove(existing)

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
    for existing in tbl_pr.findall(qn("w:tblBorders")):
        tbl_pr.remove(existing)

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


def _set_fixed_column_widths(table: Any, col_widths_mm: list[float]) -> None:
    """Mengunci lebar tabel dan setiap kolom di OOXML agar dipatuhi Word maupun LibreOffice."""
    total_dxa = sum(_mm_to_dxa(w) for w in col_widths_mm)
    tbl_pr = table._tbl.tblPr

    for tag_name in ("w:tblW", "w:tblLayout"):
        for existing in tbl_pr.findall(qn(tag_name)):
            tbl_pr.remove(existing)

    tbl_w = OxmlElement("w:tblW")
    tbl_w.set(qn("w:w"), str(total_dxa))
    tbl_w.set(qn("w:type"), "dxa")
    tbl_pr.append(tbl_w)

    tbl_layout = OxmlElement("w:tblLayout")
    tbl_layout.set(qn("w:type"), "fixed")
    tbl_pr.append(tbl_layout)

    old_grid = table._tbl.find(qn("w:tblGrid"))
    if old_grid is not None:
        table._tbl.remove(old_grid)

    new_grid = OxmlElement("w:tblGrid")
    for w_mm in col_widths_mm:
        grid_col = OxmlElement("w:gridCol")
        grid_col.set(qn("w:w"), str(_mm_to_dxa(w_mm)))
        new_grid.append(grid_col)
    tbl_pr.addnext(new_grid)

    for row in table.rows:
        for c_idx, w_mm in enumerate(col_widths_mm):
            cell = row.cells[c_idx]
            tc_pr = cell._tc.get_or_add_tcPr()
            for existing in tc_pr.findall(qn("w:tcW")):
                tc_pr.remove(existing)
            tc_w = OxmlElement("w:tcW")
            tc_w.set(qn("w:w"), str(_mm_to_dxa(w_mm)))
            tc_w.set(qn("w:type"), "dxa")
            tc_pr.append(tc_w)


def _set_cell_margins(
    cell: Any,
    top_mm: float = 1.5,
    bottom_mm: float = 1.5,
    left_mm: float = 2.5,
    right_mm: float = 2.5,
) -> None:
    tc_pr = cell._tc.get_or_add_tcPr()
    for existing in tc_pr.findall(qn("w:tcMar")):
        tc_pr.remove(existing)

    tc_mar = OxmlElement("w:tcMar")
    for side, mm_val in (("top", top_mm), ("bottom", bottom_mm), ("left", left_mm), ("right", right_mm)):
        el = OxmlElement(f"w:{side}")
        el.set(qn("w:w"), str(_mm_to_dxa(mm_val)))
        el.set(qn("w:type"), "dxa")
        tc_mar.append(el)
    tc_pr.append(tc_mar)


def _normalize_section_columns(section: Any) -> bool:
    """Menormalkan section multi-kolom bawaan menjadi 1 kolom penuh agar tata letak tidak terjepit."""
    sect_pr = section._sectPr
    cols_el = sect_pr.find(qn("w:cols"))
    was_multi_col = False

    if cols_el is not None:
        num_attr = cols_el.get(qn("w:num"))
        if num_attr and num_attr.isdigit() and int(num_attr) > 1:
            was_multi_col = True
        for child in list(cols_el):
            cols_el.remove(child)
        for attr_name in list(cols_el.attrib):
            del cols_el.attrib[attr_name]
        cols_el.set(qn("w:num"), "1")
        cols_el.set(qn("w:space"), "720")
    else:
        new_cols = OxmlElement("w:cols")
        new_cols.set(qn("w:num"), "1")
        new_cols.set(qn("w:space"), "720")
        sect_pr.append(new_cols)

    return was_multi_col


def _configure_page_setup(
    doc: DocxDocument,
    config: ProcessingConfig,
    warnings: list[WarningItem],
) -> None:
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

    footer_dist_mm = min(10.0, max(6.0, config.margin_bottom_mm * 0.5))
    normalized_multi_col = False

    for section in doc.sections:
        section.orientation = WD_ORIENT.LANDSCAPE if config.orientation == "LANDSCAPE" else WD_ORIENT.PORTRAIT
        section.page_width = Mm(width_mm)
        section.page_height = Mm(height_mm)
        section.top_margin = Mm(config.margin_top_mm)
        section.bottom_margin = Mm(config.margin_bottom_mm)
        section.left_margin = Mm(config.margin_left_mm)
        section.right_margin = Mm(config.margin_right_mm)
        section.footer_distance = Mm(footer_dist_mm)
        section.different_first_page_header_footer = True
        if _normalize_section_columns(section):
            normalized_multi_col = True

    if normalized_multi_col:
        warnings.append(
            WarningItem(
                code="MULTI_COLUMN_LAYOUT_NORMALIZED",
                severity="INFO",
                message="Tata letak multi-kolom pada dokumen asli dinormalkan menjadi 1 kolom penuh agar rapi pada kertas standar.",
                location="section_layout",
            )
        )


def _format_display_date(raw_value: Any) -> str:
    """Memastikan tanggal tampil dalam format Indonesia tanpa string ISO mentah."""
    if raw_value is None:
        return ""
    text = str(raw_value).strip()
    if not text or text == "-":
        return ""

    iso_match = re.match(r"^(\d{4})-(\d{2})-(\d{2})(?:[T\s].*)?$", text)
    if iso_match:
        year, month, day = int(iso_match.group(1)), int(iso_match.group(2)), int(iso_match.group(3))
        try:
            dt = date(year, month, day)
            days = ["Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu", "Minggu"]
            months = [
                "",
                "Januari",
                "Februari",
                "Maret",
                "April",
                "Mei",
                "Juni",
                "Juli",
                "Agustus",
                "September",
                "Oktober",
                "November",
                "Desember",
            ]
            return f"{days[dt.weekday()]}, {day:02d} {months[month]} {year}"
        except ValueError:
            return text

    return text


def _split_instruction_lines(raw_instruction: str) -> list[str]:
    """Memecah teks petunjuk menjadi baris-baris rapi seperti dokumen referensi."""
    cleaned = raw_instruction.replace("\r\n", "\n").replace("\r", "\n").strip()
    if not cleaned:
        return []

    if "\n" in cleaned:
        return [line.strip() for line in cleaned.split("\n") if line.strip()]

    parts = [p.strip().rstrip(".") for p in re.split(r"\.\s+", cleaned) if p.strip()]
    return parts if len(parts) > 1 else [cleaned]


def _set_paragraph_bidi(paragraph: Paragraph, enabled: bool = True) -> None:
    p_pr = paragraph._p.get_or_add_pPr()
    existing = p_pr.find(qn("w:bidi"))
    if enabled:
        if existing is None:
            p_pr.append(OxmlElement("w:bidi"))
    elif existing is not None:
        p_pr.remove(existing)


def _classify_exam_paragraphs(paragraphs: list[Paragraph]) -> list[str]:
    """Mengklasifikasikan peran struktural tiap paragraf naskah tanpa mengubah teksnya."""
    roles: list[str] = []
    current_q = 0
    in_question_stem = False
    in_sub_list = False
    in_essay_section = False
    after_passage_intro = False

    for para in paragraphs:
        text = para.text.strip()
        if not text:
            roles.append("EMPTY")
            continue

        # 1. Judul Bagian Romawi (I. ..., II. ...)
        if _ROMAN_SECTION_RE.match(text):
            if _ESSAY_KEYWORD_RE.search(text) or text.upper().startswith("II."):
                in_essay_section = True
            elif text.upper().startswith("I."):
                in_essay_section = False
            in_question_stem = False
            in_sub_list = False
            after_passage_intro = False
            roles.append("SECTION_ROMAN_HEADER")
            continue

        # 2. Instruksi Bacaan / Stimulus
        if _PASSAGE_INTRO_RE.match(text):
            in_question_stem = False
            in_sub_list = False
            after_passage_intro = True
            roles.append("PASSAGE_INSTRUCTION")
            continue

        # 3. Paragraf mengandung Skrip Arab (Ayat / Hadis)
        if _ARABIC_SCRIPT_RE.search(text):
            arabic_chars = len(_ARABIC_SCRIPT_RE.findall(text))
            latin_chars = len(_LATIN_LETTER_RE.findall(text))
            if arabic_chars >= latin_chars:
                roles.append("ARABIC_QUOTE")
                continue

        # 4. Opsi Pilihan Jawaban Sejajar Tab (A. ... \t B. ...)
        tab_opt_matches = _TAB_OPTION_SPLIT_RE.findall(text)
        if "\t" in text and len(tab_opt_matches) >= 2:
            in_question_stem = False
            in_sub_list = False
            after_passage_intro = False
            if len(tab_opt_matches) >= 3:
                roles.append("OPTION_TAB_4COL")
            else:
                roles.append("OPTION_TAB_2COL")
            continue

        # 5. Baris Opsi Tunggal (A. - E.) atau Sub-Pertanyaan Uraian (a. - e.)
        opt_match = _OPTION_LINE_RE.match(text)
        if opt_match:
            letter = opt_match.group(1)
            if in_essay_section and letter.islower():
                roles.append("SUB_ITEM")
                continue

            in_question_stem = False
            in_sub_list = False
            after_passage_intro = False
            roles.append("OPTION_ITEM")
            continue

        # 6. Baris Bernomor (Nomor Soal Utama vs Sub-Pernyataan di Dalam Soal)
        num_match = _NUMBERED_LINE_RE.match(text)
        if num_match:
            num_val = int(num_match.group(1))
            is_sub_statement = False

            if in_question_stem and current_q >= 1:
                if num_val == 1:
                    is_sub_statement = True
                    in_sub_list = True
                elif in_sub_list and num_val != current_q + 1 and num_val <= 10:
                    is_sub_statement = True

            if is_sub_statement:
                roles.append("SUB_ITEM")
                continue

            current_q = num_val
            in_question_stem = True
            in_sub_list = False
            after_passage_intro = False
            roles.append("QUESTION_STEM")
            continue

        # 7. Judul Bacaan singkat setelah instruksi bacaan
        if after_passage_intro and len(text) <= 85 and not text.endswith((".", "?", "!")):
            after_passage_intro = False
            roles.append("PASSAGE_TITLE")
            continue

        after_passage_intro = False
        if in_question_stem:
            roles.append("QUESTION_CONTINUATION")
        else:
            roles.append("PASSAGE_BODY")

    return roles


def _apply_exam_body_formatting(
    doc: DocxDocument,
    config: ProcessingConfig,
) -> None:
    """Merapikan struktur visual isi soal, bacaan, kutipan Arab, dan pilihan jawaban adaptif."""
    printable_width_mm = max(
        100.0,
        config.paper_width_mm - config.margin_left_mm - config.margin_right_mm,
    )

    body_paragraphs = list(doc.paragraphs)
    roles = _classify_exam_paragraphs(body_paragraphs)
    total = len(body_paragraphs)

    for idx, (para, role) in enumerate(zip(body_paragraphs, roles)):
        _apply_paragraph_typography(para, config, is_inserted_header=False)
        pf = para.paragraph_format

        if role == "EMPTY":
            pf.space_before = Pt(0)
            pf.space_after = Pt(2)
            continue

        # Jangan timpa perataan jika paragraf sengaja dibuat rata tengah (misal judul teks bacaan atau gambar)
        is_explicit_center = para.alignment == WD_ALIGN_PARAGRAPH.CENTER

        if role == "SECTION_ROMAN_HEADER":
            pf.left_indent = Mm(6.5)
            pf.first_line_indent = Mm(-6.5)
            pf.space_before = Pt(4 if idx == 0 else 8)
            pf.space_after = Pt(4)
            pf.keep_with_next = True
            for run in para.runs:
                if run.text.strip():
                    run.bold = True

        elif role == "QUESTION_STEM":
            pf.left_indent = Mm(6.5)
            pf.first_line_indent = Mm(-6.5)
            pf.space_before = Pt(3)
            pf.space_after = Pt(1.5)
            pf.keep_with_next = True

        elif role == "SUB_ITEM":
            pf.left_indent = Mm(11.5)
            pf.first_line_indent = Mm(-5.0)
            pf.space_before = Pt(0.5)
            pf.space_after = Pt(1.0)
            next_role = roles[idx + 1] if idx + 1 < total else "EMPTY"
            if next_role in ("SUB_ITEM", "QUESTION_CONTINUATION", "OPTION_ITEM", "OPTION_TAB_2COL", "OPTION_TAB_4COL"):
                pf.keep_with_next = True

        elif role == "OPTION_ITEM":
            pf.left_indent = Mm(11.5)
            pf.first_line_indent = Mm(-5.0)
            pf.space_before = Pt(0)
            next_role = roles[idx + 1] if idx + 1 < total else "EMPTY"
            if next_role == "OPTION_ITEM":
                pf.space_after = Pt(1.0)
                pf.keep_with_next = True
            else:
                pf.space_after = Pt(3.5)

        elif role in ("OPTION_TAB_2COL", "OPTION_TAB_4COL"):
            pf.left_indent = Mm(6.5)
            pf.first_line_indent = Mm(0)
            pf.space_before = Pt(0)
            next_role = roles[idx + 1] if idx + 1 < total else "EMPTY"
            pf.space_after = Pt(1.0 if next_role in ("OPTION_TAB_2COL", "OPTION_TAB_4COL") else 3.5)

            pf.tab_stops.clear_all()
            if role == "OPTION_TAB_4COL":
                col_step = (printable_width_mm - 6.5) / 4.0
                for col_i in range(4):
                    pf.tab_stops.add_tab_stop(Mm(6.5 + col_i * col_step), WD_TAB_ALIGNMENT.LEFT)
            else:
                mid_x = 6.5 + (printable_width_mm - 6.5) * 0.5
                pf.tab_stops.add_tab_stop(Mm(6.5), WD_TAB_ALIGNMENT.LEFT)
                pf.tab_stops.add_tab_stop(Mm(mid_x), WD_TAB_ALIGNMENT.LEFT)

        elif role == "ARABIC_QUOTE":
            pf.left_indent = Mm(6.5)
            pf.right_indent = Mm(6.5)
            pf.space_before = Pt(3)
            pf.space_after = Pt(3)
            pf.line_spacing = max(1.25, config.line_spacing)
            pf.keep_with_next = True
            if not is_explicit_center:
                para.alignment = WD_ALIGN_PARAGRAPH.RIGHT
                _set_paragraph_bidi(para, True)

        elif role == "PASSAGE_INSTRUCTION":
            pf.left_indent = Mm(6.5)
            pf.first_line_indent = Mm(0)
            pf.space_before = Pt(4)
            pf.space_after = Pt(2)
            pf.keep_with_next = True

        elif role == "PASSAGE_TITLE":
            pf.left_indent = Mm(6.5)
            pf.first_line_indent = Mm(0)
            pf.space_before = Pt(2)
            pf.space_after = Pt(2)
            pf.keep_with_next = True

        else:  # QUESTION_CONTINUATION atau PASSAGE_BODY
            if not is_explicit_center:
                pf.left_indent = Mm(6.5)
                pf.first_line_indent = Mm(0)
            pf.space_before = Pt(0.5)
            pf.space_after = Pt(2.0)

    # Rapikan tabel yang berada di dalam naskah soal agar tidak meluber melewati margin F4
    if config.apply_to_existing_tables:
        for table in doc.tables:
            table.alignment = WD_TABLE_ALIGNMENT.CENTER
            for row in table.rows:
                for cell in row.cells:
                    _set_cell_margins(cell, top_mm=1.0, bottom_mm=1.0, left_mm=1.8, right_mm=1.8)
                    for cp in cell.paragraphs:
                        _apply_paragraph_typography(cp, config, is_inserted_header=False)
                        cp.paragraph_format.space_before = Pt(0)
                        cp.paragraph_format.space_after = Pt(1)


def _insert_standard_school_header(
    doc: DocxDocument,
    anchor: Any,
    config: ProcessingConfig,
    warnings: list[WarningItem],
) -> None:
    """Menyusun kop sekolah, identitas ujian 2 kolom, dan kotak petunjuk pada halaman pertama (D-016)."""
    school = config.school
    document_meta = config.document

    printable_width_mm = max(
        100.0,
        config.paper_width_mm - config.margin_left_mm - config.margin_right_mm,
    )

    school_name = (school.get("name") or "MADRASAH / SEKOLAH").strip()
    foundation_name = (school.get("foundation_name") or "").strip()
    npsn = (school.get("npsn") or "").strip()
    address = (school.get("address") or "").strip()
    phone = (school.get("phone") or "").strip()
    email = (school.get("email") or "").strip()
    website = (school.get("website") or "").strip()

    exam_title = (
        (config.header_title or "").strip()
        or (document_meta.get("period_name") or "").strip()
        or "ASESMEN SUMATIF AKHIR SEMESTER"
    )
    academic_year = (document_meta.get("academic_year") or "").strip()
    exam_subtitle = (config.header_subtitle or "").strip() or (
        f"TAHUN PELAJARAN {academic_year}" if academic_year else ""
    )

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

    # 1. Tabel Kop Sekolah Simetris (Logo Kiri + Identitas Tengah + Garis Kop Ganda)
    kop_table = doc.add_table(rows=1, cols=2 if has_logo else 1)
    kop_table.alignment = WD_TABLE_ALIGNMENT.CENTER
    kop_table.autofit = False
    _set_table_borders(kop_table, val="none")

    if has_logo and logo_path is not None:
        logo_col_mm = 25.0
        text_col_mm = printable_width_mm - logo_col_mm
        _set_fixed_column_widths(kop_table, [logo_col_mm, text_col_mm])

        left_cell = kop_table.cell(0, 0)
        right_cell = kop_table.cell(0, 1)
        left_cell.vertical_alignment = WD_ALIGN_VERTICAL.CENTER
        right_cell.vertical_alignment = WD_ALIGN_VERTICAL.CENTER
        _set_cell_margins(left_cell, top_mm=0.5, bottom_mm=2.0, left_mm=0.0, right_mm=1.5)
        _set_cell_margins(right_cell, top_mm=0.5, bottom_mm=2.0, left_mm=0.5, right_mm=0.0)
        _set_cell_borders(left_cell, bottom="thickThinSmallGap", sz="18")
        _set_cell_borders(right_cell, bottom="thickThinSmallGap", sz="18")

        logo_p = left_cell.paragraphs[0]
        logo_p.alignment = WD_ALIGN_PARAGRAPH.LEFT
        logo_p.paragraph_format.space_before = Pt(0)
        logo_p.paragraph_format.space_after = Pt(0)
        logo_run = logo_p.add_run()
        try:
            logo_run.add_picture(str(logo_path), height=Mm(24.0))
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
        _set_fixed_column_widths(kop_table, [printable_width_mm])
        text_cell = kop_table.cell(0, 0)
        text_cell.vertical_alignment = WD_ALIGN_VERTICAL.CENTER
        _set_cell_margins(text_cell, top_mm=0.5, bottom_mm=2.0, left_mm=0.0, right_mm=0.0)
        _set_cell_borders(text_cell, bottom="thickThinSmallGap", sz="18")

    p_idx = 0
    if foundation_name:
        p_found = text_cell.paragraphs[0]
        p_found.alignment = WD_ALIGN_PARAGRAPH.CENTER
        p_found.paragraph_format.space_before = Pt(0)
        p_found.paragraph_format.space_after = Pt(0.5)
        p_found.paragraph_format.line_spacing = 1.0
        r_found = p_found.add_run(foundation_name.upper())
        _apply_font_to_run(r_found, config.font_family, config.font_size_pt)
        p_idx += 1

    # Nama Sekolah / Madrasah
    p_school = text_cell.paragraphs[0] if p_idx == 0 else text_cell.add_paragraph()
    p_school.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p_school.paragraph_format.space_before = Pt(0)
    p_school.paragraph_format.space_after = Pt(0.5)
    p_school.paragraph_format.line_spacing = 1.0
    r_school = p_school.add_run(school_name.upper())
    r_school.bold = True
    _apply_font_to_run(r_school, config.font_family, config.font_size_pt + 2.0)

    # Judul Asesmen
    p_title = text_cell.add_paragraph()
    p_title.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p_title.paragraph_format.space_before = Pt(0)
    p_title.paragraph_format.space_after = Pt(0.5)
    p_title.paragraph_format.line_spacing = 1.0
    r_title = p_title.add_run(exam_title.upper())
    r_title.bold = True
    _apply_font_to_run(r_title, config.font_family, config.font_size_pt + 1.5)

    # Tahun Pelajaran di bawah Judul
    if exam_subtitle:
        p_sub = text_cell.add_paragraph()
        p_sub.alignment = WD_ALIGN_PARAGRAPH.CENTER
        p_sub.paragraph_format.space_before = Pt(0)
        p_sub.paragraph_format.space_after = Pt(1.0)
        p_sub.paragraph_format.line_spacing = 1.0
        r_sub = p_sub.add_run(exam_subtitle.upper())
        r_sub.bold = True
        _apply_font_to_run(r_sub, config.font_family, max(10.5, config.font_size_pt - 0.5))

    # Alamat Sekolah
    detail_parts: list[str] = []
    if npsn:
        detail_parts.append(f"NPSN: {npsn}")
    if address:
        detail_parts.append(address)
    if detail_parts:
        p_addr = text_cell.add_paragraph()
        p_addr.alignment = WD_ALIGN_PARAGRAPH.CENTER
        p_addr.paragraph_format.space_before = Pt(0)
        p_addr.paragraph_format.space_after = Pt(0.5)
        p_addr.paragraph_format.line_spacing = 1.0
        r_addr = p_addr.add_run(" | ".join(detail_parts))
        _apply_font_to_run(r_addr, config.font_family, max(8.5, config.font_size_pt - 3.5))

    # Nomor Telepon & Email
    contact_parts: list[str] = []
    if phone:
        contact_parts.append(f"Telp. {phone}")
    if email:
        contact_parts.append(f"Email : {email}")
    if website:
        contact_parts.append(f"Web : {website}")
    if contact_parts:
        p_contact = text_cell.add_paragraph()
        p_contact.alignment = WD_ALIGN_PARAGRAPH.CENTER
        p_contact.paragraph_format.space_before = Pt(0)
        p_contact.paragraph_format.space_after = Pt(0)
        p_contact.paragraph_format.line_spacing = 1.0
        r_contact = p_contact.add_run("  ".join(contact_parts))
        _apply_font_to_run(r_contact, config.font_family, max(8.5, config.font_size_pt - 3.5))

    anchor.addprevious(kop_table._tbl)

    # 2. Identitas Ujian Dua Kolom Sejajar
    if config.show_exam_identity_table:
        raw_subject = str(document_meta.get("subject_name") or "").strip()
        subject_name = "" if raw_subject == "-" else raw_subject

        raw_class = str(document_meta.get("class_name") or "").strip()
        class_name = "" if raw_class == "-" else raw_class

        semester_label = str(document_meta.get("semester_label") or "").strip()
        if not semester_label:
            raw_sem = str(document_meta.get("semester") or "").strip().upper()
            semester_label = {"ODD": "GANJIL", "EVEN": "GENAP"}.get(raw_sem, raw_sem)

        if class_name and semester_label:
            class_semester = f"{class_name}/{semester_label.upper()}"
        else:
            class_semester = class_name or semester_label.upper()

        exam_date = _format_display_date(document_meta.get("exam_date"))
        duration = document_meta.get("duration_minutes")
        duration_text = f"{duration} Menit" if duration else ""

        id_rows: list[tuple[str, str, str, str]] = [
            ("Mata Pelajaran", subject_name, "Hari, Tanggal", exam_date),
            ("Kelas/Semester", class_semester, "Waktu", duration_text),
        ]

        id_table = doc.add_table(rows=len(id_rows), cols=4)
        id_table.alignment = WD_TABLE_ALIGNMENT.CENTER
        id_table.autofit = False
        _set_table_borders(id_table, val="none")

        col_widths = [
            printable_width_mm * 0.175,
            printable_width_mm * 0.355,
            printable_width_mm * 0.155,
            printable_width_mm * 0.315,
        ]
        _set_fixed_column_widths(id_table, col_widths)

        for r_idx, (lbl1, val1, lbl2, val2) in enumerate(id_rows):
            is_last_row = r_idx == len(id_rows) - 1
            row_cells = [
                lbl1,
                f": {val1}" if val1 else ":",
                lbl2,
                f": {val2}" if val2 else ":",
            ]
            for c_idx, cell_text in enumerate(row_cells):
                cell = id_table.cell(r_idx, c_idx)
                cell.vertical_alignment = WD_ALIGN_VERTICAL.CENTER
                _set_cell_margins(
                    cell,
                    top_mm=1.2 if r_idx == 0 else 0.5,
                    bottom_mm=1.8 if is_last_row else 0.5,
                    left_mm=0.5,
                    right_mm=0.5,
                )
                if is_last_row:
                    _set_cell_borders(cell, bottom="single", sz="10")
                cp = cell.paragraphs[0]
                cp.paragraph_format.space_before = Pt(0)
                cp.paragraph_format.space_after = Pt(0)
                cp.paragraph_format.line_spacing = 1.0
                crun = cp.add_run(cell_text)
                _apply_font_to_run(crun, config.font_family, config.font_size_pt)

        anchor.addprevious(id_table._tbl)

    # 3. Kotak Petunjuk Berbingkai Sederhana
    instruction = (config.instruction_text or "").strip()
    if instruction:
        p_gap = doc.add_paragraph()
        p_gap.paragraph_format.space_before = Pt(0)
        p_gap.paragraph_format.space_after = Pt(4)
        p_gap.paragraph_format.line_spacing = 0.5
        anchor.addprevious(p_gap._p)

        inst_table = doc.add_table(rows=1, cols=1)
        inst_table.alignment = WD_TABLE_ALIGNMENT.CENTER
        inst_table.autofit = False
        _set_table_borders(inst_table, val="single", sz="6")
        _set_fixed_column_widths(inst_table, [printable_width_mm])

        inst_cell = inst_table.cell(0, 0)
        _set_cell_margins(inst_cell, top_mm=2.0, bottom_mm=2.5, left_mm=3.5, right_mm=3.5)

        p_lbl = inst_cell.paragraphs[0]
        p_lbl.paragraph_format.space_before = Pt(0)
        p_lbl.paragraph_format.space_after = Pt(2)
        p_lbl.paragraph_format.line_spacing = 1.0
        r_lbl = p_lbl.add_run("Petunjuk :")
        _apply_font_to_run(r_lbl, config.font_family, config.font_size_pt)

        for line in _split_instruction_lines(instruction):
            p_line = inst_cell.add_paragraph()
            p_line.paragraph_format.left_indent = Mm(22.0)
            p_line.paragraph_format.space_before = Pt(0)
            p_line.paragraph_format.space_after = Pt(1.5)
            p_line.paragraph_format.line_spacing = 1.05
            r_line = p_line.add_run(line)
            _apply_font_to_run(r_line, config.font_family, config.font_size_pt)

        anchor.addprevious(inst_table._tbl)

    p_space = doc.add_paragraph()
    p_space.paragraph_format.space_before = Pt(0)
    p_space.paragraph_format.space_after = Pt(6)
    p_space.paragraph_format.line_spacing = 0.5
    anchor.addprevious(p_space._p)


def _append_page_number_field(paragraph: Paragraph, font_family: str, font_size_pt: float) -> None:
    """Menyisipkan field nomor halaman dinamis OOXML (PAGE) yang diperbarui otomatis oleh Word & LibreOffice."""
    r_begin = paragraph.add_run()
    _apply_font_to_run(r_begin, font_family, font_size_pt)
    fld_begin = OxmlElement("w:fldChar")
    fld_begin.set(qn("w:fldCharType"), "begin")
    r_begin._r.append(fld_begin)

    r_instr = paragraph.add_run()
    _apply_font_to_run(r_instr, font_family, font_size_pt)
    instr_text = OxmlElement("w:instrText")
    instr_text.set(qn("xml:space"), "preserve")
    instr_text.text = " PAGE "
    r_instr._r.append(instr_text)

    r_sep = paragraph.add_run()
    _apply_font_to_run(r_sep, font_family, font_size_pt)
    fld_sep = OxmlElement("w:fldChar")
    fld_sep.set(qn("w:fldCharType"), "separate")
    r_sep._r.append(fld_sep)

    r_val = paragraph.add_run("1")
    _apply_font_to_run(r_val, font_family, font_size_pt)

    r_end = paragraph.add_run()
    _apply_font_to_run(r_end, font_family, font_size_pt)
    fld_end = OxmlElement("w:fldChar")
    fld_end.set(qn("w:fldCharType"), "end")
    r_end._r.append(fld_end)


def _build_footer_metadata_text(config: ProcessingConfig) -> str:
    """Menyusun label footer dengan format: Mapel/Nama Ujian/Kelas/Tahun Pelajaran."""
    document_meta = config.document

    raw_subject = str(document_meta.get("subject_name") or "").strip()
    subject_name = "" if raw_subject == "-" else raw_subject

    semester_label = str(document_meta.get("semester_label") or "").strip()
    if not semester_label:
        raw_sem = str(document_meta.get("semester") or "").strip().upper()
        semester_label = {"ODD": "Ganjil", "EVEN": "Genap"}.get(raw_sem, "")

    exam_short = str(document_meta.get("exam_short_name") or "").strip()
    if not exam_short:
        raw_period = str(document_meta.get("period_name") or config.header_title or "").strip()
        paren_match = re.search(r"\(([^)]+)\)", raw_period)
        if paren_match:
            abbr = paren_match.group(1).strip()
            if semester_label and semester_label.lower() not in abbr.lower():
                exam_short = f"{abbr} {semester_label}"
            else:
                exam_short = abbr
        else:
            exam_short = raw_period

    raw_class = str(document_meta.get("class_name") or "").strip()
    class_name = "" if raw_class == "-" else raw_class

    academic_year = str(document_meta.get("academic_year") or "").strip()
    if not academic_year and config.header_subtitle:
        yr_match = re.search(r"(\d{4}\s*[-/]\s*\d{4})", str(config.header_subtitle))
        if yr_match:
            academic_year = yr_match.group(1).replace(" ", "")

    parts = [p for p in (subject_name, exam_short, class_name, academic_year) if p]
    return "/".join(parts)


def _clear_footer_container(footer_obj: Any) -> bool:
    """Membersihkan elemen footer lama sambil mendeteksi apakah sebelumnya berisi teks/tabel."""
    el = footer_obj._element
    had_content = any(p.text.strip() for p in footer_obj.paragraphs) or len(footer_obj.tables) > 0
    for child in list(el):
        el.remove(child)
    return had_content


def _populate_single_footer(
    footer_obj: Any,
    printable_width_mm: float,
    footer_text: str,
    config: ProcessingConfig,
) -> bool:
    """Membangun tabel footer 1 baris dengan garis pembatas atas dan nomor halaman otomatis."""
    had_content = _clear_footer_container(footer_obj)

    footer_font_size = max(9.5, config.font_size_pt - 2.0)
    page_col_mm = 12.0
    info_col_mm = max(80.0, printable_width_mm - page_col_mm)

    footer_table = footer_obj.add_table(rows=1, cols=2, width=Mm(printable_width_mm))
    footer_table.alignment = WD_TABLE_ALIGNMENT.CENTER
    footer_table.autofit = False
    _set_table_borders(footer_table, val="none")
    _set_fixed_column_widths(footer_table, [info_col_mm, page_col_mm])

    info_cell = footer_table.cell(0, 0)
    page_cell = footer_table.cell(0, 1)

    info_cell.vertical_alignment = WD_ALIGN_VERTICAL.CENTER
    page_cell.vertical_alignment = WD_ALIGN_VERTICAL.CENTER

    _set_cell_borders(info_cell, top="single", sz="6")
    _set_cell_borders(page_cell, top="single", sz="6")

    _set_cell_margins(info_cell, top_mm=1.2, bottom_mm=0.0, left_mm=0.0, right_mm=0.0)
    _set_cell_margins(page_cell, top_mm=1.2, bottom_mm=0.0, left_mm=0.0, right_mm=0.0)

    p_info = info_cell.paragraphs[0]
    p_info.alignment = WD_ALIGN_PARAGRAPH.RIGHT
    p_info.paragraph_format.space_before = Pt(0)
    p_info.paragraph_format.space_after = Pt(0)
    p_info.paragraph_format.line_spacing = 1.0
    if footer_text:
        r_info = p_info.add_run(footer_text)
        _apply_font_to_run(r_info, config.font_family, footer_font_size)

    p_page = page_cell.paragraphs[0]
    p_page.alignment = WD_ALIGN_PARAGRAPH.RIGHT
    p_page.paragraph_format.space_before = Pt(0)
    p_page.paragraph_format.space_after = Pt(0)
    p_page.paragraph_format.line_spacing = 1.0
    _append_page_number_field(p_page, config.font_family, footer_font_size)

    return had_content


def _apply_standard_footer(
    doc: DocxDocument,
    config: ProcessingConfig,
    warnings: list[WarningItem],
) -> None:
    """Menerapkan footer standar pada seluruh halaman (halaman pertama maupun halaman lanjutan)."""
    footer_text = _build_footer_metadata_text(config)
    replaced_existing_footer = False

    for section in doc.sections:
        printable_width_mm = max(
            100.0,
            section.page_width.mm - section.left_margin.mm - section.right_margin.mm,
        )

        section.first_page_footer.is_linked_to_previous = False
        section.footer.is_linked_to_previous = False

        if _populate_single_footer(section.first_page_footer, printable_width_mm, footer_text, config):
            replaced_existing_footer = True
        if _populate_single_footer(section.footer, printable_width_mm, footer_text, config):
            replaced_existing_footer = True

    if replaced_existing_footer:
        warnings.append(
            WarningItem(
                code="EXISTING_FOOTER_STANDARDIZED",
                severity="INFO",
                message="Footer bawaan dokumen asli digantikan dengan footer standar SIMSOAL di seluruh halaman.",
                location="footer",
            )
        )


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

    # 1. Standarisasi ukuran kertas F4, margin halaman, dan normalisasi kolom
    _configure_page_setup(doc, config, warnings)

    # 2. Standarisasi tipografi, indentasi soal, bacaan, dan pilihan jawaban adaptif
    _apply_exam_body_formatting(doc, config)

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

    # 4. Terapkan footer standar beserta nomor halaman otomatis di seluruh halaman
    _apply_standard_footer(doc, config, warnings)

    # 5. Simpan ke path output baru
    output_path.parent.mkdir(parents=True, exist_ok=True)
    doc.save(str(output_path))

    # 6. Validasi pasca-simpan
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