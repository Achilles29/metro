-- Hak akses rinci untuk petugas, pemohon, pelaku usaha, dan teknisi eksternal.
-- Aman dijalankan ulang; izin lama disalin sebelum izin gabungan dihapus.
USE metro_app;

INSERT INTO roles (role_code,role_name,description,is_active,sort_order)
VALUES ('business_actor','Pelaku usaha','Akun pemohon dengan profil instansi/perusahaan',1,55)
ON DUPLICATE KEY UPDATE role_name=VALUES(role_name),description=VALUES(description),is_active=1;

INSERT IGNORE INTO permissions (permission_code,permission_name,permission_group,description,sort_order) VALUES
('staff.view','Lihat akun petugas','Akun petugas','Lihat daftar dan rincian akun petugas',230),
('staff.create','Tambah akun petugas','Akun petugas','Buat petugas dengan peran nonadministrator',231),
('staff.update','Ubah akun petugas','Akun petugas','Ubah identitas, peran, dan kata sandi petugas nonadministrator',232),
('staff.status','Aktifkan/nonaktifkan petugas','Akun petugas','Ubah status akun petugas nonadministrator',233),
('staff.delete','Hapus akun petugas','Akun petugas','Hapus akun petugas tanpa riwayat',234),
('applicants.view','Lihat pemohon dan pelaku usaha','Akun pemohon','Lihat daftar, profil, dan riwayat pemohon',240),
('applicants.create','Tambah pemohon/pelaku usaha','Akun pemohon','Buat akun pemohon atau perusahaan',241),
('applicants.update','Ubah pemohon/pelaku usaha','Akun pemohon','Ubah profil dan kredensial pemohon',242),
('applicants.status','Aktifkan/nonaktifkan pemohon','Akun pemohon','Ubah status akun pemohon',243),
('applicants.delete','Hapus pemohon/pelaku usaha','Akun pemohon','Hapus akun pemohon tanpa riwayat',244),
('technicians.view','Lihat teknisi dan pelimpahan','Teknisi eksternal','Lihat akun teknisi dan hubungan perusahaan',250),
('technicians.verify','Verifikasi teknisi','Teknisi eksternal','Setujui atau tolak rekomendasi teknisi',251),
('technicians.delegation_review','Verifikasi pelimpahan','Teknisi eksternal','Setujui, tolak, atau cabut hubungan dengan perusahaan',252),
('masters.view','Lihat master data','Master data','Lihat daftar referensi dan unsur kaji ulang',260),
('masters.create','Tambah master data','Master data','Tambah referensi baru',261),
('masters.update','Ubah master data','Master data','Ubah referensi dan unsur kaji ulang',262),
('masters.status','Aktifkan/nonaktifkan master','Master data','Ubah status referensi',263),
('masters.delete','Hapus master data','Master data','Hapus referensi yang belum digunakan',264),
('self.profile.view','Lihat profil sendiri','Akses mandiri','Lihat profil pemohon atau pelaku usaha sendiri',300),
('self.profile.update','Ubah profil sendiri','Akses mandiri','Ubah profil pemohon atau pelaku usaha sendiri',301),
('self.uttp.view','Lihat UTTP sendiri','Akses mandiri','Lihat daftar dan riwayat UTTP milik sendiri',310),
('self.uttp.create','Tambah UTTP sendiri','Akses mandiri','Tambah UTTP milik sendiri',311),
('self.uttp.update','Ubah UTTP sendiri','Akses mandiri','Ubah UTTP milik sendiri',312),
('self.uttp.status','Aktifkan/nonaktifkan UTTP','Akses mandiri','Ubah status UTTP milik sendiri',313),
('self.uttp.delete','Hapus UTTP sendiri','Akses mandiri','Hapus UTTP sendiri yang belum dipakai',314),
('self.requests.view','Lihat permohonan sendiri','Akses mandiri','Lihat permohonan yang dimiliki atau diajukan untuk perusahaan terhubung',320),
('self.requests.create','Ajukan permohonan','Akses mandiri','Buat permohonan baru untuk diri sendiri atau perusahaan terhubung',321),
('self.requests.revise','Perbaiki permohonan','Akses mandiri','Unggah perbaikan untuk permohonan yang dapat diakses',322),
('self.requests.feedback','Beri umpan balik','Akses mandiri','Isi penilaian untuk permohonan sendiri',323),
('self.delegation.view','Lihat pelimpahan','Akses mandiri','Lihat hubungan teknisi dan perusahaan sendiri',330),
('self.delegation.invite','Ajukan pelimpahan','Akses mandiri','Undang teknisi atau perusahaan untuk bekerja sama',331),
('self.delegation.respond','Tanggapi pelimpahan','Akses mandiri','Terima, tolak, atau cabut pelimpahan sendiri',332),
('self.technician_profile.view','Lihat profil teknisi sendiri','Akses mandiri','Lihat profil dan rekomendasi sendiri',340),
('self.technician_profile.update','Ubah profil teknisi sendiri','Akses mandiri','Ubah data rekomendasi sendiri',341);

INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT rp.role_id,new_permission.id FROM role_permissions rp
JOIN permissions old_permission ON old_permission.id=rp.permission_id
JOIN permissions new_permission ON
 (old_permission.permission_code='applicants.manage' AND new_permission.permission_code IN ('applicants.view','applicants.create','applicants.update','applicants.status','applicants.delete')) OR
 (old_permission.permission_code='technicians.manage' AND new_permission.permission_code IN ('technicians.view','technicians.verify','technicians.delegation_review')) OR
 (old_permission.permission_code='masters.manage' AND new_permission.permission_code IN ('masters.view','masters.create','masters.update','masters.status','masters.delete')) OR
 (old_permission.permission_code='staff.manage' AND new_permission.permission_code IN ('staff.view','staff.create','staff.update','staff.status','staff.delete'));

INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p ON
 (r.role_code IN ('applicant','business_actor') AND p.permission_code IN
  ('self.profile.view','self.profile.update','self.uttp.view','self.uttp.create','self.uttp.update','self.uttp.status','self.uttp.delete','self.requests.view','self.requests.create','self.requests.revise','self.requests.feedback')) OR
 (r.role_code='business_actor' AND p.permission_code IN ('self.delegation.view','self.delegation.invite','self.delegation.respond')) OR
 (r.role_code='external_technician' AND p.permission_code IN
  ('self.technician_profile.view','self.technician_profile.update','self.requests.view','self.requests.create','self.requests.revise','self.delegation.view','self.delegation.invite','self.delegation.respond')) OR
 (r.role_code='administrator' AND p.permission_code IN
  ('staff.view','staff.create','staff.update','staff.status','staff.delete','applicants.view','applicants.create','applicants.update','applicants.status','applicants.delete','technicians.view','technicians.verify','technicians.delegation_review','masters.view','masters.create','masters.update','masters.status','masters.delete'));

DELETE FROM permissions WHERE permission_code IN ('staff.manage','applicants.manage','technicians.manage','masters.manage');
