<?php
$roles = $this->metro->roles($current_user->id);
$role_row = $this->db->select('r.role_name')->from('roles r')->join('user_roles ur','ur.role_id=r.id')
    ->where('ur.user_id',$current_user->id)->where('r.role_code !=','applicant')
    ->order_by('r.sort_order')->limit(1)->get()->row();
$role_name = $role_row ? $role_row->role_name : 'Petugas';
$active = 'dashboard';
if (in_array($body_view, array('staff/index','staff/workflow','requests/create','staff/choose_applicant','requests/show'), TRUE)) $active = 'requests';
if ($body_view === 'staff/ctt') $active = 'ctt';
if ($body_view === 'staff/map') $active = 'map';
if ($body_view === 'staff/integrations') $active = 'integrations';
if ($body_view === 'staff/report') $active = 'report';
if ($body_view === 'staff/gallery') $active = 'gallery';
if ($body_view === 'staff/users') $active = 'users';
if ($body_view === 'staff/permissions') $active = 'permissions';
if ($body_view === 'staff/whatsapp') $active = 'whatsapp';
if ($body_view === 'staff/technicians') $active = 'technicians';
if (strpos($body_view, 'staff/master/') === 0) $active = 'master';
if (in_array($body_view, array('staff/applicants','staff/applicant','staff/phone_code'), TRUE)) $active = 'applicants';
if ($body_view === 'staff/activity') $active = 'activity';
if ($body_view === 'auth/change_password') $active = 'security';
$menu = array(
    array('key'=>'dashboard','url'=>'petugas','label'=>'Dashboard','icon'=>'dashboard'),
    array('key'=>'requests','url'=>'petugas/permohonan','label'=>'Permohonan','icon'=>'inbox')
);
if ($this->metro->can($current_user->id,'requests.view_all')) $menu[] = array('key'=>'ctt','url'=>'petugas/ctt','label'=>'Operasional CTT','icon'=>'ctt');
if ($this->metro->can($current_user->id,'activity.view')) $menu[] = array('key'=>'activity','url'=>'petugas/aktivitas','label'=>'Log aktivitas','icon'=>'chart');
if ($this->metro->can($current_user->id,'reports.view')) $menu[] = array('key'=>'report','url'=>'petugas/laporan','label'=>'Laporan layanan','icon'=>'chart');
if ($this->metro->can($current_user->id,'reports.view') && $this->metro->can($current_user->id,'requests.view_all') && $this->metro->can($current_user->id,'requests.view'))
    $menu[] = array('key'=>'gallery','url'=>'petugas/galeri-laporan','label'=>'Galeri laporan','icon'=>'gallery');
if ($this->metro->can($current_user->id,'applicants.manage')) $menu[] = array('key'=>'applicants','url'=>'petugas/pemohon','label'=>'Data pemohon','icon'=>'person');
if ($this->metro->can($current_user->id,'applicants.manage') || $this->metro->can($current_user->id,'requests.view_all')) $menu[] = array('key'=>'map','url'=>'petugas/peta','label'=>'Peta GIS','icon'=>'chart');
if ($this->metro->can($current_user->id,'masters.manage')) $menu[] = array('key'=>'master','url'=>'petugas/master','label'=>'Master data','icon'=>'database');
$master_menu = array(array('url'=>'petugas/master','label'=>'Ringkasan'),array('url'=>'petugas/master/verifikasi','label'=>'Unsur kaji ulang'),array('url'=>'petugas/master/uttp','label'=>'Jenis UTTP'),
    array('url'=>'petugas/master/kecamatan','label'=>'Kecamatan'),array('url'=>'petugas/master/desa','label'=>'Desa / kelurahan'),
    array('url'=>'petugas/master/lokasi','label'=>'Lokasi dan alamat'),array('url'=>'petugas/master/peran','label'=>'Peran pengguna'));
if ($this->metro->can($current_user->id,'masters.manage')) foreach ($this->master->groups() as $master_group)
    $master_menu[] = array('url'=>'petugas/master/pilihan/'.$master_group->group_code,'label'=>$master_group->group_name);
