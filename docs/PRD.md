# Product Requirements Document (PRD)

## 1. Informasi Produk

| Item | Keterangan |
|---|---|
| Nama produk | SIMSOAL |
| Kepanjangan | Sistem Manajemen dan Standarisasi Soal Ujian |
| Jenis produk | Aplikasi web internal sekolah |
| Pengguna utama | Operator sekolah |
| Pengguna pendukung | Admin sistem |
| Platform | Desktop-first web application |
| Status dokumen | Baseline awal — dapat direvisi melalui keputusan proyek |
| Versi | 1.0 |

## 2. Latar Belakang

Setiap guru menyiapkan naskah soal dengan kebiasaan dan format yang berbeda. Dokumen dapat tidak memiliki kop sekolah, memakai ukuran kertas yang berbeda, margin tidak seragam, font dan spasi bervariasi, serta berisi kombinasi teks, pilihan jawaban, gambar, tabel, atau rumus.

Operator biasanya harus membuka setiap dokumen dan melakukan penyesuaian manual di Microsoft Word sebelum dokumen dapat dicetak. Proses ini memakan waktu, berulang, dan berisiko menyebabkan isi soal tidak sengaja berubah.

SIMSOAL dibuat untuk memusatkan proses penerimaan, standarisasi, pemeriksaan, ekspor, dan pengarsipan dokumen soal.

## 3. Pernyataan Masalah

1. Format dokumen soal dari guru tidak konsisten.
2. Pemasangan kop dan identitas ujian dilakukan berulang kali.
3. Ukuran halaman, margin, font, spasi, serta tata letak belum seragam.
4. Operator perlu memproses banyak file dalam waktu terbatas.
5. Dokumen sumber, dokumen hasil, dan revisi dapat tercampur jika tidak memiliki alur arsip yang jelas.
6. Konversi DOC, DOCX, dan PDF memiliki karakteristik berbeda sehingga perlu alur yang aman dan dapat diperiksa.

## 4. Tujuan Produk

- Mengurangi pekerjaan format dokumen yang berulang.
- Menghasilkan dokumen soal dengan standar halaman dan identitas sekolah.
- Memudahkan operator mengolah banyak dokumen dalam satu periode ujian.
- Menjaga dokumen asli dan menyediakan hasil pemrosesan yang dapat ditelusuri.
- Menyediakan hasil DOCX untuk revisi dan PDF untuk pencetakan.
- Memberi peringatan saat dokumen mengandung elemen yang mungkin tidak terjaga.

## 5. Indikator Keberhasilan

Target berikut merupakan sasaran awal yang harus diukur selama uji coba, bukan jaminan teknis:

1. Operator dapat membuat periode ujian dan mengunggah dokumen tanpa bantuan developer.
2. Dokumen DOCX sederhana dapat distandarkan ke F4 dengan hasil yang konsisten.
3. File sumber asli selalu tersedia dan tidak berubah.
4. Hasil DOCX dan PDF dapat dibuka dan dicetak.
5. Sistem mencatat status, waktu, dan log pemrosesan.
6. Dokumen dengan elemen yang tidak didukung ditandai untuk pemeriksaan manual.
7. Tidak ada dokumen ujian yang dapat diakses pengguna tanpa hak akses.

## 6. Pengguna dan Hak Akses

### 6.1 Admin

- Mengelola akun pengguna.
- Mengelola profil sekolah dan unit/jenjang bila digunakan.
- Mengelola logo, kop, template, dan preset format.
- Melihat seluruh periode, dokumen, log, dan arsip.
- Mengelola konfigurasi aplikasi.

### 6.2 Operator

- Membuat dan mengelola periode ujian.
- Mengunggah dokumen sumber.
- Mengisi metadata mapel, kelas, guru, dan identitas ujian.
- Memilih preset dan menjalankan pemrosesan.
- Memeriksa hasil dan peringatan.
- Menyetujui, mengulang proses, mengunduh, dan mengarsipkan dokumen.

Tidak ada akun guru pada MVP. Pengumpulan soal tetap berlangsung melalui alur sekolah yang sudah ada; operator mengunggah seluruh file.

