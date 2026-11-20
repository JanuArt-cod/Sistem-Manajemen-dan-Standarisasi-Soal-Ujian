# System Architecture

## 1. Prinsip Arsitektur

SIMSOAL menggunakan modular monolith Laravel untuk aplikasi bisnis, dengan engine dokumen Python sebagai proses terisolasi yang dipanggil melalui command-line. Hindari microservices dan infrastruktur kompleks pada MVP.

Prinsip:

1. Laravel adalah sumber kebenaran untuk pengguna, metadata, status, antrean, audit, dan arsip.
2. Python hanya menjalankan tugas pemrosesan dokumen yang diminta Laravel.
3. File berada di storage privat.
4. Proses berat berjalan di queue, bukan di HTTP request.
5. Semua hasil pemrosesan bersifat immutable: jangan menimpa file lama.
6. Adapter per format memisahkan DOCX, DOC, dan PDF.
7. Fase MVP hanya mengimplementasikan adapter DOCX.

## 2. Gambaran Komponen

```text
Browser Operator/Admin
        |
        v
Laravel 12 + Inertia + Vue 3
        |
        +---- MySQL/MariaDB
        |
        +---- Private Storage (source, output, preview)
        |
        +---- Laravel Queue (database driver untuk MVP)
                    |
                    v
              ProcessDocument Job
                    |
                    v
              Document Processor Adapter
                    |
                    +---- Python CLI (python-docx / OOXML)
                    |
                    +---- LibreOffice headless (DOCX -> PDF)
                    |
                    v
              Validate artifacts + save logs/status
```

## 3. Stack

- PHP 8.2+
- Laravel 12
- Vue 3 + Inertia.js
- Tailwind CSS
- MySQL/MariaDB
- Python 3 (gunakan versi stabil yang cocok dengan dependency)
- python-docx untuk operasi DOCX yang didukung
- lxml hanya bila dibutuhkan untuk manipulasi OOXML terkontrol
- LibreOffice untuk konversi file
- Laravel Queue database driver pada MVP

Jangan mengunci versi package tanpa memeriksa kompatibilitas aktual saat instalasi.

## 4. Batas Tanggung Jawab

### Laravel

- Autentikasi dan otorisasi.
- CRUD periode dan metadata dokumen.
- Upload dan validasi awal.
- Penyimpanan file dan pemetaan path privat.
- Pembuatan processing run.
- Menjalankan queue.
- Memanggil engine melalui Process API/Symfony Process.
- Memeriksa hasil, menyimpan log, dan memperbarui status.
- Menyediakan download dan preview terotorisasi.

### Python document engine

- Menerima path input dan konfigurasi melalui JSON.
- Memeriksa DOCX valid sebagai ZIP/OOXML.
- Menerapkan perubahan halaman dan format yang disetujui.
- Menambahkan kop/metadata jika fitur tersebut sudah diuji.
- Menyimpan output ke path baru.
- Menghasilkan JSON report: status, versi engine, jumlah section, peringatan, daftar artefak.
- Tidak memiliki akses langsung ke database.
- Tidak mengirim dokumen ke internet.

### LibreOffice

- Konversi output DOCX menjadi PDF.
- Dijalankan dalam mode headless dengan profile sementara yang terisolasi.
- Output diperiksa eksistensi, ukuran, dan dapat dibaca.

## 5. Integrasi Laravel–Python

Gunakan perintah CLI yang deterministik, bukan membuat HTTP microservice pada MVP.

Contoh kontrak konseptual:

```bash
python tools/document_engine/process_docx.py \
  --input "storage/private/source/input.docx" \
  --output "storage/private/processed/run-uuid/output.docx" \
  --config "storage/private/processed/run-uuid/config.json" \
  --report "storage/private/processed/run-uuid/report.json"
```

Aturan:

