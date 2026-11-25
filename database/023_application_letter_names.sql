USE metro_app;
UPDATE service_documents sd
JOIN service_requests sr ON sr.id=sd.service_request_id
SET sd.original_name=CONCAT('permohonan_',REPLACE(sr.request_no,'/','-'),'.pdf')
WHERE sd.document_type='application_letter' AND sr.submission_source='online';
