# Implementasi Metro di metrologi.projectpintar.com

## Status aplikasi

Folder kode: `/www/wwwroot/metro`. Situs aaPanel `metrologi.projectpintar.com` memakai folder tersebut sebagai document root. HTTPS dan sertifikat situs aktif. Rewrite URL bersih berada di `/www/server/panel/vhost/rewrite/metrologi.projectpintar.com.conf`.

Alur yang tersedia:

- Portal beranda, pendaftaran dan login pemohon, profil pemohon, ganti kata sandi.
- Satu permohonan tera/tera ulang berisi beberapa jenis UTTP; jumlah tiap jenis dipecah menjadi unit fisik tersendiri.
- Nomor permohonan tahunan dibuat di dalam transaksi database, bukti permohonan PDF, daftar dan detail permohonan.
- Antrean petugas, akun internal per peran, penugasan petugas teknis, perubahan status per unit dengan riwayat, catatan perbaikan, dan unggah ulang surat.
- Dashboard operasional dengan sidebar untuk semua halaman petugas, antrean dengan pencarian dan filter status/layanan/tanggal, serta laporan periode dengan ekspor CSV untuk administrator dan koordinator.
- Unggah cerapan internal dan SKHP PDF per unit, unduhan SKHP setelah status selesai, notifikasi dalam aplikasi, dan umpan balik 1–5.
- Pembatasan akses per akun/peran, CSRF, pembatasan percobaan login, audit log, dokumen di luar document root, dan penyaringan PDF maksimal 5 MB.

Kredensial administrator awal berada di `docs/AKSES_ADMIN.md` di server. Berkas itu tidak masuk Git dan URL `/docs/` ditutup oleh Nginx.

## Operasi

- Database: `metro_app` (MariaDB); skema dan migrasi berurutan di folder `database/`.
- Kredensial database dan kunci sesi: `/etc/metro/database.php` dan `/etc/metro/secret.php`, dapat dibaca PHP-FPM dan tidak berada di root web.
- Dokumen privat: `/var/lib/metro/documents`, sesi: `/var/lib/metro/sessions`.
- Batas PHP `open_basedir` berada di `.user.ini` situs dan mencakup direktori di atas.
- Aset PDF menggunakan Dompdf dari Composer. Jalankan `composer install --no-dev` bila membuat pemasangan ulang.
- Log aplikasi: `application/logs/`; log Nginx: `/www/wwwlogs/metrologi.projectpintar.com.error.log`.

## Verifikasi 6 November 2026

Beranda, login, registrasi, aset CSS/JS, dan rute terlindungi telah diuji lewat HTTPS publik. Uji transaksi berhasil mencakup pendaftaran, profil, pengajuan dua unit, bukti PDF, antrean petugas, perpindahan status tiap unit, unggah SKHP, unduhan pemohon, serta umpan balik. Alur perbaikan surat, cerapan internal, pembuatan akun teknis, penugasan, dan akses petugas yang ditugaskan juga diuji. Data uji dihapus setelah verifikasi. Rute `/docs/` sengaja mengembalikan 404 untuk melindungi kredensial dan dokumen rancangan.

## Keputusan proses yang masih perlu ditetapkan UPT

Penamaan status final, SOP pemeriksaan dan pengesahan, format nomor SKHP, jenis UTTP resmi, persyaratan layanan, tata cara serah terima, aturan pengingat tera ulang, dan kanal notifikasi keluar belum diberikan. Konfigurasi contoh hanya untuk menjalankan alur inti; jangan menganggapnya sebagai kebijakan resmi UPT. Pengingat 360 hari, GIS, SSO, WhatsApp, dan penerbitan SKHP otomatis belum diaktifkan karena dokumen rancangan menempatkannya setelah validasi proses.

## Pembaruan dashboard 9 November 2026

Ruang petugas kini memiliki navigasi sidebar yang konsisten untuk dashboard, antrean, laporan (administrator/koordinator), data pemohon dan akun petugas (administrator), serta keamanan akun. Pada layar kecil, sidebar dapat dibuka dari tombol menu dan tabel antrean/pemohon berubah menjadi kartu berlabel.

Definisi angka dashboard: **perlu verifikasi** = unit berstatus `submitted` atau `verification`; **dalam pengujian** = unit `testing`; **pemeriksaan hasil** = unit `review`; **perlu perbaikan** = unit `revision`. Angka bulan ini memakai permohonan dengan `submitted_at` pada bulan berjalan dan unit yang mencapai `completed` pada bulan berjalan menurut `item_status_history`. Statistik petugas teknis dibatasi pada unit yang masih ditugaskan kepadanya. Waktu pembaruan ditampilkan pada dashboard.

Laporan periode menghitung permohonan yang dikirim pada bulan terpilih dan status terkini dari unit dalam permohonan tersebut. Ekspor CSV hanya tersedia bagi administrator/koordinator, tidak memuat identitas pemohon, dan dicatat di `audit_logs`. Metrik jatuh tempo, jadwal hari ini, dan tindak lanjut umpan balik belum ditampilkan karena aturan SLA, jadwal kerja, dan status penanganan umpan balik belum ditetapkan dalam data saat ini.

## Revisi 1 — 12 November 2026

Perubahan dari `docs/revisi1.pdf` sudah diterapkan di domain aktif dengan sidebar petugas tetap dipakai. Beranda mengikuti komposisi putih, biru muda, dan emas pada contoh PDF, memakai logo Kabupaten Rembang dari proyek UMKM. Logo yang sama dipakai untuk favicon pada `/favicon.ico` dan `/assets/favicon.ico`.

