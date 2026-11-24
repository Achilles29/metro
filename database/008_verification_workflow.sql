-- Verifikasi per permohonan, CTT jamak, dokumentasi, dan integrasi.
USE metro_app;

ALTER TABLE service_requests ADD COLUMN submission_source VARCHAR(16) NOT NULL DEFAULT 'online' AFTER submitted_by_user_id;
ALTER TABLE service_requests ADD COLUMN verification_finalized_at DATETIME NULL AFTER submitted_at;
-- Permohonan lama yang sudah melewati verifikasi tetap dapat melanjutkan CTT.
UPDATE service_requests sr SET verification_finalized_at=COALESCE(sr.updated_at,sr.submitted_at)
WHERE sr.verification_finalized_at IS NULL
  AND EXISTS (SELECT 1 FROM request_items ri WHERE ri.service_request_id=sr.id)
  AND NOT EXISTS (SELECT 1 FROM request_items ri WHERE ri.service_request_id=sr.id AND ri.item_status IN ('submitted','verification','revision'));
ALTER TABLE request_items ADD COLUMN verification_decision VARCHAR(16) NULL AFTER item_status;
ALTER TABLE service_documents ADD COLUMN drive_file_id VARCHAR(128) NULL AFTER is_current;
ALTER TABLE service_documents ADD COLUMN drive_synced_at DATETIME NULL AFTER drive_file_id;
ALTER TABLE service_documents ADD COLUMN drive_error VARCHAR(500) NULL AFTER drive_synced_at;
ALTER TABLE master_options ADD COLUMN spreadsheet_url VARCHAR(500) NULL AFTER service_location_id;

CREATE TABLE verification_checks (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 label VARCHAR(190) NOT NULL,
 description VARCHAR(500) NULL,
 sort_order INT NOT NULL DEFAULT 0,
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NULL,
 KEY ix_verification_checks_active (is_active,sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO verification_checks (label,description,sort_order) VALUES
 ('Identitas dan kewenangan pemohon','Periksa identitas pemohon serta hubungan dengan pemilik UTTP.',10),
 ('Kelengkapan surat permohonan','Periksa surat permohonan yang diunggah.',20),
 ('Kesesuaian data UTTP','Periksa jenis, merek, nomor seri, jumlah, dan lokasi UTTP.',30);

CREATE TABLE request_check_results (
 request_id BIGINT UNSIGNED NOT NULL,
 check_id BIGINT UNSIGNED NOT NULL,
 result VARCHAR(16) NOT NULL,
 note VARCHAR(1000) NULL,
 reviewed_by BIGINT UNSIGNED NOT NULL,
 reviewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (request_id,check_id),
 CONSTRAINT fk_check_result_request FOREIGN KEY (request_id) REFERENCES service_requests(id) ON DELETE RESTRICT,
 CONSTRAINT fk_check_result_check FOREIGN KEY (check_id) REFERENCES verification_checks(id) ON DELETE RESTRICT,
 CONSTRAINT fk_check_result_user FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE request_check_units (
 request_id BIGINT UNSIGNED NOT NULL,
 check_id BIGINT UNSIGNED NOT NULL,
 item_id BIGINT UNSIGNED NOT NULL,
 result VARCHAR(16) NOT NULL,
 PRIMARY KEY (request_id,check_id,item_id),
 CONSTRAINT fk_check_unit_result FOREIGN KEY (request_id,check_id) REFERENCES request_check_results(request_id,check_id) ON DELETE RESTRICT,
 CONSTRAINT fk_check_unit_item FOREIGN KEY (item_id) REFERENCES request_items(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE request_ctts (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 request_id BIGINT UNSIGNED NOT NULL,
 ctt_option_id BIGINT UNSIGNED NOT NULL,
 added_by BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 sheet_synced_at DATETIME NULL,
 sheet_error VARCHAR(500) NULL,
 UNIQUE KEY uq_request_ctt (request_id,ctt_option_id),
 CONSTRAINT fk_request_ctt_request FOREIGN KEY (request_id) REFERENCES service_requests(id) ON DELETE RESTRICT,
 CONSTRAINT fk_request_ctt_option FOREIGN KEY (ctt_option_id) REFERENCES master_options(id) ON DELETE RESTRICT,
 CONSTRAINT fk_request_ctt_user FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO request_ctts (request_id,ctt_option_id,added_by)
SELECT sr.id,sr.ctt_option_id,COALESCE(sr.submitted_by_user_id,(SELECT id FROM users WHERE account_type='staff' ORDER BY id LIMIT 1))
FROM service_requests sr WHERE sr.ctt_option_id IS NOT NULL;

CREATE TABLE request_photos (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 request_id BIGINT UNSIGNED NOT NULL,
 item_id BIGINT UNSIGNED NULL,
 storage_key VARCHAR(80) NOT NULL,
 original_name VARCHAR(255) NOT NULL,
 file_size BIGINT UNSIGNED NOT NULL,
 caption VARCHAR(255) NULL,
 uploaded_by BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY ix_request_photos_request (request_id,created_at),
 CONSTRAINT fk_request_photo_request FOREIGN KEY (request_id) REFERENCES service_requests(id) ON DELETE RESTRICT,
 CONSTRAINT fk_request_photo_item FOREIGN KEY (item_id) REFERENCES request_items(id) ON DELETE RESTRICT,
 CONSTRAINT fk_request_photo_user FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE google_settings (
 id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
 spreadsheet_id VARCHAR(190) NULL,
 sheet_range VARCHAR(190) NOT NULL DEFAULT 'CTT!A:K',
 drive_folder_id VARCHAR(190) NULL,
 cerapan_filename_pattern VARCHAR(190) NOT NULL DEFAULT 'Cerapan-{request_no}-Unit-{unit_sequence}-{date}.pdf',
 updated_by BIGINT UNSIGNED NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_google_settings_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO google_settings (id) VALUES (1);

CREATE TABLE wa_notifications (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 phone_number VARCHAR(20) NOT NULL,
 message VARCHAR(600) NOT NULL,
 status VARCHAR(16) NOT NULL DEFAULT 'PENDING',
 attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
 error_message VARCHAR(500) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 sent_at DATETIME NULL,
 KEY ix_wa_notifications_queue (status,created_at),
 CONSTRAINT fk_wa_notification_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (permission_code,permission_name,permission_group,description,sort_order)
VALUES ('requests.create_offline','Input permohonan loket','Permohonan','Membuat permohonan bagi pemohon yang datang ke kantor',55);
INSERT INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p ON p.permission_code='requests.create_offline'
WHERE r.role_code='administrator';
