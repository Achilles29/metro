# Revisi Website Metrologi, 13 November 2026

Sumber: `docs/source/Website metrologi 13-11-26.pdf` (16 halaman). Revisi ini diterapkan pada aplikasi Setimbang di `https://metrologi.projectpintar.com/`.

## Pemetaan revisi

| Halaman PDF | Hasil di aplikasi |
| --- | --- |
| 1–2 | Detail dan antrean permohonan tetap memakai kartu UTTP dan tabel awal, tanpa tab Sah/Batal. Baris antrean bisa diklik. |
| 3–4 | Dashboard memuat Belum kaji ulang, Dalam pengujian, UTTP Sah, UTTP Batal; grafik menambah Terjadwal dan menggabungkan semua status akhir menjadi Selesai. Nama penera terlihat di dashboard dan antrean. |
| 5–7 | Verifikasi berkas menjadi **Kaji ulang**. Tujuh unsur awal sesuai PDF tersimpan di master yang dapat diedit. Satu formulir berlaku untuk seluruh order; setiap unsur bisa diberi UTTP tidak sesuai lebih dari satu melalui modal checklist. Hasil dibuat sebagai PDF gabungan bukti order lalu kaji ulang, hanya untuk petugas berwenang dan terbuka sebagai pratinjau browser. Tabel pengecualian muncul bila ada UTTP tidak sesuai. |
| 8 | Tab Penera dan CTT mendukung beberapa CTT, tanggal pinjam dan tanggal kembali. Laporan CTT dikirim sesudah pengembalian. Peralatan standar disembunyikan dari alur ini. |
| 9–10 | Tombol Bukti order membuka PDF di browser. Alamat pemilik diambil dari profil, sementara lokasi pengujian tetap pada masing-masing UTTP. Foto order dapat diambil berkali-kali melalui kamera/galeri, dilihat sebagai thumbnail, diperbesar, dan dihapus dengan konfirmasi. |
| 11–12 | Nomor UTTP ditampilkan tanpa kata “Unit #”. Status aktif mengikuti tempat layanan: Terjadwal/Pengujian. Hasil akhir ialah Sah, Batal, Dikembalikan, dan Tidak sesuai; Batal/Dikembalikan mewajibkan alasan. Unggah cerapan manual disembunyikan sementara, sedangkan pembuatan draf cerapan dan pengiriman Drive tetap ada. SKHP otomatis diunggah saat berkas dipilih, menampilkan progres, lalu nama berkas, pratinjau, dan hapus. PUBBM memakai satu SKHP per jenis PUBBM dalam order; selain PUBBM per UTTP. |
| 13 | Sesudah seluruh UTTP berstatus akhir, aplikasi mengirim laporan order dan TTU. Laporan order menunggu nomor SKHP untuk UTTP Sah; bila SKHP diganti, baris yang sama diperbarui. |
| 14–16 | Log aktivitas membuka **Order selesai** secara bawaan, hanya satu baris per order yang semua UTTP-nya terminal. Rentang tanggal bawaan bulan berjalan. Tabel dapat difilter dan dipaginasi, berisi nomor order, pemohon, layanan, jumlah UTTP, tanggal, dan penera; baris dapat diklik menuju detail order. Detail berisi ringkasan jenis/status UTTP, PDF, cerapan/SKHP, surat, dan foto. Aktivitas sistem tetap tersedia sebagai pilihan kedua. |

Order lama yang sudah mempunyai UTTP pada tahap lanjut namun belum menyimpan rincian kaji ulang baru tidak dipaksa kembali ke awal. Petugas dapat melanjutkan UTTP yang masih tertunda lewat tombol khusus setelah memilih penera; PDF kaji ulang baru tidak dibuat dari data pemeriksaan lama yang tidak tersedia.

## Spreadsheet yang digunakan

Nama tab diambil dari tampilan Google Sheets asli. Ekspor XLSX memotong karakter `/` pada nama tab TTU terakhir, sehingga **jangan** memakai nama tab hasil ekspor XLSX untuk konfigurasi API.

