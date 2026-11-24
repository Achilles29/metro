# Alur verifikasi, GIS admin, dan integrasi dokumen

> Dokumen ini mencatat implementasi sebelumnya. Alur terbaru dan pengaturan spreadsheet ada di [revisi 13 November 2026](11_revisi_website_20261113.md).

## Perubahan yang diterapkan

- **Data pemohon** memiliki tautan **Detail**, berisi identitas, alamat, peta profil dan seluruh titik UTTP, permohonan aktif, selesai, daftar UTTP, serta riwayat per unit. Menu **Peta GIS** pada sidebar menampilkan profil dan UTTP dengan filter kecamatan.
- Peta pada form profil, UTTP, dan permohonan tampil langsung ketika form terlihat. Koordinat dapat diketik atau penanda digeser. Halaman daftar UTTP juga memiliki peta ringkasan.
- **Input permohonan loket** tersedia di menu permohonan untuk petugas yang memiliki izin `requests.create_offline` (superadmin secara default). Petugas memilih pemohon, lalu memakai form yang sama dengan permohonan daring. Alat baru otomatis tercatat di akun pemohon/perusahaan, termasuk saat diajukan teknisi terhubung.
- Detail kerja petugas memiliki urutan tab **Penera → Verifikasi → CTT → Hasil & dokumen → Riwayat**, dengan UTTP yang diajukan di atas tab. Verifikasi dimulai setelah penera dipilih. Hasil disimpan per uraian dan per UTTP; pilihan **OK, Tolak, Terima sebagian** menentukan unit yang lolos dan tidak. Hasil ditetapkan sekali, lalu CTT dapat ditambahkan lebih dari satu. Master rincian pemeriksaan ada di **Master data → Rincian verifikasi**.
- Setelah verifikasi, layanan **di lokasi pemohon** berstatus terjadwal, sedangkan layanan **di kantor** masuk pengujian. Status selesai dapat dipilih setelah CTT tercatat. Hasil per UTTP yang ditolak tetap terlihat pada detail dan PDF verifikasi. Daftar permohonan memisahkan tab **Aktif, Selesai · sah, Selesai · batal** berdasarkan status seluruh unit.
- Foto dokumentasi JPG/PNG bisa diambil lewat kamera/galeri pada tab verifikasi. Foto disimpan privat dan hanya diakses petugas berwenang. PDF surat, cerapan, SKHP, bukti order, serta hasil verifikasi dibuka sebagai pratinjau di browser; unduhan tersedia melalui kontrol PDF browser atau `?download=1`.
- Draf cerapan dapat dihasilkan dari data permohonan, UTTP, penera, CTT, dan catatan pengujian pada tahap terjadwal/pengujian. Petugas juga dapat mengunggah cerapan PDF setelah CTT. Nama asli berkas ditampilkan bersama tautan pratinjau. SKHP diunggah setelah unit selesai. Notifikasi WA untuk SKHP dan penyelesaian masuk antrean ketika nomor pemohon telah terverifikasi; pengiriman memerlukan bot WA terhubung.
- Riwayat tiap UTTP tersedia bagi pemohon lewat **Daftar UTTP → Riwayat**, dan dalam tab **Riwayat per UTTP** pada profil admin. Satu alat tersimpan selalu mewakili satu unit fisik; pengajuan jumlah lebih dari satu membuat registri alat terpisah.

## Google Sheets dan Drive

Superadmin membuka **Integrasi Google**, mengunggah JSON service account dan mengisi Spreadsheet ID, rentang tab, Folder ID Drive bersama, serta pola nama cerapan. Sheet dan Drive bersama harus memberi akses kepada email service account yang tampil di halaman pengaturan. Tombol **Uji koneksi** memeriksa akses. CTT yang ditambahkan pada permohonan dicatat lokal lebih dulu, kemudian dikirim sebagai satu baris ke Google Sheets; jika gagal, pesan kesalahan tampil dan tersedia **Kirim ke sheet**. Master CTT dapat berisi link spreadsheet tersendiri. Pada PDF cerapan yang sudah diunggah, tombol **Kirim ke Drive** memakai pola nama yang dapat diedit, misalnya `Cerapan-{request_no}-Unit-{unit_sequence}-{date}.pdf`.

Akun layanan Google tidak memiliki kuota penyimpanan dan tidak dapat memiliki berkas di My Drive. Folder tujuan harus berada di **Shared Drive**; alternatif untuk My Drive memerlukan OAuth atas nama pengguna. Rujukan: [Google Drive error guide](https://developers.google.com/workspace/drive/api/guides/handle-errors), [dukungan Shared Drive](https://developers.google.com/workspace/drive/api/guides/enable-shareddrives), [Sheets append](https://developers.google.com/workspace/sheets/api/reference/rest/v4/spreadsheets.values/append). Kredensial disimpan di `/var/lib/metro/google/service-account.json`, di luar web. Integrasi Google belum dapat diuji terhadap akun nyata karena kredensial dan ID milik instansi belum tersedia; tombol serta penanganan kegagalan sudah siap.

## Operasional dan uji

Migrasi `database/008_verification_workflow.sql` diterapkan sesudah 007. Cadangan sebelum migrasi: `/root/metro-backups/metro-before-verification-20261113-105017.sql.gz`. Modul WA `metro-wa.service` sudah dimuat ulang. Nomor layanan masih perlu memindai QR di **WhatsApp** sebelum notifikasi benar-benar dikirim.

Uji langsung mencakup input permohonan oleh admin, dua UTTP terpisah, pemilihan penera, checklist hasil sebagian, penetapan per unit, CTT, pratinjau hasil PDF, foto dokumentasi, generate serta unggah dan pratinjau cerapan, SKHP, serta antrean notifikasi WA. Alur perusahaan–teknisi juga diuji ulang. Semua akun, permohonan, dan berkas uji dibersihkan setelahnya.
