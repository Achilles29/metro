<div class="auth-wrap"><div class="card auth-card"><div class="eyebrow">Ruang layanan</div><h1>Masuk ke Metro</h1><p class="muted">Pantau permohonan dan dokumen Anda dalam satu tempat.</p>
<form method="post" action="<?= site_url('login') ?>"><?= form_hidden($this->security->get_csrf_token_name(), $this->security->get_csrf_hash()) ?>
<label>Email<input type="email" name="email" autocomplete="email" required value="<?= e($this->input->post('email', TRUE)) ?>"></label>
<label>Kata sandi<input type="password" name="password" autocomplete="current-password" required></label>
<button class="button primary full" type="submit">Masuk</button></form>
<p class="muted small">Belum punya akun? <a href="<?= site_url('register') ?>">Daftar sebagai pemohon</a>.</p></div></div>