## 7. Ruang Lingkup Produk

### 7.1 MVP — Wajib Dibangun

1. Autentikasi Admin dan Operator.
2. Dashboard ringkasan.
3. CRUD periode ujian.
4. Metadata dokumen soal.
5. Upload DOCX.
6. Penyimpanan file asli secara privat.
7. Profil sekolah dan pengaturan kop dasar.
8. Preset format dokumen.
9. Pengaturan ukuran F4, orientasi, margin, font, spasi, dan jarak paragraf.
10. Pemrosesan DOCX tanpa menulis ulang isi soal.
11. Penyisipan kop dan identitas ujian dengan opsi yang eksplisit.
12. Konversi hasil DOCX ke PDF menggunakan LibreOffice.
13. Preview PDF hasil.
14. Status pemrosesan, log, dan penanganan error.
15. Persetujuan operator sebelum status final.
16. Download DOCX dan PDF.
17. Arsip berdasarkan periode, kelas, dan mata pelajaran.
18. Pengujian dokumen contoh.

### 7.2 Fase Berikutnya

- Konversi DOC lama ke DOCX sebelum diproses.
- Ekstraksi PDF digital dan rekonstruksi DOCX dengan peringatan bahwa tata letak dapat berubah.
- OCR PDF scan.
- Analisis struktur soal dan penomoran.
- Penanda halaman atau elemen yang perlu diperiksa.
- Download paket ZIP.
- Riwayat revisi lanjutan dan laporan.

### 7.3 Di Luar Ruang Lingkup

- Membuat atau menulis soal menggunakan AI.
- Menjawab soal atau membuat kunci jawaban.
- Mengoreksi hasil ujian siswa.
- Sistem CBT/LMS.
- Portal guru untuk mengunggah soal pada MVP.
- Distribusi soal kepada siswa.
- Pengubahan redaksi, tingkat kesulitan, atau substansi soal.
- Konversi PDF scan menjadi DOCX identik tanpa pemeriksaan manusia.

## 8. Alur Pengguna Utama

1. Operator login.
2. Operator membuat periode ujian, misalnya `PAS Ganjil 2026/2027`.
3. Operator mengunggah satu atau beberapa file DOCX.
4. Sistem memvalidasi file dan menyimpan sumber asli secara privat.
5. Operator mengisi metadata: mata pelajaran, kelas, guru, waktu, dan catatan.
6. Operator memilih preset format dan tindakan kop.
7. Operator memulai pemrosesan.
8. Laravel membuat job antrean.
9. Worker memproses DOCX, menghasilkan DOCX baru, lalu membuat PDF.
10. Sistem menampilkan status dan hasil pemeriksaan otomatis.
11. Operator membuka preview, memeriksa halaman dan elemen dokumen.
12. Operator menyetujui hasil atau mengulang dengan preset berbeda.
13. Dokumen final tersedia untuk diunduh dan diarsipkan.

## 9. Persyaratan Fungsional

### FR-01 Autentikasi

- Pengguna harus login untuk mengakses aplikasi.
- Sistem menyediakan peran Admin dan Operator.
- Setiap halaman dan aksi harus menerapkan otorisasi berdasarkan peran.

### FR-02 Profil Sekolah

- Admin dapat mengatur nama yayasan/sekolah, alamat, kontak, logo, dan teks kop.
- Data profil dapat dipakai sebagai sumber template.
- Sistem harus mendukung konfigurasi unit/jenjang secara opsional, tanpa menjadikan multi-tenant sebagai kebutuhan MVP.

### FR-03 Periode Ujian

- Operator dapat membuat, mengubah, melihat, dan mengarsipkan periode.
- Data minimal: nama periode, tahun pelajaran, semester, tanggal mulai/selesai, status.
- Periode yang sudah diarsipkan tidak boleh menerima dokumen baru kecuali dibuka kembali oleh Admin.

### FR-04 Dokumen Soal

- Sistem menerima DOCX pada MVP.
- Data minimal: periode, mata pelajaran, kelas, nama guru, nama file sumber, jenis dokumen, status.
- Operator dapat mengunggah banyak file dalam satu sesi.
- Sistem memeriksa ukuran, ekstensi, MIME, dan signature file.
- File sumber disimpan terpisah dari file hasil.
- File yang sama tidak boleh menimpa file sebelumnya.

