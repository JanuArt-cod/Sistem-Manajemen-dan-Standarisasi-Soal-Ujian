from __future__ import annotations

import io
import json
import sys
import tempfile
import unittest
from pathlib import Path

ENGINE_ROOT = Path(__file__).resolve().parents[1]
if str(ENGINE_ROOT) not in sys.path:
    sys.path.insert(0, str(ENGINE_ROOT))

from docx import Document
from docx.oxml import OxmlElement, parse_xml
from docx.oxml.ns import qn
from docx.shared import Mm, Pt
from PIL import Image

from simsoal_docx.cli import main as cli_main
from simsoal_docx.formatter import compute_sha256, standardize_docx
from simsoal_docx.models import ProcessingConfig


def _create_sample_png_bytes(color: tuple[int, int, int] = (30, 64, 175)) -> bytes:
    buf = io.BytesIO()
    img = Image.new("RGB", (64, 64), color=color)
    img.save(buf, format="PNG")
    return buf.getvalue()


def _build_fixture_exam_docx(path: Path, include_textbox: bool = False) -> None:
    doc = Document()
    # Set ukuran awal sengaja berbeda (Letter + Arial 10pt + 2 kolom) untuk menguji standarisasi penuh
    sec = doc.sections[0]
    sec.page_width = Mm(215.9)
    sec.page_height = Mm(279.4)
    sec.top_margin = Mm(12.0)
    sec.bottom_margin = Mm(12.0)
    sec.left_margin = Mm(15.0)
    sec.right_margin = Mm(15.0)

    cols_el = sec._sectPr.find(qn("w:cols"))
    if cols_el is None:
        cols_el = OxmlElement("w:cols")
        sec._sectPr.append(cols_el)
    cols_el.set(qn("w:num"), "2")
    cols_el.set(qn("w:space"), "720")

    p_sec1 = doc.add_paragraph("I. PILIHAN GANDA")
    for r in p_sec1.runs:
        r.font.name = "Arial"
        r.font.size = Pt(11)

    p1 = doc.add_paragraph()
    r1_num = p1.add_run("1. Perhatikan persamaan kimia berikut: H")
    r1_num.font.name = "Arial"
    r1_num.font.size = Pt(10)
    r1_sub = p1.add_run("2")
    r1_sub.font.subscript = True
    r1_tail = p1.add_run("O dan hitung nilai x")
    r1_sup = p1.add_run("2")
    r1_sup.font.superscript = True
    r1_end = p1.add_run(" + 5 = 21. Jawaban yang ")
    r1_bold = p1.add_run("benar")
    r1_bold.bold = True
    r1_italic = p1.add_run(" adalah...")
    r1_italic.italic = True

    # Tambahkan persamaan matematika OMML asli
    p_math = doc.add_paragraph()
    omml_xml = (
        '<m:oMath xmlns:m="http://schemas.openxmlformats.org/officeDocument/2006/math">'
        "<m:r><m:t>E=mc^2</m:t></m:r>"
        "</m:oMath>"
    )
    p_math._p.append(parse_xml(omml_xml))

    # Tambahkan pilihan ganda A-D
    for opt in ("A. 4", "B. -4", "C. 4 atau -4", "D. 16"):
        po = doc.add_paragraph(opt)
        for r in po.runs:
            r.font.name = "Calibri"
            r.font.size = Pt(11)

    # Tambahkan gambar inline pada soal nomor 2
    p2 = doc.add_paragraph("2. Perhatikan topologi jaringan pada gambar di bawah ini:")
    p2.add_run().add_picture(io.BytesIO(_create_sample_png_bytes()), width=Mm(25))

    # Tambahkan tabel data pada soal nomor 3
    doc.add_paragraph("3. Lengkapi tabel alamat IP berikut:")
    tbl = doc.add_table(rows=2, cols=2)
    tbl.cell(0, 0).text = "Subnet"
    tbl.cell(0, 1).text = "Prefix"
    tbl.cell(1, 0).text = "192.168.10.0"
    tbl.cell(1, 1).text = "/24"

    # Tambahkan soal dengan opsi 2 kolom tabulasi & bagian Uraian
    doc.add_paragraph("4. Pilihlah pasangan protokol dan port yang sesuai:")
    doc.add_paragraph("A. HTTP - 80\tB. HTTPS - 443")
    doc.add_paragraph("C. SSH - 22\tD. DNS - 53")

    doc.add_paragraph("II. URAIAN")
    doc.add_paragraph("5. Jelaskan fungsi perangkat jaringan berikut:")
    doc.add_paragraph("a. Router")
    doc.add_paragraph("b. Switch")

    if include_textbox:
        p_tb = doc.add_paragraph()
        tb_xml = (
            '<w:txbxContent xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            "<w:p><w:r><w:t>Catatan Kotak Melayang</w:t></w:r></w:p>"
            "</w:txbxContent>"
        )
        p_tb._p.append(parse_xml(tb_xml))

    doc.save(str(path))


