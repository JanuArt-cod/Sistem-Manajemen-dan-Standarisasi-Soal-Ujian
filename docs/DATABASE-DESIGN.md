# Database Design

Rancangan berikut adalah baseline relasional. Nama kolom dapat disesuaikan dengan konvensi Laravel, tetapi relasi dan prinsip versioning harus dipertahankan.

## 1. Entitas Utama

```text
users
  └── created exam_periods
          └── exam_documents
                  ├── document_files
                  └── document_processing_runs
                         ├── processing_logs
                         └── document_files (generated artifacts)

school_profiles
  └── document_templates

formatting_presets
  └── document_processing_runs (snapshot preset)
```

## 2. Tabel

### users

Kolom inti:

- id
- name
- email (unique)
- password
- role (`ADMIN`, `OPERATOR`)
- is_active
- email_verified_at (opsional)
- timestamps

Gunakan hashing password Laravel. Jangan menyimpan password mentah.

### school_profiles

- id
- institution_name
- foundation_name (nullable)
- address (nullable)
- phone (nullable)
- email (nullable)
- logo_path (nullable, private atau asset terkontrol)
- header_lines (JSON atau field terstruktur)
- is_active
- timestamps

Untuk MVP, satu profil aktif dapat digunakan. Jika beberapa unit membutuhkan kop berbeda, tambah field `unit_name` atau tabel unit melalui keputusan terpisah.

### exam_periods

- id
- name
- academic_year (contoh `2026/2027`)
- semester (`ODD`, `EVEN`, `CUSTOM`)
- start_date (nullable)
- end_date (nullable)
- status (`DRAFT`, `ACTIVE`, `ARCHIVED`)
- created_by (FK users)
- timestamps
- softDeletes (opsional)

### subjects

- id
- name
- code (nullable)
- is_active
- timestamps

### school_classes

- id
- name (contoh `IX A`)
- level (nullable)
- is_active
- timestamps

Tabel `subjects` dan `school_classes` mendukung pilihan autocomplete dan konsistensi penamaan. Untuk MVP sederhana, nilai string dapat disimpan di `exam_documents`, tetapi tabel referensi lebih baik jika data dipakai berulang.

### exam_documents

- id
- exam_period_id (FK)
- subject_id (FK nullable)
- school_class_id (FK nullable)
- teacher_name
- exam_date (nullable)
- duration_minutes (nullable)
- document_title (nullable)
- source_format (`DOCX`, `DOC`, `PDF`)
- current_status
- notes (nullable)
- uploaded_by (FK users)
- approved_by (FK users nullable)
- approved_at (nullable)
- timestamps
- softDeletes (opsional)

### document_files

Menyimpan semua file sumber dan artefak. Gunakan beberapa record per dokumen.

- id
- exam_document_id (FK)
- processing_run_id (FK nullable)
- file_role (`SOURCE`, `NORMALIZED_SOURCE`, `OUTPUT_DOCX`, `OUTPUT_PDF`, `PREVIEW`, `OTHER`)
- original_filename
- storage_disk
- storage_path
- mime_type
- extension
- size_bytes
- checksum_sha256
- is_current
- created_by (FK users nullable)
- timestamps

Catatan:
- Jangan memakai original filename sebagai path fisik.
- `storage_path` mengarah ke storage privat.
- `checksum_sha256` membantu mendeteksi duplikasi dan memastikan sumber tidak berubah.

### formatting_presets

- id
- name
- description (nullable)
- page_width_mm (default 215)
- page_height_mm (default 330)
- orientation (`PORTRAIT`, `LANDSCAPE`)
- margin_top_mm (default 20)
- margin_bottom_mm (default 20)
- margin_left_mm (default 25)
- margin_right_mm (default 20)
- default_font_name (default `Times New Roman`)
- default_font_size_pt (default 12)
- line_spacing (default 1.0)
- paragraph_space_before_pt (default 0)
- paragraph_space_after_pt (default 0)
- preserve_alignment (default true)
- preserve_numbering (default true)
- is_default
- is_active
- created_by (FK users)
- timestamps

Semua satuan harus eksplisit. Di database gunakan milimeter untuk ukuran halaman/margin agar tidak ambigu.

### document_templates

- id
- name
- template_type (`SCHOOL_LETTERHEAD`, `EXAM_IDENTITY`, `FOOTER`)
- school_profile_id (FK nullable)
- configuration (JSON)
- is_default
- is_active
- created_by (FK users)
- timestamps

### document_processing_runs

- id
- exam_document_id (FK)
- source_file_id (FK document_files)
- preset_id (FK nullable)
- preset_snapshot (JSON)
- template_snapshot (JSON nullable)
- engine_name
- engine_version (nullable)
- status
- started_at (nullable)
- finished_at (nullable)
- duration_ms (nullable)
- report (JSON nullable)
- error_code (nullable)
- error_message (nullable; sanitized)
- initiated_by (FK users)
- timestamps

Simpan `preset_snapshot`, bukan hanya relasi ke preset aktif. Ini menjaga riwayat proses tetap dapat dijelaskan.

### processing_logs

- id
- processing_run_id (FK)
- level (`INFO`, `WARNING`, `ERROR`)
- event
- message
- context (JSON nullable)
- created_at

Jangan menyimpan isi soal, password, token, atau path sensitif di log.

## 3. Status yang Direkomendasikan

Pisahkan status pemrosesan dari status persetujuan bila membuat enum yang terpisah:

- processing_status: `QUEUED`, `PROCESSING`, `PROCESSED`, `PDF_FAILED`, `FAILED`
- review_status: `PENDING`, `NEEDS_REVIEW`, `APPROVED`, `REVISION_REQUIRED`
- archive_status atau status periode: `ACTIVE`, `ARCHIVED`

Ini mencegah satu kolom status menjadi terlalu banyak kombinasi. Untuk MVP, boleh memakai kolom status sederhana selama transisinya terdokumentasi.

## 4. Indeks dan Constraint

- Index `exam_documents.exam_period_id`, `subject_id`, `school_class_id`, `current_status`.
- Index `document_files.exam_document_id`, `processing_run_id`, `checksum_sha256`.
- Index `document_processing_runs.exam_document_id`, `status`, `created_at`.
- Foreign key gunakan kebijakan delete yang aman. Jangan cascade-delete file arsip tanpa alur penghapusan eksplisit.
- Email pengguna unique.
- Hindari unique constraint pada checksum global jika sekolah boleh mengunggah file sama untuk dua mapel/periode; gunakan sebagai peringatan duplikasi saja.

## 5. Catatan Implementasi

1. Buat migration kecil per kelompok tabel.
2. Buat model, relasi, casts JSON, factory, dan seeder.
3. Seed Admin awal melalui command atau seeder aman; jangan hardcode password produksi.
4. Buat policy untuk `ExamPeriod`, `ExamDocument`, dan download `DocumentFile`.
5. Jangan simpan file biner di MySQL; simpan path storage privat.
6. Lakukan transaksi database saat membuat metadata dan record file, tetapi pahami bahwa filesystem tidak otomatis ikut rollback. Siapkan cleanup bila penyimpanan gagal.
