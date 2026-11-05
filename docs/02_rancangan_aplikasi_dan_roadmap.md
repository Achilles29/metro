# Draft Rancangan Aplikasi dan Roadmap

## 1. Gambaran Produk

**Sistem Informasi Pelayanan Metrologi Legal UPT Kabupaten Rembang** adalah layanan web untuk mengelola permohonan tera dan tera ulang UTTP, mengikuti perkembangan pekerjaan per unit, mengelola berkas pengujian dan SKHP, serta membantu petugas mengatur antrean dan pelayanan lapangan.

Aplikasi melayani dua kebutuhan dalam satu sistem:

- **Ruang pemohon:** mengelola profil, mengajukan layanan, memantau setiap UTTP, mengunduh dokumen yang tersedia, dan memberi umpan balik.
- **Ruang kerja petugas:** memeriksa permohonan, mengatur antrean dan penugasan, mencatat pemeriksaan/pengujian, mengelola dokumen, dan melihat beban kerja.

Informasi publik seperti jenis layanan, persyaratan, panduan, lokasi/jadwal pelayanan, berita, dan kontak dapat dilihat tanpa akun. Akun diminta saat pengguna perlu menyimpan data, mengajukan layanan, atau melihat dokumen pribadi.

## 2. Sasaran dan Prinsip

1. **Pemohon tidak mengisi data yang sama berkali-kali.** Profil pemohon dan data UTTP dapat dipakai kembali untuk permohonan berikutnya.
2. **Satu permohonan boleh memuat beberapa UTTP.** Setiap unit fisik tetap mempunyai identitas dan status pengerjaan sendiri.
3. **Status selalu menjelaskan langkah berikutnya.** Jika berkas perlu diperbaiki, petugas menyebutkan berkas atau bagian yang kurang.
4. **Petugas bekerja dari satu antrean.** Permohonan, penugasan, jadwal, riwayat status, dan dokumen terkait dapat ditemukan dari satu halaman detail.
5. **Dokumen dan data hanya dibuka sesuai tugas.** Dokumen internal pengujian tidak tampil pada ruang pemohon kecuali ditetapkan sebagai dokumen yang dapat dibagikan.
6. **Antarmuka konsisten dan mudah di ponsel.** Istilah, tombol, pola formulir, notifikasi, dan warna status mempunyai arti yang sama di seluruh halaman.
7. **Keputusan teknis dan penerbitan resmi tetap pada pejabat/petugas berwenang.** Aplikasi mencatat proses dan dokumen yang disahkan; aplikasi tidak menggantikan kewenangan atau prosedur resmi.

## 3. Pengguna dan Hak Akses

| Peran | Hak dan tanggung jawab utama |
| --- | --- |
| Pengunjung | Membaca informasi layanan, panduan, agenda, kontak, dan FAQ. |
| Pemohon/pemilik UTTP | Mengelola profil dan UTTP miliknya, membuat pengajuan, melengkapi kekurangan, melihat status unit, mengunduh bukti dan SKHP yang tersedia, serta mengirim umpan balik. |
| Petugas penerima/verifikator | Memeriksa identitas, surat permohonan, kelengkapan, dan data UTTP; meminta perbaikan; menerima atau meneruskan pengajuan. |
| Petugas teknis/penera | Melihat penugasan, melakukan proses pengujian, mengisi hasil sesuai prosedur, dan mengunggah cerapan untuk unit yang ditugaskan. |
| Koordinator | Mengatur antrean, penjadwalan, penugasan, prioritas operasional, dan pemantauan beban kerja. |
| Pejabat/penerbit yang berwenang | Menelaah dan mengesahkan hasil/dokumen sesuai kewenangan yang berlaku. |
| Administrator aplikasi | Mengelola akun internal, peran, daftar referensi, template dokumen, pengaturan notifikasi, dan konfigurasi aplikasi. |

Hak akses diterapkan pada menu dan pada setiap data/dokumen. Petugas hanya mendapat akses yang diperlukan untuk tugasnya; akses lintas peran dan perubahan penting dicatat dalam audit log. Akun internal sebaiknya dibuat oleh administrator, dengan penggantian kata sandi dan penonaktifan akun saat petugas tidak lagi berwenang.

## 4. Struktur Navigasi yang Diusulkan

### Portal informasi

- Beranda dan profil UPT
- Layanan tera dan tera ulang
- Persyaratan, panduan, FAQ, dan tarif jika berlaku
- Jadwal/lokasi pelayanan dan informasi layanan lapangan
- Berita, agenda, dokumentasi yang layak dipublikasikan
- Kontak, kritik dan saran, serta kanal pengaduan

