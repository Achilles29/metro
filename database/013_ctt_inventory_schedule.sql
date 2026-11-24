-- Stok CTT dan rentang jadwal pelaksanaan. NULL berarti jumlah aset belum didata.
ALTER TABLE master_options
  ADD COLUMN asset_quantity INT UNSIGNED NULL AFTER spreadsheet_url;

ALTER TABLE service_requests
  ADD COLUMN tera_start_date DATE NULL AFTER verification_finalized_at,
  ADD COLUMN tera_end_date DATE NULL AFTER tera_start_date;

ALTER TABLE request_ctts
  ADD COLUMN asset_units INT UNSIGNED NOT NULL DEFAULT 1 AFTER ctt_option_id,
  ADD COLUMN schedule_start_date DATE NULL AFTER returned_by,
  ADD COLUMN schedule_end_date DATE NULL AFTER schedule_start_date,
  ADD KEY ix_ctt_inventory_schedule (ctt_option_id, schedule_start_date, schedule_end_date);
