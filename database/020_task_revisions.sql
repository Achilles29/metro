USE metro_app;

-- Jenis layanan dicatat pada setiap UTTP. Nilai lama mengikuti layanan order.
ALTER TABLE request_items ADD COLUMN service_type VARCHAR(24) NULL AFTER uttp_type_id;
UPDATE request_items ri JOIN service_requests sr ON sr.id=ri.service_request_id
SET ri.service_type=sr.service_type WHERE ri.service_type IS NULL;
CREATE INDEX ix_request_items_service_type ON request_items (service_type,service_request_id);

-- Token persisten untuk pilihan "Ingat saya"; hanya hash verifier disimpan.
CREATE TABLE remember_tokens (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 selector CHAR(24) NOT NULL,
 verifier_hash CHAR(64) NOT NULL,
 expires_at DATETIME NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 last_used_at DATETIME NULL,
 UNIQUE KEY uq_remember_selector (selector),
 KEY ix_remember_user (user_id),
 KEY ix_remember_expiry (expires_at),
 CONSTRAINT fk_remember_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
