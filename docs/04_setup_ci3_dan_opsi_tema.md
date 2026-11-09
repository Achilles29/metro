# Setup CI3 Metro dan Opsi Tema (catatan rancangan awal)

Implementasi dan konfigurasi produksi terbaru tercatat di `05_implementasi_dan_operasional.md`. Bagian di bawah ini adalah kondisi scaffold sebelum pengembangan.

## Struktur Proyek Saat Ini

Kerangka diambil dari `C:\xampp\htdocs\CodeIgniter-3.1.13` (CI 3.1.13), lalu ditempatkan di root `metro`. Struktur utamanya:

```text
metro/
├── application/
│   ├── config/                 # konfigurasi app, route, database
│   ├── controllers/            # Dashboard sebagai route awal
│   ├── models/                 # akses dan aturan data
│   ├── views/dashboard/        # halaman awal scaffold
│   ├── libraries/              # layanan aplikasi (auth, berkas, PDF, dll.)
│   └── logs/ cache/            # runtime; perlu izin tulis di server
├── system/                     # framework CodeIgniter 3.1.13
├── database/001_initial_schema.sql
├── docs/
├── index.php
└── .htaccess
```

Alur implementasi yang disarankan tetap mengikuti MVC CI3. Untuk menambah fitur, susun controller, model, dan view terpisah per fungsi/domain. Jangan menaruh kueri database dan aturan status kompleks di view. Jika jumlah file bertambah, kelompokkan dengan subdirektori controller seperti `admin/`, `pemohon/`, atau `petugas/`; tidak perlu memasang HMVC sebelum ada kebutuhan nyata.

## Konfigurasi Lokal dan Sebelum Produksi

- `application/config/config.php` saat ini menunjuk ke `http://localhost/metro/`, menggunakan URL tanpa `index.php`, CSRF aktif, cookie HttpOnly, dan encryption key acak lokal.
- `.htaccess` menyediakan rewrite URL bersih. Apache harus mengaktifkan `mod_rewrite` dan mengizinkan override (`AllowOverride All`). Jika akses memakai virtual host atau subdirektori lain, sesuaikan `RewriteBase` dan `base_url`.
- `application/config/database.php` memakai database `metro_app`, user `root`, password kosong untuk instalasi XAMPP lokal. Ubah dengan akun DB khusus dan sandi kuat sebelum lingkungan bersama/produksi.
- Sebelum produksi, atur `ENVIRONMENT` menjadi `production`, gunakan HTTPS, setel cookie secure, encryption key rahasia baru melalui konfigurasi deploy, batasi izin file, dan matikan tampilan error publik.
- File dokumen pemohon/cerapan/SKHP harus berada di penyimpanan privat. Jangan simpan di folder aset publik. Controller harus memeriksa kepemilikan/peran sebelum men-stream file.
- CI3 3.1.13 adalah versi yang diminta dan dipasang sebagai scaffold. Sebelum peluncuran publik, periksa kompatibilitas PHP dan kebijakan pemeliharaan/keamanan framework serta seluruh dependensi yang dipilih.

## Opsi Tema Gratis

Tema admin adalah aset HTML/CSS/JS yang ditempatkan di `application/views` dan `assets/`; tema tidak mengubah backend CI3. Pilih tema dengan versi HTML statis/Bootstrap, bukan paket React/Vue, agar integrasinya tidak memerlukan frontend framework terpisah.

| Tema | Karakter | Integrasi CI3 | Lisensi/hal yang diperhatikan |
| --- | --- | --- | --- |
| [Mazer](https://github.com/zuramai/mazer) | Bootstrap 5, ringan, bersih, panel terasa lapang; cocok untuk daftar antrean dan form operasional. | Baik: pecah HTML menjadi partial header/sidebar/footer di CI3. | MIT menurut repositori. |
| [AdminKit](https://github.com/adminkit/adminkit) | Bootstrap 5, dashboard enterprise yang rapi, banyak pola siap pakai. | Baik: gunakan versi HTML dan salin aset terpilih. | Versi gratis berlisensi MIT; repositori membedakan cakupan Free dan PRO. |
| [MDBootstrap Admin Dashboard](https://github.com/mdbootstrap/bootstrap-5-admin-template) | Bootstrap 5 dengan komponen dan tampilan Material Design; terasa lebih khas dari template admin standar. | Baik: berbasis Bootstrap dan JavaScript biasa. | Repositori menyatakan MIT untuk template gratis; cek lisensi paket/aset yang ikut dipakai. |
| [CoreUI Free Bootstrap Admin](https://github.com/coreui/coreui-free-bootstrap-admin-template) | Bootstrap 5, komponen administrasi konsisten, cukup formal untuk aplikasi instansi. | Baik: ambil template HTML dan integrasikan bertahap. | Kode free berlisensi MIT; bedakan aset/fitur PRO. |

**Rekomendasi awal:** coba Mazer untuk gaya ringan dan bersih; pilih MDBootstrap jika ingin karakter visual yang lebih berbeda; pilih CoreUI bila prioritasnya konsistensi komponen kerja. Minta pengguna membandingkan satu layar dashboard dan satu layar tabel/form sebelum tema diterapkan ke seluruh halaman. Pertahankan satu tema saja agar aset, komponen, dan istilah antarmuka konsisten.

Belum ada tema eksternal yang disalin ke proyek. Halaman dashboard saat ini hanya scaffold ringan agar route CI3 dapat diarahkan ke halaman Metro; pilihan tema masih terbuka.