def _extract_ordered_body_blocks(doc: Document) -> list[str]:
    """Mengambil seluruh teks blok (paragraf dan tabel) secara berurutan dari atas ke bawah."""
    blocks: list[str] = []
    para_iter = iter(doc.paragraphs)
    table_iter = iter(doc.tables)

    for child in doc.element.body:
        if child.tag.endswith("}p"):
            para = next(para_iter, None)
            if para is not None and para.text.strip():
                blocks.append(para.text.strip())
        elif child.tag.endswith("}tbl"):
            tbl = next(table_iter, None)
            if tbl is not None:
                cell_texts = [
                    p.text.strip()
                    for row in tbl.rows
                    for cell in row.cells
                    for p in cell.paragraphs
                    if p.text.strip()
                ]
                if cell_texts:
                    blocks.append(" | ".join(cell_texts))

    return blocks


def _footer_has_page_field(footer_obj) -> bool:
    for instr in footer_obj._element.findall(f".//{qn('w:instrText')}"):
        if instr.text and "PAGE" in instr.text:
            return True
    return False


class SimsoalDocxEngineTest(unittest.TestCase):
    def test_standardizes_to_f4_tnr_12pt_and_preserves_all_content(self) -> None:
        with tempfile.TemporaryDirectory() as tmp_dir:
            tmp = Path(tmp_dir)
            src = tmp / "soal_asli.docx"
            out = tmp / "soal_standar.docx"
            logo = tmp / "logo_sekolah.png"
            logo.write_bytes(_create_sample_png_bytes((15, 118, 110)))

            _build_fixture_exam_docx(src, include_textbox=False)
            src_hash_before = compute_sha256(src)

            snapshot = {
                "preset": {
                    "paper_code": "F4",
                    "paper_width_mm": 215.0,
                    "paper_height_mm": 330.0,
                    "orientation": "PORTRAIT",
                    "margin_top_mm": 20.0,
                    "margin_bottom_mm": 20.0,
                    "margin_left_mm": 25.0,
                    "margin_right_mm": 20.0,
                    "font_family": "Times New Roman",
                    "font_size_pt": 12.0,
                    "line_spacing": 1.0,
                },
                "template": {
                    "header_mode": "STANDARD_SCHOOL",
                    "first_page_only": True,
                    "show_school_logo": True,
                    "show_exam_identity_table": True,
                    "header_title": "SUMATIF AKHIR SEMESTER GANJIL",
                    "header_subtitle": "TAHUN PELAJARAN 2026/2027",
                    "instruction_text": "Kerjakan soal dengan jujur dan teliti.",
                },
                "school": {
                    "foundation_name": "YAYASAN PENDIDIKAN ISLAM",
                    "name": "SMK BINA INSAN MANDIRI",
                    "npsn": "20253901",
                    "address": "Jl. Raya Bogor - Sukabumi KM 15",
                },
                "document": {
                    "subject_name": "Teknik Komputer dan Jaringan",
                    "subject_code": "TKJ-11",
                    "exam_short_name": "ASAS Ganjil",
                    "academic_year": "2026-2027",
                    "class_name": "XI TKJ 1",
                    "semester_label": "Ganjil",
                    "teacher_name": "Muhammad Januar Ramdhani, S.Kom.",
                    "exam_date": "Senin, 07 Desember 2026",
                    "duration_minutes": 90,
                },
                "runtime": {
                    "logo_abs_path": str(logo),
                },
            }

            config = ProcessingConfig.from_snapshot(snapshot)
            report = standardize_docx(src, out, config)

            self.assertEqual(report.status, "COMPLETED_WITH_WARNINGS")
            self.assertFalse(report.needs_manual_review)
            self.assertTrue(any(w.code == "MULTI_COLUMN_LAYOUT_NORMALIZED" for w in report.warnings))
            self.assertEqual(compute_sha256(src), src_hash_before)
            self.assertTrue(out.is_file())

            result_doc = Document(str(out))
            sec = result_doc.sections[0]
            self.assertAlmostEqual(sec.page_width.mm, 215.0, places=1)
            self.assertAlmostEqual(sec.page_height.mm, 330.0, places=1)
            self.assertAlmostEqual(sec.top_margin.mm, 20.0, places=1)
            self.assertAlmostEqual(sec.bottom_margin.mm, 20.0, places=1)
            self.assertAlmostEqual(sec.left_margin.mm, 25.0, places=1)
            self.assertAlmostEqual(sec.right_margin.mm, 20.0, places=1)
            self.assertTrue(sec.different_first_page_header_footer)

            # Pastikan section dinormalkan menjadi 1 kolom penuh
            cols_el = sec._sectPr.find(qn("w:cols"))
            self.assertIsNotNone(cols_el)
            self.assertEqual(cols_el.get(qn("w:num")), "1")

            # Pastikan urutan blok halaman pertama: Kop Sekolah -> Identitas Ujian -> Kotak Petunjuk -> Soal No. 1
            ordered_blocks = _extract_ordered_body_blocks(result_doc)
            idx_kop = next(i for i, b in enumerate(ordered_blocks) if "SUMATIF AKHIR SEMESTER GANJIL" in b)
            idx_id = next(i for i, b in enumerate(ordered_blocks) if "Mata Pelajaran" in b and "Hari, Tanggal" in b)
            idx_inst = next(i for i, b in enumerate(ordered_blocks) if "Petunjuk :" in b)
            idx_q1 = next(i for i, b in enumerate(ordered_blocks) if b.startswith("1. Perhatikan persamaan"))
            self.assertLess(idx_kop, idx_id)
            self.assertLess(idx_id, idx_inst)
            self.assertLess(idx_inst, idx_q1)

            # Pastikan footer standar + field PAGE ada di halaman pertama maupun halaman lanjutan
            expected_footer = "Teknik Komputer dan Jaringan/ASAS Ganjil/XI TKJ 1/2026-2027"
            for ftr in (sec.first_page_footer, sec.footer):
                self.assertEqual(len(ftr.tables), 1)
                self.assertEqual(ftr.tables[0].cell(0, 0).text.strip(), expected_footer)
                self.assertTrue(_footer_has_page_field(ftr))

            # Pastikan penekanan inline (subscript, superscript, bold, italic), font TNR 12pt, & hanging indent terjaga
            q1_para = result_doc.paragraphs[
                next(i for i, p in enumerate(result_doc.paragraphs) if p.text.startswith("1. Perhatikan persamaan"))
            ]
            self.assertAlmostEqual(q1_para.paragraph_format.left_indent.mm, 6.5, places=1)
            self.assertAlmostEqual(q1_para.paragraph_format.first_line_indent.mm, -6.5, places=1)
            self.assertTrue(q1_para.paragraph_format.keep_with_next)
            self.assertTrue(any(r.font.subscript for r in q1_para.runs))
            self.assertTrue(any(r.font.superscript for r in q1_para.runs))
            self.assertTrue(any(r.bold for r in q1_para.runs))
            self.assertTrue(any(r.italic for r in q1_para.runs))
            for r in q1_para.runs:
                self.assertEqual(r.font.name, "Times New Roman")
                self.assertEqual(r.font.size.pt, 12.0)

            # Pastikan opsi pilihan ganda & sub-item uraian memiliki indentasi sejajar
            opt_a_para = next(p for p in result_doc.paragraphs if p.text.startswith("A. 4"))
            self.assertAlmostEqual(opt_a_para.paragraph_format.left_indent.mm, 11.5, places=1)
            self.assertAlmostEqual(opt_a_para.paragraph_format.first_line_indent.mm, -5.0, places=1)

            tab_opt_para = next(p for p in result_doc.paragraphs if p.text.startswith("A. HTTP - 80"))
            self.assertEqual(len(tab_opt_para.paragraph_format.tab_stops), 2)

            essay_sub_para = next(p for p in result_doc.paragraphs if p.text.startswith("a. Router"))
            self.assertAlmostEqual(essay_sub_para.paragraph_format.left_indent.mm, 11.5, places=1)

            # Tabel kop + tabel identitas + tabel kotak petunjuk + tabel soal asli = 4 tabel
            self.assertEqual(len(result_doc.tables), 4)
            self.assertEqual(report.metrics["output_equation_count"], 1)

    def test_custom_docx_header_mode_and_cli_execution(self) -> None:
        with tempfile.TemporaryDirectory() as tmp_dir:
            tmp = Path(tmp_dir)
            src = tmp / "soal_sumber.docx"
            tpl = tmp / "kop_khusus.docx"
            out = tmp / "hasil_cli.docx"
            cfg_file = tmp / "snapshot.json"
            rep_file = tmp / "report.json"

            _build_fixture_exam_docx(src, include_textbox=True)

            tpl_doc = Document()
            p_kop = tpl_doc.add_paragraph()
            rk = p_kop.add_run("KOP KHUSUS YASPISA BOGOR")
            rk.bold = True
            tpl_doc.save(str(tpl))

            snapshot = {
                "preset": {
                    "paper_code": "F4",
                    "paper_width_mm": 215.0,
                    "paper_height_mm": 330.0,
                    "font_family": "Times New Roman",
                    "font_size_pt": 12.0,
                    "line_spacing": 1.0,
                },
                "template": {
                    "header_mode": "CUSTOM_DOCX",
                    "first_page_only": True,
                },
                "runtime": {
                    "template_abs_path": str(tpl),
                },
            }
            cfg_file.write_text(json.dumps(snapshot), encoding="utf-8")

            exit_code = cli_main(
                [
                    "--input",
                    str(src),
                    "--config",
                    str(cfg_file),
                    "--output",
                    str(out),
                    "--report",
                    str(rep_file),
                ]
            )

            self.assertEqual(exit_code, 0)
            report_data = json.loads(rep_file.read_text(encoding="utf-8"))
            self.assertEqual(report_data["status"], "COMPLETED_WITH_WARNINGS")
            self.assertTrue(report_data["needs_manual_review"])
            self.assertTrue(any(w["code"] == "TEXTBOX_OR_FLOATING_SHAPE" for w in report_data["warnings"]))

            out_doc = Document(str(out))
            self.assertTrue(any("KOP KHUSUS YASPISA BOGOR" in p.text for p in out_doc.paragraphs[:3]))

    def test_refuses_to_overwrite_source_file(self) -> None:
        with tempfile.TemporaryDirectory() as tmp_dir:
            tmp = Path(tmp_dir)
            src = tmp / "dokumen.docx"
            _build_fixture_exam_docx(src)

            report = standardize_docx(src, src, ProcessingConfig())
            self.assertEqual(report.status, "FAILED")
            self.assertIsNotNone(report.error_message)


if __name__ == "__main__":
    unittest.main()