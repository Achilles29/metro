<div class="auth-wrap"><div class="card auth-card"><div class="eyebrow">Akun pemohon</div><h1>Daftar akun</h1><p class="muted">Setelah mendaftar, lengkapi alamat dan penanggung jawab.</p>
<form method="post" action="<?= site_url('register') ?>"><?= form_hidden($this->security->get_csrf_token_name(), $this->security->get_csrf_hash()) ?>
<label>Nama lengkap<input name="full_name" minlength="3" maxlength="160" required value="<?= e($this->input->post('full_name', TRUE)) ?>"></label>
<label>Email<input type="email" name="email" autocomplete="email" required value="<?= e($this->input->post('email', TRUE)) ?>"></label>
<label>Nomor telepon <span class="optional">opsional</span><input name="phone" maxlength="32" value="<?= e($this->input->post('phone', TRUE)) ?>"></label>
<label>Kata sandi <small>minimal 12 karakter</small><input type="password" name="password" minlength="12" maxlength="72" autocomplete="new-password" required></label>
<button class="button primary full" type="submit">Buat akun</button></form>
<p class="muted small">Sudah terdaftar? <a href="<?= site_url('login') ?>">Masuk</a>.</p></div></div>
