USE metro_app;
CREATE TABLE spbu_import_profiles (
  applicant_profile_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  source_key VARCHAR(60) NOT NULL,
  processed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_spbu_import_profile FOREIGN KEY (applicant_profile_id) REFERENCES applicant_profiles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
