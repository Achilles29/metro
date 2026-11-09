USE metro_app;
CREATE TABLE IF NOT EXISTS login_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_login_attempts_email_time (email, attempted_at),
  KEY ix_login_attempts_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO uttp_types (type_code, type_name) VALUES
('TIMBANGAN','Timbangan'), ('ANAK_TIMBANGAN','Anak timbangan'),
('METER_AIR','Meter air'), ('METER_LISTRIK','Meter listrik'),
('POMPA_UKUR','Pompa ukur BBM'), ('TAKARAN','Takaran'),
('LAINNYA','UTTP lainnya');
