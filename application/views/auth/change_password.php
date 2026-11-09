<div class="auth-wrap"><div class="card auth-card"><div class="eyebrow">Keamanan akun</div><h1>Ganti kata sandi</h1><p class="muted">Gunakan kata sandi baru yang belum digunakan di tempat lain.</p>
<form method="post" action="<?= site_url('ganti-kata-sandi') ?>"><?= form_hidden($this->security->get_csrf_token_name(), $this->security->get_csrf_hash()) ?>
<label>Kata sandi saat ini<input type="password" name="old_password" autocomplete="current-password" required></label>
<label>Kata sandi baru<input type="password" name="new_password" minlength="12" maxlength="72" autocomplete="new-password" required></label>
<label>Ulangi kata sandi baru<input type="password" name="confirm_password" minlength="12" maxlength="72" autocomplete="new-password" required></label>
<button class="button primary full" type="submit">Simpan kata sandi baru</button></form></div></div>
