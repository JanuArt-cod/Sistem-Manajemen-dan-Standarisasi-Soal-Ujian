# Prompt Lanjutan per Milestone

Gunakan prompt yang sesuai setelah milestone sebelumnya lulus. Jangan menjalankan semua prompt sekaligus.

## Prompt Milestone 1 — Foundation

Baca ulang PRD, PROJECT-DECISIONS, SYSTEM-ARCHITECTURE, UI-UX-GUIDELINES, ROADMAP, dan PROJECT-PROGRESS. Kerjakan Milestone 1 saja: Laravel 12 foundation, Inertia + Vue + Tailwind, konfigurasi database, autentikasi, role Admin/Operator, layout desktop, dan navigasi. Sebelum coding, periksa repository serta versi package aktual. Jangan membuat modul dokumen atau Python engine dahulu. Terapkan policy/authorization, buat test relevan, uji login dan role, lalu perbarui PROJECT-PROGRESS.md. Laporkan file yang berubah dan cara menguji.

## Prompt Milestone 2 — Data Administrasi

Kerjakan Milestone 2 sesuai roadmap. Bangun profil sekolah, periode ujian, master mapel/kelas bila diperlukan, metadata dokumen, upload DOCX ke private storage, daftar/filter dokumen, validasi file, dan download terotorisasi. Jangan mulai processing engine. Uji upload valid, file palsu, akses role, dan penyimpanan privat. Jangan mengubah keputusan produk.

## Prompt Milestone 3 — Preset dan Template

Kerjakan Milestone 3. Bangun CRUD preset F4 dan template kop sesuai PRD. Pastikan semua pengaturan memiliki satuan jelas dan snapshot disimpan saat proses kelak dimulai. Buat preview kop. Jangan membuat engine pemrosesan dulu. Uji validasi preset, preset default, dan hak akses.

## Prompt Milestone 4 — DOCX Processing MVP

Baca DOCUMENT-PROCESSING.md secara penuh. Sebelum implementasi, buat test kecil pada fixtures untuk memverifikasi pendekatan python-docx/OOXML tidak merusak elemen yang digunakan sekolah. Bangun Python CLI dengan input/output/config/report terstruktur. Implementasikan hanya DOCX: F4, margin, pengaturan font/paragraph spacing, dan kop opsional. Jangan ubah teks atau substansi soal. Integrasikan melalui Laravel Queue dan Symfony Process menggunakan argumen terpisah, timeout, working directory unik, log aman, dan output path baru. Jalankan test fixtures. Jika ada struktur yang tidak aman, tampilkan warning NEEDS_REVIEW. Jangan mulai PDF input atau OCR.

## Prompt Milestone 5 — PDF Export dan Review

Kerjakan DOCX hasil ke PDF menggunakan LibreOffice headless. Buat deteksi binary, profile sementara unik untuk setiap job, validasi hasil, preview PDF yang terlindungi otorisasi, log, approval/revision workflow, serta download DOCX/PDF. Jika konversi PDF gagal, DOCX tetap tersedia. Uji seluruh alur dari upload hingga approval.

## Prompt Milestone 6 — Pilot

Siapkan checklist uji coba operator, data contoh, pencatatan hasil, backup, dan panduan penggunaan. Jangan menambah fitur besar sebelum pilot selesai. Analisis setiap kegagalan berdasarkan test case dan perbarui dokumentasi.

## Prompt Milestone 7 — Format Tambahan

Sebelum menambah DOC dan PDF, buat desain pipeline terpisah dan matriks risiko. Implementasikan satu format pada satu waktu. Untuk DOC, konversi dengan LibreOffice ke DOCX dan tandai kemungkinan perubahan layout. Untuk PDF digital, ekstraksi/rekonstruksi harus diberi label bahwa hasil tidak dijamin identik. Untuk PDF scan, OCR wajib masuk NEEDS_REVIEW. Jangan auto-approve dan jangan mengubah substansi soal.

## Prompt Milestone 8 — Fitur Lanjutan

Tambahkan deteksi struktur soal, pemeriksaan nomor, laporan, ZIP, dan arsip lanjutan hanya setelah pipeline dokumen stabil. Deteksi tidak boleh mengubah isi. Setiap anomali harus menjadi warning yang bisa diverifikasi operator.
