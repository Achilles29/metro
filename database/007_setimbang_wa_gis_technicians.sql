-- Setimbang: WhatsApp verification, GIS snapshots, and external technicians.
-- Apply once after 006_role_permissions.sql. Back up metro_app first.
USE metro_app;

ALTER TABLE phone_verification_codes MODIFY created_by BIGINT UNSIGNED NULL;
ALTER TABLE phone_verification_codes ADD COLUMN delivery_channel VARCHAR(16) NOT NULL DEFAULT 'manual' AFTER created_by;

CREATE TABLE wa_session (
 id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
 is_enabled TINYINT(1) NOT NULL DEFAULT 0,
 status VARCHAR(24) NOT NULL DEFAULT 'DISABLED',
 phone_number VARCHAR(32) NULL,
 qr_data TEXT NULL,
 last_ping_at DATETIME NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO wa_session (id) VALUES (1);

CREATE TABLE wa_settings (
 id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
 otp_template VARCHAR(500) NOT NULL,
 updated_by BIGINT UNSIGNED NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_wa_settings_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO wa_settings (id,otp_template) VALUES
(1,'Kode verifikasi Setimbang Anda: {code}. Berlaku 15 menit. Jangan berikan kode ini kepada siapa pun.');

CREATE TABLE wa_outbox (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 code_id BIGINT UNSIGNED NOT NULL,
 phone_number VARCHAR(20) NOT NULL,
 message VARCHAR(600) NOT NULL,
 status VARCHAR(16) NOT NULL DEFAULT 'PENDING',
 attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
 error_message VARCHAR(500) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 sent_at DATETIME NULL,
 KEY ix_wa_outbox_queue (status,created_at),
 KEY ix_wa_outbox_user (user_id,created_at),
 CONSTRAINT fk_wa_outbox_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
 CONSTRAINT fk_wa_outbox_code FOREIGN KEY (code_id) REFERENCES phone_verification_codes(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE applicant_profiles ADD COLUMN location_source VARCHAR(16) NULL AFTER longitude;
ALTER TABLE applicant_uttp ADD COLUMN location_source VARCHAR(16) NULL AFTER longitude;
ALTER TABLE request_items ADD COLUMN service_address TEXT NULL AFTER applicant_note;
ALTER TABLE request_items ADD COLUMN latitude DECIMAL(10,7) NULL AFTER service_address;
ALTER TABLE request_items ADD COLUMN longitude DECIMAL(10,7) NULL AFTER latitude;
ALTER TABLE request_items ADD COLUMN location_source VARCHAR(16) NULL AFTER longitude;
ALTER TABLE service_requests ADD COLUMN submitted_by_user_id BIGINT UNSIGNED NULL AFTER applicant_profile_id;
ALTER TABLE service_requests ADD KEY ix_requests_submitter (submitted_by_user_id,created_at);
ALTER TABLE service_requests ADD CONSTRAINT fk_requests_submitter FOREIGN KEY (submitted_by_user_id) REFERENCES users(id) ON DELETE RESTRICT;

INSERT INTO roles (role_code,role_name,description,is_active,sort_order)
VALUES ('external_technician','Teknisi eksternal','Teknisi rekomendasi dinas yang dapat mewakili perusahaan setelah disetujui',1,45);

CREATE TABLE technician_profiles (
 user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 recommendation_number VARCHAR(120) NULL,
 recommendation_note TEXT NULL,
 verification_status VARCHAR(16) NOT NULL DEFAULT 'pending',
 reviewed_by BIGINT UNSIGNED NULL,
 reviewed_at DATETIME NULL,
 review_note TEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NULL,
 KEY ix_technician_status (verification_status,created_at),
 CONSTRAINT fk_technician_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
 CONSTRAINT fk_technician_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE company_technicians (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 company_profile_id BIGINT UNSIGNED NOT NULL,
 technician_user_id BIGINT UNSIGNED NOT NULL,
 initiated_by_user_id BIGINT UNSIGNED NOT NULL,
 status VARCHAR(24) NOT NULL DEFAULT 'awaiting_counterpart',
 counterpart_accepted_at DATETIME NULL,
 reviewed_by BIGINT UNSIGNED NULL,
 reviewed_at DATETIME NULL,
 review_note TEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NULL,
 UNIQUE KEY uq_company_technician (company_profile_id,technician_user_id),
 KEY ix_company_technician_status (status,created_at),
 KEY ix_company_technician_user (technician_user_id,status),
 CONSTRAINT fk_company_technician_company FOREIGN KEY (company_profile_id) REFERENCES applicant_profiles(id) ON DELETE RESTRICT,
 CONSTRAINT fk_company_technician_user FOREIGN KEY (technician_user_id) REFERENCES users(id) ON DELETE RESTRICT,
 CONSTRAINT fk_company_technician_initiator FOREIGN KEY (initiated_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
 CONSTRAINT fk_company_technician_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (permission_code,permission_name,permission_group,description,sort_order)
VALUES ('technicians.manage','Verifikasi teknisi dan pelimpahan','Administrasi','Menilai teknisi eksternal dan hubungan teknisi dengan perusahaan',205);
INSERT INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p ON p.permission_code='technicians.manage'
WHERE r.role_code='administrator';