| Laporan | Spreadsheet | Tab/rentang | Waktu pengiriman |
| --- | --- | --- | --- |
| CTT | [Spreadsheet CTT](https://docs.google.com/spreadsheets/d/1vRT5UnWhgJAE6btv4-5nu92X-dYsxO0CVAk77J8mGDc/edit) | `Template!A3:F` | Setelah CTT dikembalikan |
| Order | [Spreadsheet order](https://docs.google.com/spreadsheets/d/1W7gjvozo0O2VL3eiP0GGK7036At-14upy6xPCDaH5ZM/edit) | `Template!A3:L` | Seluruh UTTP terminal dan SKHP untuk UTTP Sah tersedia |
| TTU Sah | [Spreadsheet TTU](https://docs.google.com/spreadsheets/d/14rX60Mmuo8vDsLvWoLCffoBs2DsY5mJy_jNLVRUnpzM/edit) | `'Template Sah'!A3:I` | Seluruh UTTP terminal |
| TTU Batal | Spreadsheet TTU di atas | `'Template Batal'!A3:I` | Seluruh UTTP terminal |
| TTU Dikembalikan/Tidak sesuai | Spreadsheet TTU di atas | `'Template Dikembalikan/Tidak sesuai'!A3:J` | Seluruh UTTP terminal |

Setiap UTTP menjadi satu baris. Pada laporan order, nomor order, tanggal order, nama dan alamat pemilik diisi pada baris pertama order sesuai contoh. Kolom **No.** pada laporan CTT/TTU memakai nomor baris sheet. Pengiriman lokal disimpan di `request_report_syncs`, menampilkan status dan pesan kesalahan, serta bisa diulang tanpa menggandakan baris yang sudah berhasil. Data teks yang diawali karakter formula diamankan sebelum dikirim ke Sheets.

## Google Drive dan autentikasi

Folder tujuan: [Hasil Laporan Website](https://drive.google.com/drive/folders/1xIJV2ZHLVecQXTj70L-Cx0MXHmX7aLuv). ID folder dan ketiga spreadsheet sudah diisi di **Integrasi Google**.

Panduan yang dapat langsung diteruskan ke pemilik akun Google: [Panduan pemilik Google](12_panduan_pemilik_google.md).

- Jika folder berada di **Shared Drive**, unggah JSON service account melalui halaman Integrasi Google, beri akun itu akses Editor pada ketiga spreadsheet dan folder, lalu pilih metode **Akun layanan**.
- Jika folder berada di **My Drive**, unggah JSON OAuth client bertipe Web application. Daftarkan URI redirect `https://metrologi.projectpintar.com/petugas/integrasi/google/callback`, lalu klik **Hubungkan akun Google** dan pilih metode **OAuth akun admin**. Akun Google yang dihubungkan harus bisa mengedit ketiga spreadsheet dan folder.
- Aktifkan Google Sheets API dan Google Drive API pada proyek Google. Jalankan **Uji koneksi semua tujuan** sebelum mengirim laporan. Kredensial dan refresh token disimpan di `/var/lib/metro/google/`, di luar web root.

**Status saat revisi:** JSON akun layanan dan OAuth client belum tersedia di server. Karena itu penulisan nyata ke Google Sheets/Drive belum dapat diuji atau dinyatakan berhasil. Saat seluruh UTTP selesai, kegagalan autentikasi disimpan sebagai laporan tertunda dan dapat dicoba ulang dari detail order. CTT juga memiliki tombol kirim ulang setelah tanggal kembali tercatat.

## Migrasi dan pemeriksaan

- Cadangan sebelum migrasi: `/root/metro-backups/metro-before-pdf-revisi-20261113-183741.sql.gz`.
- `database/009_revisi_website_20261113.sql`, `database/010_report_sync_remote_range.sql`, dan `database/011_review_criteria.sql` telah diterapkan ke database produksi. `009` hanya dijalankan sekali; perubahan tab TTU yang ditemukan kemudian sudah diperbaiki langsung di konfigurasi produksi.
- Lint seluruh 97 file PHP: **0 kesalahan**. JavaScript `staff-revisi.js` dan `verification.js` lolos pemeriksaan sintaks.
- Uji login admin dan GET halaman dashboard, antrean, detail order, log aktivitas, master kaji ulang, integrasi Google: HTTP 200 tanpa galat PHP/database.
- Bukti order lama: HTTP 200 dan berkas PDF valid. Arsip verifikasi lama tidak mempunyai rincian unsur kaji ulang, sehingga tautan PDF gabungan disembunyikan agar tidak menampilkan tabel kosong. PDF gabungan baru hanya tersedia setelah formulir kaji ulang lengkap disimpan.
- UI detail order diperiksa melalui tangkapan layar desktop. Alur mutasi pada data pemohon produksi tidak dipaksakan untuk pengujian; setelah kredensial Google tersedia, uji tulis dengan satu order uji dan hapus baris uji tersebut dari sheet bila diperlukan.