- Path harus berasal dari storage Laravel yang sudah divalidasi, bukan nilai bebas dari browser.
- Gunakan Symfony Process/Laravel Process dengan argument array; hindari shell string yang menggabungkan input pengguna.
- Terapkan timeout dan batas resource.
- Exit code bukan satu-satunya indikator; validasi juga report dan file output.
- Catat stdout/stderr secara aman; jangan memasukkan isi soal sensitif ke log.
- Buat working directory unik per processing run.

## 6. Struktur Folder yang Disarankan

```text
simsoal/
├── app/
│   ├── Actions/
│   ├── Enums/
│   ├── Http/Controllers/
│   ├── Http/Requests/
│   ├── Jobs/
│   ├── Models/
│   ├── Policies/
│   └── Services/
│       ├── Documents/
│       ├── Storage/
│       └── Processing/
├── database/
│   ├── migrations/
│   └── seeders/
├── resources/js/
│   ├── Components/
│   ├── Layouts/
│   ├── Pages/
│   └── Composables/
├── routes/
├── storage/app/private/
│   ├── sources/
│   ├── outputs/
│   ├── previews/
│   └── temp/
├── tools/document_engine/
│   ├── process_docx.py
│   ├── requirements.txt
│   └── README.md
├── docs/
└── tests/
```

Struktur ini adalah rekomendasi, bukan kewajiban untuk membuat semua folder sekaligus. Ikuti konvensi Laravel dan hindari folder kosong yang belum diperlukan.

## 7. Alur Queue

1. Controller menyimpan metadata dan file sumber.
2. Controller membuat `document_processing_runs` dengan status `QUEUED`.
3. Dispatch `ProcessDocumentJob`.
4. Job mengubah status menjadi `PROCESSING`.
5. Job mengambil snapshot preset dan konfigurasi.
6. Job memanggil adapter DOCX.
7. Adapter menghasilkan DOCX baru dan report.
8. Job memverifikasi output.
9. Job memanggil LibreOffice untuk PDF.
10. Job menyimpan artefak dan peringatan.
11. Status menjadi `NEEDS_REVIEW`, `PROCESSED`, `PDF_FAILED`, atau `FAILED` sesuai hasil.
12. Operator melakukan pemeriksaan dan persetujuan.

## 8. Konfigurasi Lingkungan

Sediakan konfigurasi terpusat melalui `.env`, misalnya:

```dotenv
DOCUMENT_ENGINE_PYTHON="python"
DOCUMENT_ENGINE_SCRIPT="tools/document_engine/process_docx.py"
LIBREOFFICE_BINARY="C:\\Program Files\\LibreOffice\\program\\soffice.exe"
DOCUMENT_MAX_UPLOAD_MB=30
DOCUMENT_QUEUE_TIMEOUT=300
```

Nilai di atas contoh. Jangan menyimpan path absolut yang khusus untuk satu komputer ke kode aplikasi. Sediakan dokumentasi konfigurasi Windows dan Linux jika deployment direncanakan.

## 9. Deployment Awal

### Local development

- Laravel berjalan melalui lingkungan XAMPP atau `php artisan serve`.
- MySQL/MariaDB dari XAMPP.
- `npm run dev` untuk frontend.
- `php artisan queue:work` untuk queue database.
- Python virtual environment untuk engine.
- LibreOffice terpasang di komputer yang menjalankan job.

### Server internal

- Web server menjalankan Laravel.
- Worker queue harus berjalan terus sebagai service/supervisor yang sesuai OS.
- Python dan LibreOffice tersedia untuk worker.
- Storage privat memiliki backup.
- HTTPS wajib bila diakses melalui jaringan.
- Atur batas upload di PHP, web server, dan aplikasi.

## 10. Keputusan yang Tidak Disarankan untuk MVP

- Jangan memakai Kubernetes.
- Jangan membuat Python microservice HTTP tanpa kebutuhan terukur.
- Jangan memakai AI generatif untuk mengubah teks soal.
- Jangan menyimpan file ujian pada public disk.
- Jangan membuat preview Word dengan membangun ulang dokumen dari HTML.
- Jangan mengembangkan OCR sebelum DOCX stabil.
