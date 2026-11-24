USE metro_app;
ALTER TABLE service_requests ADD COLUMN penera_name VARCHAR(160) NULL AFTER applicant_note;
ALTER TABLE service_requests ADD COLUMN ctt_code VARCHAR(12) NULL AFTER penera_name;
ALTER TABLE service_requests ADD COLUMN standard_equipment VARCHAR(190) NULL AFTER ctt_code;
UPDATE uttp_types SET is_active=0;
INSERT INTO uttp_types (type_code,type_name,is_active) VALUES
('NERACA','Neraca',1),('DACIN','Dacin',1),('TIMBANGAN_ELEKTRONIK','Timbangan elektronik',1),
('TIMBANGAN_PEGAS','Timbangan pegas',1),('TIMBANGAN_SENTISIMAL','Timbangan sentisimal',1),
('TIMBANGAN_MILISIMAL','Timbangan milisimal',1),('TIMBANGAN_DESIMAL','Timbangan desimal',1),
('TIMBANGAN_MEJA','Timbangan meja',1),('TIMBANGAN_BOBOT_INGSUT','Timbangan bobot ingsut',1),
('TIMBANGAN_CEPAT','Timbangan cepat',1),('TIMBANGAN_JEMBATAN','Timbangan jembatan',1),
('TIMBANGAN_AMP','Timbangan pada AMP',1),('TIMBANGAN_BATCHING','Timbangan pada batching plant',1),
('TIMBANGAN_PENGECEK','Timbangan pengecek dan penyortir',1),('WIM','Weighing in motion (WIM)',1),
('ANAK_TIMBANGAN_PELENGKAP','Anak Timbangan Pelengkap',1),('PUBBM','Pompa ukur BBM (PUBBM)',1),
('TAKARAN_KERING','Takaran kering',1),('TAKARAN_BASAH','Takaran basah',1),('METER_KAYU','Meter kayu',1)
ON DUPLICATE KEY UPDATE type_name=VALUES(type_name),is_active=1;