### Ruang pemohon

- Ringkasan pengajuan dan hal yang perlu dilakukan
- Profil pemohon/instansi dan kontak
- Daftar UTTP milik pemohon
- Ajukan tera/tera ulang
- Permohonan dan pelacakan per unit UTTP
- Dokumen dan unduhan
- Notifikasi dan riwayat komunikasi

### Ruang kerja petugas

- Dashboard operasional dan antrean kerja
- Permohonan masuk dan verifikasi
- Data pemohon dan UTTP
- Penugasan, jadwal, serta pelayanan di kantor/lapangan
- Proses teknis dan unggah cerapan
- Pemeriksaan hasil dan penerbitan/unggah SKHP
- Pengambilan/penyerahan dan umpan balik
- Laporan, pengelolaan konten, pengguna, dan konfigurasi sesuai peran

Navigasi menggunakan pola yang sama: judul halaman, jejak lokasi/menu, tombol aksi utama pada posisi tetap, filter dan pencarian yang mudah ditemukan, tabel yang tetap terbaca di layar kecil, serta panel detail yang menampilkan konteks dan riwayat. Drawer detail dapat digunakan untuk tindakan cepat; pekerjaan teknis yang panjang tetap memiliki halaman detail penuh agar tidak sesak.

## 5. Modul Fungsional

### 5.1 Portal dan pusat informasi

Pengelola dapat memperbarui profil UPT, jenis layanan, persyaratan, panduan, FAQ, jadwal, berita, agenda, kontak, dan kanal resmi. Informasi mempunyai status draf/terbit dan tanggal pembaruan. Persyaratan serta estimasi layanan harus dapat dikonfigurasi agar perubahan prosedur tidak mengharuskan perubahan kode.

### 5.2 Akun dan profil pemohon

Pemohon mendaftar dan masuk, lalu mengisi profil orang atau instansi/perusahaan, penanggung jawab, alamat, dan kontak. Verifikasi email atau nomor telepon dapat digunakan untuk memastikan kanal komunikasi aktif. Petugas dapat membantu pembuatan akun sesuai kebijakan.

Data profil digunakan kembali pada formulir. Pemohon bisa memiliki beberapa lokasi pelayanan atau kontak, tetapi akses berkas tetap dibatasi. Opsi SSO dapat dipertimbangkan setelah penyedia identitas dan tata kelola integrasinya tersedia.

### 5.3 Registri UTTP

Pemohon menyimpan data UTTP yang pernah diajukan agar tidak mengisi ulang informasi dasar. Data mencakup pemilik, jenis, merek, tipe, model, kapasitas, dan atribut lain yang ditetapkan untuk tiap jenis. Petugas dapat mengoreksi data dengan alasan dan jejak perubahan.

Jika jumlah pada formulir lebih dari satu, sistem membuat catatan unit fisik terpisah dengan nomor unit turunan. Setiap unit dapat memiliki identitas teknis tambahan yang ditetapkan SOP (misalnya nomor seri bila tersedia). Penggabungan atau penghapusan unit yang telah masuk proses harus dibatasi dan tercatat.

### 5.4 Pengajuan tera/tera ulang

Formulir bertahap mengumpulkan data pemohon, lokasi pelayanan, satu atau lebih UTTP, jenis permohonan, dan surat permohonan. Pemohon dapat menyimpan draf, menambah/menghapus UTTP sebelum pengiriman, mendapat validasi yang mudah dimengerti, lalu meninjau ringkasan sebelum mengirim.

Setelah dikirim, sistem membuat nomor permohonan dan bukti penerimaan PDF. Penomoran harus atomik agar tidak ganda saat beberapa permohonan masuk bersamaan, dan format/periode reset ditentukan administrator sesuai kebijakan. Dokumen ini disebut **bukti permohonan**; istilah invoice hanya digunakan jika memang mewakili tagihan yang sah. QR/barcode, bila dipakai, membuka halaman validasi minimum dan tidak membocorkan dokumen atau data pribadi.

### 5.5 Verifikasi dan perbaikan berkas

Petugas melihat antrean berdasarkan tanggal masuk, jenis layanan, lokasi, dan status. Petugas dapat menerima, meminta perbaikan, atau menolak dengan alasan yang jelas. Pemohon hanya mengunggah ulang bagian yang diminta dan melihat tenggat/langkah berikutnya bila ada. Riwayat versi berkas dan catatan verifikasi tersimpan.

### 5.6 Antrean, penugasan, dan pelayanan lapangan

