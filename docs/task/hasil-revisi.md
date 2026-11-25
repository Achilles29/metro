# Hasil revisi Website Metrologi

Dokumen acuan: *Website metro 16-11-2026.pdf* dan *Website metro 24-11-2026.pdf*.

## Sudah diterapkan

- Logo Metrologi Legal ditambahkan di samping logo Kabupaten Rembang. Di bawah nama **Setimbang** tampil kepanjangan nama aplikasi dan nama UPT. Logo Metrologi Legal menggunakan lambang [Bantjana Patakaran Pralaja Kapradanan dari portal pemerintah Kabupaten Mojokerto](https://metrologi-sinergismart.mojokertokab.go.id/assets/media/logo-bantjana.png).
- Halaman masuk menyediakan pilihan **Ingat saya selama 30 hari**. Perangkat yang sudah keluar atau akunnya berganti kata sandi tidak dapat memakai token lama.
- Form permohonan menempatkan nama pemilik UTTP pada detail layanan. Jenis layanan dipilih untuk **setiap UTTP**, sehingga satu order dapat memuat jenis layanan berbeda. Data lama sudah disesuaikan tanpa mengubah pilihan sebelumnya.
- Tombol **Tambahkan UTTP** selebar formulir dan berjarak dari bagian surat permohonan.
- Bagian **Kabar terbaru** di daftar permohonan pemohon dihapus karena riwayat tersedia pada detail permohonan.
- Detail permohonan menampilkan jumlah UTTP pada tiap tahap, rincian status yang dapat dibuka, penjelasan status, jenis layanan per UTTP, serta riwayat dengan warna status dan spesifikasi alat.
- Dashboard petugas menampilkan kartu **Belum kaji ulang**, **Terjadwal**, **Dalam pengujian**, **UTTP Sah**, dan **UTTP Batal**. Posisi **Selesai** menghitung semua hasil akhir, termasuk Dikembalikan dan Tidak sesuai.
- Dokumentasi kegiatan dapat mengambil beberapa foto. Foto tampil sebagai thumbnail, dapat dibuka dan diperbesar, serta dapat dihapus setelah konfirmasi.
- Unggah SKHP dilakukan saat PDF dipilih, dengan indikator proses. Nama berkas tetap terlihat pada daftar dokumen, dapat dipratinjau, dan tersedia tombol hapus. SKHP PUBBM berlaku per order; jenis UTTP lain per unit.
- Setelah seluruh UTTP pada order mencapai hasil akhir, aplikasi menyiapkan sinkronisasi **laporan order** dan **laporan data TTU**. Nomor SKHP yang ditambahkan kemudian memperbarui laporan order yang sama.

## Pengaturan Google yang masih diperlukan

ID spreadsheet dan folder Drive sudah tercatat di pengaturan, tetapi **JSON akun layanan Google belum diunggah**. Karena itu, pengiriman laporan otomatis belum dapat berhasil. Pemilik akun Google perlu menyelesaikan langkah pada [panduan pengaturan Google](../12_panduan_pemilik_google.md), lalu petugas menguji koneksi di halaman Integrasi Google dan mengulang sinkronisasi laporan yang tertunda.
