from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path

# Pastikan direktori engine ada di sys.path saat dipanggil langsung oleh PHP/Laravel
ENGINE_ROOT = Path(__file__).resolve().parents[1]
if str(ENGINE_ROOT) not in sys.path:
    sys.path.insert(0, str(ENGINE_ROOT))

from simsoal_docx import ENGINE_NAME, __version__
from simsoal_docx.formatter import standardize_docx
from simsoal_docx.models import ProcessingConfig


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(
        prog="simsoal-docx",
        description="Mesin standarisasi dokumen soal ujian (.docx) SIMSOAL.",
    )
    parser.add_argument("--version", action="version", version=f"{ENGINE_NAME} {__version__}")
    parser.add_argument("--input", required=True, help="Path absolut file .docx sumber asli.")
    parser.add_argument("--config", required=True, help="Path absolut file JSON snapshot konfigurasi.")
    parser.add_argument("--output", required=True, help="Path absolut file .docx keluaran baru.")
    parser.add_argument("--report", required=False, help="Path absolut file JSON laporan hasil proses.")
    return parser


def main(argv: list[str] | None = None) -> int:
    parser = build_parser()
    args = parser.parse_args(argv)

    input_path = Path(args.input)
    config_path = Path(args.config)
    output_path = Path(args.output)
    report_path = Path(args.report) if args.report else None

    if not config_path.is_file():
        err_payload = {
            "status": "FAILED",
            "engine_name": ENGINE_NAME,
            "engine_version": __version__,
            "error_message": f"File konfigurasi JSON tidak ditemukan: {config_path.name}",
            "warnings": [],
            "metrics": {},
        }
        if report_path:
            report_path.parent.mkdir(parents=True, exist_ok=True)
            report_path.write_text(json.dumps(err_payload, ensure_ascii=False, indent=2), encoding="utf-8")
        print(json.dumps(err_payload, ensure_ascii=False))
        return 1

    try:
        raw_snapshot = json.loads(config_path.read_text(encoding="utf-8"))
        config = ProcessingConfig.from_snapshot(raw_snapshot)
    except Exception as exc:
        err_payload = {
            "status": "FAILED",
            "engine_name": ENGINE_NAME,
            "engine_version": __version__,
            "error_message": f"Gagal membaca JSON snapshot konfigurasi: {exc}",
            "warnings": [],
            "metrics": {},
        }
        if report_path:
            report_path.parent.mkdir(parents=True, exist_ok=True)
            report_path.write_text(json.dumps(err_payload, ensure_ascii=False, indent=2), encoding="utf-8")
        print(json.dumps(err_payload, ensure_ascii=False))
        return 1

    report = standardize_docx(input_path=input_path, output_path=output_path, config=config)
    report_dict = report.to_dict()
    serialized = json.dumps(report_dict, ensure_ascii=False, indent=2)

    if report_path:
        report_path.parent.mkdir(parents=True, exist_ok=True)
        report_path.write_text(serialized, encoding="utf-8")

    print(serialized)
    return 0 if report.status in ("COMPLETED", "COMPLETED_WITH_WARNINGS") else 1


if __name__ == "__main__":
    raise SystemExit(main())