Koordinator menugaskan permohonan/unit kepada petugas teknis dan menentukan jadwal serta lokasi kerja. Untuk pengujian lapangan, catatan kunjungan dapat memuat waktu, petugas, lokasi, hasil kunjungan, dan bukti yang diperlukan. Perubahan jadwal memberi notifikasi kepada pemohon.

### 5.7 Pengerjaan per unit UTTP

Setiap unit memiliki timeline sendiri. Status yang disarankan untuk rancangan awal:

`Draf → Dikirim → Verifikasi berkas → Perlu perbaikan (bila ada) → Terjadwal/menunggu layanan → Pengujian → Pemeriksaan hasil → Selesai (sah) / Tidak dapat diselesaikan / Dibatalkan`

Status akhir dan istilahnya perlu disesuaikan dengan SOP pelayanan tera setempat. Status permohonan induk merupakan ringkasan dari seluruh unit, bukan pengganti status per unit. Sistem merekam siapa yang mengubah status, waktu, alasan, dan catatan yang boleh dilihat pemohon.

Petugas teknis mengunggah cerapan per unit sebagai dokumen internal. Hasil teknis dicatat sesuai formulir dan prosedur yang disahkan. Pemohon hanya melihat ringkasan atau dokumen yang memang boleh dibagikan.

### 5.8 Pemeriksaan, SKHP, dan pengambilan

Setelah hasil ditelaah sesuai kewenangan, petugas yang berhak mengunggah atau menerbitkan SKHP digital, memasukkan nomor/tanggal, dan mengaitkannya ke unit yang tepat. Unduhan aktif hanya setelah dokumen disahkan dan statusnya sesuai.

Jika seluruh unit dalam satu permohonan siap, pemohon memperoleh pemberitahuan bahwa proses selesai atau UTTP dapat diambil/dikirim sesuai pilihan layanan. Bila sebagian unit selesai lebih dahulu, status dan dokumen unit tersebut tetap dapat dilihat tanpa menunggu seluruh permohonan selesai, sepanjang prosedur mengizinkan.

### 5.9 Notifikasi, kritik-saran, dan pengaduan

Notifikasi dalam aplikasi menjadi kanal dasar. Email/WhatsApp gateway dapat ditambahkan setelah kanal, biaya, dan persetujuan pengguna ditetapkan. Pesan menggunakan template singkat, tidak menyertakan dokumen sensitif, dan mengarahkan pengguna untuk masuk ke aplikasi.

Kritik dan saran dapat dikirim setelah layanan selesai. Pengaduan umum diberi nomor tiket, kategori, petugas tindak lanjut, dan status; isi serta identitas pengadu tidak dipublikasikan. Pisahkan survei kepuasan dari pengaduan yang memerlukan penanganan.

### 5.10 Pengingat tera ulang

Sistem dapat mengingatkan pemohon saat waktu tera ulang mendekat. Jangka waktu tidak dikunci permanen pada angka 360 hari. Aturan pengingat perlu mengikuti jenis UTTP, tanggal berlaku/masa tera yang tercatat, ketentuan teknis yang berlaku, serta kalender dan kebijakan UPT. Pengingat dapat dikonfigurasi dan dinonaktifkan per jenis bila tidak ada dasar tanggal yang andal.

### 5.11 Dashboard dan laporan

Dashboard internal berfokus pada tindakan: permohonan menunggu verifikasi, unit yang sedang dikerjakan, pekerjaan jatuh tempo, jadwal hari ini, SKHP yang perlu ditelaah, dan umpan balik belum ditangani. Pimpinan dapat melihat volume layanan per periode, jenis UTTP, status, lokasi layanan, durasi proses, dan beban kerja.

Angka selalu menunjukkan periode, tanggal pembaruan, sumber, dan definisi. Hindari menampilkan data pemohon, dokumen, atau lokasi rinci pada dashboard publik. Ekspor laporan dibatasi sesuai peran dan dicatat.

### 5.12 GIS sebagai pendukung operasional

GIS bermanfaat terutama untuk permohonan yang memerlukan pelayanan lapangan: melihat lokasi pelanggan/alat yang perlu didatangi, mengelompokkan kunjungan berdasarkan wilayah, merencanakan rute, serta merangkum sebaran permintaan. Koordinat memiliki sumber, waktu pencatatan, dan status verifikasi.

Peta internal hanya tersedia bagi petugas terkait. Portal publik dapat menampilkan kantor UPT, lokasi/jadwal pelayanan publik, atau area layanan. Titik lokasi pemohon tidak ditampilkan terbuka secara default; publikasi memerlukan dasar dan persetujuan yang sesuai. GIS dapat ditunda sampai data alamat dan proses kunjungan lapangan sudah konsisten.

