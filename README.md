# SIMSOAL — Starter Kit Proyek

**Sistem Manajemen dan Standarisasi Soal Ujian**

Starter kit dokumentasi untuk memulai pembangunan SIMSOAL menggunakan Chat AI Gemini (Hydrogen / penalaran mendalam).

## Tujuan proyek

SIMSOAL membantu operator sekolah mengolah dokumen soal dari guru menjadi dokumen ujian yang memiliki format sekolah yang seragam. Operator menjadi pengguna utama. Guru tidak perlu masuk ke aplikasi pada versi awal.

## Keputusan awal

- Backend: Laravel 12
- Frontend: Vue 3 + Inertia.js + Tailwind CSS
- Database: MySQL atau MariaDB yang tersedia di lingkungan pengembangan
- Pengolah DOCX: Python 3 + `python-docx`, dengan lapisan pemrosesan terpisah
- Konversi DOC/DOCX ke PDF: LibreOffice headless
- Antrean: Laravel Queue dengan database driver pada MVP
- Input target: DOCX, DOC, dan PDF
- Output target: DOCX dan PDF
- Ukuran halaman default: F4/Folio 21,5 × 33 cm
- Pengguna: Admin dan Operator
- Platform: Web desktop untuk penggunaan internal sekolah

## Urutan membaca

1. `docs/PRD.md` — kebutuhan produk dan ruang lingkup.
2. `docs/PROJECT-DECISIONS.md` — keputusan teknis dan produk yang sudah ditetapkan.
3. `docs/SYSTEM-ARCHITECTURE.md` — arsitektur aplikasi.
4. `docs/DOCUMENT-PROCESSING.md` — aturan paling penting untuk pengolahan berkas.
5. `docs/DATABASE-DESIGN.md` — rancangan basis data.
6. `docs/UI-UX-GUIDELINES.md` — pedoman antarmuka.
7. `docs/TESTING-PLAN.md` — strategi pengujian.
8. `docs/ROADMAP.md` dan `docs/PROJECT-PROGRESS.md` — tahapan dan status pengerjaan.
9. `SKILL.md` — instruksi kerja tetap untuk AI coding assistant.
10. `prompts/START-PROJECT-PROMPT.md` — prompt pertama untuk Gemini.

## Cara memulai di Windows + XAMPP

Contoh lokasi proyek:

```text
C:\xampp\htdocs\simsoal
```

Prasyarat yang perlu disiapkan:

- PHP 8.2 atau lebih baru dan Composer.
- Node.js dan npm.
- MySQL/MariaDB dari XAMPP.
- Python 3 dan `pip`.
- LibreOffice Desktop untuk konversi dokumen.
- Git.

Versi dependency Laravel dan package frontend harus diverifikasi saat proyek diinisialisasi. Jangan memasang package hanya karena AI mengasumsikan nama atau versi tertentu.

## Aturan pengembangan yang tidak boleh dilanggar

1. Bangun MVP DOCX terlebih dahulu. Jangan mulai OCR atau rekonstruksi PDF sebelum DOCX lulus pengujian.
2. Simpan file sumber asli dan jangan pernah menimpanya.
3. Jangan mengubah kalimat soal, jawaban, angka, simbol, urutan, tabel, gambar, maupun rumus secara otomatis.
4. Perlakukan setiap hasil pemrosesan sebagai file baru dan versi baru.
5. Jika sistem tidak yakin mempertahankan elemen dokumen, tandai `NEEDS_REVIEW`; jangan mengklaim berhasil sempurna.
6. Jangan melakukan OCR atau menggunakan AI generatif untuk menulis ulang isi soal pada MVP.
7. Jangan mengirim dokumen ujian ke layanan eksternal tanpa keputusan dan persetujuan eksplisit sekolah.
8. Semua proses file harus memvalidasi ekstensi, MIME/signature, ukuran, lokasi penyimpanan, dan hak akses.
9. Setiap tahap coding harus diuji sebelum berpindah ke modul berikutnya.
10. Perbarui `PROJECT-PROGRESS.md` dan `PROJECT-DECISIONS.md` saat ada perubahan.

## Catatan penting tentang PDF

PDF bukan format sumber yang setara dengan DOCX. PDF digital dapat diekstrak, tetapi menyusun ulang menjadi Word bisa mengubah tata letak. PDF scan membutuhkan OCR dan pemeriksaan manual. Karena itu, dukungan PDF penuh masuk fase lanjutan. Jangan menjanjikan konversi PDF ke DOCX yang identik.
