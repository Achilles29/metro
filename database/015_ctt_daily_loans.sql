-- Setiap CTT dapat dipinjam dan dikembalikan sekali per hari jadwal.
CREATE TABLE request_ctt_daily_loans (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  request_ctt_id BIGINT UNSIGNED NOT NULL,
  loan_date DATE NOT NULL,
  borrowed_at DATETIME NOT NULL,
  borrowed_by BIGINT UNSIGNED NOT NULL,
  returned_at DATETIME NULL,
  returned_by BIGINT UNSIGNED NULL,
  sheet_synced_at DATETIME NULL,
  sheet_error VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_ctt_daily_loan (request_ctt_id, loan_date),
  KEY ix_ctt_daily_open (returned_at, loan_date),
  CONSTRAINT fk_ctt_daily_reservation FOREIGN KEY (request_ctt_id) REFERENCES request_ctts(id) ON DELETE RESTRICT,
  CONSTRAINT fk_ctt_daily_borrower FOREIGN KEY (borrowed_by) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_ctt_daily_returner FOREIGN KEY (returned_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO request_ctt_daily_loans
  (request_ctt_id,loan_date,borrowed_at,borrowed_by,returned_at,returned_by,sheet_synced_at,sheet_error)
SELECT id,DATE(borrowed_at),borrowed_at,COALESCE(borrowed_by,added_by),
  returned_at,returned_by,sheet_synced_at,sheet_error
FROM request_ctts WHERE borrowed_at IS NOT NULL;
