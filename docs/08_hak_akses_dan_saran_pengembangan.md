# Hak akses dan saran pengembangan Metro

## Pengaturan hak akses

Buka **Ruang kerja → Hak akses** atau `https://metrologi.projectpintar.com/petugas/hak-akses` menggunakan akun superadmin (`administrator`). Pilih peran petugas, centang izin yang sesuai tugasnya, lalu simpan. Pengaturan ini diterapkan pada menu, halaman, tindakan, ekspor, dan unduhan dokumen di server. Perubahan berlaku pada permintaan berikutnya dan dicatat di log aktivitas.

Izin disimpan pada tabel `permissions` dan `role_permissions` melalui migrasi `database/006_role_permissions.sql`. Nilai awal menyalin akses lama untuk peran verifier, coordinator, technician, dan issuer. Superadmin selalu memiliki seluruh akses dan tidak dapat dikunci melalui matriks. Peran pemohon mengakses data miliknya sendiri. Izin dasar **Lihat antrean** wajib untuk semua peran petugas, sedangkan **Kelola akun petugas** khusus superadmin agar pengelola peran tidak dapat menaikkan haknya sendiri lewat perubahan akun.

**Lihat semua permohonan** menentukan cakupan data. Tanpa izin itu, petugas hanya melihat permohonan dan unit yang masih ditugaskan kepadanya. Izin tindakan seperti verifikasi, penugasan, status unit, dan unggah dokumen tetap mengikuti syarat tahap proses. Akses dokumen privat diperiksa lagi pada endpoint unduh; menyembunyikan tautan saja tidak dianggap pengamanan.

Sebelum mengubah izin peran yang sedang dipakai, cocokkan tugas nyata petugas. Akun dengan peran tersebut menerima perubahan tanpa perlu membuat akun baru. Cadangan basis data sebelum migrasi disimpan di `/root/metro-backups/metro-before-permissions-20261112-151114.sql.gz`.

## Saran pengembangan berikutnya

Urutan ini mengikuti [roadmap aplikasi](02_rancangan_aplikasi_dan_roadmap.md) dan [catatan implementasi](05_implementasi_dan_operasional.md). Ini adalah usulan kerja, bukan keputusan SOP resmi.

1. **Sahkan alur layanan bersama UPT.** Tetapkan status resmi, kewenangan pemeriksa dan penerbit, format SKHP, serta aturan penyerahan hasil. Cocokkan SOP dengan [Permendag 24/2024 yang berstatus berlaku](https://jdih.kemendag.go.id/peraturan/peraturan-menteri-perdagangan-nomor-24-tahun-2024-tentang-kegiatan-tera-dan-tera-ulang-alat-ukur-alat-timbang-dan-alat-perlengkapan-metrologi-legal-1) sebelum mengotomatiskan keputusan layanan.
2. **Tetapkan pengesahan cerapan dan SKHP per unit.** Draf/unggah cerapan kini tersedia sejak pengujian atau jadwal lapangan, dan SKHP sesudah unit selesai. Berikutnya, sediakan telaah dan tanda tangan/pengesahan berjenjang sesuai SOP yang disahkan.
3. **Bangun jadwal dan kunjungan lapangan.** Koordinator memerlukan kalender, penugasan, catatan hasil kunjungan, dan pemberitahuan perubahan jadwal. Peta titik sudah tersedia; kalender, rute, dan bukti kunjungan dapat memanfaatkannya.
4. **Tambahkan draf pengajuan.** Daftar UTTP tersimpan dan titik lokasi per alat sudah tersedia; langkah berikutnya adalah menyimpan pengajuan yang belum dikirim agar pemohon atau teknisi dapat melanjutkannya.
5. **Perkuat operasi produksi.** Buat uji otomatis untuk hak akses, perpindahan status, dan dokumen; lakukan uji pemulihan cadangan; tinjau keamanan memakai [OWASP ASVS](https://asvs.owasp.org/). [CodeIgniter 3 berada dalam mode pemeliharaan](https://codeigniter.com/index.php/download), sehingga rencana pembaruan platform perlu disiapkan bertahap.

**Pilihan tahap terdekat:** sahkan SOP, lalu selesaikan alur teknis hingga SKHP. Pengingat tera ulang dan QR dokumen menyusul ketika tanggal berlaku serta format dokumen sudah disepakati. Verifikasi WhatsApp, GIS, dan alur checklist/CTT dijelaskan di [catatan Setimbang](09_setimbang_whatsapp_gis_teknisi.md) serta [revisi verifikasi](10_alur_verifikasi_gis_integrasi.md).
