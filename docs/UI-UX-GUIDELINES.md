# UI/UX Guidelines

## 1. Arah Desain

SIMSOAL adalah alat kerja operator administrasi sekolah. Prioritas antarmuka adalah keterbacaan, kecepatan kerja, konsistensi, dan kejelasan status dokumen.

- Desktop-first; target utama layar laptop/desktop.
- Tampilan modern, bersih, profesional, dan tidak ramai.
- Gunakan sidebar untuk navigasi utama.
- Gunakan tabel untuk daftar dokumen karena operator akan mengelola banyak file.
- Gunakan form terstruktur dengan label jelas.
- Hindari animasi dekoratif berlebihan.
- Pastikan status tidak hanya dibedakan melalui warna; sertakan teks.

Warna dapat ditetapkan saat implementasi. Gunakan palet netral dengan satu warna primer dan warna status semantik. Jangan hardcode warna di banyak komponen; gunakan design tokens.

## 2. Struktur Navigasi

### Sidebar

1. Dashboard
2. Periode Ujian
3. Dokumen Soal
4. Template & Kop
5. Preset Format
6. Arsip
7. Pengguna (Admin saja)
8. Pengaturan (Admin saja)

### Header

- Breadcrumb.
- Nama halaman.
- Periode aktif (jika relevan).
- Informasi pengguna dan menu akun.

## 3. Halaman

### Dashboard

Komponen:
- Ringkasan jumlah dokumen.
- Ringkasan status: menunggu, diproses, perlu diperiksa, disetujui, gagal.
- Periode ujian aktif.
- Aktivitas terbaru.
- Tombol cepat `Buat Periode` dan `Upload Soal`.

Jangan menampilkan grafik yang tidak membantu tugas operator.

### Daftar Periode Ujian

- Tabel nama periode, tahun pelajaran, semester, tanggal, jumlah dokumen, status.
- Filter status dan tahun pelajaran.
- Search.
- Action: detail, edit, arsip.

### Detail Periode

- Ringkasan periode.
- Tabel dokumen di dalam periode.
- Tombol upload dokumen.
- Filter kelas, mapel, status.
- Progress ringkas pengolahan.

### Upload Dokumen

- Drag-and-drop dan tombol browse.
- Dukungan multi-file.
- Tampilkan nama, ukuran, ekstensi, dan hasil validasi.
- Jangan langsung memulai pemrosesan sebelum metadata dan preset dipastikan.
- Untuk MVP terima DOCX; format lain tampilkan penjelasan bahwa dukungan belum tersedia pada versi ini.

### Form Metadata

Field:
- Periode ujian.
- Mata pelajaran.
- Kelas.
- Nama guru.
- Tanggal ujian (opsional).
- Durasi (opsional).
- Judul naskah (opsional).
- Catatan operator.

Dukung pengisian massal untuk beberapa dokumen apabila mapel/kelas sama, tetapi tetap tampilkan nilai per file sebelum proses.

### Pengaturan Pemrosesan

Tampilkan:
- Preset format.
- Ringkasan ukuran halaman F4.
- Margin.
- Font dan ukuran.
- Spasi.
- Aksi kop: tambah, lewati, sumber sudah punya kop.
- Opsi pertahankan alignment/numbering.
- Peringatan bahwa sistem tidak mengubah isi soal.

Jangan membuat terlalu banyak pilihan teknis pada form utama. Pengaturan lanjutan dapat berada di accordion.

### Detail Dokumen / Review

Layout yang disarankan:
- Panel informasi dokumen dan status.
- Tab `Sumber`, `Hasil Word`, `Hasil PDF`, `Log`.
- Preview PDF di area utama.
- Tombol download sumber dan hasil.
- Daftar warning yang mudah dipahami.
- Tombol `Setujui hasil` dan `Minta proses ulang`.

Tombol persetujuan harus meminta konfirmasi melalui modal aplikasi, bukan browser alert/confirm.

### Template & Kop

- Preview kop.
- Upload logo.
- Edit baris identitas sekolah.
- Pengaturan garis dan alignment.
- Tombol simpan dan uji preview.

### Preset Format

- Tabel preset.
- Form pengaturan halaman dan tipografi.
- Tandai satu preset default.
- Tampilkan peringatan bahwa perubahan preset hanya berlaku untuk pemrosesan baru.

### Arsip

- Filter periode, tahun, kelas, mapel, dan status.
- Download dokumen final.
- Lihat sumber dan riwayat proses.

## 4. Komponen UI

Bangun komponen reusable:

- `AppSidebar`
- `AppHeader`
- `PageHeader`
- `StatCard`
- `DataTable`
- `FilterBar`
- `StatusBadge`
- `FileDropzone`
- `FileRow`
- `ProcessingTimeline`
- `WarningList`
- `ConfirmDialog`
- `Toast`
- `EmptyState`
- `LoadingState`
- `ErrorState`

Jangan duplikasi komponen yang sama pada setiap halaman.

## 5. Aturan UX

- Semua tombol harus memiliki aksi nyata.
- Tampilkan loading state untuk upload dan proses.
- Cegah double-submit.
- Berikan pesan sukses/gagal yang spesifik.
- Sediakan empty state yang menjelaskan langkah berikutnya.
- Form mempunyai validasi client dan server.
- Gunakan pagination server-side untuk data yang banyak.
- Search dan filter harus mempertahankan query saat berpindah halaman.
- Jangan menampilkan stack trace kepada operator.
- Jangan gunakan browser `alert()`, `confirm()`, atau `prompt()`; gunakan komponen modal/dialog/toast aplikasi.
- Pastikan keyboard focus dan kontras cukup.

## 6. Responsivitas

Desktop adalah target utama, tetapi halaman dasar tetap usable pada tablet. Pada layar kecil, sidebar dapat menjadi drawer dan tabel dapat memiliki horizontal scroll. Tidak perlu mengoptimalkan alur pemrosesan penuh untuk ponsel pada MVP.

## 7. Design Consistency

Sebelum membuat seluruh halaman:

1. Buat layout utama.
2. Tetapkan typography, spacing, radius, shadow, dan warna.
3. Buat komponen dasar.
4. Implementasikan Dashboard dan satu halaman tabel sebagai referensi.
5. Minta review visual.
6. Baru lanjutkan halaman lainnya.
