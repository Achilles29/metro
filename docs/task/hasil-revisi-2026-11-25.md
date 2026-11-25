# Hasil revisi Setimbang — 25 November 2026

## Pendaftaran dan akun

- Pendaftar mengisi nama, email, nomor WhatsApp, jenis akun, dan wilayah. Akun baru menunggu pemeriksaan petugas.
- Petugas menekan **Setujui** pada daftar pemohon atau pelaku usaha. Setelah itu muncul username, kata sandi awal, dan pesan yang dapat dikirim lewat WhatsApp petugas. Pengiriman dilakukan manual.
- Bila pesan belum sempat dikirim, petugas dapat memakai **Atur ulang akses** untuk membuat kata sandi dan pesan baru. Kata sandi lama langsung tidak berlaku.
- Pengguna dapat mengubah username, email, nomor WhatsApp, dan kata sandi sendiri di halaman profil. Username terpisah dari email.
- Pengaturan WhatsApp otomatis dan kode telepon manual tetap tersedia, tetapi pendaftaran baru tidak mengirim kode otomatis.

## Data SPBU dan alat

- Spreadsheet baru berisi 48 lokasi. Sebanyak 273 nozzle aktif dimasukkan sebagai UTTP, dengan nomor nozzle, dispenser, media, merek, tipe, nomor seri, tahun pembuatan, dan Qmax/Qmin.
- Dibuat 12 akun lokasi yang sebelumnya belum ada. Delapan lokasi tanpa rincian nozzle juga mendapat akun agar datanya dapat ditambah kemudian.
- Daftar username dan kata sandi awal 48 lokasi ada di **akun-spbu-terbaru.md** pada folder ini. Dua akun SPBUN arsip yang tidak ada pada spreadsheet baru tetap dapat membuka riwayat; alat lamanya tidak lagi ditampilkan sebagai alat aktif.
- Pemohon menambah atau mengubah nozzle melalui **Daftar UTTP**. Petugas melakukannya dari tab **Daftar UTTP** pada detail pemohon.
- Untuk PUBBM, formulir menampilkan media, nozzle, dispenser, tahun pembuatan, dan Qmax/Qmin. Untuk UTTP lain, formulir menyediakan daya baca.

## Permohonan dan hasil

- Pemohon dapat membatalkan permohonan sendiri sebelum kaji ulang dimulai, dengan alasan pembatalan.
- Rincian alat tampil pada status per unit, riwayat proses, pilihan UTTP tersimpan, dan kabar terbaru.
- Riwayat permohonan berada di atas kabar terbaru. Keduanya memiliki pencarian, filter status, pilihan 5/10/25 baris, pemuatan tanpa membuka ulang halaman, pagination, dan area gulir.
- Peta tampil pada setiap UTTP yang mempunyai koordinat.
- Surat permohonan yang diunggah pemohon tampil dengan nama **permohonan_(nomor order).pdf**.
- Form SKHP terisi nomor usulan otomatis, misalnya **510.9/001/SKHP/2026**, dan petugas masih dapat mengubahnya. PUBBM memakai satu SKHP untuk satu order; jenis lain per UTTP.
- Kontrol cerapan disembunyikan sementara pada halaman proses. Fungsinya tetap tersimpan untuk dipakai lagi nanti.
- Dokumentasi proses memakai tombol kamera bersama thumbnail; foto dapat diperbesar, diperkecil, dan dihapus. Foto di galeri juga dapat dihapus.
- Lacak status mempunyai halaman tersendiri. Setelah order selesai, SKHP dapat diunduh dengan kode booking tanpa login.

## Pelaku usaha dan pelimpahan

- Istilah yang terlihat pengguna memakai **pelaku usaha**.
- Pencarian pihak pelimpahan memakai daftar hasil yang muncul saat mengetik nama atau username, lengkap dengan pratinjau akun.
- Profil pelaku usaha memuat nomor TDUR, pilihan jenis UTTP sesuai kewenangan, beberapa dokumen PDF pendukung, dan rekomendasi kepala dinas. Dinas memeriksa kelengkapan sebelum menyetujui. Permohonan yang diajukan pelaku usaha dibatasi pada jenis UTTP dalam kewenangan TDUR yang disetujui.

## Pemeriksaan

- Halaman beranda, pendaftaran, pelacakan, profil, daftar UTTP, daftar permohonan, dan formulir permohonan dibuka di domain aktif.
- Login menggunakan username SPBU berhasil. Halaman pelacakan menampilkan status dan unduhan SKHP PDF untuk order selesai.
- Dengan akun petugas uji sementara, halaman daftar pemohon, detail SPBU, pelaku usaha, permohonan 580, dan galeri terbuka tanpa kesalahan. Persetujuan pendaftar uji menampilkan template WhatsApp; pengaturan ulang akses juga berhasil. Seluruh akun uji dibersihkan setelah pemeriksaan.
- Pendaftaran pemohon dan pelaku usaha uji masuk sebagai akun menunggu persetujuan tanpa antrean WhatsApp otomatis; seluruh akun uji dibersihkan.
- Berkas PHP dan JavaScript yang diubah telah diperiksa sintaksnya. Berkas daftar akun di folder docs tidak dapat dibuka melalui alamat web.
