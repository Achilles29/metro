<?php
$role_labels = array('administrator'=>'Administrator', 'coordinator'=>'Koordinator', 'verifier'=>'Verifikator', 'technician'=>'Petugas teknis', 'issuer'=>'Penerbit');
$roles = $this->metro->roles($current_user->id);
$role_name = 'Petugas';
foreach ($role_labels as $code => $label) if (in_array($code, $roles, TRUE)) { $role_name = $label; break; }
$active = 'dashboard';
if (in_array($body_view, array('staff/index','staff/show','requests/show'), TRUE)) $active = 'requests';
if ($body_view === 'staff/report') $active = 'report';
if ($body_view === 'staff/users') $active = 'users';
if ($body_view === 'staff/applicants') $active = 'applicants';
if ($body_view === 'auth/change_password') $active = 'security';
$menu = array(
    array('key'=>'dashboard','url'=>'petugas','label'=>'Dashboard','icon'=>'dashboard'),
    array('key'=>'requests','url'=>'petugas/permohonan','label'=>'Permohonan','icon'=>'inbox')
);
if (array_intersect($roles, array('administrator','coordinator'))) $menu[] = array('key'=>'report','url'=>'petugas/laporan','label'=>'Laporan layanan','icon'=>'chart');
if (in_array('administrator', $roles, TRUE)) $menu[] = array('key'=>'applicants','url'=>'petugas/pemohon','label'=>'Data pemohon','icon'=>'person');
if (in_array('administrator', $roles, TRUE)) $menu[] = array('key'=>'users','url'=>'petugas/akun','label'=>'Akun petugas','icon'=>'users');
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'Ruang kerja') ?> · Metro Rembang</title>
<link rel="stylesheet" href="<?= base_url('assets/metro.css') ?>"><link rel="stylesheet" href="<?= base_url('assets/admin.css') ?>">
<script src="<?= base_url('assets/admin.js') ?>" defer></script></head>
<body class="admin-body">
<div class="admin-shell">
<button class="sidebar-backdrop" type="button" data-sidebar-close aria-label="Tutup menu"></button>
<aside class="admin-sidebar" id="admin-sidebar" aria-label="Navigasi petugas">
<a class="admin-brand" href="<?= site_url('petugas') ?>"><span class="admin-brand-mark">M</span><span><strong>METRO</strong><small>METROLOGI LEGAL</small></span></a>
<div class="admin-sidebar-caption">RUANG KERJA</div>
<nav class="admin-nav" aria-label="Menu utama">
<?php foreach ($menu as $item): ?><a href="<?= site_url($item['url']) ?>" class="admin-nav-link<?= $active === $item['key'] ? ' active' : '' ?>"<?= $active === $item['key'] ? ' aria-current="page"' : '' ?>><?= admin_icon($item['icon']) ?><span><?= e($item['label']) ?></span><?php if ($active === $item['key']): ?><span class="active-indicator"></span><?php endif; ?></a><?php endforeach; ?>
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
<footer class="admin-footer">Metro · UPT Metrologi Legal Kabupaten Rembang <span>Data layanan hanya untuk petugas berwenang.</span></footer>
</div></main>
</div></div></body></html>
