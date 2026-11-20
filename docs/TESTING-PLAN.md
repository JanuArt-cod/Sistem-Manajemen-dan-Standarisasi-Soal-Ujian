# Testing Plan

## 1. Sasaran

Memastikan aplikasi aman, alur operator berfungsi, file sumber tidak berubah, dan hasil standarisasi tidak merusak konten dokumen yang didukung.

## 2. Test Fixtures

Sebelum mengembangkan document engine, siapkan salinan dokumen contoh tanpa data rahasia/identitas sensitif bila memungkinkan.

Minimum fixtures:

1. DOCX sederhana dengan paragraf dan pilihan A–E.
2. DOCX dengan numbering/list otomatis.
3. DOCX dengan tabel.
4. DOCX dengan gambar inline.
5. DOCX dengan gambar floating atau drawing kompleks jika ditemukan.
6. DOCX dengan rumus Equation.
7. DOCX dengan header/footer dan page number.
8. DOCX multi-section atau landscape section.
9. DOC/DOCX lama untuk pengujian konversi fase berikutnya.
10. PDF digital dan PDF scan untuk fase selanjutnya.

Jangan memasukkan soal ujian aktif yang rahasia ke repository Git. Simpan fixtures lokal yang aman, dengan instruksi cara menyiapkannya.

## 3. Pengujian Unit

### Laravel

- Validasi periode.
- Validasi metadata dokumen.
- Upload ukuran dan tipe file.
- Otorisasi Admin/Operator.
- Transisi status processing run.
- Download privat.
- Snapshot preset.

### Python

- Membuka DOCX valid.
- Menolak file bukan DOCX.
- Mengatur page size dan margin.
- Mempertahankan paragraf, tabel, dan gambar pada fixtures.
- Menghasilkan report JSON valid.
- Mengembalikan kode error yang sesuai.

## 4. Pengujian Integrasi

- Upload → storage privat → database.
- Dispatch queue → worker → engine Python.
- Engine output → validasi output.
- DOCX output → LibreOffice → PDF.
- PDF output → preview terotorisasi.
- Approve → status final.
- Retry job gagal tanpa menimpa sumber atau versi lama.

## 5. Pengujian Visual

Untuk setiap fixture:

1. Buka dokumen sumber di Word atau LibreOffice.
2. Ekspor sumber ke PDF sebagai baseline.
3. Jalankan SIMSOAL.
4. Buka PDF hasil.
5. Bandingkan setiap halaman.
6. Periksa soal pertama, tengah, terakhir, page break, tabel, gambar, rumus, dan footer.
7. Catat perubahan yang diharapkan dan yang tidak diharapkan.

Jika tersedia, gunakan visual diff image untuk membantu, tetapi hasil akhir tetap diperiksa manusia.

## 6. Pengujian Non-Fungsional

- File terlalu besar ditolak dengan pesan yang jelas.
- File ekstensi DOCX tetapi bukan paket DOCX ditolak.
- Nama file berbahaya tidak memengaruhi path penyimpanan.
- Pengguna tidak berwenang tidak dapat membuka file melalui URL langsung.
- Job yang timeout menghasilkan status gagal dan log.
- Dua job bersamaan tidak menggunakan temporary directory/profile yang sama.
- File sumber checksum sebelum dan sesudah sama.
- Tidak ada isi soal yang tercetak di log aplikasi.

## 7. Matriks Penerimaan MVP

| ID | Skenario | Hasil yang diharapkan |
|---|---|---|
| T-01 | Login Admin | Admin masuk dan melihat menu admin |
| T-02 | Login Operator | Operator masuk tanpa menu pengelolaan user |
| T-03 | Upload DOCX valid | File tersimpan secara privat |
| T-04 | Upload file palsu berekstensi DOCX | Ditolak |
| T-05 | Buat periode ujian | Periode tersimpan |
| T-06 | Proses DOCX sederhana | DOCX F4 dibuat |
| T-07 | Cek margin | Sesuai preset |
| T-08 | Tambah kop | Kop tersisip tanpa menghapus isi sumber |
| T-09 | Skip kop | Kop tidak ditambahkan |
| T-10 | DOCX bertabel | Tabel tetap ada dan terbaca |
| T-11 | DOCX bergambar | Gambar tetap ada dan terlihat |
| T-12 | DOCX numbering | Urutan numbering tidak rusak |
| T-13 | Export PDF | PDF dapat dibuka |
| T-14 | LibreOffice gagal | DOCX tetap tersedia, status PDF_FAILED |
| T-15 | Approval | Hanya Operator/Admin berhak menyetujui |
| T-16 | Download tanpa hak | Akses ditolak |
| T-17 | Retry | Versi lama tetap ada, versi baru tercatat |

## 8. Definition of Done per Modul

Sebuah modul dianggap selesai jika:

- Alur normal berhasil.
- Validasi input tersedia.
- Hak akses diperiksa.
- Loading, empty, success, dan error state tersedia.
- Tidak ada tombol palsu/inert.
- Test yang relevan lulus.
- Dokumentasi/progress diperbarui.
- Tidak ada regresi pada modul yang sudah selesai.