if ($this->metro->can($current_user->id,'staff.manage')) $menu[] = array('key'=>'users','url'=>'petugas/akun','label'=>'Akun petugas','icon'=>'users');
if ($this->metro->can($current_user->id,'technicians.manage')) $menu[] = array('key'=>'technicians','url'=>'petugas/teknisi','label'=>'Teknisi eksternal','icon'=>'users');
if (in_array('administrator',$roles,TRUE)) $menu[] = array('key'=>'permissions','url'=>'petugas/hak-akses','label'=>'Hak akses','icon'=>'lock');
if (in_array('administrator',$roles,TRUE)) $menu[] = array('key'=>'whatsapp','url'=>'petugas/whatsapp','label'=>'WhatsApp','icon'=>'message');
if (in_array('administrator',$roles,TRUE)) $menu[] = array('key'=>'integrations','url'=>'petugas/integrasi','label'=>'Integrasi Google','icon'=>'external');
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'Ruang kerja') ?> · Setimbang Rembang</title>
<link rel="icon" type="image/png" sizes="32x32" href="<?= base_url('assets/favicon-rembang-32.png?v=2') ?>"><link rel="shortcut icon" href="<?= base_url('assets/favicon-rembang-v2.ico?v=2') ?>"><link rel="apple-touch-icon" sizes="180x180" href="<?= base_url('assets/favicon-rembang-180.png?v=2') ?>"><link rel="stylesheet" href="<?= base_url('assets/metro.css?v=4') ?>"><link rel="stylesheet" href="<?= base_url('assets/revisi.css?v=5') ?>"><link rel="stylesheet" href="<?= base_url('assets/admin.css?v=20') ?>">
<script src="<?= base_url('assets/admin.js?v=5') ?>" defer></script><script src="<?= base_url('assets/revisi.js') ?>" defer></script></head>
<body class="admin-body">
<div class="admin-shell">
<button class="sidebar-backdrop" type="button" data-sidebar-close aria-label="Tutup menu"></button>
<aside class="admin-sidebar" id="admin-sidebar" aria-label="Navigasi petugas">
<a class="admin-brand" href="<?= site_url('petugas') ?>"><img class="admin-brand-logo" src="<?= base_url('assets/logo-rembang-transparan.png?v=2') ?>" alt="Logo Kabupaten Rembang"><span><strong>Setimbang</strong><small>METROLOGI LEGAL</small></span></a>
<div class="admin-sidebar-caption">RUANG KERJA</div>
<nav class="admin-nav" aria-label="Menu utama">
<?php foreach ($menu as $item): ?><?php if ($item['key']==='master'): ?>
<details class="admin-nav-group"<?= $active==='master'?' open':'' ?>><summary class="admin-nav-link<?= $active==='master'?' active':'' ?>"><?= admin_icon('database') ?><span>Master data</span><span class="admin-nav-caret"><?= admin_icon('chevron') ?></span></summary>
<div class="admin-subnav"><?php foreach ($master_menu as $subitem): $selected=$this->uri->uri_string()===$subitem['url']; ?><a href="<?= site_url($subitem['url']) ?>" class="admin-subnav-link<?= $selected?' active':'' ?>"<?= $selected?' aria-current="page"':'' ?>><?= e($subitem['label']) ?></a><?php endforeach; ?></div></details>
<?php else: ?><a href="<?= site_url($item['url']) ?>" class="admin-nav-link<?= $active === $item['key'] ? ' active' : '' ?>"<?= $active === $item['key'] ? ' aria-current="page"' : '' ?>><?= admin_icon($item['icon']) ?><span><?= e($item['label']) ?></span><?php if ($active === $item['key']): ?><span class="active-indicator"></span><?php endif; ?></a><?php endif; ?><?php endforeach; ?>
<div class="admin-nav-divider"></div>
<a href="<?= site_url('ganti-kata-sandi') ?>" class="admin-nav-link<?= $active === 'security' ? ' active' : '' ?>"<?= $active === 'security' ? ' aria-current="page"' : '' ?>><?= admin_icon('lock') ?><span>Keamanan akun</span></a>
<a href="<?= site_url() ?>" class="admin-nav-link"><?= admin_icon('external') ?><span>Lihat portal</span></a>
</nav>
<div class="admin-sidebar-bottom"><div class="admin-user"><span class="admin-avatar"><?= e(mb_strtoupper(mb_substr($current_user->full_name, 0, 1))) ?></span><span class="admin-user-copy"><strong><?= e($current_user->full_name) ?></strong><small><?= e($role_name) ?></small></span></div>
<form method="post" action="<?= site_url('logout') ?>"><?= form_hidden($this->security->get_csrf_token_name(), $this->security->get_csrf_hash()) ?><button class="admin-logout" type="submit"><?= admin_icon('logout') ?> Keluar</button></form></div>
</aside>
<div class="admin-workspace">
<header class="admin-header"><div class="admin-header-left"><button class="sidebar-toggle" type="button" data-sidebar-toggle aria-controls="admin-sidebar" aria-expanded="false" aria-label="Buka menu"><?= admin_icon('menu') ?></button><div class="admin-breadcrumb"><span>Ruang kerja</span><?= admin_icon('chevron') ?><strong><?= e($title ?? 'Dashboard') ?></strong></div></div><div class="admin-header-right"><span class="admin-today"><?= e(tanggal_id(date('Y-m-d'))) ?></span><span class="admin-header-avatar" title="<?= e($current_user->full_name) ?>"><?= e(mb_strtoupper(mb_substr($current_user->full_name, 0, 1))) ?></span></div></header>
<main class="admin-main"><div class="admin-content">
<?php if (!empty($flash)): ?><div class="alert success" role="status"><?= e($flash) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert danger" role="alert"><?= e($error) ?></div><?php endif; ?>
<?php $this->load->view($body_view); ?>
<footer class="admin-footer">Setimbang · UPT Metrologi Legal Kabupaten Rembang <span>Data layanan hanya untuk petugas berwenang.</span></footer>
</div></main>
</div></div></body></html>
