-- Simpan rentang laporan order untuk memperbarui nomor SKHP tanpa menggandakan baris.
ALTER TABLE request_report_syncs ADD COLUMN remote_range VARCHAR(190) NULL AFTER row_count;