### FR-05 Preset Format

- Admin dapat membuat preset.
- Preset memuat ukuran kertas, orientasi, margin, font default, ukuran font, spasi, jarak paragraf, dan opsi header/footer.
- Preset default MVP adalah F4 portrait.
- Preset yang sudah digunakan tetap dapat ditelusuri oleh dokumen lama; perubahan preset tidak boleh mengubah hasil yang telah dibuat.

### FR-06 Kop dan Identitas

- Sistem dapat menyisipkan blok kop di awal dokumen.
- Operator dapat memilih: `Tambah kop`, `Tanpa kop`, atau `Dokumen sudah memiliki kop — jangan tambah`.
- Penggantian otomatis kop lama bukan bagian dari MVP.
- Sistem dapat menyisipkan metadata ujian bila dipilih.
- Kop dan metadata disisipkan tanpa menghapus isi asli.

### FR-07 Pemrosesan DOCX

- Sistem menghasilkan salinan baru.
- Sistem menerapkan ukuran F4 dan margin sesuai preset.
- Sistem menerapkan font dasar dan pengaturan paragraf yang dipilih.
- Secara default, alignment, numbering, indentation, tabel, gambar, dan rumus dipertahankan.
- Pemrosesan tidak boleh mengubah kalimat, angka, simbol, opsi jawaban, atau urutan isi.
- Sistem menyimpan log dan versi hasil.

### FR-08 Konversi PDF

- Sistem mengonversi DOCX hasil ke PDF melalui LibreOffice.
- Sistem memverifikasi file PDF terbentuk dan memiliki ukuran lebih dari nol.
- Kegagalan PDF tidak boleh menghapus DOCX hasil.
- PDF harus dapat dibuka melalui preview.

### FR-09 Pemeriksaan dan Persetujuan

- Operator melihat status proses.
- Operator dapat mengunduh sumber dan hasil.
- Operator dapat memberi status `Disetujui` atau `Perlu revisi`.
- Sistem menampilkan daftar peringatan teknis.
- Sistem tidak boleh otomatis menyetujui dokumen.

### FR-10 Arsip

- Dokumen dapat dicari berdasarkan periode, kelas, mapel, guru, dan status.
- Dokumen final dan sumber tetap saling terhubung.
- Penghapusan file memerlukan hak Admin dan konfirmasi.

### FR-11 Log

- Sistem mencatat pengguna, waktu, nama proses, status, pesan error, dan versi output.
- Log tidak boleh menyimpan isi soal secara penuh.

## 10. Persyaratan Non-Fungsional

### NFR-01 Keamanan

- File disimpan di storage privat, bukan direktori public.
- Download harus melalui controller yang memeriksa otorisasi.
- Validasi jenis file dilakukan menggunakan ekstensi dan MIME/signature.
- Nama file penyimpanan dibuat unik dan tidak memakai nama sumber secara langsung sebagai path.
- Batasi ukuran dan jumlah file upload melalui konfigurasi.
- Lindungi dari path traversal, file executable, dan akses langsung.
- Gunakan CSRF protection dan validasi request Laravel.
- Jangan mengekspos path absolut server kepada pengguna.

### NFR-02 Privasi

- Dokumen ujian bersifat rahasia.
- Tidak ada upload otomatis ke API AI atau layanan cloud eksternal.
- Penggunaan OCR/cloud service di masa depan perlu keputusan dan persetujuan sekolah.

### NFR-03 Keandalan

- Proses berat berjalan melalui queue.
- Job memiliki timeout, retry terbatas, dan status gagal.
- File sumber tidak diubah.
- Proses yang gagal harus dapat dijalankan ulang tanpa membuat duplikasi hasil final.

### NFR-04 Kemudahan Penggunaan

- Antarmuka desktop-first.
- Tindakan utama jelas: upload, proses, preview, setujui, download.
- Status menggunakan teks dan indikator visual yang konsisten.
- Form menggunakan validasi dan pesan kesalahan yang mudah dipahami.

