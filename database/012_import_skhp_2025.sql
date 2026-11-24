-- Penanda impor arsip SKHP 2025. Jalankan sekali setelah cadangan basis data.
-- Kunci sumber memastikan impor dapat diulang tanpa membuat akun atau layanan ganda.

CREATE TABLE IF NOT EXISTS legacy_skhp_accounts (
  owner_key VARCHAR(190) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (owner_key),
  UNIQUE KEY uq_legacy_skhp_user (user_id),
  CONSTRAINT fk_legacy_skhp_account_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS legacy_skhp_files (
  source_file VARCHAR(190) NOT NULL,
  certificate_no VARCHAR(100) NOT NULL,
  reported_order_no VARCHAR(48) NOT NULL,
  effective_order_no VARCHAR(48) NOT NULL,
  service_request_id BIGINT UNSIGNED NOT NULL,
  first_document_id BIGINT UNSIGNED NOT NULL,
  unit_count SMALLINT UNSIGNED NOT NULL,
  source_note TEXT NULL,
  imported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (source_file),
  KEY ix_legacy_skhp_certificate (certificate_no),
  KEY ix_legacy_skhp_request (service_request_id),
  CONSTRAINT fk_legacy_skhp_request FOREIGN KEY (service_request_id) REFERENCES service_requests(id) ON DELETE RESTRICT,
  CONSTRAINT fk_legacy_skhp_document FOREIGN KEY (first_document_id) REFERENCES service_documents(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
