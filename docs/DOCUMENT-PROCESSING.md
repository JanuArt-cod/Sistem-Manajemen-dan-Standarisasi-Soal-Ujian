# Document Processing Specification

Dokumen ini adalah spesifikasi paling kritis SIMSOAL. Implementasi harus mengikuti prinsip non-destruktif dan diuji dengan dokumen nyata.

## 1. Tujuan

Menghasilkan salinan dokumen ujian dengan pengaturan halaman dan format sekolah tanpa mengubah substansi soal.

## 2. Definisi Format

- DOCX: format utama MVP.
- DOC: format Word lama; pada fase selanjutnya dikonversi ke DOCX menggunakan LibreOffice sebelum pemrosesan.
- PDF digital: PDF dengan lapisan teks; ekstraksi dan rekonstruksi ke DOCX memiliki potensi perubahan tata letak.
- PDF scan: halaman berupa gambar; perlu OCR dan pemeriksaan manual.

## 3. Kebijakan Non-Destruktif

Dilarang pada semua fase:

- Menulis ulang kalimat soal.
- Memperbaiki ejaan/grammar secara otomatis.
- Mengubah angka, satuan, simbol, rumus, opsi jawaban, atau kunci jawaban.
- Menghapus paragraf kosong tanpa aturan yang disetujui.
- Mengurutkan ulang soal.
- Mengubah tabel menjadi teks atau sebaliknya tanpa persetujuan.
- Mengubah gambar menjadi teks atau mengganti gambar.
- Menggunakan AI generatif untuk parafrasa dokumen.

Setiap output adalah salinan baru. File sumber dan checksum sumber harus tetap tersedia.

## 4. Preset F4 Default

Gunakan ukuran fisik:

- Lebar: 215 mm (21,5 cm)
- Tinggi: 330 mm (33 cm)
- Orientasi: Portrait
- Margin atas: 20 mm
- Margin bawah: 20 mm
- Margin kiri: 25 mm
- Margin kanan: 20 mm

Default tipografi:

- Font: Times New Roman
- Ukuran: 12 pt
- Line spacing: 1.0
- Space before: 0 pt
- Space after: 0 pt

Aturan default:

- Pertahankan alignment yang sudah ada.
- Pertahankan indentation dan numbering/list.
- Pertahankan tabel dan gambar.
- Jangan memaksakan semua paragraf menjadi justify.
- Jangan menghapus page break atau section break tanpa alasan dan test.

Pengaturan F4 adalah konfigurasi default sekolah, bukan jaminan bahwa setiap printer memiliki area cetak sampai tepi halaman. Hasil harus diuji dengan printer sekolah.

## 5. Tindakan Kop

MVP menyediakan pilihan eksplisit:

1. `ADD_LETTERHEAD`: sisipkan kop terkonfigurasi pada awal dokumen.
2. `SKIP_LETTERHEAD`: jangan menambahkan kop.
3. `SOURCE_HAS_LETTERHEAD`: anggap sumber sudah memiliki kop; jangan menambahkan kop baru.

MVP tidak menghapus atau mengganti kop lama secara otomatis.

Sebelum dipakai, uji penyisipan kop pada DOCX dengan:
- Dokumen kosong.
- Dokumen yang dimulai dengan judul.
- Dokumen dengan tabel di awal.
- Dokumen dengan section break.
- Dokumen dengan header/footer sendiri.

Jika kop disisipkan sebagai blok di awal body, jangan menghapus header/footer asli tanpa izin. Tentukan apakah kop hanya halaman pertama atau seluruh halaman melalui keputusan sekolah; default MVP: blok kop pada awal dokumen/halaman pertama.

## 6. Normalisasi DOCX MVP

Urutan proses:

1. Validasi file sebagai paket DOCX/OOXML yang dapat dibuka.
2. Salin file sumber ke working directory unik.
3. Baca preset snapshot.
4. Periksa jumlah section dan elemen yang diketahui.
5. Atur page width/height dan margins per section.
6. Atur style Normal dan run teks sesuai opsi preset tanpa menghapus bold/italic/underline.
7. Terapkan line spacing dan paragraph spacing sesuai pilihan.
8. Jangan mengubah alignment, indentation, numbering, tabel, gambar, atau objek rumus pada konfigurasi default.
9. Sisipkan kop/identitas hanya jika dipilih dan fitur sudah lulus pengujian.
10. Simpan output ke path baru.
11. Buka kembali output untuk validasi struktur dasar.
12. Buat report JSON dan peringatan.

Penting: `python-docx` tidak mendukung seluruh fitur Microsoft Word. Elemen OOXML yang tidak dikenal, content controls, field tertentu, objek OLE, drawing kompleks, tracked changes, atau fitur khusus dapat berperilaku berbeda saat disimpan. Karena itu:

- Jangan mengklaim preservasi sempurna untuk semua file.
- Buat fixture untuk elemen penting yang dijumpai di sekolah.
- Bila terdeteksi fitur kompleks yang tidak didukung atau hasil validasi meragukan, tandai `NEEDS_REVIEW`.
- Untuk dokumen kompleks, sediakan opsi melewati normalisasi otomatis dan hanya melakukan pemeriksaan manual.

## 7. DOC ke DOCX (Fase 2)

- Jalankan LibreOffice headless di working directory terisolasi.
- Konversi ke DOCX.
- Validasi hasil konversi.
- Simpan file normalisasi sebagai artefak terpisah.
- Catat peringatan bahwa layout mungkin berubah.
- Lanjutkan ke pipeline DOCX hanya setelah operator menyetujui jenis input dan sistem dapat mendeteksi hasil konversi valid.

## 8. PDF Digital (Fase 2)

PDF tidak memiliki struktur paragraf Word yang sama. Jangan menjanjikan rekonstruksi identik.

Strategi:

1. Deteksi apakah PDF memiliki text layer.
2. Ekstrak teks, halaman, posisi blok, dan gambar bila diperlukan.
3. Buat dokumen DOCX hasil rekonstruksi dengan label bahwa layout hasil konversi dapat berubah.
4. Tampilkan sumber PDF dan hasil DOCX untuk pemeriksaan.
5. Hanya buat PDF final dari DOCX setelah operator menyetujui rekonstruksi.

Sediakan alternatif: PDF tetap disimpan sebagai sumber dan operator dapat memilih untuk tidak mengubahnya.

## 9. PDF Scan / OCR (Fase 3)

- Deteksi PDF image-only atau halaman tanpa text layer memadai.
- Render halaman ke gambar pada resolusi yang sesuai.
- OCR teks dengan koordinat.
- Pertahankan halaman sumber sebagai referensi.
- Jangan menganggap OCR rumus, simbol, tabel, dan pilihan jawaban selalu benar.
- Tandai seluruh hasil OCR sebagai `NEEDS_REVIEW`.
- Jangan auto-approve OCR.

OCR bukan fitur MVP.

## 10. DOCX ke PDF

- Gunakan LibreOffice headless.
- Gunakan user profile sementara yang unik untuk setiap job agar proses paralel tidak saling mengunci.
- Tentukan output directory eksplisit.
- Verifikasi PDF terbentuk, ukuran lebih dari nol, dan dapat dibuka.
- Simpan log versi LibreOffice jika tersedia.
- Jika PDF gagal, pertahankan DOCX dan set status `PDF_FAILED`.

## 11. Report JSON

Contoh struktur:

```json
{
  "engine": "simsoal-docx",
  "engine_version": "0.1.0",
  "status": "success_with_warnings",
  "input_format": "docx",
  "output": {
    "docx_created": true,
    "pdf_created": true
  },
  "inspection": {
    "sections": 1,
    "tables": 2,
    "inline_images": 1,
    "paragraphs": 84
  },
  "warnings": [
    {
      "code": "COMPLEX_DRAWING_REVIEW",
      "message": "Dokumen mengandung elemen gambar/objek yang perlu pemeriksaan visual."
    }
  ]
}
```

Report di atas hanya contoh kontrak data; nilai harus berasal dari hasil inspeksi sebenarnya.

## 12. Error Codes

- `INVALID_DOCX_PACKAGE`
- `UNSUPPORTED_DOCUMENT_FEATURE`
- `SOURCE_NOT_FOUND`
- `ENGINE_TIMEOUT`
- `ENGINE_EXECUTION_FAILED`
- `OUTPUT_VALIDATION_FAILED`
- `LIBREOFFICE_NOT_FOUND`
- `PDF_CONVERSION_FAILED`
- `INSUFFICIENT_STORAGE`

Pesan untuk pengguna harus mudah dimengerti. Detail teknis berada di log internal.

## 13. Security Requirements

- Jangan menjalankan makro dokumen.
- Jangan mengeksekusi file yang diunggah.
- Batasi ukuran, jumlah halaman jika memungkinkan, dan waktu proses.
- Gunakan nama file acak untuk working file.
- Bersihkan temporary files setelah proses selesai atau kedaluwarsa.
- Jangan menerima path dari browser sebagai path filesystem.
- Jalankan LibreOffice/Python dengan hak akses minimum.
- Jangan membuka file dari URL remote.
- Pertimbangkan antivirus scanning pada deployment sekolah.

## 14. Definition of Done Engine DOCX

- File sumber checksum tetap sama.
- Output DOCX dapat dibuka.
- Semua section memiliki page setup yang sesuai preset.
- Isi teks penting tidak hilang atau berubah pada fixtures.
- Tabel dan gambar fixture tetap ada.
- Numbering fixture tidak rusak.
- Output PDF dapat dibuka.
- Peringatan tercatat.
- Proses gagal tidak merusak artefak sebelumnya.
