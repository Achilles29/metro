<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------------
| This file lets you re-map URI requests to specific controller functions.
|
| Typically there is a one-to-one relationship between a URL string
| and its corresponding controller class/method. The segments in a
| URL normally follow this pattern:
|
|	example.com/class/method/id/
|
| In some instances, however, you may want to remap this relationship
| so that a different class/function is called than the one
| corresponding to the URL.
|
| Please see the user guide for complete details:
|
|	https://codeigniter.com/userguide3/general/routing.html
|
| -------------------------------------------------------------------------
| RESERVED ROUTES
| -------------------------------------------------------------------------
|
| There are three reserved routes:
|
|	$route['default_controller'] = 'welcome';
|
| This route indicates which controller class should be loaded if the
| URI contains no data. In the above example, the "welcome" class
| would be loaded.
|
|	$route['404_override'] = 'errors/page_missing';
|
| This route will tell the Router which controller/method to use if those
| provided in the URL cannot be matched to a valid route.
|
|	$route['translate_uri_dashes'] = FALSE;
|
| This is not exactly a route, but allows you to automatically route
| controller and method names that contain dashes. '-' isn't a valid
| class or method name character, so it requires translation.
| When you set this option to TRUE, it will replace ALL dashes in the
| controller and method URI segments.
|
| Examples:	my-controller/index	-> my_controller/index
|		my-controller/my-method	-> my_controller/my_method
*/
$route['default_controller'] = 'dashboard';
$route['api/wilayah/desa'] = 'regions/villages';
$route['login'] = 'auth/login';
$route['masuk'] = 'auth/login';
$route['register'] = 'auth/register';
$route['logout'] = 'auth/logout';
$route['profil'] = 'auth/profile';
$route['profil-teknisi'] = 'technicians/profile';
$route['pelimpahan'] = 'technicians/connections';
$route['pelimpahan/undang'] = 'technicians/invite';
$route['pelimpahan/tanggapi/(:num)'] = 'technicians/respond/$1';
$route['uttp'] = 'registry/index';
$route['uttp/riwayat/(:num)'] = 'registry/history/$1';
$route['uttp/simpan'] = 'registry/save';
$route['uttp/simpan/(:num)'] = 'registry/save/$1';
$route['uttp/status/(:num)'] = 'registry/status/$1';
$route['uttp/hapus/(:num)'] = 'registry/delete/$1';
$route['verifikasi-telepon'] = 'auth/verify_phone';
$route['verifikasi-telepon/kirim-ulang'] = 'auth/resend_phone_code';
$route['petugas/pemohon/(:num)/kode-telepon'] = 'staff/phone_code/$1';
$route['ganti-kata-sandi'] = 'auth/change_password';
$route['permohonan'] = 'requests/index';
$route['permohonan/baru'] = 'requests/create';
$route['permohonan/(:num)'] = 'requests/show/$1';
$route['permohonan/(:num)/bukti'] = 'requests/receipt/$1';
$route['permohonan/(:num)/umpan-balik'] = 'requests/feedback/$1';
$route['permohonan/(:num)/perbaiki'] = 'requests/revise/$1';
$route['petugas'] = 'staff/index';
$route['petugas/permohonan'] = 'staff/queue';
$route['petugas/ctt'] = 'ctt/index';
$route['petugas/ctt/pinjam'] = 'ctt/borrow_bulk';
$route['petugas/peta'] = 'geo/index';
$route['petugas/permohonan/baru'] = 'requests/create';
$route['petugas/master/verifikasi'] = 'verification/criteria';
$route['petugas/permohonan/(:num)/kaji-ulang'] = 'verification/review/$1';
$route['petugas/permohonan/(:num)/kaji-ulang/ubah'] = 'verification/reopen_review/$1';
$route['petugas/permohonan/(:num)/kaji-ulang/batalkan'] = 'verification/cancel_review/$1';
$route['petugas/permohonan/(:num)/jadwal-tera'] = 'verification/schedule/$1';
$route['petugas/permohonan/(:num)/kaji-ulang/pdf'] = 'verification/combined_pdf/$1';
$route['petugas/foto/(:num)/hapus'] = 'verification/delete_photo/$1';
$route['petugas/ctt/(:num)/pinjam'] = 'verification/borrow_ctt/$1';
$route['petugas/ctt/(:num)/kembali'] = 'verification/return_ctt/$1';
$route['petugas/ctt/(:num)/hapus'] = 'verification/delete_ctt/$1';
$route['petugas/master/verifikasi/simpan'] = 'verification/save_criteria';
$route['petugas/master/verifikasi/simpan/(:num)'] = 'verification/save_criteria/$1';
$route['petugas/master/verifikasi/status/(:num)'] = 'verification/toggle_criteria/$1';
$route['petugas/integrasi'] = 'integrations/index';
$route['petugas/integrasi/simpan'] = 'integrations/save';
$route['petugas/integrasi/kredensial'] = 'integrations/credentials';
$route['petugas/integrasi/uji'] = 'integrations/test';
$route['petugas/integrasi/oauth-client'] = 'integrations/oauth_credentials';
$route['petugas/integrasi/google/hubungkan'] = 'integrations/oauth_start';
$route['petugas/integrasi/google/callback'] = 'integrations/oauth_callback';
$route['petugas/permohonan/(:num)/verifikasi/mulai'] = 'verification/start/$1';
$route['petugas/permohonan/(:num)/verifikasi/(:num)'] = 'verification/save_check/$1/$2';
$route['petugas/permohonan/(:num)/verifikasi/tetapkan'] = 'verification/finalize/$1';
$route['petugas/permohonan/(:num)/verifikasi/hasil'] = 'verification/pdf/$1';
$route['petugas/permohonan/(:num)/ctt/stok'] = 'verification/ctt_stock/$1';
$route['petugas/permohonan/(:num)/ctt'] = 'verification/add_ctt/$1';
$route['petugas/ctt/harian/(:num)/sinkronkan'] = 'verification/retry_ctt/$1';
$route['petugas/ctt/(:num)/sinkronkan'] = 'verification/retry_ctt_legacy/$1';
$route['petugas/permohonan/(:num)/foto'] = 'verification/photo/$1';
$route['petugas/foto/(:num)'] = 'verification/photo_file/$1';
$route['petugas/cerapan/(:num)/drive'] = 'verification/drive/$1';
$route['petugas/cerapan/(:num)/generate'] = 'verification/generate_cerapan/$1';
$route['petugas/permohonan/(:num)'] = 'staff/show/$1';
$route['petugas/laporan'] = 'staff/report';
$route['petugas/galeri-laporan'] = 'gallery/index';
$route['petugas/galeri-laporan/unduh'] = 'gallery/download';
$route['petugas/pemohon'] = 'staff/applicants';
$route['petugas/pemohon/tambah'] = 'staff/create_applicant';
$route['petugas/pemohon/ubah/(:num)'] = 'staff/update_applicant/$1';
$route['petugas/pemohon/status/(:num)'] = 'staff/toggle_applicant/$1';
$route['petugas/pemohon/hapus/(:num)'] = 'staff/delete_applicant/$1';
$route['petugas/aktivitas'] = 'staff/activity';
$route['petugas/permohonan/(:num)/sinkronkan-laporan'] = 'staff/sync_reports/$1';
$route['petugas/permohonan/(:num)/lanjutkan-arsip'] = 'staff/legacy_continue/$1';
$route['petugas/dokumen/(:num)/hapus'] = 'staff/delete_skhp/$1';
$route['petugas/pemohon/(:num)'] = 'staff/applicant/$1';
$route['staff/order-details/(:num)'] = 'staff/order_details/$1';
$route['staff/verify-order/(:num)'] = 'staff/verify_order/$1';
$route['petugas/master'] = 'masters/index';
$route['petugas/master/status/pilihan/(:any)/(:num)'] = 'masters/toggle_option/$1/$2';
$route['petugas/master/status/peran/(:num)'] = 'masters/toggle_role/$1';
$route['petugas/master/status/(:any)/(:num)'] = 'masters/toggle/$1/$2';
$route['petugas/master/hapus/pilihan/(:any)/(:num)'] = 'masters/delete_option/$1/$2';
$route['petugas/master/hapus/(:any)/(:num)'] = 'masters/delete/$1/$2';
$route['petugas/master/uttp'] = 'masters/types';
$route['petugas/master/kecamatan'] = 'masters/districts';
$route['petugas/master/desa'] = 'masters/villages';
$route['petugas/master/lokasi'] = 'masters/locations';
$route['petugas/master/peran'] = 'masters/roles';
$route['petugas/master/ctt/(:num)/riwayat'] = 'masters/ctt_history/$1';
$route['petugas/master/pilihan/(:any)'] = 'masters/options/$1';
$route['petugas/hak-akses'] = 'permissions/index';
$route['petugas/hak-akses/(:num)'] = 'permissions/save/$1';
$route['petugas/whatsapp'] = 'whatsapp/index';
$route['petugas/whatsapp/qr'] = 'whatsapp/qr';
$route['petugas/whatsapp/kontrol'] = 'whatsapp/control';
$route['petugas/whatsapp/template'] = 'whatsapp/template';
$route['petugas/whatsapp/ulang/(:num)'] = 'whatsapp/retry/$1';
$route['petugas/whatsapp/notifikasi/ulang/(:num)'] = 'whatsapp/retry_notification/$1';
$route['petugas/teknisi'] = 'technicians/admin';
$route['petugas/teknisi/nilai/(:num)'] = 'technicians/review_technician/$1';
$route['petugas/teknisi/pelimpahan/(:num)'] = 'technicians/review_connection/$1';
$route['petugas/teknisi/(:num)/kode-telepon'] = 'staff/phone_code/$1';
$route['petugas/akun'] = 'staff/users';
$route['petugas/akun/ubah/(:num)'] = 'staff/update_user/$1';
$route['petugas/akun/status/(:num)'] = 'staff/toggle_user/$1';
$route['petugas/akun/hapus/(:num)'] = 'staff/delete_user/$1';
$route['dokumen/(:num)'] = 'documents/download/$1';
$route['404_override'] = '';
$route['translate_uri_dashes'] = FALSE;
