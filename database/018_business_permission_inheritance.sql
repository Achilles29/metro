-- Pelaku usaha mewarisi izin Pemohon; peran tambahan hanya mengatur pelimpahan.
USE metro_app;
DELETE rp FROM role_permissions rp
JOIN roles r ON r.id=rp.role_id
JOIN permissions p ON p.id=rp.permission_id
WHERE r.role_code='business_actor'
  AND p.permission_code LIKE 'self.%'
  AND p.permission_code NOT LIKE 'self.delegation.%';
