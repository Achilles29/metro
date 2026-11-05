# Rancangan Awal Database Metro

**Status:** draft v0.1 untuk dibahas sebelum implementasi fitur.  
**Target awal:** MySQL/MariaDB InnoDB, `utf8mb4`, sesuai proyek CI3 3.1.13.

## 1. Keputusan Model Data

- Akun disimpan terpisah dari profil pemohon. Petugas dan pemohon memakai tabel akun yang sama, lalu memperoleh peran melalui relasi many-to-many.
- Satu `service_request` mewakili satu pengajuan tera/tera ulang; satu pengajuan mempunyai banyak `request_items`.
- **Satu baris `request_items` mewakili satu UTTP fisik.** Jika pemohon mengisi jumlah 3, aplikasi membuat tiga baris unit. Ini memungkinkan status, cerapan, penugasan, dan SKHP per unit.
- Data UTTP katalog/pemohon dapat dipakai ulang, sedangkan nilai saat diajukan disimpan sebagai snapshot pada item agar riwayat tidak berubah jika profil UTTP diperbarui.
- Dokumen disimpan sebagai berkas privat di filesystem/object storage. Database hanya menyimpan metadata dan storage key, bukan blob dokumen.
- Status saat ini disimpan di entitas terkait untuk pencarian cepat; setiap perubahan status dicatat terpisah di tabel riwayat.
- Nomor permohonan memakai sequence per tahun dan jenis dokumen. Aplikasi harus mengambil nomor dalam transaksi dengan row lock agar tidak ganda saat pengajuan bersamaan.
- Permohonan induk memiliki status ringkasan. Status pekerjaan dan hasil teknis yang terperinci berada di tiap unit UTTP.

## 2. Relasi Inti

```text
users ──< user_roles >── roles
  │
  └── applicant_profiles ──< applicant_uttp >── uttp_types
                 │
                 └──< service_requests ──< request_items >── uttp_types
                              │                    │
                              │                    ├──< item_status_history
                              │                    ├──< staff_assignments
                              │                    └──< certificates >── service_documents
                              ├──< request_status_history
                              ├──< service_documents
                              └──< field_visits

users ──< service_documents / history / assignments / notifications / audit_logs
```

## 3. Tabel MVP

| Tabel | Fungsi | Catatan |
| --- | --- | --- |
| `users` | Akun login pemohon dan petugas | Kata sandi dalam bentuk hash; status akun dapat dinonaktifkan. |
| `roles`, `user_roles` | Hak akses berbasis peran | Peran awal: pemohon, verifikator, petugas teknis, koordinator, pejabat penerbit, administrator. Otorisasi tetap diperiksa di server. |
| `applicant_profiles` | Profil pemohon/instansi | Data kontak dipisah dari data login; satu profil pemohon per akun pada versi awal. |
| `uttp_types` | Referensi jenis UTTP | Ditambah/diperbarui petugas admin, tidak ditanam di source code. |
| `applicant_uttp` | Daftar UTTP tersimpan milik pemohon | Membantu pengisian ulang. Perubahan nilai tidak mengubah snapshot pengajuan lama. |
| `number_sequences` | Penghitung nomor per tahun dan jenis | Perlu transaksi dan `SELECT ... FOR UPDATE` saat menerbitkan nomor. |
| `service_requests` | Kepala permohonan | Nomor, pemohon, layanan, tanggal, mode kantor/lapangan, status ringkasan. |
| `request_items` | Unit fisik UTTP pada permohonan | Satu row per unit; menyimpan snapshot spesifikasi dan status terkini. |
| `request_status_history`, `item_status_history` | Jejak perubahan status | Simpan pelaku, waktu, catatan internal/pemohon, dan status sebelumnya/berikutnya. |
| `service_documents` | Metadata surat, cerapan, dan SKHP | Akses file melalui controller berotorisasi; cerapan ditandai internal. |
| `staff_assignments` | Penugasan verifikasi/teknis | Bisa ditetapkan per unit; versi awal menggunakan unit sebagai target tugas. |
| `field_visits` | Jadwal/hasil kunjungan lapangan | Koordinat bersifat internal; tidak dipublikasikan otomatis. |
| `certificates` | Metadata SKHP per unit | Mendukung nomor, tanggal, versi, pejabat penerbit, dan dokumen terkait. |
| `notifications` | Pemberitahuan in-app | Status kirim dan waktu baca; email/WA dapat ditambahkan kemudian. |
| `service_feedback` | Kritik-saran setelah layanan | Terpisah dari pengaduan formal. |
| `audit_logs` | Audit tindakan penting | Catat actor, aksi, objek, timestamp, IP, dan ringkasan perubahan non-rahasia. |

