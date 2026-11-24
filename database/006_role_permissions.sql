-- Hak akses per peran. Jalankan setelah 005_master_data.sql.
USE metro_app;
CREATE TABLE IF NOT EXISTS permissions (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 permission_code VARCHAR(80) NOT NULL,
 permission_name VARCHAR(120) NOT NULL,
 permission_group VARCHAR(80) NOT NULL,
 description VARCHAR(255) NULL,
 sort_order INT NOT NULL DEFAULT 0,
 UNIQUE KEY uq_permission_code (permission_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS role_permissions (
 role_id BIGINT UNSIGNED NOT NULL,
 permission_id BIGINT UNSIGNED NOT NULL,
 granted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 granted_by BIGINT UNSIGNED NULL,
 PRIMARY KEY (role_id,permission_id),
 KEY ix_role_permission_permission (permission_id),
 CONSTRAINT fk_role_permission_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
 CONSTRAINT fk_role_permission_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
 CONSTRAINT fk_role_permission_granter FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO permissions (permission_code,permission_name,permission_group,description,sort_order) VALUES
('requests.view','Lihat antrean','Permohonan','Akses dasar ruang kerja dan permohonan yang ditugaskan',10),
('requests.view_all','Lihat semua permohonan','Permohonan','Tanpa izin ini, petugas hanya melihat unit yang ditugaskan',20),
('requests.order_edit','Ubah data order','Permohonan','Penera, CTT, dan peralatan standar',30),
('requests.verify','Verifikasi berkas','Permohonan','Terima, minta perbaikan, atau tolak berkas',40),
('requests.assign','Tugaskan petugas','Permohonan','Menetapkan penanggung jawab pengujian per unit',50),
('units.testing','Proses pengujian','Status unit','Mulai atau ulang pengujian',60),
('units.review','Kirim pemeriksaan hasil','Status unit','Pindah dari pengujian ke pemeriksaan hasil',70),
('units.complete','Selesaikan unit','Status unit','Menandai unit selesai',80),
('units.cancel','Batalkan unit','Status unit','Membatalkan unit sesuai alur',90),
('units.reject','Tolak hasil unit','Status unit','Menolak unit dari pemeriksaan hasil',100),
('documents.application.read','Baca surat permohonan','Dokumen','Untuk petugas terbatas, hanya permohonan yang ditugaskan',110),
('documents.cerapan.read','Baca cerapan','Dokumen','Untuk petugas terbatas, hanya unit yang ditugaskan',120),
('documents.cerapan.upload','Unggah cerapan','Dokumen','Untuk petugas terbatas, hanya unit yang ditugaskan',130),
('documents.skhp.read','Baca SKHP','Dokumen','Akses dokumen hasil pengujian',140),
('documents.skhp.upload','Unggah SKHP','Dokumen','Terbitkan dokumen hasil per unit',150),
('reports.view','Lihat laporan','Analisis','Ringkasan layanan per periode',160),
('reports.export','Ekspor laporan','Analisis','Unduh CSV layanan',170),
('activity.view','Lihat log aktivitas','Analisis','Catatan tindakan pengguna dan unit selesai',180),
('applicants.manage','Kelola pemohon','Administrasi','Lihat dan ubah akun pemohon',190),
('applicants.phone_code','Buat kode verifikasi','Administrasi','Membuat kode sekali pakai nomor telepon',200),
('staff.manage','Kelola akun petugas','Administrasi','Tambah, ubah, aktifkan, dan hapus akun petugas',210),
('masters.manage','Kelola master data','Administrasi','Tambah, ubah, nonaktifkan, dan hapus referensi',220);
-- Izin awal mengikuti aturan aplikasi sebelum matriks diperkenalkan.
INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p ON
 (r.role_code='administrator') OR
 (r.role_code IN ('verifier','coordinator','technician','issuer') AND p.permission_code='requests.view') OR
 (r.role_code IN ('verifier','coordinator','issuer') AND p.permission_code='requests.view_all') OR
 (r.role_code IN ('verifier','coordinator') AND p.permission_code='requests.order_edit') OR
 (r.role_code='verifier' AND p.permission_code IN ('requests.verify','units.reject')) OR
 (r.role_code='coordinator' AND p.permission_code IN ('requests.assign','units.cancel','reports.view','reports.export','activity.view')) OR
 (r.role_code='technician' AND p.permission_code IN ('units.testing','units.review','documents.cerapan.read','documents.cerapan.upload')) OR
 (r.role_code='issuer' AND p.permission_code IN ('units.review','units.complete','units.reject','documents.cerapan.read','documents.skhp.read','documents.skhp.upload')) OR
 (r.role_code IN ('verifier','coordinator','issuer','technician') AND p.permission_code='documents.application.read') OR
 (r.role_code IN ('verifier','coordinator') AND p.permission_code='documents.skhp.read')
WHERE NOT EXISTS (SELECT 1 FROM role_permissions existing WHERE existing.role_id=r.id);
