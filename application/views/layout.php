<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title ?? 'Metro') ?> · Metro Rembang</title>
<link rel="stylesheet" href="<?= base_url('assets/metro.css') ?>"></head>
<body>
<header class="topbar"><div class="container topbar-inner">
<a class="brand" href="<?= site_url() ?>"><span class="brand-mark">M</span><span><strong>METRO</strong><small>UPT Metrologi Legal · Rembang</small></span></a>
<nav class="nav">
<a href="<?= site_url() ?>">Beranda</a>
<?php if ($current_user): ?>
<a href="<?= site_url($this->metro->is_staff($current_user->id) ? 'petugas' : 'permohonan') ?>"><?= $this->metro->is_staff($current_user->id) ? 'Antrean' : 'Permohonan saya' ?></a>
<?php if (!$this->metro->is_staff($current_user->id)): ?><a href="<?= site_url('profil') ?>">Profil</a><?php endif; ?>
<?php if ($this->metro->has_role($current_user->id, array('administrator'))): ?><a href="<?= site_url('petugas/akun') ?>">Akun petugas</a><?php endif; ?>
<a href="<?= site_url('ganti-kata-sandi') ?>">Kata sandi</a>
<form method="post" action="<?= site_url('logout') ?>" class="inline"><?= form_hidden($this->security->get_csrf_token_name(), $this->security->get_csrf_hash()) ?><button class="nav-button" type="submit">Keluar</button></form>
<?php else: ?><a href="<?= site_url('login') ?>">Masuk</a><a class="nav-cta" href="<?= site_url('register') ?>">Daftar</a><?php endif; ?>
</nav></div></header>
<main class="container main">
<?php if (!empty($flash)): ?><div class="alert success" role="status"><?= e($flash) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert danger" role="alert"><?= e($error) ?></div><?php endif; ?>
<?php $this->load->view($body_view); ?>
</main>
<footer class="footer"><div class="container"><span>Metro · Layanan Metrologi Legal Kabupaten Rembang</span><span>Informasi proses dan dokumen tersedia melalui akun Anda.</span></div></footer>
</body></html>