## 4. DDL Awal MySQL/MariaDB

File SQL pendamping: [`database/001_initial_schema.sql`](../database/001_initial_schema.sql). Jalankan pada database baru setelah meninjau nama status/atribut dan versi MySQL/MariaDB yang terpasang.

## 5. Status yang Disimpan

Status disimpan sebagai kode `VARCHAR` yang ditentukan aplikasi; deskripsi/terjemahan dikelola di satu tempat agar UI konsisten. Kode awal yang disarankan:

- Permohonan: `draft`, `submitted`, `verification`, `needs_revision`, `scheduled`, `in_service`, `partially_completed`, `completed`, `rejected`, `cancelled`.
- Unit UTTP: `submitted`, `verification`, `needs_revision`, `scheduled`, `testing`, `result_review`, `completed`, `not_completed`, `cancelled`.
- Penugasan: `assigned`, `accepted`, `in_progress`, `done`, `reassigned`.
- Kunjungan: `scheduled`, `completed`, `rescheduled`, `cancelled`.

Status akhir dan transisi yang dibolehkan harus disepakati berdasarkan SOP UPT. Jangan menerima status arbitrer dari request browser; validasi transisi di service/controller dan simpan riwayat dalam transaksi yang sama.

## 6. Aturan Integritas Penting

1. `request_no`, `email` (jika disediakan), nomor sertifikat, dan kunci dokumen memiliki indeks unik yang sesuai.
2. Foreign key memakai InnoDB. Gunakan `RESTRICT` untuk riwayat/dokumen penting agar penghapusan induk tidak menghapus bukti proses.
3. Untuk akun/profil/data master, gunakan status aktif/nonaktif atau soft delete. Jangan menghapus riwayat layanan yang sudah diproses.
4. Simpan koordinat sebagai `DECIMAL`, disertai sumber dan status verifikasi; jangan memakai koordinat sebagai pengganti alamat/wilayah administratif.
5. Catat waktu dalam UTC pada database, lalu tampilkan dalam zona `Asia/Jakarta`.
6. Berkas mengandung metadata minimal: nama asli, kunci/path acak, MIME yang terdeteksi server, ukuran, hash SHA-256, pengunggah, kategori, visibility, dan waktu unggah.
7. Jangan menulis kata sandi, token sesi, isi dokumen, nomor identitas sensitif, atau kredensial ke `audit_logs`.
8. Nomor urut tahunan tidak boleh dihasilkan dengan `MAX(id)+1`. Naikkan tabel `number_sequences` atomik dalam transaksi.

## 7. Penambahan Setelah MVP

Tabel berikut dapat ditambahkan setelah proses inti stabil:

- `service_catalog`, `service_requirements`, `service_fees` untuk konfigurasi layanan/persyaratan/tarif yang disetujui.
- `regions` (provinsi/kabupaten/kecamatan/desa) dan alamat terstruktur jika pemetaan serta master wilayah tersedia.
- `notification_outbox` untuk retry idempoten email/WhatsApp gateway.
- `complaints` untuk tiket aduan terpisah dari survei kepuasan.
- `content_pages`, `news`, `events` untuk CMS portal.
- `uttp_validity_rules` atau jadwal pengingat per jenis, setelah aturan tanggal/masa tera diverifikasi oleh UPT.
- Tabel integrasi eksternal jika API dan dasar pertukaran data telah disepakati.

## 8. Hal yang Harus Dikonfirmasi dengan UPT

- Apakah profil satu pemohon dapat memiliki beberapa penanggung jawab/login.
- Atribut wajib dan identitas pembeda UTTP untuk tiap jenis, termasuk nomor seri dan kondisi alat.
- Apakah berkas surat permohonan melekat pada permohonan atau wajib per item.
- Status, alasan batal/ditolak, proses revisi, serta pengesahan SKHP.
- Apakah revisi/penerbitan ulang SKHP mempertahankan versi lama dan bagaimana format nomornya.
- Kebutuhan tarif/tagihan, pembayaran, bukti pengambilan, serta retensi berkas.
- Dasar, cakupan, dan visibilitas penyimpanan koordinat pemohon/UTTP.
- Periode retensi dan aturan pemusnahan data sesuai kebijakan instansi.

Ini rancangan awal yang sengaja berfokus pada jejak layanan, kontrol akses, dan histori yang tidak berubah diam-diam. Kolom dan aturan final perlu ditinjau bersama pemilik proses sebelum database produksi dibuat.