Form pemohon memakai satu nama pemilik untuk satu order, 20 jenis UTTP sesuai PDF, merek dan jumlah wajib, kolom model dihilangkan, kapasitas hanya muncul untuk 15 jenis massa, tombol tambah berada di bawah kartu terakhir, serta alamat kantor UPT terisi otomatis saat memilih layanan di kantor. Kecamatan menjadi pilihan 14 kecamatan Rembang. Kata sandi minimal 8 karakter dan memiliki tombol tampilkan/sembunyikan.

Petugas memverifikasi berkas sekali pada nomor order. Penera, CTT, dan peralatan standar dicatat sekali per order; unit ditampilkan sebagai kartu yang dapat dibuka. Cerapan dan SKHP diunggah setelah unit berstatus selesai, dengan unggah tanpa pemuatan ulang dan tautan pratinjau. Penyimpanan status kembali ke unit semula dan menampilkan tanda centang. Administrator memiliki log unit selesai dengan filter tanggal/jenis UTTP, profil pemohon dengan riwayat aktif/selesai, serta kemampuan mengubah nomor telepon pemohon.

Verifikasi telepon untuk akun baru memakai kode enam digit, berlaku 15 menit, maksimal lima percobaan per kode dan lima kode per jam. Administrator membuka profil pemohon, memilih **Buat kode telepon**, menghubungi nomor yang terdaftar, lalu menyampaikan kode; pemohon memasukkannya pada halaman **Profil**. Kode disimpan sebagai hash dan hanya ditampilkan sekali. Akun lama tidak diblokir oleh kebijakan baru; akun baru harus terverifikasi sebelum mengajukan permohonan. Setelah admin mengubah nomor, verifikasi perlu diulang. Pengiriman SMS/WhatsApp otomatis belum aktif karena belum ada gateway atau kredensial penyedia di server.

Migrasi revisi ini: `database/003_revisi1.sql` dan `database/004_phone_verification.sql`. Jenis UTTP lama dinonaktifkan dari pilihan baru, tetapi tetap disimpan agar riwayat permohonan lama utuh. Untuk pemasangan baru, jalankan migrasi 001 sampai 006 sesuai urutan nomor. Migrasi 003–005 hanya dijalankan sekali.

Uji HTTPS publik mencakup beranda, login, registrasi, rute petugas, log aktivitas, profil pemohon, detail permohonan, aset, dan favicon; tidak ditemukan rute aplikasi yang 404. Uji akun sementara mencakup registrasi, pengisian profil, penguncian pengajuan sebelum verifikasi, pembuatan kode oleh administrator, verifikasi oleh pemohon, dan pembukaan formulir pengajuan setelah verifikasi. Akun uji dan datanya telah dihapus.

## Perbaikan logo dan favicon

Logo header memakai `assets/logo-rembang-transparan.png`, dibuat dari gambar resmi proyek UMKM dengan menghapus hanya latar putih di luar lambang. Favicon kini tersedia dalam PNG 32/48/180 piksel dan ICO baru; HTML memakai nama berkas serta versi URL baru untuk melewati cache lama. Jalur standar `/favicon.ico` juga berisi ikon baru.

## Master data — 12 November 2026

Master data administrator berada di sidebar **Master data**. Kecamatan, desa/kelurahan, UTTP, lokasi/alamat, penera, CTT, alat standar, jenis/tempat layanan, label status/dokumen, jenis pemohon, dan nama peran dapat dikelola tanpa perubahan kode. Detail sumber dan aturan integrasi ada di `docs/07_master_data_dan_wilayah.md`. Migrasi tambahan `database/005_master_data.sql` sudah diterapkan setelah cadangan database dibuat. Dropdown desa pada registrasi dan profil memakai pencarian AJAX yang dibatasi kecamatan.

## Hak akses per peran — 12 November 2026

Superadmin mengatur izin petugas, pemohon, pelaku usaha, dan teknisi eksternal melalui **Ruang kerja → Hak akses**. Izin CRUD akun dan master data dipisah per tindakan. Pelaku usaha mewarisi izin dasar Pemohon dan memiliki izin tambahan untuk pelimpahan. Migrasi `database/016_granular_role_permissions.sql` hingga `database/018_business_permission_inheritance.sql` sudah diterapkan. Panduan terbaru ada di [Pengaturan hak akses](18_pengaturan_hak_akses.md).

## Setimbang — 13 November 2026

Aplikasi diberi nama **Setimbang** (Sistem pendaftaran tera dan informasi pengawasan Kabupaten Rembang). Verifikasi nomor via WhatsApp, kode manual cadangan, GIS profil dan UTTP per lokasi, registri UTTP, serta teknisi eksternal dengan pelimpahan perusahaan dan verifikasi dinas telah diterapkan. Migrasi 007 dan petunjuk operasi ada di [Setimbang — WhatsApp, GIS, dan teknisi eksternal](09_setimbang_whatsapp_gis_teknisi.md). Bot masih perlu ditautkan ke nomor layanan dengan memindai QR pada halaman admin sebelum pengiriman WhatsApp nyata.

## Revisi alur verifikasi dan GIS (13 November 2026)

Alur input loket, peta GIS admin, master rincian verifikasi, keputusan per UTTP, CTT jamak, foto dokumentasi, pratinjau PDF, antrean WA hasil, dan pengaturan Google tersedia. Rincian pemasangan, pembatasan akses, dan hasil uji ada di [10_alur_verifikasi_gis_integrasi.md](10_alur_verifikasi_gis_integrasi.md). Pengiriman WA nyata membutuhkan pemindaian QR; sinkronisasi Google membutuhkan kredensial dan Shared Drive milik instansi.
