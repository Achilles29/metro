# Panduan pemilik akun Google untuk Setimbang

Panduan ini untuk pemilik akun Google yang mengelola laporan metrologi. Silakan teruskan kepada orang yang akan menyiapkan Google Drive dan spreadsheet. Setelah pengaturan Google selesai, administrator Setimbang akan menghubungkan dan mengujinya.

**Hasil yang diharapkan:** laporan CTT, order, dan TTU masuk ke spreadsheet yang sesuai; PDF cerapan tersimpan di folder **Hasil Laporan Website**.

## Sebelum mulai

Siapkan hal berikut:

- Akun Google yang dapat membuka ketiga spreadsheet dan folder hasil.
- Akses untuk membuat atau mengelola satu proyek di [Google Cloud Console](https://console.cloud.google.com/).
- Bantuan administrator Setimbang yang dapat membuka [halaman Integrasi Google](https://metrologi.projectpintar.com/petugas/integrasi).

**Jangan bagikan kata sandi Google.** Bila nanti mengunduh berkas penghubung dari Google, simpan secara pribadi dan berikan hanya kepada administrator Setimbang melalui saluran yang aman.

## Langkah 1 — Periksa lokasi folder hasil

1. Buka [folder Hasil Laporan Website](https://drive.google.com/drive/folders/1xIJV2ZHLVecQXTj70L-Cx0MXHmX7aLuv).
2. Lihat lokasi folder di Google Drive:
   - Jika berada di **Drive Saya / My Drive**, ikuti **Pilihan A: Hubungkan akun Google**.
   - Jika berada di **Drive bersama / Shared Drive**, ikuti **Pilihan B: Buat akun layanan**. Pilihan A juga dapat dipakai jika lebih sesuai.
3. Catat pilihan tersebut untuk disampaikan kepada administrator Setimbang.

Folder di Drive Saya perlu dihubungkan melalui akun Google pemilik atau akun lain yang diberi izin. Akun layanan hanya cocok untuk penyimpanan PDF di Drive bersama. [Penjelasan resmi Google](https://developers.google.com/workspace/drive/api/guides/about-shareddrives).

## Langkah 2 — Pastikan semua tujuan bisa diakses

Buka keempat tautan berikut dan pastikan akun yang akan dihubungkan dapat mengakses semuanya:

1. [Spreadsheet laporan CTT](https://docs.google.com/spreadsheets/d/1vRT5UnWhgJAE6btv4-5nu92X-dYsxO0CVAk77J8mGDc/edit).
2. [Spreadsheet laporan order](https://docs.google.com/spreadsheets/d/1W7gjvozo0O2VL3eiP0GGK7036At-14upy6xPCDaH5ZM/edit?gid=0#gid=0).
3. [Spreadsheet laporan TTU](https://docs.google.com/spreadsheets/d/14rX60Mmuo8vDsLvWoLCffoBs2DsY5mJy_jNLVRUnpzM/edit?gid=911257616#gid=911257616).
4. [Folder penyimpanan PDF cerapan](https://drive.google.com/drive/folders/1xIJV2ZHLVecQXTj70L-Cx0MXHmX7aLuv).

Untuk ketiga spreadsheet, izin yang dibutuhkan adalah **Editor**. Pada folder hasil, akun harus **boleh menambahkan berkas**. Bila berkas dimiliki akun yang berbeda, buka berkas tersebut, klik **Bagikan**, lalu berikan izin kepada akun yang akan dihubungkan. Ulangi pada **setiap** spreadsheet dan folder. Jika akun tersebut sudah menjadi pemilik, langkah berbagi tidak diperlukan.

Nama tab laporan yang dipakai adalah **Template** pada CTT dan order; pada TTU terdapat **Template Sah**, **Template Batal**, dan **Template Dikembalikan/Tidak sesuai**. Nama tab jangan diubah tanpa menyesuaikan pengaturan Setimbang.

## Langkah 3 — Aktifkan layanan Google

Langkah ini dilakukan **sekali** untuk kedua pilihan di bawah.

1. Masuk ke [Google Cloud Console](https://console.cloud.google.com/).
2. Pilih proyek untuk Setimbang di bagian atas layar. Jika belum ada, buat proyek baru dan beri nama yang mudah dikenali, misalnya **Setimbang Rembang**.
3. Buka menu **APIs & Services**, lalu **Library**.
4. Cari **Google Sheets API**, buka, lalu klik **Enable / Aktifkan**.
5. Kembali ke Library. Cari **Google Drive API**, buka, lalu klik **Enable / Aktifkan**.
6. Pastikan kedua layanan tersebut sudah tertulis sebagai **Enabled / Aktif**. Jangan berpindah proyek saat melanjutkan langkah berikutnya.

[Petunjuk resmi Google untuk mengaktifkan layanan](https://developers.google.com/workspace/guides/enable-apis).

## Pilihan A — Hubungkan akun Google

**Pilih ini jika folder hasil berada di Drive Saya.** Orang yang menghubungkan harus masuk dengan akun Google yang sudah mendapat izin pada langkah 2.

### A. Siapkan izin aplikasi di Google Cloud

1. Di proyek yang sama, buka **Google Auth platform → Branding**. Jika muncul **Get Started / Mulai**, klik lalu isi nama aplikasi, misalnya **Setimbang Kabupaten Rembang**, serta alamat email yang diminta. Simpan.
2. Buka **Audience / Pengguna**:
   - Pilih **Internal** bila akun dan proyek Google berada dalam organisasi Google Workspace yang sama.
   - Pilih **External** bila memakai Gmail pribadi atau akun di luar organisasi.
3. Jika memilih **External** dan statusnya masih **Testing / Pengujian**, tambahkan alamat email akun yang akan menghubungkan Setimbang pada daftar **Test users / Pengguna uji**.
4. Jika Google meminta **Data Access / Akses data**, pilih izin untuk **Google Sheets** dan **Google Drive** yang diminta aplikasi. Administrator teknis dapat melihat rincian izin pada catatan di bagian akhir panduan.

**Untuk penggunaan rutin:** status **External + Testing** dapat menyebabkan sambungan perlu diperbarui setelah sekitar **7 hari**. Jika memungkinkan, gunakan **Internal**. Untuk External yang dipakai terus-menerus, ikuti proses **In production** dan persyaratan verifikasi dari Google. Izin Drive yang dipakai aplikasi saat ini termasuk kategori yang dapat memerlukan pemeriksaan tambahan. [Penjelasan Google tentang layar persetujuan](https://developers.google.com/workspace/guides/configure-oauth-consent) dan [masa berlaku sambungan](https://developers.google.com/identity/protocols/oauth2#expiration).

### B. Buat berkas penghubung

1. Buka **Google Auth platform → Clients** lalu klik **Create Client / Buat klien**.
2. Pilih jenis **Web application / Aplikasi web**. Beri nama, misalnya **Setimbang Produksi**.
3. Pada kolom **Authorized redirect URIs / URI pengalihan yang diizinkan**, masukkan alamat berikut **persis**, termasuk awalan `https` dan subdomain `metrologi`:

   **https://metrologi.projectpintar.com/petugas/integrasi/google/callback**

4. Klik **Create / Buat**, lalu unduh berkas **JSON** dari klien yang baru dibuat. Pilih berkas untuk **Web application**, bukan untuk aplikasi desktop.
5. Simpan berkas tersebut secara pribadi. Serahkan kepada administrator Setimbang melalui saluran aman, atau unggah sendiri bila Anda memiliki akses administrator Setimbang.

[Petunjuk resmi Google untuk membuat klien aplikasi web](https://developers.google.com/workspace/guides/create-credentials#web_application).

### C. Hubungkan di Setimbang

1. Administrator membuka [Integrasi Google Setimbang](https://metrologi.projectpintar.com/petugas/integrasi).
2. Pada bagian **OAuth akun Google**, unggah berkas JSON tadi dengan tombol **Unggah OAuth client**.
3. Klik **Hubungkan akun Google**.
4. Di halaman Google, pilih **akun yang sudah memiliki izin** ke tiga spreadsheet dan folder hasil. Setujui permintaan akses, lalu tunggu sampai kembali ke Setimbang.
5. Pastikan status pada bagian OAuth berubah menjadi **Tersambung**.
6. Lanjutkan ke bagian **Uji hasil**.

## Pilihan B — Buat akun layanan untuk Drive bersama

**Pilih ini hanya jika folder hasil berada di Drive bersama.** Akun layanan adalah akun khusus untuk aplikasi. Alamat emailnya akan diberi izin pada spreadsheet dan Drive bersama.

1. Di proyek Google Cloud yang sama, buka **IAM & Admin → Service Accounts / Akun layanan**.
2. Klik **Create service account / Buat akun layanan**. Beri nama, misalnya **setimbang-laporan**, lalu selesaikan pembuatan.
3. Buka akun layanan yang baru dibuat. Pilih **Keys / Kunci → Add key / Tambah kunci → Create new key / Buat kunci baru → JSON**. Google akan mengunduh satu berkas.
4. Salin **alamat email akun layanan** yang ditampilkan Google. Alamat ini biasanya berakhiran `iam.gserviceaccount.com`.
5. Buka **masing-masing** dari tiga spreadsheet pada langkah 2. Klik **Bagikan**, masukkan alamat email akun layanan, lalu beri izin **Editor**. Lakukan pada ketiganya.
6. Tambahkan alamat email akun layanan sebagai anggota **Drive bersama** yang berisi folder hasil, dengan izin yang dapat membuat atau mengunggah berkas. Jika organisasi melarang penambahan akun layanan, minta bantuan pengelola Google Workspace atau gunakan Pilihan A.
7. Administrator membuka [Integrasi Google Setimbang](https://metrologi.projectpintar.com/petugas/integrasi). Pada bagian **Akun layanan**, unggah berkas JSON dengan tombol **Unggah kredensial**.
8. Di bagian **Spreadsheet dan folder**, pilih **Metode akses Google: Akun layanan (Shared Drive)**, lalu klik **Simpan pengaturan**.
9. Lanjutkan ke bagian **Uji hasil**.

Jaga berkas JSON seperti kata sandi. Izin pada proyek Google Cloud saja tidak memberi akses ke spreadsheet; ketiga berkas tetap harus dibagikan ke alamat email akun layanan. [Petunjuk resmi Google tentang akun layanan](https://developers.google.com/workspace/guides/create-credentials#service_account_credentials).

## Uji hasil — dilakukan oleh administrator Setimbang

1. Buka [Integrasi Google Setimbang](https://metrologi.projectpintar.com/petugas/integrasi). Pastikan pilihan metode sesuai dengan yang disiapkan: **OAuth akun admin** atau **Akun layanan**.
2. Klik **Uji koneksi semua tujuan**. Pesan berhasil seharusnya menyebut **CTT, Order, TTU, dan Folder**.
3. Buat **satu permohonan uji** yang disepakati petugas dan catat nomornya. Pemeriksaan ini akan benar-benar menambah data ke spreadsheet dan folder.
4. Pada permohonan uji, catat peminjaman CTT, lalu **Tandai kembali**. Periksa apakah laporan muncul di spreadsheet CTT. Laporan CTT baru dikirim setelah CTT dikembalikan.
5. Selesaikan semua UTTP pada permohonan. Periksa laporan order dan TTU di spreadsheet masing-masing. Untuk UTTP dengan hasil **Sah**, laporan order menunggu berkas **SKHP** tersedia.
6. Pada dokumen cerapan, klik **Kirim ke Drive**. Periksa apakah PDF muncul di folder hasil dan status di Setimbang menjadi **Terkirim ke Drive**.
7. Jika ada laporan yang tertunda, lihat pesan pada detail permohonan. Setelah penyebabnya diperbaiki, gunakan tombol **Kirim laporan** untuk CTT atau **Sinkronkan laporan order dan TTU** untuk laporan lainnya.

**Catatan:** tombol **Uji koneksi semua tujuan** hanya memeriksa bahwa spreadsheet dan folder dapat dikenali. Langkah 3–6 diperlukan untuk membuktikan bahwa Setimbang benar-benar dapat **menulis** laporan dan mengunggah PDF.

## Jika ada kendala

- **Google menolak saat menghubungkan akun:** periksa apakah akun yang dipilih sudah masuk daftar **Test users**, atau apakah aplikasi dibatasi oleh kebijakan Google Workspace.
- **Muncul kesalahan alamat pengalihan:** cocokkan alamat pada **Authorized redirect URIs** dengan alamat pada Pilihan A bagian B. Harus sama persis.
- **Spreadsheet atau folder tidak ditemukan:** pastikan akun yang terhubung sudah diberi akses ke **semua** tujuan pada langkah 2, bukan hanya salah satunya.
- **Folder dinyatakan berada di My Drive:** gunakan **Pilihan A**. Pilihan B hanya untuk folder di **Drive bersama**.
- **Koneksi berhasil tetapi laporan belum muncul:** pastikan CTT sudah dikembalikan, semua UTTP sudah selesai, dan SKHP untuk hasil Sah sudah tersedia. Periksa juga pesan sinkronisasi pada detail permohonan.
- **PDF belum ada di folder:** pastikan tombol **Kirim ke Drive** sudah ditekan dan akun yang dihubungkan boleh menambahkan berkas di folder hasil.
- **Sambungan OAuth tiba-tiba berhenti setelah beberapa hari:** jika aplikasi masih berstatus **External + Testing**, hubungkan ulang dan tinjau pengaturan penggunaan rutin pada Pilihan A.

## Daftar cek sebelum diserahkan ke administrator

- [ ] Lokasi folder hasil sudah diketahui: **Drive Saya** atau **Drive bersama**.
- [ ] **Google Sheets API** dan **Google Drive API** sudah aktif pada proyek yang sama.
- [ ] Akun yang akan dipakai sudah dapat mengedit **CTT, order, dan TTU**.
- [ ] Akun tersebut sudah dapat menambahkan PDF ke folder hasil.
- [ ] Berkas JSON yang sesuai sudah dibuat dan disimpan secara pribadi.
- [ ] Untuk Pilihan A, alamat pengalihan sudah didaftarkan dan akun yang akan masuk sudah diizinkan pada pengaturan Audience.
- [ ] Administrator Setimbang sudah menerima berkas JSON secara aman atau mengunggahnya sendiri.
- [ ] **Uji koneksi** dan **satu uji penulisan** sudah berhasil.

**Informasi yang cukup disampaikan kepada administrator:** lokasi folder, pilihan A atau B, alamat email akun yang diberi izin, nama proyek Google Cloud, serta status langkah pada daftar cek. Jangan sertakan kata sandi Google atau isi berkas JSON dalam pesan biasa.

### Catatan untuk administrator teknis

Setimbang sudah menyimpan tautan tujuan dan pengaturan tab laporan. Jika tidak ada perubahan pada berkas Google, isian tersebut tidak perlu diubah. Pada pengaturan izin OAuth di Google Cloud, aplikasi saat ini meminta akses **Google Sheets** dan **Google Drive**. Jika Google meminta alamat izin secara tepat, gunakan `https://www.googleapis.com/auth/spreadsheets` dan `https://www.googleapis.com/auth/drive`. Izin Drive tersebut tergolong **restricted** menurut [dokumentasi Google](https://developers.google.com/workspace/drive/api/guides/api-specific-auth#drive-api-scopes), sehingga penerbitan untuk pengguna eksternal dapat memerlukan verifikasi tambahan.
