# Data awal dari arsip SKHP 2025

## Ringkasan

Arsip `SKHP 2025.zip` telah dimasukkan ke Setimbang sebagai **riwayat pelayanan tahun 2025**. Tahun pada berkas sumber adalah 2025, meskipun impor dilakukan pada 13 November 2026. Riwayat ini dapat dilihat oleh pemohon dan petugas. Data pelayanan yang sudah ada sebelumnya tetap dipertahankan.

| Data | Jumlah |
| --- | ---: |
| Akun pemilik baru | 109 |
| Organisasi atau perusahaan | 102 |
| Pemilik perorangan | 7 |
| Nomor order pelayanan | 132 |
| UTTP berbeda yang didaftarkan | 468 |
| Baris riwayat pengujian UTTP | 488 |
| Berkas SKHP yang masuk | 228 |
| Lampiran PDF pada riwayat layanan | 237 |
| Nomor sertifikat unik yang dapat dicatat di daftar sertifikat | 227 |

Satu UTTP dapat muncul pada beberapa riwayat pengujian. Beberapa SKHP berlaku untuk lebih dari satu UTTP. Karena itu, jumlah UTTP, baris pengujian, SKHP, dan lampiran PDF memang berbeda.

## Cara melihat data

1. Buka `https://metrologi.projectpintar.com/` dan masuk sebagai petugas.
2. Buka **Permohonan**. Isi filter tanggal **1 Januari 2025** sampai **31 Desember 2025** untuk menampilkan 132 order arsip. Nomor order arsip diawali `SKHP25/`.
3. Buka salah satu order untuk melihat pemilik, UTTP, hasil, dan pratinjau PDF SKHP.
4. Buka **Data pemohon** untuk melihat akun pemilik. Riwayat layanan dan UTTP juga tersedia pada akun pemohon masing-masing.

Arsip pelayanan ini hanya untuk dibaca. Form pengolahan aktif tidak ditampilkan pada order lama, agar SKHP yang sudah diterbitkan tidak berubah. Laporan tahun 2025 juga tidak dikirim ulang ke spreadsheet laporan aktif.

## Akun dan kata sandi awal

Daftar lengkap nama pemilik, ID masuk, dan kata sandi awal disimpan **secara privat di server**, pada:

`/var/lib/metro/imports/akun_skhp_2025_20261113_212121.csv`

File tersebut hanya dapat dibaca oleh pengelola server. **Jangan memindahkannya ke folder situs atau membagikannya sebagai daftar terbuka.** Untuk menyerahkan akun, cari baris pemilik yang bersangkutan lalu berikan ID dan kata sandinya secara pribadi. Pada 14 November 2026, seluruh ID masuk arsip diperbarui dan kata sandi awalnya disamakan sesuai permintaan pengelola. Satu pemilik tetap hanya memiliki satu akun, termasuk pemilik dengan beberapa SKHP.

Arsip sumber tidak menyediakan email dan nomor telepon pemilik. Pada 14 November 2026, ID masuk dipendekkan lagi menjadi pola seperti `skhp001@metrologi`. Ini **ID login, bukan alamat email**. Setelah identitas dan kontak resmi diperoleh, petugas dapat menggantinya dengan email sebenarnya serta mengisi nomor telepon di **Data pemohon**. Pemilik sebaiknya segera mengganti kata sandi awal. Isian profil yang belum tersedia pada SKHP, termasuk penanggung jawab, desa, dan titik peta, perlu dilengkapi dengan data asli; jangan ditebak.

## Catatan pemeriksaan sumber

