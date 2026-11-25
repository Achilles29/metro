-- Aksi CRUD akun teknisi eksternal di matriks hak akses.
USE metro_app;
INSERT IGNORE INTO permissions (permission_code,permission_name,permission_group,description,sort_order) VALUES
('technicians.create','Tambah akun teknisi','Teknisi eksternal','Buat akun teknisi eksternal baru',253),
('technicians.update','Ubah akun teknisi','Teknisi eksternal','Ubah identitas dan rekomendasi teknisi',254),
('technicians.status','Aktifkan/nonaktifkan teknisi','Teknisi eksternal','Ubah status akun teknisi eksternal',255),
('technicians.delete','Hapus akun teknisi','Teknisi eksternal','Hapus akun teknisi tanpa riwayat',256);
INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p ON p.permission_code IN
('technicians.create','technicians.update','technicians.status','technicians.delete')
WHERE r.role_code='administrator';
