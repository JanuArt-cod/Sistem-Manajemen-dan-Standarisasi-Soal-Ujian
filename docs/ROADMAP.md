# Project Roadmap

Roadmap ini berbasis milestone, bukan estimasi tanggal. Jangan melanjutkan milestone jika kriteria penerimaannya belum lulus.

## Milestone 0 — Validasi dan Persiapan

- [ ] Baca seluruh dokumen starter.
- [ ] Periksa versi PHP, Composer, Node, MySQL/MariaDB, Python, dan LibreOffice.
- [ ] Siapkan test fixtures aman.
- [ ] Tentukan contoh kop dan format identitas sekolah.
- [ ] Buat repository Git dan baseline proyek.

Exit criteria:
- Lingkungan development terdokumentasi.
- Dokumen contoh tersedia.
- Keputusan yang belum diketahui dicatat, bukan ditebak.

## Milestone 1 — Foundation

- [ ] Inisialisasi Laravel 12.
- [ ] Integrasi Inertia + Vue + Tailwind.
- [ ] Konfigurasi database.
- [ ] Autentikasi.
- [ ] Role Admin dan Operator.
- [ ] Layout desktop dan navigasi.
- [ ] Seeder Admin development yang aman.

Exit criteria:
- Pengguna dapat login/logout.
- Role dan policy bekerja.
- Layout konsisten.

## Milestone 2 — Data Administrasi

- [ ] Profil sekolah.
- [ ] Periode ujian.
- [ ] Referensi mapel.
- [ ] Referensi kelas.
- [ ] CRUD dokumen dan metadata.
- [ ] Upload DOCX ke storage privat.
- [ ] Daftar dan filter dokumen.

Exit criteria:
- Dokumen dapat diunggah dan dicari.
- File hanya dapat diunduh pengguna berwenang.

## Milestone 3 — Preset dan Template

- [ ] CRUD preset format.
- [ ] Preset F4 default.
- [ ] Template kop.
- [ ] Form pengaturan pemrosesan.
- [ ] Snapshot preset/template.

Exit criteria:
- Operator dapat memilih pengaturan sebelum proses.
- Histori menyimpan snapshot yang dipakai.

## Milestone 4 — DOCX Processing MVP

- [ ] Python virtual environment.
- [ ] Document engine CLI.
- [ ] Kontrak input/output JSON.
- [ ] Ubah ukuran halaman F4.
- [ ] Terapkan margin.
- [ ] Terapkan font dan paragraph spacing.
- [ ] Pertahankan alignment/numbering secara default.
- [ ] Sisipkan kop dengan pilihan eksplisit.
- [ ] Validasi output dan report.
- [ ] Jalankan melalui Laravel Queue.

Exit criteria:
- Semua fixture MVP lulus pemeriksaan.
- Sumber tidak berubah.
- Warning ditampilkan.

## Milestone 5 — PDF Export dan Review

- [ ] Deteksi LibreOffice.
- [ ] Konversi DOCX ke PDF.
- [ ] Preview PDF.
- [ ] Detail processing run dan log.
- [ ] Approve/revision workflow.
- [ ] Download DOCX/PDF.

Exit criteria:
- Alur upload sampai approved dan download berfungsi.

## Milestone 6 — Pilot

- [ ] Uji dengan dokumen nyata yang telah diizinkan.
- [ ] Bandingkan hasil di Microsoft Word dan PDF.
- [ ] Catat format yang gagal.
- [ ] Perbaiki engine hanya berdasarkan test case.
- [ ] Buat panduan operator.

Exit criteria:
- Operator dapat memakai sistem dengan supervisi dan mengetahui batasannya.

## Milestone 7 — Format Tambahan

- [ ] Konversi DOC ke DOCX.
- [ ] Ekstraksi PDF digital.
- [ ] Rekonstruksi PDF digital dengan warning.
- [ ] Pengenalan PDF scan.
- [ ] OCR dan workflow review.

Exit criteria:
- Setiap format memiliki pipeline, status, dan kriteria penerimaan sendiri.

## Milestone 8 — Fitur Lanjutan

- [ ] Deteksi struktur soal.
- [ ] Deteksi nomor soal yang tidak berurutan.
- [ ] Statistik isi dokumen.
- [ ] Download ZIP.
- [ ] Laporan dan arsip lanjutan.
