-- Seed awal unsur kaji ulang menurut PDF 13-11-2026; perubahan berikutnya lewat master.
UPDATE verification_checks SET is_active=0 WHERE label IN ('Identitas dan kewenangan pemohon','Kelengkapan surat permohonan','Kesesuaian data UTTP');
INSERT INTO verification_checks (label,sort_order,is_active) SELECT 'Kesesuaian ruang lingkup',1,1 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM verification_checks WHERE label='Kesesuaian ruang lingkup');
INSERT INTO verification_checks (label,sort_order,is_active) SELECT 'Kesesuaian metoda',2,1 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM verification_checks WHERE label='Kesesuaian metoda');
INSERT INTO verification_checks (label,sort_order,is_active) SELECT 'Kesiapan UPT Metrologi Legal',3,1 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM verification_checks WHERE label='Kesiapan UPT Metrologi Legal');
INSERT INTO verification_checks (label,sort_order,is_active) SELECT 'Izin tipe (label kuning)',4,1 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM verification_checks WHERE label='Izin tipe (label kuning)');
INSERT INTO verification_checks (label,sort_order,is_active) SELECT 'Kesiapan SDM',5,1 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM verification_checks WHERE label='Kesiapan SDM');
INSERT INTO verification_checks (label,sort_order,is_active) SELECT 'Visual cek',6,1 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM verification_checks WHERE label='Visual cek');
INSERT INTO verification_checks (label,sort_order,is_active) SELECT 'Waktu',7,1 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM verification_checks WHERE label='Waktu');
