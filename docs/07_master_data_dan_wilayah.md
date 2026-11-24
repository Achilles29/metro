# Master data dan wilayah Metro

## Sumber wilayah

Master wilayah awal menggunakan salinan `wilayah_rembang_2025.json` dari proyek UMKM. Metadata sumber salinan tersebut menyebut Kepmendagri 300.2.2-2430/2025 melalui API wilayah emsifa v2, diunduh 26 Oktober 2026. Hasil impor: **14 kecamatan dan 294 desa/kelurahan**. Jumlah per kecamatan diperiksa terhadap [tabel BPS Kabupaten Rembang 2025](https://rembangkab.bps.go.id/id/statistics-table/3/YkVWWFUyNTJTVTloVGpCeFdFVTNaMk5wUzFaUFFUMDkjMw%3D%3D/jumlah-desa-kelurahan-menurut-kecamatan-di-kabupaten-rembang--2014.html) dan [data resmi Pemerintah Kabupaten Rembang](https://pkk.rembangkab.go.id/data-sekretariat/).

Kode wilayah disimpan sebagai identitas tetap. Nama, urutan, dan status aktif dapat diubah oleh administrator. Pengajuan baru hanya menerima kecamatan dan desa aktif yang saling cocok. Data permohonan lama tetap menyimpan teks alamat/wilayah pada saat pengajuan. Beberapa profil lama berisi nama wilayah di luar Rembang atau nama desa yang tidak cocok tepat dengan master; data itu tidak dihapus atau ditebak padanannya. Pemilik akun dapat memilih wilayah resmi saat memperbarui profil.

## Kelompok yang dapat dikelola

Buka **Ruang kerja → Master data** pada sidebar akun dengan izin **Kelola master data**, lalu pilih sub menu kategori yang ingin dikelola. Setiap kategori memiliki halaman sendiri. Akun tanpa izin tersebut ditolak oleh server, termasuk bila membuka URL secara langsung. Pengaturan izin ada pada [Hak akses](08_hak_akses_dan_saran_pengembangan.md).

| Halaman | Data dan efek perubahan |
| --- | --- |
| Jenis UTTP | Kode, nama, urutan, status aktif, dan apakah kolom kapasitas muncul pada pengajuan. Jenis lama yang tidak aktif tetap muncul pada riwayat. |
| Kecamatan | Kode, nama, urutan, status aktif untuk formulir pemohon. |
| Desa/kelurahan | Kode, nama, kecamatan, urutan, status aktif. Dipakai pencarian AJAX sesuai kecamatan pada registrasi, profil, dan master lokasi. |
| Lokasi dan alamat | Nama dan alamat tetap tempat pelayanan. Pilihan tempat layanan dapat dihubungkan ke lokasi ini; perubahan alamat berlaku pada pengajuan baru. |
| Penera, CTT, alat standar | Pilihan yang diisi satu kali per order. Pengubahan nama master tampil pada order yang memiliki hubungan ID master. |
| Jenis dan tempat layanan | Nama, urutan, status aktif; tempat layanan dapat memakai alamat lokasi tetap atau alamat pemohon. |
| Jenis pemohon | Nama tampilan untuk pilihan profil. |
| Label status | Nama tampilan dan warna lencana status. Kode/status alur tetap. |
| Jenis dokumen | Nama tampilan dokumen; kode keamanan dokumen tetap. |
| Peran | Nama, deskripsi, urutan, status aktif untuk akun baru. Kode peran tetap; izin tiap peran diatur pada halaman Hak akses. |

Superadmin dapat menambah, melihat, mengubah, dan menghapus data operasional yang belum dipakai. Penghapusan ditolak jika data telah dirujuk oleh profil, permohonan, unit, atau pilihan lain; nonaktifkan data tersebut untuk menyembunyikannya dari transaksi baru. Kode status, dokumen, dan peran adalah kode sistem sehingga hanya label serta pengaturan aman yang dapat diubah. Kode peran dan katalog izin tidak dapat ditambah atau dihapus dari halaman master karena harus memiliki aturan otorisasi yang jelas di aplikasi. Izin untuk peran yang ada dapat diatur pada [Hak akses](08_hak_akses_dan_saran_pengembangan.md).

Halaman master operasional menampilkan tombol **Tambah** di atas tabel. Formulir Tambah dan Edit dibuka dalam modal. Kolom **Aksi** menyediakan Edit, Aktifkan/Nonaktifkan, dan Hapus sesuai jenis data. Master peran, label status, jenis pemohon, dan jenis dokumen hanya menyediakan pengubahan metadata yang aman; peran noninti dapat diaktifkan atau dinonaktifkan. Kelompok sistem tersebut tidak menerima kode baru melalui antarmuka karena kode baru tidak memiliki aturan alur/otorisasi.

Di luar master, **Akun petugas** dan **Data pemohon** juga memiliki formulir Tambah/Edit dalam modal serta tindakan status dan Hapus. Penghapusan akun ditolak jika akun sudah memiliki riwayat kerja atau layanan. Pemohon yang dibuat admin tetap perlu verifikasi nomor telepon sebelum mengajukan layanan. Profil pemohon menampilkan riwayat aktif/selesai dalam tab; laporan memisahkan rincian status, jenis layanan, dan jenis UTTP ke dalam tab.

Daftar master, antrean permohonan, data pemohon, akun petugas, dan log aktivitas menggunakan pagination. Filter pencarian, kecamatan, jenis layanan, status, dan tanggal yang sedang dipilih tetap terbawa pada tautan halaman berikutnya. Log aktivitas membuka catatan audit sistem untuk bulan berjalan; pilih **Unit selesai** untuk melihat riwayat penyelesaian UTTP.

## Integrasi formulir

- Registrasi dan profil menyimpan ID kecamatan/desa serta teks snapshot. Dropdown desa melakukan pencarian melalui `GET /api/wilayah/desa?district_id=...&q=...`, membatasi hasil ke kecamatan aktif dan maksimal 30 opsi.
- Pengajuan memuat jenis UTTP, jenis layanan, tempat layanan, dan alamat tetap dari master. Validasi server mengulang semua pemeriksaan sehingga nilai yang dimanipulasi di browser tidak diterima.
- Detail order petugas menyimpan ID master penera, CTT, dan alat standar, serta snapshot teks untuk kompatibilitas data lama. Opsi yang telah nonaktif tetap terbaca pada order lama.
- Label jenis layanan, status, dokumen, dan peran pada tampilan mengambil data master.

## Pemasangan dan pemulihan

Skema/seed: `database/005_master_data.sql`, dijalankan sekali setelah migrasi 001–004. Berkas ini memuat data awal untuk seluruh kategori, termasuk 294 desa/kelurahan. Sebelum penerapan produksi pada 12 November 2026, salinan database dibuat di `/root/metro-backups/metro-before-master-20261112-122707.sql.gz` (akses root saja).
