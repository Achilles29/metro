-- Kode booking publik yang tidak berurutan untuk melacak status permohonan.
USE metro_app;
ALTER TABLE service_requests
 ADD COLUMN booking_code CHAR(6) NULL AFTER request_no,
 ADD UNIQUE KEY uq_service_requests_booking_code (booking_code);

CREATE TABLE booking_lookup_attempts (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 ip_address VARCHAR(45) NOT NULL,
 attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY ix_booking_lookup_ip_time (ip_address,attempted_at),
 KEY ix_booking_lookup_time (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
