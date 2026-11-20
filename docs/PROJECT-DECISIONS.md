# Project Decisions Log

Keputusan di sini menjadi sumber kebenaran proyek. AI tidak boleh mengubah keputusan yang berstatus `LOCKED` tanpa menjelaskan alasan, dampak, dan meminta keputusan pemilik proyek.

## D-001 — Nama dan Tujuan Produk

- Status: LOCKED
- Keputusan: Nama produk SIMSOAL — Sistem Manajemen dan Standarisasi Soal Ujian.
- Alasan: Fokus pada penerimaan, standarisasi, pemeriksaan, ekspor, dan arsip naskah soal.

## D-002 — Pengguna MVP

- Status: LOCKED
- Keputusan: Admin dan Operator; Operator adalah pengguna kerja utama. Guru belum memiliki akun pada MVP.

## D-003 — Pola Pengumpulan

- Status: LOCKED
- Keputusan: Operator mengunggah dokumen yang diterima dari guru.

## D-004 — Input dan Output

- Status: LOCKED untuk target produk; bertahap untuk implementasi.
- Keputusan target: Input DOC, DOCX, PDF; output DOCX dan PDF.
- Keputusan MVP: DOCX dahulu; DOC dan PDF ditambahkan pada fase berikutnya.
- Alasan: DOCX memiliki jalur standarisasi paling terkontrol. PDF dan DOC memerlukan pipeline khusus.

## D-005 — Jenis Konten

- Status: LOCKED
- Keputusan: Dokumen dapat berisi pilihan ganda, isian, uraian, gambar, tabel, dan rumus.
- Batasan: Sistem tidak menulis ulang atau mengubah substansi konten.

## D-006 — Platform dan UI

- Status: LOCKED
- Keputusan: Web application internal, desktop-first untuk operator.

## D-007 — Stack Aplikasi

- Status: LOCKED
- Keputusan: Laravel 12, Vue 3, Inertia.js, Tailwind CSS, dan MySQL/MariaDB.

## D-008 — Document Engine

- Status: LOCKED sebagai arah arsitektur
- Keputusan: Python CLI terpisah untuk pemrosesan dokumen, dipanggil oleh Laravel Queue. LibreOffice digunakan untuk konversi.
- Batasan: Tidak membuat microservice HTTP pada MVP.

## D-009 — Ukuran Halaman Default

- Status: LOCKED sebagai preset awal
- Keputusan: F4 215 × 330 mm, portrait; margin atas 20 mm, bawah 20 mm, kiri 25 mm, kanan 20 mm.
- Catatan: Dapat diubah melalui preset jika kebijakan sekolah berbeda.

## D-010 — Tipografi Default

- Status: LOCKED sebagai preset awal
- Keputusan: Times New Roman 12 pt, line spacing 1.0, paragraph spacing 0 pt.
- Catatan: Pertahankan alignment dan numbering dari dokumen sumber pada MVP.

## D-011 — Penyimpanan Sumber

- Status: LOCKED
- Keputusan: File sumber disimpan secara privat, tidak pernah ditimpa, dan setiap proses menghasilkan versi baru.

## D-012 — PDF dan OCR

- Status: LOCKED
- Keputusan: PDF digital dan PDF scan bukan fitur MVP. OCR masuk fase lanjutan dan hasilnya wajib diperiksa manual.

## D-013 — AI dalam Konten Soal

- Status: LOCKED
- Keputusan: AI generatif tidak boleh mengubah, memperbaiki, meringkas, atau menulis ulang soal pada MVP.

## D-014 — Antarmuka Konfirmasi

- Status: LOCKED
- Keputusan: Gunakan modal/dialog/toast buatan aplikasi. Jangan memakai browser alert/confirm/prompt.

## D-015 — Workflow AI Coding

- Status: LOCKED
- Keputusan: Satu milestone/modul pada satu waktu. Jalankan test sebelum melanjutkan. Perbarui roadmap, progress, dan decision log.

## Open Decisions

1. Kop tampil hanya halaman pertama atau seluruh halaman.
2. Layout identitas ujian.
3. Apakah tanda tangan/pengesahan dibutuhkan.
4. Apakah perlu beberapa kop untuk beberapa unit/jenjang.
5. Batas ukuran upload dan batch.
6. Kebijakan retensi arsip dan backup.

Setiap open decision harus diselesaikan berdasarkan contoh dokumen dan kebijakan sekolah, bukan asumsi AI.
