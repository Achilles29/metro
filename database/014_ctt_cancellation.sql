-- Pembatalan pemilihan CTT menyimpan riwayat peminjaman dan membebaskan stok.
ALTER TABLE request_ctts
  ADD COLUMN cancelled_at DATETIME NULL AFTER returned_by,
  ADD COLUMN cancelled_by BIGINT UNSIGNED NULL AFTER cancelled_at,
  ADD COLUMN cancel_reason VARCHAR(255) NULL AFTER cancelled_by,
  DROP INDEX uq_request_ctt,
  ADD KEY ix_request_ctt_active (request_id, ctt_option_id, cancelled_at);