- ZIP berisi 242 entri RTF. Sebanyak 14 merupakan berkas sementara Office yang diawali `~$`, sehingga 228 SKHP nyata yang diimpor.
- Nomor `510.9/0128/SKHP/2025` tertulis pada dua SKHP milik pemilik berbeda: SPBU 44.592.02 dan PT Sadhana Arifnusa. Kedua PDF dan riwayat pelayanannya tersimpan. Daftar sertifikat hanya dapat menyimpan satu baris untuk nomor yang sama; baris tersebut mengikuti SKHP yang cocok dengan buku order. Nomor sumber pada PDF kedua tidak diubah. Perlu pemeriksaan dokumen asli oleh dinas jika nomor ini akan dipakai sebagai rujukan resmi.
- Dua nomor order berbeda antara SKHP dan buku order: berkas SKHP 129 memuat `0090-25` sedangkan buku order `0091-25`; berkas SKHP 40 memuat `0023-25` sedangkan buku order `0029-25`. Pengelompokan riwayat memakai nomor dari buku order. Perbedaan nomor asli tetap dicatat pada sumber arsip.
- Lima SKHP pompa ukur BBM memiliki jumlah unit lampiran yang berbeda dari buku order: SKHP 179 (5 berbanding 6), 19 (12 berbanding 13), 38 (12 berbanding 13), 55 (2 berbanding 1), dan 85 (2 berbanding 1). Jumlah UTTP yang dimasukkan mengikuti rincian unit pada lampiran SKHP.
- Dua berkas memiliki tanggal order sesudah tanggal pengujian: SKHP 157 dan 44. Tanggal sumber dipertahankan dan ditandai untuk pemeriksaan.
- Lima baris buku order tidak mempunyai SKHP nyata dalam ZIP, sehingga tidak dibuat sebagai pelayanan selesai: `0010-25` UD Apel Merah, `0016-25` Raisa Laundry, `0034-25` PT Semen Gresik, `0040-25` PT Malindo Feedmill, dan `0057-25` Taufiqul Anwar. Baris terakhir bahkan ditandai “TIDAK SKHP” pada buku order.
- Rincian verifikasi, CTT, koordinat, email, dan nomor telepon yang tidak tercantum pada SKHP tidak direkayasa. Dua nama penera tambahan disimpan di master sebagai data arsip dan tidak diaktifkan untuk permohonan baru.

Perbedaan per berkas juga dicatat pada data sumber impor agar dapat ditelusuri kembali. Pengimpor memakai penanda berkas dan pemilik yang unik, sehingga menjalankannya lagi tidak membuat akun atau pelayanan ganda.

## Cadangan dan pemeriksaan

Cadangan basis data sebelum impor tersedia di `/var/backups/metro/metro_before_skhp2025_20261113_211005.sql.gz` dengan akses terbatas.

Sebelum ID masuk dan kata sandi diubah, basis data kembali dicadangkan ke `/var/backups/metro/metro_before_skhp_login_change_20261114_053820.sql.gz`. Daftar akun sebelumnya juga dicadangkan secara privat di `/var/backups/metro/akun_skhp_2025_sebelum_ganti_login_20261114_053857.csv`.

Sebelum ID masuk dipendekkan menjadi `@metrologi`, basis data dicadangkan ke `/var/backups/metro/metro_before_skhp_short_ids_20261114_054316.sql.gz`. Daftar akun sebelumnya dicadangkan di `/var/backups/metro/akun_skhp_2025_sebelum_id_singkat_20261114_054335.csv`.

Pemeriksaan setelah impor menunjukkan 109 akun unik, 132 order, 468 UTTP, 488 baris pengujian, dan 228 berkas sumber. Semua 237 lampiran PDF ditemukan dan cocok dengan sidik berkas yang dicatat. Uji langsung pada situs berhasil untuk filter arsip 2025, detail petugas, masuk akun pemohon, riwayat pemohon, dan pratinjau PDF. Percobaan mengubah order arsip ditolak oleh aplikasi.

Alat persiapan dan pengimpor disimpan di folder `scripts/` proyek. Pengimpor memiliki pilihan pemeriksaan tanpa menulis data dan simulasi dengan pembatalan transaksi.
