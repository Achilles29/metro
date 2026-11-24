-- Revisi alur kaji ulang, CTT, hasil UTTP, dan laporan Google.
ALTER TABLE service_requests
  ADD COLUMN completed_at DATETIME NULL AFTER verification_finalized_at;

ALTER TABLE request_items
  ADD COLUMN tested_at DATE NULL AFTER verification_decision;

ALTER TABLE request_ctts
  ADD COLUMN borrowed_at DATETIME NULL AFTER added_by,
  ADD COLUMN borrowed_by BIGINT UNSIGNED NULL AFTER borrowed_at,
  ADD COLUMN returned_at DATETIME NULL AFTER borrowed_by,
  ADD COLUMN returned_by BIGINT UNSIGNED NULL AFTER returned_at;

ALTER TABLE request_photos
  ADD COLUMN deleted_at DATETIME NULL AFTER created_at,
  ADD COLUMN deleted_by BIGINT UNSIGNED NULL AFTER deleted_at;

ALTER TABLE uttp_types
  ADD COLUMN certificate_scope VARCHAR(8) NOT NULL DEFAULT 'unit' AFTER requires_capacity;

ALTER TABLE google_settings
  ADD COLUMN order_spreadsheet_id VARCHAR(190) NULL AFTER sheet_range,
  ADD COLUMN order_sheet_range VARCHAR(190) NOT NULL DEFAULT 'Template!A3:L' AFTER order_spreadsheet_id,
  ADD COLUMN ttu_spreadsheet_id VARCHAR(190) NULL AFTER order_sheet_range,
  ADD COLUMN ttu_sah_range VARCHAR(190) NOT NULL DEFAULT "'Template Sah'!A3:I" AFTER ttu_spreadsheet_id,
  ADD COLUMN ttu_batal_range VARCHAR(190) NOT NULL DEFAULT "'Template Batal'!A3:I" AFTER ttu_sah_range,
  ADD COLUMN ttu_other_range VARCHAR(190) NOT NULL DEFAULT "'Template Dikembalikan/Tidak sesuai'!A3:J" AFTER ttu_batal_range,
  ADD COLUMN drive_auth_mode VARCHAR(24) NOT NULL DEFAULT 'service_account' AFTER drive_folder_id;

CREATE TABLE IF NOT EXISTS request_report_syncs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_id BIGINT UNSIGNED NOT NULL,
  report_type VARCHAR(24) NOT NULL,
  row_count INT UNSIGNED NOT NULL DEFAULT 0,
  synced_at DATETIME NULL,
  last_error VARCHAR(500) NULL,
  attempted_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_request_report_type (request_id,report_type),
  CONSTRAINT fk_request_report_request FOREIGN KEY (request_id) REFERENCES service_requests(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE request_ctts SET borrowed_at=created_at,borrowed_by=added_by WHERE borrowed_at IS NULL;
UPDATE uttp_types SET certificate_scope='order' WHERE type_name LIKE 'Pompa ukur BBM%';
UPDATE service_requests sr SET completed_at=COALESCE(
  (SELECT MAX(h.created_at) FROM item_status_history h JOIN request_items i ON i.id=h.request_item_id
   WHERE i.service_request_id=sr.id AND h.to_status IN ('completed','rejected','cancelled')),
  sr.updated_at)
WHERE completed_at IS NULL AND NOT EXISTS
  (SELECT 1 FROM request_items i WHERE i.service_request_id=sr.id
   AND i.item_status NOT IN ('completed','rejected','cancelled'));

UPDATE master_options SET option_label='Kaji ulang' WHERE group_code='status' AND option_code='verification';
UPDATE master_options SET option_label='Sah',tone='success' WHERE group_code='status' AND option_code='completed';
UPDATE master_options SET option_label='Batal',tone='danger' WHERE group_code='status' AND option_code='cancelled';
UPDATE master_options SET option_label='Tidak sesuai',tone='warning' WHERE group_code='status' AND option_code='rejected';
INSERT IGNORE INTO master_options (group_code,option_code,option_label,sort_order,tone) VALUES
 ('status','valid','Sah',81,'success'),
 ('status','invalid','Batal',91,'danger'),
 ('status','returned','Dikembalikan',95,'warning'),
 ('status','not_suitable','Tidak sesuai',101,'warning'),
 ('status','closed','Selesai',120,'info');

UPDATE google_settings SET
  spreadsheet_id='1vRT5UnWhgJAE6btv4-5nu92X-dYsxO0CVAk77J8mGDc',
  sheet_range='Template!A3:F',
  order_spreadsheet_id='1W7gjvozo0O2VL3eiP0GGK7036At-14upy6xPCDaH5ZM',
  order_sheet_range='Template!A3:L',
  ttu_spreadsheet_id='14rX60Mmuo8vDsLvWoLCffoBs2DsY5mJy_jNLVRUnpzM',
  ttu_sah_range="'Template Sah'!A3:I",
  ttu_batal_range="'Template Batal'!A3:I",
  ttu_other_range="'Template Dikembalikan/Tidak sesuai'!A3:J",
  drive_folder_id='1xIJV2ZHLVecQXTj70L-Cx0MXHmX7aLuv'
WHERE id=1 AND (spreadsheet_id IS NULL OR spreadsheet_id='');
