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

- Database: `metro_app` (MariaDB); skema di `database/001_initial_schema.sql` dan `database/002_operational.sql`.
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
