USE metro_app;

ALTER TABLE users ADD COLUMN username VARCHAR(90) NULL AFTER id,
 ADD UNIQUE KEY uq_users_username (username);

ALTER TABLE applicant_uttp
 ADD COLUMN nozzle_no VARCHAR(40) NULL AFTER serial_number,
 ADD COLUMN dispenser_no VARCHAR(40) NULL AFTER nozzle_no,
 ADD COLUMN media VARCHAR(80) NULL AFTER dispenser_no,
 ADD COLUMN manufacture_year VARCHAR(40) NULL AFTER media,
 ADD COLUMN flow_rate VARCHAR(80) NULL AFTER manufacture_year,
 ADD COLUMN readability VARCHAR(80) NULL AFTER flow_rate,
 ADD COLUMN source_reference VARCHAR(100) NULL AFTER readability,
 ADD UNIQUE KEY uq_applicant_uttp_source (source_reference);

ALTER TABLE request_items
 ADD COLUMN nozzle_no VARCHAR(40) NULL AFTER serial_number,
 ADD COLUMN dispenser_no VARCHAR(40) NULL AFTER nozzle_no,
 ADD COLUMN media VARCHAR(80) NULL AFTER dispenser_no,
 ADD COLUMN manufacture_year VARCHAR(40) NULL AFTER media,
 ADD COLUMN flow_rate VARCHAR(80) NULL AFTER manufacture_year,
 ADD COLUMN readability VARCHAR(80) NULL AFTER flow_rate;

ALTER TABLE service_requests
 ADD COLUMN cancelled_at DATETIME NULL AFTER completed_at,
 ADD COLUMN cancelled_by_user_id BIGINT UNSIGNED NULL AFTER cancelled_at,
 ADD COLUMN cancellation_reason VARCHAR(500) NULL AFTER cancelled_by_user_id,
 ADD KEY ix_service_requests_cancelled_by (cancelled_by_user_id),
 ADD CONSTRAINT fk_service_requests_cancelled_by FOREIGN KEY (cancelled_by_user_id) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE technician_profiles
 ADD COLUMN tdur_number VARCHAR(120) NULL AFTER recommendation_number,
 ADD COLUMN tdur_scope TEXT NULL AFTER tdur_number,
 ADD COLUMN recommendation_head VARCHAR(160) NULL AFTER tdur_scope;

CREATE TABLE technician_documents (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 technician_user_id BIGINT UNSIGNED NOT NULL,
 document_type VARCHAR(40) NOT NULL,
 original_name VARCHAR(255) NOT NULL,
 storage_key VARCHAR(255) NOT NULL,
 uploaded_by BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 deleted_at DATETIME NULL,
 UNIQUE KEY uq_technician_documents_key (storage_key),
 KEY ix_technician_documents_user (technician_user_id,deleted_at),
 CONSTRAINT fk_technician_documents_user FOREIGN KEY (technician_user_id) REFERENCES users(id),
 CONSTRAINT fk_technician_documents_uploader FOREIGN KEY (uploaded_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
