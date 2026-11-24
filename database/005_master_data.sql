-- Master data Metro. Wilayah dari /www/wwwroot/umkm/database/seeds/wilayah_rembang_2025.json
-- Sumber seed wilayah: Kepmendagri 300.2.2-2430/2025 via emsifa API v2; jumlah 14 kecamatan, 294 desa/kelurahan.
USE metro_app;
CREATE TABLE master_districts (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 district_code VARCHAR(24) NOT NULL, district_name VARCHAR(120) NOT NULL,
 sort_order INT NOT NULL DEFAULT 0, is_active TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL,
 UNIQUE KEY uq_master_district_code (district_code), KEY ix_master_district_active (is_active,sort_order,district_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE master_villages (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 district_id BIGINT UNSIGNED NOT NULL, village_code VARCHAR(32) NOT NULL,
 village_name VARCHAR(120) NOT NULL, sort_order INT NOT NULL DEFAULT 0,
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL,
 UNIQUE KEY uq_master_village_code (village_code), KEY ix_master_village_search (district_id,is_active,village_name),
 CONSTRAINT fk_master_village_district FOREIGN KEY (district_id) REFERENCES master_districts(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE master_locations (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 location_code VARCHAR(48) NOT NULL, location_name VARCHAR(160) NOT NULL,
 address TEXT NOT NULL, district_id BIGINT UNSIGNED NULL, village_id BIGINT UNSIGNED NULL,
 sort_order INT NOT NULL DEFAULT 0, is_active TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL,
 UNIQUE KEY uq_master_location_code (location_code),
 CONSTRAINT fk_master_location_district FOREIGN KEY (district_id) REFERENCES master_districts(id) ON DELETE SET NULL,
 CONSTRAINT fk_master_location_village FOREIGN KEY (village_id) REFERENCES master_villages(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE master_groups (
 group_code VARCHAR(40) NOT NULL PRIMARY KEY,
 group_name VARCHAR(120) NOT NULL, description VARCHAR(255) NULL,
 sort_order INT NOT NULL DEFAULT 0, allow_create TINYINT(1) NOT NULL DEFAULT 1,
 has_location TINYINT(1) NOT NULL DEFAULT 0, has_tone TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE master_options (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 group_code VARCHAR(40) NOT NULL, option_code VARCHAR(48) NOT NULL,
 option_label VARCHAR(190) NOT NULL, description VARCHAR(255) NULL,
 sort_order INT NOT NULL DEFAULT 0, is_active TINYINT(1) NOT NULL DEFAULT 1,
 tone VARCHAR(16) NULL, service_location_id BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL,
 UNIQUE KEY uq_master_option_code (group_code,option_code), KEY ix_master_option_active (group_code,is_active,sort_order),
 CONSTRAINT fk_master_option_group FOREIGN KEY (group_code) REFERENCES master_groups(group_code) ON DELETE RESTRICT,
 CONSTRAINT fk_master_option_location FOREIGN KEY (service_location_id) REFERENCES master_locations(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE uttp_types ADD COLUMN requires_capacity TINYINT(1) NOT NULL DEFAULT 0 AFTER description;
ALTER TABLE uttp_types ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER requires_capacity;
ALTER TABLE roles ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER description;
ALTER TABLE applicant_profiles ADD COLUMN district_id BIGINT UNSIGNED NULL AFTER district;
ALTER TABLE applicant_profiles ADD COLUMN village_id BIGINT UNSIGNED NULL AFTER village;
ALTER TABLE applicant_profiles ADD KEY ix_applicant_profile_village (district_id,village_id);
ALTER TABLE applicant_profiles ADD CONSTRAINT fk_applicant_profile_district FOREIGN KEY (district_id) REFERENCES master_districts(id) ON DELETE SET NULL;
ALTER TABLE applicant_profiles ADD CONSTRAINT fk_applicant_profile_village FOREIGN KEY (village_id) REFERENCES master_villages(id) ON DELETE SET NULL;
ALTER TABLE service_requests ADD COLUMN district_id BIGINT UNSIGNED NULL;
ALTER TABLE service_requests ADD CONSTRAINT fk_service_request_district_id FOREIGN KEY (district_id) REFERENCES master_districts(id) ON DELETE SET NULL;
ALTER TABLE service_requests ADD COLUMN village_id BIGINT UNSIGNED NULL;
ALTER TABLE service_requests ADD CONSTRAINT fk_service_request_village_id FOREIGN KEY (village_id) REFERENCES master_villages(id) ON DELETE SET NULL;
ALTER TABLE service_requests ADD COLUMN service_location_id BIGINT UNSIGNED NULL;
ALTER TABLE service_requests ADD CONSTRAINT fk_service_request_service_location_id FOREIGN KEY (service_location_id) REFERENCES master_locations(id) ON DELETE SET NULL;
ALTER TABLE service_requests ADD COLUMN penera_option_id BIGINT UNSIGNED NULL;
ALTER TABLE service_requests ADD CONSTRAINT fk_service_request_penera_option_id FOREIGN KEY (penera_option_id) REFERENCES master_options(id) ON DELETE SET NULL;
ALTER TABLE service_requests ADD COLUMN ctt_option_id BIGINT UNSIGNED NULL;
ALTER TABLE service_requests ADD CONSTRAINT fk_service_request_ctt_option_id FOREIGN KEY (ctt_option_id) REFERENCES master_options(id) ON DELETE SET NULL;
ALTER TABLE service_requests ADD COLUMN standard_equipment_option_id BIGINT UNSIGNED NULL;
ALTER TABLE service_requests ADD CONSTRAINT fk_service_request_standard_equipment_option_id FOREIGN KEY (standard_equipment_option_id) REFERENCES master_options(id) ON DELETE SET NULL;
INSERT IGNORE INTO master_groups (group_code,group_name,description,sort_order,allow_create,has_location,has_tone) VALUES
('penera','Penera','Nama petugas penera untuk satu order',10,1,0,0),
('ctt','CTT','Kode CTT yang dipilih petugas',20,1,0,0),
('standard_equipment','Alat standar','Peralatan standar yang digunakan',30,1,0,0),
('service_type','Jenis layanan','Pilihan tera dan tera ulang',40,1,0,0),
('service_mode','Tempat layanan','Pilihan lokasi layanan dan alamat kantor',50,1,1,0),
('profile_kind','Jenis pemohon','Jenis profil pemohon',60,0,0,0),
('status','Label status','Label dan warna status layanan',70,0,0,1),
('document_type','Jenis dokumen','Label dokumen layanan',80,0,0,0);
INSERT IGNORE INTO master_districts (district_code,district_name,sort_order) VALUES ('33.17.01','Sumber',1);
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.01.2001','Ronggo Mulyo',1 FROM master_districts WHERE district_code='33.17.01';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.01.2002','Logede',2 FROM master_districts WHERE district_code='33.17.01';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.01.2003','Pelemsari',3 FROM master_districts WHERE district_code='33.17.01';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.01.2004','Logung',4 FROM master_districts WHERE district_code='33.17.01';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.01.2005','Krikilan',5 FROM master_districts WHERE district_code='33.17.01';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.01.2006','Kedungtulup',6 FROM master_districts WHERE district_code='33.17.01';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.01.2007','Polbayem',7 FROM master_districts WHERE district_code='33.17.01';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.01.2008','Jatihadi',8 FROM master_districts WHERE district_code='33.17.01';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.01.2009','Sumber',9 FROM master_districts WHERE district_code='33.17.01';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.01.2010','Jadi',10 FROM master_districts WHERE district_code='33.17.01';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.01.2011','Grawan',11 FROM master_districts WHERE district_code='33.17.01';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.01.2012','Randuagung',12 FROM master_districts WHERE district_code='33.17.01';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.01.2013','Sukorejo',13 FROM master_districts WHERE district_code='33.17.01';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.01.2014','Tlogotunggal',14 FROM master_districts WHERE district_code='33.17.01';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.01.2015','Bogorejo',15 FROM master_districts WHERE district_code='33.17.01';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.01.2016','Megulung',16 FROM master_districts WHERE district_code='33.17.01';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.01.2017','Kedungasem',17 FROM master_districts WHERE district_code='33.17.01';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.01.2018','Sekarsari',18 FROM master_districts WHERE district_code='33.17.01';
INSERT IGNORE INTO master_districts (district_code,district_name,sort_order) VALUES ('33.17.02','Bulu',2);
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.02.2001','Mlatirejo',1 FROM master_districts WHERE district_code='33.17.02';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.02.2002','Sendangmulyo',2 FROM master_districts WHERE district_code='33.17.02';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.02.2003','Pondokrejo',3 FROM master_districts WHERE district_code='33.17.02';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.02.2004','Warugunung',4 FROM master_districts WHERE district_code='33.17.02';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.02.2005','Pinggan',5 FROM master_districts WHERE district_code='33.17.02';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.02.2006','Cabean Kidul',6 FROM master_districts WHERE district_code='33.17.02';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.02.2007','Lambangan Kulon',7 FROM master_districts WHERE district_code='33.17.02';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.02.2008','Lambangan Wetan',8 FROM master_districts WHERE district_code='33.17.02';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.02.2009','Sumbermulyo',9 FROM master_districts WHERE district_code='33.17.02';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.02.2010','Karangasem',10 FROM master_districts WHERE district_code='33.17.02';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.02.2011','Pasedan',11 FROM master_districts WHERE district_code='33.17.02';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.02.2012','Ngulaan',12 FROM master_districts WHERE district_code='33.17.02';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.02.2013','Jukung',13 FROM master_districts WHERE district_code='33.17.02';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.02.2014','Bulu',14 FROM master_districts WHERE district_code='33.17.02';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.02.2015','Mantingan',15 FROM master_districts WHERE district_code='33.17.02';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.02.2016','Kadiwono',16 FROM master_districts WHERE district_code='33.17.02';
INSERT IGNORE INTO master_districts (district_code,district_name,sort_order) VALUES ('33.17.03','Gunem',3);
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.03.2001','Kajar',1 FROM master_districts WHERE district_code='33.17.03';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.03.2002','Timbrangan',2 FROM master_districts WHERE district_code='33.17.03';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.03.2003','Tegaldowo',3 FROM master_districts WHERE district_code='33.17.03';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.03.2004','Pasucen',4 FROM master_districts WHERE district_code='33.17.03';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.03.2005','Suntri',5 FROM master_districts WHERE district_code='33.17.03';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.03.2006','Dowan',6 FROM master_districts WHERE district_code='33.17.03';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.03.2007','Trembes',7 FROM master_districts WHERE district_code='33.17.03';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.03.2008','Gunem',8 FROM master_districts WHERE district_code='33.17.03';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.03.2009','Kulutan',9 FROM master_districts WHERE district_code='33.17.03';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.03.2010','Sidomulyo',10 FROM master_districts WHERE district_code='33.17.03';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.03.2011','Telgawah',11 FROM master_districts WHERE district_code='33.17.03';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.03.2012','Sendangmulyo',12 FROM master_districts WHERE district_code='33.17.03';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.03.2013','Panohan',13 FROM master_districts WHERE district_code='33.17.03';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.03.2014','Demaan',14 FROM master_districts WHERE district_code='33.17.03';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.03.2015','Banyuurip',15 FROM master_districts WHERE district_code='33.17.03';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.03.2016','Sambongpayak',16 FROM master_districts WHERE district_code='33.17.03';
INSERT IGNORE INTO master_districts (district_code,district_name,sort_order) VALUES ('33.17.04','Sale',4);
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.04.2001','Bancang',1 FROM master_districts WHERE district_code='33.17.04';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.04.2002','Mrayun',2 FROM master_districts WHERE district_code='33.17.04';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.04.2003','Ngajaran',3 FROM master_districts WHERE district_code='33.17.04';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.04.2004','Tahunan',4 FROM master_districts WHERE district_code='33.17.04';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.04.2005','Gading',5 FROM master_districts WHERE district_code='33.17.04';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.04.2006','Jinanten',6 FROM master_districts WHERE district_code='33.17.04';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.04.2007','Joho',7 FROM master_districts WHERE district_code='33.17.04';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.04.2008','Sale',8 FROM master_districts WHERE district_code='33.17.04';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.04.2009','Wonokerto',9 FROM master_districts WHERE district_code='33.17.04';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.04.2010','Sumbermulyo',10 FROM master_districts WHERE district_code='33.17.04';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.04.2011','Tengger',11 FROM master_districts WHERE district_code='33.17.04';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.04.2012','Bitingan',12 FROM master_districts WHERE district_code='33.17.04';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.04.2013','Pakis',13 FROM master_districts WHERE district_code='33.17.04';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.04.2014','Rendeng',14 FROM master_districts WHERE district_code='33.17.04';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.04.2015','Ukir',15 FROM master_districts WHERE district_code='33.17.04';
INSERT IGNORE INTO master_districts (district_code,district_name,sort_order) VALUES ('33.17.05','Sarang',5);
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.05.2001','Lodankulon',1 FROM master_districts WHERE district_code='33.17.05';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.05.2002','Lodanwetan',2 FROM master_districts WHERE district_code='33.17.05';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.05.2003','Bonjor',3 FROM master_districts WHERE district_code='33.17.05';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.05.2004','Tawangrejo',4 FROM master_districts WHERE district_code='33.17.05';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.05.2005','Sampung',5 FROM master_districts WHERE district_code='33.17.05';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.05.2006','Baturno',6 FROM master_districts WHERE district_code='33.17.05';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.05.2007','Babaktulung',7 FROM master_districts WHERE district_code='33.17.05';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.05.2008','Nglojo',8 FROM master_districts WHERE district_code='33.17.05';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.05.2009','Jambangan',9 FROM master_districts WHERE district_code='33.17.05';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.05.2010','Pelang',10 FROM master_districts WHERE district_code='33.17.05';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.05.2011','Gilis',11 FROM master_districts WHERE district_code='33.17.05';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.05.2012','Gunungmulyo',12 FROM master_districts WHERE district_code='33.17.05';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.05.2013','Gonggang',13 FROM master_districts WHERE district_code='33.17.05';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.05.2014','Sumbermulyo',14 FROM master_districts WHERE district_code='33.17.05';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.05.2015','Kalipang',15 FROM master_districts WHERE district_code='33.17.05';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.05.2016','Dadapmulyo',16 FROM master_districts WHERE district_code='33.17.05';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.05.2017','Sendangmulyo',17 FROM master_districts WHERE district_code='33.17.05';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.05.2018','Banowan',18 FROM master_districts WHERE district_code='33.17.05';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.05.2019','Temperak',19 FROM master_districts WHERE district_code='33.17.05';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.05.2020','Karangmangu',20 FROM master_districts WHERE district_code='33.17.05';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.05.2021','Bajingjowo',21 FROM master_districts WHERE district_code='33.17.05';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.05.2022','Bajingmeduro',22 FROM master_districts WHERE district_code='33.17.05';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.05.2023','Sarangmeduro',23 FROM master_districts WHERE district_code='33.17.05';
INSERT IGNORE INTO master_districts (district_code,district_name,sort_order) VALUES ('33.17.06','Sedan',6);
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.06.2001','Ngulahan',1 FROM master_districts WHERE district_code='33.17.06';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.06.2002','Pacing',2 FROM master_districts WHERE district_code='33.17.06';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.06.2003','Karas',3 FROM master_districts WHERE district_code='33.17.06';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.06.2004','Mojosari',4 FROM master_districts WHERE district_code='33.17.06';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.06.2005','Gesikan',5 FROM master_districts WHERE district_code='33.17.06';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.06.2006','Sambiroto',6 FROM master_districts WHERE district_code='33.17.06';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.06.2007','Sedan',7 FROM master_districts WHERE district_code='33.17.06';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.06.2008','Karangasem',8 FROM master_districts WHERE district_code='33.17.06';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.06.2009','Sidorejo',9 FROM master_districts WHERE district_code='33.17.06';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.06.2010','Sidomulyo',10 FROM master_districts WHERE district_code='33.17.06';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.06.2011','Kedungringin',11 FROM master_districts WHERE district_code='33.17.06';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.06.2012','Gandrirojo',12 FROM master_districts WHERE district_code='33.17.06';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.06.2013','Candimulyo',13 FROM master_districts WHERE district_code='33.17.06';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.06.2014','Lemahputih',14 FROM master_districts WHERE district_code='33.17.06';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.06.2015','Kumbo',15 FROM master_districts WHERE district_code='33.17.06';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.06.2016','Dadapan',16 FROM master_districts WHERE district_code='33.17.06';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.06.2017','Sambong',17 FROM master_districts WHERE district_code='33.17.06';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.06.2018','Bogorejo',18 FROM master_districts WHERE district_code='33.17.06';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.06.2019','Kenongo',19 FROM master_districts WHERE district_code='33.17.06';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.06.2020','Jambeyan',20 FROM master_districts WHERE district_code='33.17.06';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.06.2021','Menoro',21 FROM master_districts WHERE district_code='33.17.06';
INSERT IGNORE INTO master_districts (district_code,district_name,sort_order) VALUES ('33.17.07','Pamotan',7);
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.07.2001','Megal',1 FROM master_districts WHERE district_code='33.17.07';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.07.2002','Ngemplakrejo',2 FROM master_districts WHERE district_code='33.17.07';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.07.2003','Pragen',3 FROM master_districts WHERE district_code='33.17.07';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.07.2004','Samaran',4 FROM master_districts WHERE district_code='33.17.07';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.07.2005','Gambiran',5 FROM master_districts WHERE district_code='33.17.07';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.07.2006','Bamban',6 FROM master_districts WHERE district_code='33.17.07';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.07.2007','Bangunrejo',7 FROM master_districts WHERE district_code='33.17.07';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.07.2008','Pamotan',8 FROM master_districts WHERE district_code='33.17.07';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.07.2009','Sidorejo',9 FROM master_districts WHERE district_code='33.17.07';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.07.2010','Tempaling',10 FROM master_districts WHERE district_code='33.17.07';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.07.2011','Joho',11 FROM master_districts WHERE district_code='33.17.07';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.07.2012','Mlagen',12 FROM master_districts WHERE district_code='33.17.07';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.07.2013','Kepohagung',13 FROM master_districts WHERE district_code='33.17.07';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.07.2014','Mlawat',14 FROM master_districts WHERE district_code='33.17.07';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.07.2015','Segoromulyo',15 FROM master_districts WHERE district_code='33.17.07';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.07.2016','Ketangi',16 FROM master_districts WHERE district_code='33.17.07';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.07.2017','Sendangagung',17 FROM master_districts WHERE district_code='33.17.07';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.07.2018','Gegersimo',18 FROM master_districts WHERE district_code='33.17.07';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.07.2019','Sumberejo',19 FROM master_districts WHERE district_code='33.17.07';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.07.2020','Japerejo',20 FROM master_districts WHERE district_code='33.17.07';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.07.2021','Tulung',21 FROM master_districts WHERE district_code='33.17.07';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.07.2022','Ringin',22 FROM master_districts WHERE district_code='33.17.07';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.07.2023','Sumbangrejo',23 FROM master_districts WHERE district_code='33.17.07';
INSERT IGNORE INTO master_districts (district_code,district_name,sort_order) VALUES ('33.17.08','Sulang',8);
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.08.2001','Tanjung',1 FROM master_districts WHERE district_code='33.17.08';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.08.2002','Kemadu',2 FROM master_districts WHERE district_code='33.17.08';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.08.2003','Sulang',3 FROM master_districts WHERE district_code='33.17.08';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.08.2004','Pomahan',4 FROM master_districts WHERE district_code='33.17.08';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.08.2005','Rukem',5 FROM master_districts WHERE district_code='33.17.08';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.08.2006','Korowelang',6 FROM master_districts WHERE district_code='33.17.08';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.08.2007','Karangharjo',7 FROM master_districts WHERE district_code='33.17.08';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.08.2008','Jatimudo',8 FROM master_districts WHERE district_code='33.17.08';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.08.2009','Kunir',9 FROM master_districts WHERE district_code='33.17.08';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.08.2010','Glebeg',10 FROM master_districts WHERE district_code='33.17.08';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.08.2011','Bogorame',11 FROM master_districts WHERE district_code='33.17.08';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.08.2012','Kaliombo',12 FROM master_districts WHERE district_code='33.17.08';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.08.2013','Sudo',13 FROM master_districts WHERE district_code='33.17.08';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.08.2014','Karangsari',14 FROM master_districts WHERE district_code='33.17.08';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.08.2015','Pragu',15 FROM master_districts WHERE district_code='33.17.08';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.08.2016','Kebonagung',16 FROM master_districts WHERE district_code='33.17.08';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.08.2017','Seren',17 FROM master_districts WHERE district_code='33.17.08';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.08.2018','Pranti',18 FROM master_districts WHERE district_code='33.17.08';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.08.2019','Pedak',19 FROM master_districts WHERE district_code='33.17.08';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.08.2020','Landoh',20 FROM master_districts WHERE district_code='33.17.08';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.08.2021','Kerep',21 FROM master_districts WHERE district_code='33.17.08';
INSERT IGNORE INTO master_districts (district_code,district_name,sort_order) VALUES ('33.17.09','Kaliori',9);
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.09.2001','Meteseh',1 FROM master_districts WHERE district_code='33.17.09';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.09.2002','Maguan',2 FROM master_districts WHERE district_code='33.17.09';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.09.2003','Sidomulyo',3 FROM master_districts WHERE district_code='33.17.09';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.09.2004','Wiroto',4 FROM master_districts WHERE district_code='33.17.09';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.09.2005','Banggi',5 FROM master_districts WHERE district_code='33.17.09';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.09.2006','Kuangsan',6 FROM master_districts WHERE district_code='33.17.09';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.09.2007','Gunungsari',7 FROM master_districts WHERE district_code='33.17.09';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.09.2008','Sendangagung',8 FROM master_districts WHERE district_code='33.17.09';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.09.2009','Karangsekar',9 FROM master_districts WHERE district_code='33.17.09';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.09.2010','Babadan',10 FROM master_districts WHERE district_code='33.17.09';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.09.2011','Pengkol',11 FROM master_districts WHERE district_code='33.17.09';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.09.2012','Sambiyan',12 FROM master_districts WHERE district_code='33.17.09';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.09.2013','Mojorembun',13 FROM master_districts WHERE district_code='33.17.09';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.09.2014','Tunggulsari',14 FROM master_districts WHERE district_code='33.17.09';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.09.2015','Tambakagung',15 FROM master_districts WHERE district_code='33.17.09';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.09.2016','Mojowarno',16 FROM master_districts WHERE district_code='33.17.09';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.09.2017','Dresi Kulon',17 FROM master_districts WHERE district_code='33.17.09';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.09.2018','Dresi Wetan',18 FROM master_districts WHERE district_code='33.17.09';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.09.2019','Tasikharjo',19 FROM master_districts WHERE district_code='33.17.09';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.09.2020','Purworejo',20 FROM master_districts WHERE district_code='33.17.09';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.09.2021','Bogoharjo',21 FROM master_districts WHERE district_code='33.17.09';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.09.2022','Banyudono',22 FROM master_districts WHERE district_code='33.17.09';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.09.2023','Pantiharjo',23 FROM master_districts WHERE district_code='33.17.09';
INSERT IGNORE INTO master_districts (district_code,district_name,sort_order) VALUES ('33.17.10','Rembang',10);
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.10.1019','Magersari',1 FROM master_districts WHERE district_code='33.17.10';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.10.1020','Gegunung Kulon',2 FROM master_districts WHERE district_code='33.17.10';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.10.1022','Pacar',3 FROM master_districts WHERE district_code='33.17.10';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.10.1023','Tanjungsari',4 FROM master_districts WHERE district_code='33.17.10';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.10.1027','Leteh',5 FROM master_districts WHERE district_code='33.17.10';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.10.1028','Sidowayah',6 FROM master_districts WHERE district_code='33.17.10';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.10.1029','Kutoharjo',7 FROM master_districts WHERE district_code='33.17.10';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.10.2001','Kedungrejo',8 FROM master_districts WHERE district_code='33.17.10';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.10.2002','Turusgede',9 FROM master_districts WHERE district_code='33.17.10';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.10.2003','Kumendung',10 FROM master_districts WHERE district_code='33.17.10';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.10.2004','Sridadi',11 FROM master_districts WHERE district_code='33.17.10';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.10.2005','Pandean',12 FROM master_districts WHERE district_code='33.17.10';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.10.2006','Tlogomojo',13 FROM master_districts WHERE district_code='33.17.10';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.10.2007','Kasreman',14 FROM master_districts WHERE district_code='33.17.10';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.10.2008','Punjulharjo',15 FROM master_districts WHERE district_code='33.17.10';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.10.2009','Tritunggal',16 FROM master_districts WHERE district_code='33.17.10';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.10.2010','Pasar Banggi',17 FROM master_districts WHERE district_code='33.17.10';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.10.2011','Gedangan',18 FROM master_districts WHERE district_code='33.17.10';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.10.2012','Weton',19 FROM master_districts WHERE district_code='33.17.10';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.10.2013','Ngotet',20 FROM master_districts WHERE district_code='33.17.10';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.10.2014','Mondoteko',21 FROM master_districts WHERE district_code='33.17.10';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.10.2015','Ngadem',22 FROM master_districts WHERE district_code='33.17.10';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.10.2016','Ketanggi',23 FROM master_districts WHERE district_code='33.17.10';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.10.2017','Pulo',24 FROM master_districts WHERE district_code='33.17.10';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.10.2018','Waru',25 FROM master_districts WHERE district_code='33.17.10';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.10.2021','Gegunung Wetan',26 FROM master_districts WHERE district_code='33.17.10';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.10.2024','Sumberejo',27 FROM master_districts WHERE district_code='33.17.10';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.10.2025','Tasikagung',28 FROM master_districts WHERE district_code='33.17.10';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.10.2026','Sawahan',29 FROM master_districts WHERE district_code='33.17.10';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.10.2030','Padaran',30 FROM master_districts WHERE district_code='33.17.10';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.10.2031','Sukoharjo',31 FROM master_districts WHERE district_code='33.17.10';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.10.2032','Kabongan Lor',32 FROM master_districts WHERE district_code='33.17.10';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.10.2033','Kabongan Kidul',33 FROM master_districts WHERE district_code='33.17.10';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.10.2034','Tireman',34 FROM master_districts WHERE district_code='33.17.10';
INSERT IGNORE INTO master_districts (district_code,district_name,sort_order) VALUES ('33.17.11','Pancur',11);
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.11.2001','Japeledok',1 FROM master_districts WHERE district_code='33.17.11';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.11.2002','Jeruk',2 FROM master_districts WHERE district_code='33.17.11';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.11.2003','Doropayung',3 FROM master_districts WHERE district_code='33.17.11';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.11.2004','Karaskepoh',4 FROM master_districts WHERE district_code='33.17.11';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.11.2005','Tuyuhan',5 FROM master_districts WHERE district_code='33.17.11';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.11.2006','Pandan',6 FROM master_districts WHERE district_code='33.17.11';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.11.2007','Gemblengmulyo',7 FROM master_districts WHERE district_code='33.17.11';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.11.2008','Sumberagung',8 FROM master_districts WHERE district_code='33.17.11';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.11.2009','Kalitengah',9 FROM master_districts WHERE district_code='33.17.11';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.11.2010','Sidowayah',10 FROM master_districts WHERE district_code='33.17.11';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.11.2011','Kedung',11 FROM master_districts WHERE district_code='33.17.11';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.11.2012','Punggurharjo',12 FROM master_districts WHERE district_code='33.17.11';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.11.2013','Langkir',13 FROM master_districts WHERE district_code='33.17.11';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.11.2014','Pancur',14 FROM master_districts WHERE district_code='33.17.11';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.11.2015','Pohlandak',15 FROM master_districts WHERE district_code='33.17.11';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.11.2016','Warugunung',16 FROM master_districts WHERE district_code='33.17.11';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.11.2017','Criwik',17 FROM master_districts WHERE district_code='33.17.11';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.11.2018','Wuwur',18 FROM master_districts WHERE district_code='33.17.11';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.11.2019','Ngulangan',19 FROM master_districts WHERE district_code='33.17.11';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.11.2020','Banyuurip',20 FROM master_districts WHERE district_code='33.17.11';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.11.2021','Johogunung',21 FROM master_districts WHERE district_code='33.17.11';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.11.2022','Trenggulunan',22 FROM master_districts WHERE district_code='33.17.11';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.11.2023','Ngroto',23 FROM master_districts WHERE district_code='33.17.11';
INSERT IGNORE INTO master_districts (district_code,district_name,sort_order) VALUES ('33.17.12','Kragan',12);
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.12.2001','Tanjungsari',1 FROM master_districts WHERE district_code='33.17.12';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.12.2002','Sendangmulyo',2 FROM master_districts WHERE district_code='33.17.12';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.12.2003','Sendangwaru',3 FROM master_districts WHERE district_code='33.17.12';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.12.2004','Ngasinan',4 FROM master_districts WHERE district_code='33.17.12';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.12.2005','Kendalagung',5 FROM master_districts WHERE district_code='33.17.12';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.12.2006','Mojokerto',6 FROM master_districts WHERE district_code='33.17.12';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.12.2007','Tanjungan',7 FROM master_districts WHERE district_code='33.17.12';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.12.2008','Kebloran',8 FROM master_districts WHERE district_code='33.17.12';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.12.2009','Karanganyar',9 FROM master_districts WHERE district_code='33.17.12';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.12.2010','Karanglincak',10 FROM master_districts WHERE district_code='33.17.12';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.12.2011','Karangharjo',11 FROM master_districts WHERE district_code='33.17.12';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.12.2012','Kragan',12 FROM master_districts WHERE district_code='33.17.12';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.12.2013','Tegalmulyo',13 FROM master_districts WHERE district_code='33.17.12';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.12.2014','Balongmulyo',14 FROM master_districts WHERE district_code='33.17.12';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.12.2015','Narukan',15 FROM master_districts WHERE district_code='33.17.12';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.12.2016','Sudan',16 FROM master_districts WHERE district_code='33.17.12';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.12.2017','Terjan',17 FROM master_districts WHERE district_code='33.17.12';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.12.2018','Sendang',18 FROM master_districts WHERE district_code='33.17.12';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.12.2019','Watupecah',19 FROM master_districts WHERE district_code='33.17.12';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.12.2020','Woro',20 FROM master_districts WHERE district_code='33.17.12';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.12.2021','Sumurpule',21 FROM master_districts WHERE district_code='33.17.12';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.12.2022','Plawangan',22 FROM master_districts WHERE district_code='33.17.12';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.12.2023','Sumbergayam',23 FROM master_districts WHERE district_code='33.17.12';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.12.2024','Pandangan Wetan',24 FROM master_districts WHERE district_code='33.17.12';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.12.2025','Pandangan Kulon',25 FROM master_districts WHERE district_code='33.17.12';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.12.2026','Sumurtawang',26 FROM master_districts WHERE district_code='33.17.12';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.12.2027','Sumbersari',27 FROM master_districts WHERE district_code='33.17.12';
INSERT IGNORE INTO master_districts (district_code,district_name,sort_order) VALUES ('33.17.13','Sluke',13);
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.13.2001','Sanetan',1 FROM master_districts WHERE district_code='33.17.13';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.13.2002','Rakitan',2 FROM master_districts WHERE district_code='33.17.13';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.13.2003','Bendo',3 FROM master_districts WHERE district_code='33.17.13';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.13.2004','Labuhan Kidul',4 FROM master_districts WHERE district_code='33.17.13';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.13.2005','Sendangmulyo',5 FROM master_districts WHERE district_code='33.17.13';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.13.2006','Blimbing',6 FROM master_districts WHERE district_code='33.17.13';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.13.2007','Manggar',7 FROM master_districts WHERE district_code='33.17.13';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.13.2008','Jatisari',8 FROM master_districts WHERE district_code='33.17.13';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.13.2009','Langgar',9 FROM master_districts WHERE district_code='33.17.13';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.13.2010','Sluke',10 FROM master_districts WHERE district_code='33.17.13';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.13.2011','Jurangjero',11 FROM master_districts WHERE district_code='33.17.13';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.13.2012','Leran',12 FROM master_districts WHERE district_code='33.17.13';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.13.2013','Trahan',13 FROM master_districts WHERE district_code='33.17.13';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.13.2014','Pangkalan',14 FROM master_districts WHERE district_code='33.17.13';
INSERT IGNORE INTO master_districts (district_code,district_name,sort_order) VALUES ('33.17.14','Lasem',14);
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.14.2001','Karasgede',1 FROM master_districts WHERE district_code='33.17.14';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.14.2002','Jolotundo',2 FROM master_districts WHERE district_code='33.17.14';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.14.2003','Sumbergirang',3 FROM master_districts WHERE district_code='33.17.14';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.14.2004','Karangturi',4 FROM master_districts WHERE district_code='33.17.14';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.14.2005','Babagan',5 FROM master_districts WHERE district_code='33.17.14';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.14.2006','Dorokandang',6 FROM master_districts WHERE district_code='33.17.14';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.14.2007','Gedongmulyo',7 FROM master_districts WHERE district_code='33.17.14';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.14.2008','Dasun',8 FROM master_districts WHERE district_code='33.17.14';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.14.2009','Soditan',9 FROM master_districts WHERE district_code='33.17.14';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.14.2010','Ngemplak',10 FROM master_districts WHERE district_code='33.17.14';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.14.2011','Selopuro',11 FROM master_districts WHERE district_code='33.17.14';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.14.2012','Sendangcoyo',12 FROM master_districts WHERE district_code='33.17.14';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.14.2013','Ngargomulyo',13 FROM master_districts WHERE district_code='33.17.14';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.14.2014','Kajar',14 FROM master_districts WHERE district_code='33.17.14';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.14.2015','Gowak',15 FROM master_districts WHERE district_code='33.17.14';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.14.2016','Sendangasri',16 FROM master_districts WHERE district_code='33.17.14';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.14.2017','Tasiksono',17 FROM master_districts WHERE district_code='33.17.14';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.14.2018','Sriombo',18 FROM master_districts WHERE district_code='33.17.14';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.14.2019','Bonang',19 FROM master_districts WHERE district_code='33.17.14';
INSERT IGNORE INTO master_villages (district_id,village_code,village_name,sort_order) SELECT id,'33.17.14.2020','Binangun',20 FROM master_districts WHERE district_code='33.17.14';
INSERT IGNORE INTO master_locations (location_code,location_name,address,district_id,village_id,sort_order) SELECT 'UPT_REMBANG','UPT Metrologi Legal Kabupaten Rembang','UPT Metrologi Legal Kab. Rembang, Jl. Rembang - Lasem Km. 02, Ds. Tireman, Kab. Rembang',d.id,v.id,10 FROM master_districts d LEFT JOIN master_villages v ON v.district_id=d.id AND v.village_name='Tireman' WHERE d.district_name='Rembang';
INSERT IGNORE INTO master_options (group_code,option_code,option_label,description,sort_order,tone,service_location_id) VALUES ('penera','BUN_YAN','Bun Yan Marshush Al Wathon, S.T.',NULL,10,NULL,NULL);
INSERT IGNORE INTO master_options (group_code,option_code,option_label,description,sort_order,tone,service_location_id) VALUES ('penera','APRILIA_FONI','Aprilia Foni Kartini, A.Md',NULL,20,NULL,NULL);
INSERT IGNORE INTO master_options (group_code,option_code,option_label,description,sort_order,tone,service_location_id) VALUES ('ctt','B4','B4',NULL,10,NULL,NULL);
INSERT IGNORE INTO master_options (group_code,option_code,option_label,description,sort_order,tone,service_location_id) VALUES ('ctt','B6','B6',NULL,20,NULL,NULL);
INSERT IGNORE INTO master_options (group_code,option_code,option_label,description,sort_order,tone,service_location_id) VALUES ('ctt','D4','D4',NULL,30,NULL,NULL);
INSERT IGNORE INTO master_options (group_code,option_code,option_label,description,sort_order,tone,service_location_id) VALUES ('ctt','D8','D8',NULL,40,NULL,NULL);
INSERT IGNORE INTO master_options (group_code,option_code,option_label,description,sort_order,tone,service_location_id) VALUES ('ctt','J2','J2',NULL,50,NULL,NULL);
INSERT IGNORE INTO master_options (group_code,option_code,option_label,description,sort_order,tone,service_location_id) VALUES ('ctt','J4','J4',NULL,60,NULL,NULL);
INSERT IGNORE INTO master_options (group_code,option_code,option_label,description,sort_order,tone,service_location_id) VALUES ('ctt','J5','J5',NULL,70,NULL,NULL);
INSERT IGNORE INTO master_options (group_code,option_code,option_label,description,sort_order,tone,service_location_id) VALUES ('ctt','J8','J8',NULL,80,NULL,NULL);
INSERT IGNORE INTO master_options (group_code,option_code,option_label,description,sort_order,tone,service_location_id) VALUES ('ctt','JP8','JP8',NULL,90,NULL,NULL);
INSERT IGNORE INTO master_options (group_code,option_code,option_label,description,sort_order,tone,service_location_id) VALUES ('ctt','HP','HP',NULL,100,NULL,NULL);
INSERT IGNORE INTO master_options (group_code,option_code,option_label,description,sort_order,tone,service_location_id) VALUES ('ctt','H','H',NULL,110,NULL,NULL);
INSERT IGNORE INTO master_options (group_code,option_code,option_label,description,sort_order,tone,service_location_id) VALUES ('standard_equipment','EQUIPMENT_1','Anak timbangan standar',NULL,10,NULL,NULL);
INSERT IGNORE INTO master_options (group_code,option_code,option_label,description,sort_order,tone,service_location_id) VALUES ('standard_equipment','EQUIPMENT_2','Timbangan standar',NULL,20,NULL,NULL);
INSERT IGNORE INTO master_options (group_code,option_code,option_label,description,sort_order,tone,service_location_id) VALUES ('standard_equipment','EQUIPMENT_3','Bejana ukur standar',NULL,30,NULL,NULL);
INSERT IGNORE INTO master_options (group_code,option_code,option_label,description,sort_order,tone,service_location_id) VALUES ('standard_equipment','EQUIPMENT_4','Meter standar',NULL,40,NULL,NULL);
INSERT IGNORE INTO master_options (group_code,option_code,option_label,description,sort_order,tone,service_location_id) VALUES ('standard_equipment','EQUIPMENT_5','Peralatan standar lainnya',NULL,50,NULL,NULL);
INSERT IGNORE INTO master_options (group_code,option_code,option_label,description,sort_order,tone,service_location_id) VALUES ('service_type','tera','Tera',NULL,10,NULL,NULL);
INSERT IGNORE INTO master_options (group_code,option_code,option_label,description,sort_order,tone,service_location_id) VALUES ('service_type','tera_ulang','Tera ulang',NULL,20,NULL,NULL);
INSERT IGNORE INTO master_options (group_code,option_code,option_label,description,sort_order,tone,service_location_id) VALUES ('service_mode','office','Di kantor UPT',NULL,10,NULL,(SELECT id FROM master_locations WHERE location_code='UPT_REMBANG'));
INSERT IGNORE INTO master_options (group_code,option_code,option_label,description,sort_order,tone,service_location_id) VALUES ('service_mode','field','Di lokasi pemohon',NULL,20,NULL,NULL);
INSERT IGNORE INTO master_options (group_code,option_code,option_label,description,sort_order,tone,service_location_id) VALUES ('profile_kind','individual','Perorangan',NULL,10,NULL,NULL);
INSERT IGNORE INTO master_options (group_code,option_code,option_label,description,sort_order,tone,service_location_id) VALUES ('profile_kind','organization','Instansi / perusahaan',NULL,20,NULL,NULL);
INSERT IGNORE INTO master_options (group_code,option_code,option_label,description,sort_order,tone,service_location_id) VALUES ('status','draft','Draf',NULL,10,'info',NULL);
INSERT IGNORE INTO master_options (group_code,option_code,option_label,description,sort_order,tone,service_location_id) VALUES ('status','submitted','Dikirim',NULL,20,'info',NULL);
INSERT IGNORE INTO master_options (group_code,option_code,option_label,description,sort_order,tone,service_location_id) VALUES ('status','verification','Verifikasi berkas',NULL,30,'info',NULL);
INSERT IGNORE INTO master_options (group_code,option_code,option_label,description,sort_order,tone,service_location_id) VALUES ('status','revision','Perlu perbaikan',NULL,40,'danger',NULL);
INSERT IGNORE INTO master_options (group_code,option_code,option_label,description,sort_order,tone,service_location_id) VALUES ('status','scheduled','Terjadwal',NULL,50,'warning',NULL);
INSERT IGNORE INTO master_options (group_code,option_code,option_label,description,sort_order,tone,service_location_id) VALUES ('status','testing','Pengujian',NULL,60,'warning',NULL);
INSERT IGNORE INTO master_options (group_code,option_code,option_label,description,sort_order,tone,service_location_id) VALUES ('status','review','Pemeriksaan hasil',NULL,70,'warning',NULL);
INSERT IGNORE INTO master_options (group_code,option_code,option_label,description,sort_order,tone,service_location_id) VALUES ('status','completed','Selesai',NULL,80,'success',NULL);
INSERT IGNORE INTO master_options (group_code,option_code,option_label,description,sort_order,tone,service_location_id) VALUES ('status','cancelled','Dibatalkan',NULL,90,'danger',NULL);
INSERT IGNORE INTO master_options (group_code,option_code,option_label,description,sort_order,tone,service_location_id) VALUES ('status','rejected','Ditolak',NULL,100,'danger',NULL);
INSERT IGNORE INTO master_options (group_code,option_code,option_label,description,sort_order,tone,service_location_id) VALUES ('status','in_progress','Dalam proses',NULL,110,'info',NULL);
INSERT IGNORE INTO master_options (group_code,option_code,option_label,description,sort_order,tone,service_location_id) VALUES ('document_type','application_letter','Surat permohonan',NULL,10,NULL,NULL);
INSERT IGNORE INTO master_options (group_code,option_code,option_label,description,sort_order,tone,service_location_id) VALUES ('document_type','cerapan','Cerapan',NULL,20,NULL,NULL);
INSERT IGNORE INTO master_options (group_code,option_code,option_label,description,sort_order,tone,service_location_id) VALUES ('document_type','skhp','SKHP',NULL,30,NULL,NULL);
UPDATE uttp_types SET requires_capacity=1,sort_order=10 WHERE type_code='NERACA';
UPDATE uttp_types SET requires_capacity=1,sort_order=20 WHERE type_code='DACIN';
UPDATE uttp_types SET requires_capacity=1,sort_order=30 WHERE type_code='TIMBANGAN_ELEKTRONIK';
UPDATE uttp_types SET requires_capacity=1,sort_order=40 WHERE type_code='TIMBANGAN_PEGAS';
UPDATE uttp_types SET requires_capacity=1,sort_order=50 WHERE type_code='TIMBANGAN_SENTISIMAL';
UPDATE uttp_types SET requires_capacity=1,sort_order=60 WHERE type_code='TIMBANGAN_MILISIMAL';
UPDATE uttp_types SET requires_capacity=1,sort_order=70 WHERE type_code='TIMBANGAN_DESIMAL';
UPDATE uttp_types SET requires_capacity=1,sort_order=80 WHERE type_code='TIMBANGAN_MEJA';
UPDATE uttp_types SET requires_capacity=1,sort_order=90 WHERE type_code='TIMBANGAN_BOBOT_INGSUT';
UPDATE uttp_types SET requires_capacity=1,sort_order=100 WHERE type_code='TIMBANGAN_CEPAT';
UPDATE uttp_types SET requires_capacity=1,sort_order=110 WHERE type_code='TIMBANGAN_JEMBATAN';
UPDATE uttp_types SET requires_capacity=1,sort_order=120 WHERE type_code='TIMBANGAN_AMP';
UPDATE uttp_types SET requires_capacity=1,sort_order=130 WHERE type_code='TIMBANGAN_BATCHING';
UPDATE uttp_types SET requires_capacity=1,sort_order=140 WHERE type_code='TIMBANGAN_PENGECEK';
UPDATE uttp_types SET requires_capacity=1,sort_order=150 WHERE type_code='WIM';
UPDATE uttp_types SET requires_capacity=0,sort_order=160 WHERE type_code='ANAK_TIMBANGAN_PELENGKAP';
UPDATE uttp_types SET requires_capacity=0,sort_order=170 WHERE type_code='PUBBM';
UPDATE uttp_types SET requires_capacity=0,sort_order=180 WHERE type_code='TAKARAN_KERING';
UPDATE uttp_types SET requires_capacity=0,sort_order=190 WHERE type_code='TAKARAN_BASAH';
UPDATE uttp_types SET requires_capacity=0,sort_order=200 WHERE type_code='METER_KAYU';
UPDATE roles SET sort_order=CASE role_code WHEN 'administrator' THEN 10 WHEN 'coordinator' THEN 20 WHEN 'verifier' THEN 30 WHEN 'technician' THEN 40 WHEN 'issuer' THEN 50 WHEN 'applicant' THEN 60 ELSE 100 END;
UPDATE applicant_profiles p JOIN master_districts d ON TRIM(p.district)=d.district_name SET p.district_id=d.id WHERE p.district_id IS NULL;
UPDATE applicant_profiles p JOIN master_villages v ON v.district_id=p.district_id AND TRIM(p.village)=v.village_name SET p.village_id=v.id WHERE p.village_id IS NULL;
UPDATE service_requests sr JOIN master_districts d ON TRIM(sr.district)=d.district_name SET sr.district_id=d.id WHERE sr.district_id IS NULL;
UPDATE service_requests sr JOIN master_villages v ON v.district_id=sr.district_id AND TRIM(sr.village)=v.village_name SET sr.village_id=v.id WHERE sr.village_id IS NULL;
UPDATE service_requests sr JOIN master_options o ON o.group_code='penera' AND sr.penera_name=o.option_label SET sr.penera_option_id=o.id WHERE sr.penera_option_id IS NULL;
UPDATE service_requests sr JOIN master_options o ON o.group_code='ctt' AND sr.ctt_code=o.option_code SET sr.ctt_option_id=o.id WHERE sr.ctt_option_id IS NULL;
UPDATE service_requests sr JOIN master_options o ON o.group_code='standard_equipment' AND sr.standard_equipment=o.option_label SET sr.standard_equipment_option_id=o.id WHERE sr.standard_equipment_option_id IS NULL;
UPDATE service_requests sr JOIN master_options o ON o.group_code='service_mode' AND o.option_code=sr.service_mode SET sr.service_location_id=o.service_location_id WHERE sr.service_location_id IS NULL AND o.service_location_id IS NOT NULL;
