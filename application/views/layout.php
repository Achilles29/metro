<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title ?? 'Setimbang') ?> · Setimbang Rembang</title>
<link rel="icon" type="image/png" sizes="32x32" href="<?= base_url('assets/favicon-rembang-32.png?v=2') ?>"><link rel="shortcut icon" href="<?= base_url('assets/favicon-rembang-v2.ico?v=2') ?>"><link rel="apple-touch-icon" sizes="180x180" href="<?= base_url('assets/favicon-rembang-180.png?v=2') ?>"><link rel="stylesheet" href="<?= base_url('assets/metro.css?v=4') ?>"><link rel="stylesheet" href="<?= base_url('assets/revisi.css?v=5') ?>"><?php if (in_array($body_view,array('auth/profile','requests/create','requests/registry'),TRUE)): ?><link rel="stylesheet" href="<?= base_url('assets/leaflet/leaflet.css') ?>"><?php endif; ?><?php if ($body_view==='requests/registry'): ?><link rel="stylesheet" href="<?= base_url('assets/admin.css?v=6') ?>"><?php endif; ?><script src="<?= base_url('assets/revisi.js') ?>" defer></script></head>
<body class="<?= $body_view === 'dashboard/index' ? 'landing-body' : '' ?>">
<header class="topbar"><div class="container topbar-inner">
<a class="brand" href="<?= site_url() ?>"><img class="brand-logo" src="<?= base_url('assets/logo-rembang-transparan.png?v=2') ?>" alt="Logo Kabupaten Rembang"><span><strong>Setimbang</strong><small>Metrologi Legal · Rembang</small></span></a>
<nav class="nav" aria-label="Navigasi utama">
<a href="<?= site_url() ?>" <?= $this->uri->uri_string() === '' ? 'class="active" aria-current="page"' : '' ?>>Beranda</a>
<?php if ($current_user): ?>
<a href="<?= site_url($this->metro->is_staff($current_user->id) ? 'petugas' : 'permohonan') ?>" <?= in_array($this->uri->segment(1),array('permohonan','petugas'),TRUE) ? 'class="active" aria-current="page"' : '' ?>><?= $this->metro->is_staff($current_user->id) ? 'Antrean' : 'Permohonan saya' ?></a>
<?php if (!$this->metro->is_staff($current_user->id)): ?><a href="<?= site_url($current_user->account_type==='technician'?'profil-teknisi':'profil') ?>" <?= in_array($this->uri->segment(1),array('profil','profil-teknisi'),TRUE) ? 'class="active" aria-current="page"' : '' ?>>Profil</a><?php endif; ?>
<?php if ($current_user->account_type==='applicant'): ?><a href="<?= site_url('uttp') ?>" <?= $this->uri->segment(1)==='uttp'?'class="active" aria-current="page"':'' ?>>UTTP saya</a><?php endif; ?>
<?php if (in_array($current_user->account_type,array('applicant','technician'),TRUE)): ?><a href="<?= site_url('pelimpahan') ?>" <?= $this->uri->segment(1)==='pelimpahan'?'class="active" aria-current="page"':'' ?>>Pelimpahan</a><?php endif; ?>
<?php if ($this->metro->can($current_user->id,'staff.manage')): ?><a href="<?= site_url('petugas/akun') ?>">Akun petugas</a><?php endif; ?>
<a href="<?= site_url('ganti-kata-sandi') ?>">Kata sandi</a>
<form method="post" action="<?= site_url('logout') ?>" class="inline"><?= form_hidden($this->security->get_csrf_token_name(), $this->security->get_csrf_hash()) ?><button class="nav-button" type="submit">Keluar</button></form>
<?php else: ?><a href="<?= site_url() ?>#tentang">Tentang Kami</a><a href="<?= site_url() ?>#layanan">Layanan</a><a href="<?= site_url() ?>#berita">Berita</a><a href="<?= site_url('login') ?>" <?= $this->uri->segment(1) === 'login' ? 'class="active" aria-current="page"' : '' ?>>Masuk</a><a class="nav-cta" href="<?= site_url('register') ?>">Daftar</a><?php endif; ?>
</nav></div></header>
<main class="container main">
<?php if (!empty($flash)): ?><div class="alert success" role="status"><?= e($flash) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert danger" role="alert"><?= e($error) ?></div><?php endif; ?>
<?php $this->load->view($body_view); ?>
</main>
<footer class="footer <?= $body_view === 'dashboard/index' ? 'landing-footer' : '' ?>"><div class="container"><div><strong>Setimbang</strong><small>Sistem pendaftaran tera dan informasi pengawasan Kabupaten Rembang</small></div><div><strong>Informasi</strong><a href="<?= site_url() ?>#tentang">Tentang Kami</a><a href="<?= site_url() ?>#layanan">Layanan</a></div><div><strong>Akses cepat</strong><a href="<?= site_url('login') ?>">Masuk</a><a href="<?= site_url('register') ?>">Daftar akun</a></div></div></footer>
</body></html>