## 6. Proses Bisnis Utama

### A. Pengajuan hingga penerbitan SKHP

1. Pemohon membaca layanan dan persyaratan, masuk, lalu memilih tera atau tera ulang.
2. Sistem mengisi data profil dan UTTP yang telah tersimpan; pemohon menambahkan atau memperbarui data yang diperlukan.
3. Pemohon menyimpan draf atau meninjau ringkasan dan mengirim permohonan.
4. Sistem membuat nomor permohonan dan bukti penerimaan, kemudian membuat catatan unit fisik untuk setiap UTTP.
5. Verifikator memeriksa berkas. Jika perlu perbaikan, pemohon menerima daftar kekurangan dan mengirim pembaruan.
6. Koordinator menjadwalkan dan menugaskan unit/permohonan ke petugas teknis.
7. Petugas melakukan pengujian sesuai prosedur, memperbarui status unit, dan mengunggah cerapan internal.
8. Hasil diperiksa dan disahkan oleh pihak berwenang. SKHP diunggah/dicatat pada unit terkait.
9. Sistem memberi tahu pemohon, membuka dokumen yang dapat diunduh, serta mencatat proses pengambilan/penyerahan jika digunakan.
10. Pemohon memberi kritik-saran; data layanan masuk ke laporan sesuai hak akses.

### B. Penjadwalan layanan lapangan

```text
Permohonan terverifikasi
        ↓
Lokasi dan kebutuhan lapangan dikonfirmasi
        ↓
Koordinator mengelompokkan jadwal dan menugaskan petugas
        ↓
Petugas melakukan kunjungan dan mencatat hasil per unit
        ↓
Hasil ditelaah → SKHP/hasil layanan → pemohon diberi kabar
```

Jika layanan dilakukan di kantor, langkah penjadwalan lokasi lapangan dapat dilewati.

## 7. Konsistensi Antarmuka dan Bahasa

- Sediakan pola halaman yang sama untuk daftar, detail, formulir, konfirmasi, status kosong, dan kesalahan.
- Gunakan satu kamus istilah: tera, tera ulang, UTTP, cerapan, SKHP, permohonan, unit. Beri penjelasan singkat saat istilah teknis pertama kali muncul.
- Bedakan status dengan label teks dan ikon, bukan warna saja; pertahankan arti warna yang sama di semua halaman.
- Formulir dibagi per langkah, menandai wajib/opsional, memberi contoh format, dan menyimpan draf.
- Tampilkan tombol utama dengan nama tindakan yang spesifik, misalnya “Kirim permohonan” atau “Minta perbaikan”. Konfirmasi diperlukan untuk tindakan berdampak seperti pembatalan atau pengesahan.
- Optimalkan untuk ponsel: kontrol mudah disentuh, unggah dari kamera/berkas, tabel berubah menjadi kartu/daftar ringkas, serta tidak mengandalkan hover.
- Berikan ringkasan “yang perlu dilakukan” pada beranda pemohon dan antrean kerja yang dapat difilter pada ruang petugas.

## 8. Data, Keamanan, dan Operasional

- Simpan dokumen di penyimpanan privat dengan validasi jenis/ukuran, nama berkas acak, kontrol akses unduh, dan pencatatan akses penting. Hindari tautan permanen yang dapat ditebak.
- Pisahkan berkas publik, berkas pemohon, dan dokumen internal seperti cerapan. Terapkan retensi serta pencadangan sesuai kebijakan instansi.
- Catat audit untuk login internal, perubahan data utama, status, penugasan, unggah/penggantian dokumen, dan penerbitan/unggah SKHP.
- Terapkan validasi server, perlindungan CSRF, pembatasan percobaan login, manajemen sesi, dan otorisasi per objek agar pemohon hanya melihat datanya sendiri.
- Gunakan HTTPS pada produksi, kata sandi yang di-hash dengan aman, pencadangan teruji, dan prosedur pemulihan.
- Karena target teknis awal adalah CI3, mulai dengan modul terpisah dan pola MVC yang konsisten. Hindari perubahan besar ke framework lain pada tahap awal; evaluasi pemeliharaan dan dukungan versi CI3 sebelum peluncuran produksi.
- Jadwalkan pengingat dan pembuatan notifikasi melalui job terjadwal yang aman/idempoten agar pesan tidak terkirim berulang.

## 9. Roadmap Implementasi

Tahap dan durasi berikut merupakan urutan kerja awal, bukan komitmen kalender. Setiap tahap ditutup dengan uji coba bersama pengguna dan perbaikan alur.

