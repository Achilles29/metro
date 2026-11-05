-- Metro v0.1 - initial operational schema
-- Target: MySQL/MariaDB with InnoDB and utf8mb4
-- Review status names, retention, and required UTTP attributes with UPT before production.

CREATE DATABASE IF NOT EXISTS metro_app
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE metro_app;

CREATE TABLE roles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  role_code VARCHAR(40) NOT NULL,
  role_name VARCHAR(100) NOT NULL,
  description VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_roles_code (role_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(190) NULL,
  phone VARCHAR(32) NULL,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(160) NOT NULL,
  account_type VARCHAR(24) NOT NULL DEFAULT 'applicant',
  account_status VARCHAR(24) NOT NULL DEFAULT 'pending',
  email_verified_at DATETIME NULL,
  phone_verified_at DATETIME NULL,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  UNIQUE KEY uq_users_phone (phone),
  KEY ix_users_status_type (account_status, account_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_roles (
  user_id BIGINT UNSIGNED NOT NULL,
  role_id BIGINT UNSIGNED NOT NULL,
  assigned_by BIGINT UNSIGNED NULL,
  assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, role_id),
  CONSTRAINT fk_user_roles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_user_roles_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT,
  CONSTRAINT fk_user_roles_assigner FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE applicant_profiles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  profile_kind VARCHAR(24) NOT NULL DEFAULT 'individual',
  organization_name VARCHAR(190) NULL,
  organization_id_ref VARCHAR(100) NULL,
  contact_person VARCHAR(160) NULL,
  address TEXT NULL,
  village VARCHAR(120) NULL,
  district VARCHAR(120) NULL,
  postal_code VARCHAR(12) NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  location_visibility VARCHAR(20) NOT NULL DEFAULT 'private',
  profile_status VARCHAR(24) NOT NULL DEFAULT 'unverified',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_applicant_profiles_user (user_id),
  KEY ix_applicant_profiles_district (district, village),
  CONSTRAINT fk_applicant_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE uttp_types (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  type_code VARCHAR(48) NOT NULL,
  type_name VARCHAR(160) NOT NULL,
  description TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_uttp_types_code (type_code),
  KEY ix_uttp_types_active_name (is_active, type_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE applicant_uttp (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  applicant_profile_id BIGINT UNSIGNED NOT NULL,
  uttp_type_id BIGINT UNSIGNED NOT NULL,
  owner_name VARCHAR(190) NOT NULL,
  brand VARCHAR(120) NULL,
  model_name VARCHAR(120) NULL,
  type_name VARCHAR(120) NULL,
  capacity VARCHAR(120) NULL,
  serial_number VARCHAR(120) NULL,
  service_address TEXT NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY ix_applicant_uttp_owner (applicant_profile_id, is_active),
  KEY ix_applicant_uttp_serial (serial_number),
  CONSTRAINT fk_applicant_uttp_profile FOREIGN KEY (applicant_profile_id) REFERENCES applicant_profiles(id) ON DELETE RESTRICT,
  CONSTRAINT fk_applicant_uttp_type FOREIGN KEY (uttp_type_id) REFERENCES uttp_types(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE number_sequences (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  sequence_code VARCHAR(40) NOT NULL,
  sequence_year SMALLINT UNSIGNED NOT NULL,
  last_value BIGINT UNSIGNED NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_number_sequences_code_year (sequence_code, sequence_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE service_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_no VARCHAR(48) NOT NULL,
  applicant_profile_id BIGINT UNSIGNED NOT NULL,
  service_type VARCHAR(24) NOT NULL,
  request_status VARCHAR(32) NOT NULL DEFAULT 'draft',
  service_mode VARCHAR(24) NOT NULL DEFAULT 'office',
  submitted_at DATETIME NULL,
  contact_name VARCHAR(160) NULL,
  contact_phone VARCHAR(32) NULL,
  service_address TEXT NULL,
  village VARCHAR(120) NULL,
  district VARCHAR(120) NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  applicant_note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_service_requests_no (request_no),
  KEY ix_service_requests_queue (request_status, submitted_at),
  KEY ix_service_requests_applicant (applicant_profile_id, created_at),
  CONSTRAINT fk_service_requests_profile FOREIGN KEY (applicant_profile_id) REFERENCES applicant_profiles(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE request_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  service_request_id BIGINT UNSIGNED NOT NULL,
  unit_sequence SMALLINT UNSIGNED NOT NULL,
  applicant_uttp_id BIGINT UNSIGNED NULL,
  uttp_type_id BIGINT UNSIGNED NOT NULL,
  item_status VARCHAR(32) NOT NULL DEFAULT 'submitted',
  owner_name VARCHAR(190) NOT NULL,
  brand VARCHAR(120) NULL,
  type_name VARCHAR(120) NULL,
  model_name VARCHAR(120) NULL,
  capacity VARCHAR(120) NULL,
  serial_number VARCHAR(120) NULL,
  applicant_note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_request_items_request_sequence (service_request_id, unit_sequence),
  KEY ix_request_items_status (item_status, created_at),
  KEY ix_request_items_serial (serial_number),
  CONSTRAINT fk_request_items_request FOREIGN KEY (service_request_id) REFERENCES service_requests(id) ON DELETE RESTRICT,
  CONSTRAINT fk_request_items_inventory FOREIGN KEY (applicant_uttp_id) REFERENCES applicant_uttp(id) ON DELETE SET NULL,
  CONSTRAINT fk_request_items_type FOREIGN KEY (uttp_type_id) REFERENCES uttp_types(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE request_status_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  service_request_id BIGINT UNSIGNED NOT NULL,
  from_status VARCHAR(32) NULL,
  to_status VARCHAR(32) NOT NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  visibility VARCHAR(16) NOT NULL DEFAULT 'applicant',
  note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_request_history_timeline (service_request_id, created_at),
  CONSTRAINT fk_request_history_request FOREIGN KEY (service_request_id) REFERENCES service_requests(id) ON DELETE RESTRICT,
  CONSTRAINT fk_request_history_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE item_status_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_item_id BIGINT UNSIGNED NOT NULL,
  from_status VARCHAR(32) NULL,
  to_status VARCHAR(32) NOT NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  visibility VARCHAR(16) NOT NULL DEFAULT 'applicant',
  note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_item_history_timeline (request_item_id, created_at),
  CONSTRAINT fk_item_history_item FOREIGN KEY (request_item_id) REFERENCES request_items(id) ON DELETE RESTRICT,
  CONSTRAINT fk_item_history_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE service_documents (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  service_request_id BIGINT UNSIGNED NOT NULL,
  request_item_id BIGINT UNSIGNED NULL,
  document_type VARCHAR(40) NOT NULL,
  visibility VARCHAR(16) NOT NULL DEFAULT 'applicant',
  original_name VARCHAR(255) NOT NULL,
  storage_key VARCHAR(255) NOT NULL,
  detected_mime VARCHAR(120) NOT NULL,
  file_size BIGINT UNSIGNED NOT NULL,
  sha256 CHAR(64) NOT NULL,
  uploaded_by BIGINT UNSIGNED NULL,
  uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  is_current TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_service_documents_storage_key (storage_key),
  KEY ix_service_documents_request_type (service_request_id, document_type, is_current),
  KEY ix_service_documents_item (request_item_id, document_type, is_current),
  CONSTRAINT fk_service_documents_request FOREIGN KEY (service_request_id) REFERENCES service_requests(id) ON DELETE RESTRICT,
  CONSTRAINT fk_service_documents_item FOREIGN KEY (request_item_id) REFERENCES request_items(id) ON DELETE RESTRICT,
  CONSTRAINT fk_service_documents_uploader FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE staff_assignments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_item_id BIGINT UNSIGNED NOT NULL,
  assigned_to BIGINT UNSIGNED NOT NULL,
  assigned_by BIGINT UNSIGNED NULL,
  assignment_type VARCHAR(32) NOT NULL,
  assignment_status VARCHAR(24) NOT NULL DEFAULT 'assigned',
  assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  note TEXT NULL,
  PRIMARY KEY (id),
  KEY ix_assignments_staff_status (assigned_to, assignment_status),
  KEY ix_assignments_item (request_item_id, assigned_at),
  CONSTRAINT fk_assignments_item FOREIGN KEY (request_item_id) REFERENCES request_items(id) ON DELETE RESTRICT,
  CONSTRAINT fk_assignments_assignee FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_assignments_assigner FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE field_visits (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  service_request_id BIGINT UNSIGNED NOT NULL,
  scheduled_by BIGINT UNSIGNED NULL,
  visit_status VARCHAR(24) NOT NULL DEFAULT 'scheduled',
  scheduled_at DATETIME NULL,
  started_at DATETIME NULL,
  completed_at DATETIME NULL,
  service_address TEXT NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  location_source VARCHAR(32) NULL,
  location_verified_at DATETIME NULL,
  result_note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_field_visits_schedule (visit_status, scheduled_at),
  KEY ix_field_visits_request (service_request_id),
  CONSTRAINT fk_field_visits_request FOREIGN KEY (service_request_id) REFERENCES service_requests(id) ON DELETE RESTRICT,
  CONSTRAINT fk_field_visits_scheduler FOREIGN KEY (scheduled_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE certificates (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_item_id BIGINT UNSIGNED NOT NULL,
  service_document_id BIGINT UNSIGNED NOT NULL,
  certificate_no VARCHAR(100) NOT NULL,
  certificate_version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  issued_at DATETIME NOT NULL,
  issued_by BIGINT UNSIGNED NULL,
  certificate_status VARCHAR(24) NOT NULL DEFAULT 'valid',
  verification_token CHAR(64) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_certificates_no (certificate_no),
  UNIQUE KEY uq_certificates_document (service_document_id),
  UNIQUE KEY uq_certificates_token (verification_token),
  KEY ix_certificates_item_version (request_item_id, certificate_version),
  CONSTRAINT fk_certificates_item FOREIGN KEY (request_item_id) REFERENCES request_items(id) ON DELETE RESTRICT,
  CONSTRAINT fk_certificates_document FOREIGN KEY (service_document_id) REFERENCES service_documents(id) ON DELETE RESTRICT,
  CONSTRAINT fk_certificates_issuer FOREIGN KEY (issued_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  notification_type VARCHAR(40) NOT NULL,
  title VARCHAR(190) NOT NULL,
  message VARCHAR(500) NOT NULL,
  target_url VARCHAR(255) NULL,
  sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  read_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY ix_notifications_inbox (user_id, read_at, sent_at),
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE service_feedback (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  service_request_id BIGINT UNSIGNED NOT NULL,
  applicant_profile_id BIGINT UNSIGNED NOT NULL,
  rating TINYINT UNSIGNED NOT NULL,
  comment TEXT NULL,
  submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_service_feedback_request (service_request_id),
  CONSTRAINT fk_feedback_request FOREIGN KEY (service_request_id) REFERENCES service_requests(id) ON DELETE RESTRICT,
  CONSTRAINT fk_feedback_profile FOREIGN KEY (applicant_profile_id) REFERENCES applicant_profiles(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  actor_user_id BIGINT UNSIGNED NULL,
  action_code VARCHAR(64) NOT NULL,
  entity_type VARCHAR(64) NOT NULL,
  entity_id BIGINT UNSIGNED NULL,
  summary VARCHAR(500) NULL,
  ip_address VARBINARY(16) NULL,
  user_agent VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_audit_entity (entity_type, entity_id, created_at),
  KEY ix_audit_actor_time (actor_user_id, created_at),
  CONSTRAINT fk_audit_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO roles (role_code, role_name, description) VALUES
  ('applicant', 'Pemohon', 'Mengelola profil, pengajuan, dan dokumen miliknya'),
  ('verifier', 'Verifikator', 'Memeriksa kelengkapan dan meminta perbaikan'),
  ('technician', 'Petugas teknis', 'Menangani pekerjaan teknis dan cerapan sesuai penugasan'),
  ('coordinator', 'Koordinator', 'Mengatur antrean, jadwal, dan penugasan'),
  ('issuer', 'Pejabat penerbit', 'Menelaah atau mengesahkan hasil sesuai kewenangan'),
  ('administrator', 'Administrator', 'Mengelola akun internal dan konfigurasi aplikasi');
