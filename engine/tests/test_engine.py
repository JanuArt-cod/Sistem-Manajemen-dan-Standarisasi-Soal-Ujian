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
from docx.oxml import parse_xml
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
    # Set ukuran awal sengaja berbeda (Letter + Arial 10pt) untuk menguji standarisasi ke F4 TNR 12pt
    sec = doc.sections[0]
    sec.page_width = Mm(215.9)
    sec.page_height = Mm(279.4)
    sec.top_margin = Mm(12.0)
    sec.bottom_margin = Mm(12.0)
    sec.left_margin = Mm(15.0)
    sec.right_margin = Mm(15.0)

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

    if include_textbox:
        p_tb = doc.add_paragraph()
        tb_xml = (
            '<w:txbxContent xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            "<w:p><w:r><w:t>Catatan Kotak Melayang</w:t></w:r></w:p>"
            "</w:txbxContent>"
        )
        p_tb._p.append(parse_xml(tb_xml))

    doc.save(str(path))


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
                    "class_name": "XI TKJ 1",
                    "teacher_name": "Muhammad Januar Ramdhani, S.Kom.",
                    "exam_date": "Senin, 7 Desember 2026",
                    "duration_minutes": 90,
                },
                "runtime": {
                    "logo_abs_path": str(logo),
                },
            }

            config = ProcessingConfig.from_snapshot(snapshot)
            report = standardize_docx(src, out, config)

            self.assertEqual(report.status, "COMPLETED")
            self.assertFalse(report.needs_manual_review)
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

            # Pastikan kop halaman pertama muncul di awal dokumen sebelum soal nomor 1
            all_texts = [p.text for p in result_doc.paragraphs if p.text.strip()]
            idx_title = next(i for i, t in enumerate(all_texts) if "SUMATIF AKHIR SEMESTER GANJIL" in t)
            idx_q1 = next(i for i, t in enumerate(all_texts) if t.startswith("1. Perhatikan persamaan"))
            self.assertLess(idx_title, idx_q1)

            # Pastikan penekanan inline (subscript, superscript, bold, italic) & font TNR 12pt terjaga
            q1_para = result_doc.paragraphs[
                next(i for i, p in enumerate(result_doc.paragraphs) if p.text.startswith("1. Perhatikan persamaan"))
            ]
            self.assertTrue(any(r.font.subscript for r in q1_para.runs))
            self.assertTrue(any(r.font.superscript for r in q1_para.runs))
            self.assertTrue(any(r.bold for r in q1_para.runs))
            self.assertTrue(any(r.italic for r in q1_para.runs))
            for r in q1_para.runs:
                self.assertEqual(r.font.name, "Times New Roman")
                self.assertEqual(r.font.size.pt, 12.0)

            # Tabel kop + tabel identitas + tabel soal asli = 3 tabel
            self.assertEqual(len(result_doc.tables), 3)
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