| Tahap | Cakupan | Hasil yang dituju |
| --- | --- | --- |
| 0. Validasi proses dan data | Petakan SOP tera/tera ulang, jenis UTTP dan atributnya, status resmi, alur kantor/lapangan, pejabat penerbit, dokumen, aturan pengingat, kanal komunikasi, dan kebutuhan laporan. Rapikan master kecamatan/desa, pemohon, serta daftar jenis UTTP bila tersedia. | Kamus data, alur yang disepakati, matriks peran, template dokumen, dan prioritas MVP. |
| 1. Fondasi dan ruang informasi | Kerangka CI3, autentikasi, RBAC, audit dasar, profil pemohon, portal informasi, pengelolaan konten, daftar referensi, tata letak responsif, dan konfigurasi dokumen privat. | Portal dapat dipakai; pengguna dan petugas memiliki akses sesuai peran. |
| 2. MVP pengajuan dan pelacakan | Registri UTTP, formulir multi-UTTP, draf, validasi, nomor permohonan, bukti PDF, breakdown unit, verifikasi/perbaikan, timeline, unggah berkas, notifikasi dasar. | Pemohon dapat mengajukan dan melihat status; petugas memproses antrean dari sistem. |
| 3. Operasional teknis dan SKHP | Penugasan, jadwal, catatan layanan lapangan, cerapan internal, telaah hasil, unggah SKHP per unit, unduhan pemohon, penyelesaian sebagian/seluruh permohonan, kritik-saran. | Alur inti dari penerimaan sampai hasil tersedia terlacak ujung ke ujung. |
| 4. Pelaporan dan GIS operasional | Dashboard berbasis antrean, laporan periode, ekspor terkontrol, peta internal lokasi kunjungan, filter wilayah, verifikasi koordinat, ringkasan rute/kelompok kunjungan. | Koordinator dapat merencanakan operasi berdasarkan beban dan lokasi yang tercatat. |
| 5. Otomasi dan pengembangan | Pengingat tera ulang berbasis aturan yang disahkan, integrasi notifikasi, validasi QR SKHP/bukti, survei dan analitik layanan, integrasi eksternal jika API resmi dan kesepakatan tersedia. | Pengingat dan integrasi mengurangi pekerjaan manual tanpa mengurangi kendali petugas. |

Untuk MVP, prioritasnya adalah alur pengajuan multi-UTTP sampai status per unit dan akses dokumen yang aman. GIS, SSO, integrasi WhatsApp, dan analitik lanjutan dapat menyusul setelah proses inti dan kualitas data stabil.

## 10. Ukuran Keberhasilan Awal

- Pemohon dapat menyelesaikan pengajuan tanpa mengisi ulang profil/UTTP yang sudah tersimpan.
- Petugas dapat menemukan permohonan berdasarkan nomor, pemohon, tanggal, jenis, dan status.
- Setiap unit mempunyai status, penanggung jawab, dan riwayat yang dapat ditelusuri.
- Pemohon mengetahui kekurangan dan langkah berikutnya tanpa harus menghubungi petugas untuk menanyakan status dasar.
- SKHP dan cerapan hanya dapat diakses oleh peran yang tepat.
- Waktu verifikasi, waktu penyelesaian, jumlah perbaikan berkas, antrean, dan umpan balik dapat dilaporkan dengan definisi yang konsisten.

## 11. Hal yang Perlu Ditetapkan Sebelum Pengembangan Lanjut

1. SOP status resmi dan terminologi pelayanan tera/tera ulang, termasuk kondisi batal/tidak dapat diselesaikan.
2. Apakah semua permohonan perlu diverifikasi lokasi dan kapan layanan lapangan diperlukan.
3. Atribut wajib UTTP per jenis, identifikasi unit fisik, serta penanganan beberapa unit dengan karakteristik sama.
4. Format nomor permohonan, QR, bukti penerimaan, format/otorisasi SKHP, dan tata cara pengambilan/penyerahan.
5. Kanal resmi notifikasi dan pesan apa yang boleh disampaikan melalui kanal tersebut.
6. Ketentuan masa berlaku/pengingat per jenis UTTP, tarif bila ada, retensi dokumen, serta kewenangan akses dan pengesahan.
7. Definisi laporan publik dan internal, data yang boleh dipublikasikan, serta dasar publikasi lokasi.

Dokumen ini adalah draft rancangan produk berdasarkan PDF kebutuhan awal dan pola pengelolaan layanan pada rancangan koperasi/UKM. Detail teknis dan prosedural perlu disahkan bersama UPT/pemilik proses sebelum menjadi spesifikasi implementasi.
