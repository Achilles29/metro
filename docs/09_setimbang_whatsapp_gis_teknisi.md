# Setimbang — WhatsApp, GIS, dan teknisi eksternal

Nama aplikasi: **Setimbang** (**Sistem pendaftaran tera dan informasi pengawasan Kabupaten Rembang**). Domain tetap `https://metrologi.projectpintar.com`; folder kode tetap `/www/wwwroot/metro` agar konfigurasi situs dan data lama tidak berubah.

## Verifikasi WhatsApp

Superadmin membuka **Ruang kerja → WhatsApp**, menekan **Aktifkan / sambungkan**, lalu memindai QR melalui WhatsApp nomor layanan → **Perangkat tertaut**. Halaman menampilkan status, nomor tertaut, QR, template kode, serta 30 pengiriman terakhir. Bot hanya mendengar di `127.0.0.1:3072` dan memakai token dari `/etc/metro/wa.php` dan `/etc/metro/wa-engine.env`; file tersebut tidak berada di web. Source `wa-engine/` ditutup oleh aturan Nginx. Service `metro-wa.service` aktif otomatis saat server mulai.

Saat status **CONNECTED**, pendaftaran pemohon atau teknisi membuat kode enam digit dan memasukkannya ke antrean WhatsApp. Pemilik akun dapat meminta pengiriman ulang pada halaman profil. Batasnya lima kode per jam, tiap kode berlaku 15 menit dengan maksimal lima percobaan. Kode pada tabel verifikasi disimpan sebagai hash. Teks kode hanya berada sementara di antrean pengiriman, lalu dihapus setelah terkirim, dibatalkan, atau kedaluwarsa; pesan yang belum dikirim dibatalkan bila nomor berubah atau kode terpakai. Isi pesan dapat diubah superadmin dengan placeholder `{code}`.

**Cadangan manual tetap ada.** Admin membuka profil pemohon atau daftar teknisi, memilih **Kode manual**, lalu menyampaikan kode yang tampil satu kali kepada pemilik nomor. Jalur ini berfungsi saat bot terputus atau nomor WhatsApp layanan dibatasi. Kode manual dan otomatis memakai verifikasi serta batas per jam yang sama.

QR sudah dapat dimuat, tetapi **pengiriman nyata belum dapat diuji sampai nomor layanan dipindai**. Gunakan nomor layanan khusus; engine QR memakai Baileys seperti Pustaka/Finance. Sesi aplikasi ini terpisah dari keduanya. Status koneksi dan hasil pengiriman perlu diperiksa setelah penautan. Jika layanan resmi berkapasitas besar diperlukan, evaluasi WhatsApp Business Platform sebelum mengganti engine.

## GIS profil dan UTTP

Profil pemohon menyimpan alamat, lintang, bujur, dan asal koordinat. **UTTP saya** menyimpan lebih dari satu alat per akun, tiap alat dengan jenis, identitas, alamat, dan titik sendiri. Titik bisa diketik sebagai angka atau dipilih pada peta lalu penandanya digeser. Form pengajuan dapat menarik UTTP tersimpan; alat baru yang diajukan sendiri oleh pemilik akun masuk ke registri. Teknisi yang mewakili perusahaan dapat membaca daftar UTTP perusahaan, sementara perubahan registri tetap dilakukan perusahaan.

Saat permohonan dikirim, alamat dan titik disalin ke setiap unit `request_items`. Karena itu perubahan profil atau registri kemudian tidak mengubah lokasi historis permohonan. Halaman detail pemohon/petugas, profil pemohon di ruang admin, dan bukti PDF menampilkan lokasi per unit. Koordinat opsional agar data lama dan alamat yang belum dipetakan tetap bisa dipakai; lintang dan bujur harus diisi berpasangan dan valid.

Peta memakai Leaflet 1.9.4 yang disalin dari aset proyek UMKM dan ubin interaktif dari OpenStreetMap dengan atribusi terlihat. Peta tampil langsung saat formulir terlihat, dengan Leaflet yang dimuat seperlunya dan tanpa pengunduhan massal. Penggunaan ubin mengikuti [kebijakan resmi OpenStreetMap](https://operations.osmfoundation.org/policies/tiles/). Koordinat pemohon tidak ditampilkan pada portal publik.

## Teknisi eksternal dan pelimpahan

Jenis akun **Teknisi eksternal** terpisah dari petugas teknis internal. Saat mendaftar, teknisi mengisi nomor telepon dan nomor rekomendasi dinas. Nomor telepon diverifikasi dengan cara yang sama seperti pemohon. Dinas memeriksa rekomendasi dan menyetujui atau menolak akun pada **Ruang kerja → Teknisi eksternal**. Perubahan data rekomendasi setelah disetujui meminta verifikasi ulang.

Pada **Pelimpahan**, perusahaan dapat mengundang teknisi terverifikasi melalui email, atau teknisi dapat mengajukan hubungan ke email perusahaan. Pihak penerima undangan harus menyetujui, lalu dinas mengesahkan hubungan. Hanya hubungan **aktif** yang memungkinkan teknisi memilih perusahaan, menarik UTTP-nya, dan mengirim permohonan. Perusahaan tetap melihat seluruh permohonan miliknya. Teknisi melihat permohonan yang ia kirim sendiri selama hubungan aktif; pencabutan hubungan segera menghentikan akses teknisi ke data perusahaan. Tindakan persetujuan dan pencabutan dicatat di log aktivitas.

Akun perusahaan menggunakan jenis profil **Instansi / perusahaan** dan nama perusahaan yang terisi. Akun teknisi tidak diberi akses ruang petugas internal. Persetujuan hubungan memerlukan akun teknisi aktif, nomor terverifikasi, rekomendasi disetujui dinas, persetujuan pihak kedua, dan keputusan dinas.

## Pemasangan dan pemeriksaan

Migrasi `database/007_setimbang_wa_gis_technicians.sql` diterapkan setelah 006. Cadangan sebelum migrasi: `/root/metro-backups/metro-before-setimbang-20261113-095745.sql.gz`. Kredensial admin tetap di `docs/AKSES_ADMIN.md`, yang URL-nya ditutup oleh Nginx.

Uji akun sementara mencakup pendaftaran perusahaan dan teknisi, kode manual, profil dan dua UTTP dengan koordinat berbeda, verifikasi dinas, pelimpahan yang dimulai perusahaan, pencabutan, pelimpahan yang dimulai teknisi, permohonan oleh teknisi, snapshot lokasi tiap unit, dan penolakan akses teknisi setelah hubungan dicabut. Semua akun, hubungan, permohonan, PDF, dan data uji dihapus sesudahnya; nomor urut uji dikembalikan karena tidak ada nomor lain yang dibuat selama uji. Halaman admin WhatsApp dan teknisi merespons 200, QR tersedia, dan source engine merespons 404 dari web.
