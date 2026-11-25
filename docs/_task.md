lakukan perbaikan:
- pendaftaran sementara manual, user memasukkan data,  - daftar - admin verifikasi verifikasi - admin wa manual acc ke user mengirimkan template dengan user dan password. belum melalui verifikasi wa otomatis. modul wa jangan hapus, biarkan dulu. pikirkan caranya.

- update data master untuk spbu, ganti dengan https://docs.google.com/spreadsheets/d/1gHkHtWvfGh4YBGKUoG-qO2HwZeErY6Mn_gxznYUOUD0/edit?gid=0#gid=0 , nozzle n dispenser, replace data + update, yang kosong buat akunnya dulu. admin dan user bisa create / add nozzle

- ganti username pengusaha - nama+kodespbu, dan atau nama usaha, tuliskan akun baru semua di docs/task

- tambahkan modul batal permohonan oleh user
- /permohonan/ di data uttp Status per unit ditambahakan deskripsi lain (media , nozle dispense (untuk pubbm), data lain untuk selain pubbm)

- crud nomor hp profile , form edit email
- form ganti username password mandiri, username beda dengan email
- crud data pemohon oleh admin


- /petugas/permohonan/580?tab=process bagian dokumentasi kegiatan  buat seperti file task/Website metro 24-11-2026.pdf halaman 2 (dengan icon dan thumbnail)

- nomor skhp saat verifikasi di hasil dan dokumen dibuat otomatis muncul di form, tapi tetep bisa diubah. contoh format default 510.9/001/SKHP/2026 001 adalah urutan dalam 1 tahun, 2026 adalah tahun. khusus SPBU nomor SKHP sama berapapun UTTP yang diajukan, yang lainnya urut sesuai UTTP yang diajukan
- modul cerapan di hide dulu petugas/permohonan/578?tab=process&saved=2080#unit-2080  , belum siap, kedepan tampilkan lagi
- nama file /permohonan/ yang di upload user dirubah permohonan_(nomor order)
- untuk SPBU ada nozle
- form input /uttp khusus PUBBM disesuaikan spreadshet terbaru. selain PUBBM tambah form "Daya Baca"
- modul hapus foto di galeri 

- /permohonan/baru dropdwon UTTP tersimpan ganti (khusus PUMBBM) - Media - Nozle - Dispenser, selain PUBBM sesuaikan deskripsi lainnya
- /permohonan riwayat permohonan naik ke atas, kabar terbaru di bawah

- Riwayat proses , setelah nomor - jenis uttp - status , dibawahnya spesifikasi deskripsi uttp
- /uttp peta di masing masing uttp , bukan di /uttp
- Kabar terbaru tambah spesifikasi UTTP, 
- Kabar terbaru dan Kabar terbaru paginaton, search ajax, scrollabel, filter baris, maks 25 baris
- lacak status menuju halaman baru khusus lacak status, tampilkan datanya, bisa langsung download SKHP jika status sudah selesai tanpa login
- istilah teknisi ganti pelaku usaha
- /pelimpahan search ajax pelaku usaha dengan dropdown preview
- CRUD pelaku usaha, ceklist kewenangan pelaku usaha sesuai TDUR, upload Dokumen pendukung , bisa lebih dari satu, rekomendasi kadinas





<!-- - jenis layanan /permohonan/baru detail layanan pindah setelah pilih uttp , 1 permhohonan bisa beberap jenis. jenis diganti nama pemilik -->



- update spreadshet urut SPBU dan UTTP

- tambah logo metrologi di sebelah logo pemkab, setimbang dijabarkan
- rapikan footer
-  UPT Metrologi Legal Kabupaten Rembang di Beranda kurang besar
- Setimbang Rembang, rembang nya diilangi, huruf T kayak neraca
- beranda jam layanan, kontak, kritik saran, faq, footer
- map hotspot masuk beranda


<!-- - tambahkan ingat saya saat login -->

- tambahkan kode booking saat ajukan permohonan, dapa di cek statusnya dari lacak status di beranda
- nomor boking acak 6 karakter, muncul saat kirim permohonan dan di bukti order

- beranda blok kotak gambar speedometer ganti slider foto, buat pengaturan untuk ubah fotonya
- pengaturan berita di admin, tampil di beranda di bawah



==============
==============


hak akses + uttp pemohon off, button masih muncul

- /petugas/master/pilihan/ctt tambah  kolom jumlah aset, aset tersdia hari h, riwayat peminjaman
- peminjaman otomatis saat pelaksanaan
- /petugas/permohonan/578?tab=verification atur jadwal tera , bisa lebih dari 1 hari. petugas/permohonan/578?tab=ctt saat pilih CTT muncul stok CTT tersedia pada jadwal terpilih

- ubah / batalkan kaji ulang
- petugas/permohonan/578?tab=ctt tambah hapus CTT
- tambah spasi button tambah CTT dan ganti warna
- petugas/permohonan/576?tab=verification kaji ulang sebelum di verifikasi, kurang mobile friendly, tabel harus digeser (responsive)
- petugas/permohonan/baru tabel pagination
- /uttp dibawahn nama UTTP tampilkan spesifikasi



- ctt lebih dari sehari tetap dikembalikan tiap hari
- ctt di pinjam dan kembalikan harian , tab CTT


- add modull CTT => tampil CTT yang dipakai ada hari H, klik CTT, muncul modal Order hari itu yang pakai CTT, ceklits, tandai pinjam. waktu mengembalikan tinggal klik kembalikan oada CTT yang dipinjam.
masukkan sidebar dibawah permohonan





- opsional CTT, bisa pilih di petugas/permohonan/46?tab=ctt (tidak dibatasi stok per tanggal) , atau langsung di Operasional CTT saat mau pelaksanaan. jika pilih di Operasional CTT data otomatis masuk ke petugas/permohonan/46?tab=ctt.

- stok aset CTT hanya terhitung dipakai saat statusnya dipinjam
- /petugas/permohonan/578?tab=verification foto / dokumentasi pindah di tab hasil, setelah selesai baru foto / upload. posisi taruh di atas, sebelah Status dan dokumen hasil


- revisi setelah kaji ulang pilih CTT dulu, operasianal CTT yang muncul hanya CTT yang dipakai pada jadwal kaji ulang hari itu

- foto di kompres maksimal 1 MB
- buatkan halaman galeri laporan , foto per order, download bulk foto denghan ceklist. masukan sidebar
=======================
=======================