### NFR-05 Auditabilitas

- Setiap pemrosesan dapat ditelusuri ke file sumber, preset, pengguna, waktu, dan versi engine.

## 11. Status Dokumen

Gunakan status berikut:

- `UPLOADED` — file sumber berhasil diunggah.
- `VALIDATION_FAILED` — file tidak lolos validasi.
- `QUEUED` — menunggu diproses.
- `PROCESSING` — sedang diproses.
- `PROCESSED` — DOCX hasil berhasil dibuat.
- `PDF_FAILED` — DOCX berhasil, konversi PDF gagal.
- `NEEDS_REVIEW` — hasil memiliki peringatan atau perlu pemeriksaan operator.
- `APPROVED` — telah disetujui operator.
- `REVISION_REQUIRED` — operator meminta proses ulang/revisi.
- `FAILED` — pemrosesan utama gagal.
- `ARCHIVED` — masuk arsip.

Status proses dan status persetujuan dapat dipisahkan secara teknis jika implementasi membutuhkannya.

## 12. Aturan Bisnis

1. Dokumen sumber tidak pernah ditimpa.
2. Setiap proses menghasilkan record run/versi baru.
3. Satu file hasil hanya dapat disetujui setelah pemeriksaan operator.
4. Preset disalin sebagai snapshot pengaturan ketika proses dimulai agar perubahan preset di masa depan tidak mengubah histori.
5. PDF scan tidak dianggap setara dengan DOCX.
6. Jika proses tidak yakin mempertahankan objek khusus, hasil ditandai `NEEDS_REVIEW`.
7. Sistem tidak mengubah isi soal secara semantik.
8. Dokumen final harus mempunyai relasi ke periode ujian dan metadata mapel/kelas.

## 13. Risiko dan Mitigasi

| Risiko | Mitigasi |
|---|---|
| Format Word terlalu kompleks | Batasi MVP pada DOCX; sediakan peringatan dan pemeriksaan manual |
| Objek gambar/rumus berubah | Gunakan test fixtures nyata dan uji perbandingan sebelum/sesudah |
| LibreOffice tidak tersedia | Deteksi instalasi; tampilkan error konfigurasi; DOCX hasil tetap tersedia |
| PDF scan sulit dikenali | Pindahkan ke fase OCR; hasil wajib diverifikasi |
| Proses file lama | Gunakan queue, timeout, dan log |
| File ujian bocor | Storage privat, otorisasi, audit log, dan tanpa link publik |
| AI coding membuat fitur terlalu banyak | Ikuti roadmap dan satu modul per iterasi |

## 14. Kriteria Penerimaan MVP

MVP dapat dinyatakan layak uji coba apabila:

- Admin dapat mengatur profil sekolah dan preset.
- Operator dapat membuat periode dan mengunggah DOCX.
- Sistem menyimpan sumber asli tanpa perubahan.
- DOCX hasil mempunyai ukuran halaman F4 dan margin sesuai preset.
- Kop/metadata dapat ditambahkan melalui pilihan eksplisit.
- Konten dasar, tabel, gambar inline, dan numbering pada test fixtures tetap utuh.
- PDF dapat dibuat dan dibuka.
- Kegagalan satu tahap tercatat dan tidak menghapus artefak yang berhasil.
- Operator dapat memeriksa dan menyetujui hasil.
- Dokumen hanya dapat diunduh oleh pengguna yang berwenang.
- Seluruh alur diuji dengan data contoh dan hasilnya dicatat.

## 15. Pertanyaan yang Ditunda

Pertanyaan berikut tidak menghalangi MVP dan dapat diputuskan setelah melihat dokumen asli:

- Apakah kop harus tampil hanya di halaman pertama atau semua halaman?
- Apakah identitas ujian berada dalam tabel dua kolom atau format bebas?
- Apakah halaman terakhir perlu kolom tanda tangan?
- Apakah sekolah memiliki beberapa unit/jenjang dengan kop berbeda?
- Apakah PDF perlu dipertahankan sebagai PDF saja atau direkonstruksi ke DOCX?
- Berapa batas ukuran dan jumlah file per batch?
