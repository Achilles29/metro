# Koordinat profil pemilik dari arsip SKHP 2025

Pada 14 November 2026, lokasi pemilik arsip SKHP 2025 dicocokkan dengan nama usaha, kode SPBU atau Pertashop, dan alamat pada peta daring. Titik yang hanya menunjuk pusat desa, usaha lain dengan nama mirip, atau cabang di luar alamat SKHP tidak dipakai.

| Hasil | Jumlah profil |
| --- | ---: |
| Nama dan alamat atau kode lokasi cocok dengan peta | 61 |
| Titik terpasang, tetapi masih perlu konfirmasi pemilik atau petugas | 10 |
| Belum ditemukan titik usaha yang dapat dipastikan | 38 |
| **Jumlah akun arsip** | **109** |

Total **71 profil** telah memiliki koordinat. Titik tersebut muncul pada halaman **Profil pemohon**, detail pemohon di admin, dan **Peta GIS**. Titik yang perlu konfirmasi diberi keterangan khusus pada halaman profil dan peta admin. Lokasi UTTP tidak disalin dari profil karena setiap alat dapat berada di lokasi yang berbeda.

## Sumber dan cara meninjau

- Rincian tiap pemilik, koordinat, nama serta alamat pada peta, catatan pencocokan, dan tautan sumber tersedia pada file privat `/var/lib/metro/imports/koordinat_profil_skhp_2025_20261114.csv`.
- Sebagian besar titik berasal dari Google Maps. Khusus SPBU 44.592.01, titik diambil dari [Waze](https://www.waze.com/en-GB/live-map/directions/spbu-44.592.01-jalan-raya-kragan-kragan?to=place.w.73139909.731399092.3279341) karena nomor SPBU tidak tampil pada hasil Google Maps. [Daftar penyalur BBM ESDM](https://migas.esdm.go.id/cms/uploads/list-badan-usaha-hilir-migas/juli-2021/penyalur-bbm-pertamina-mei-2021.pdf) membantu mencocokkan kode SPBU dengan desa.
- Koordinat PT Sari Buana Hasil Bumi juga dibandingkan dengan [tautan lokasi yang dipasang perusahaan](https://linktr.ee/PTSARIBUANAHASILBUMI).
- Pencocokan peta daring adalah titik awal. Pemilik dapat mengoreksi koordinat di **Profil pemohon** dengan memilih atau menggeser penanda pada peta.

## Titik yang perlu konfirmasi

Profil berikut sudah memiliki titik awal. Nama atau alamat pada peta belum sepenuhnya sama dengan SKHP, sehingga sebaiknya diperiksa saat kunjungan atau saat pemilik melengkapi profil.

| Pemilik | Alasan pemeriksaan |
| --- | --- |
| CV. ANUGRAH BULU RAYA | Ejaan nama berbeda satu huruf; desa dan lokasi sesuai. |
| CV. MINA BERKAH SEJAHTERA | Alamat sumber hanya menyebut jalan Pantura; nama usaha cocok. |
| PERTASHOP 4P.592.10 | Penanda Pertashop tanpa kode berada tepat di Desa Sendangagung. |
| PT. CANDI JAYA MAKMUR SENTOSA | Titik berdasarkan nomor jalan yang sama, belum berupa penanda usaha. |
| PT. LINTANG SEKAR WIDURI UTAMI | Titik berdasarkan nomor jalan yang sama, belum berupa penanda usaha. |
| PT. OMYA INDONESIA | Dua lokasi Omya di Wonokerto; dipilih penanda pabrik utama. |
| PT. RATIH REKYAN ANANTAVERA | Nama usaha cocok, alamat hasil peta tidak lengkap. |
| SPBE PT. INDAH SRI REJEKI | Nama usaha cocok, alamat hasil peta tidak lengkap. |
| SPBU 45.592.26 | Penanda bernama SPBU Mondoteko; alamat peta menyebut Ngotet Kidul yang berdekatan. |
| SPBUN 48.592.03 ( KUD MISOYO MARDI MINO ) | Nama SPBUN dan desa cocok, kode tidak tampil pada penanda peta. |

## Profil yang belum memiliki titik pasti

Sebanyak **38 profil** sengaja dibiarkan tanpa koordinat karena hasil pencarian hanya menunjukkan wilayah umum, usaha lain, atau beberapa kandidat yang tidak dapat dibedakan. Nama, alamat SKHP, dan tautan pencariannya tersedia dalam file rincian privat di atas. Mohon minta pin lokasi kepada pemilik atau petugas lapangan sebelum titik diisi.

## Pemeriksaan setelah pengisian

- Pada data arsip terdapat 61 profil bertanda sumber `google`, 10 profil bertanda `google_review`, dan 38 profil tanpa koordinat.
- Uji langsung di situs berhasil membuka peta admin dan detail profil. Peta admin menampilkan 72 profil bertitik pada saat pemeriksaan; satu di antaranya adalah profil yang sudah memiliki titik sebelum pengisian ini.
- Cadangan basis data sebelum pengisian koordinat berada di `/var/backups/metro/metro_before_skhp_geocode_20261114_062939.sql.gz`.
