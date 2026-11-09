<div class="page-heading"><div><div class="eyebrow">Akun pemohon</div><h1>Profil pemohon</h1><p>Data ini membantu petugas menghubungi Anda dan digunakan pada permohonan berikutnya.</p></div></div>
<div class="card form-card"><form method="post" action="<?= site_url('profil') ?>"><?= form_hidden($this->security->get_csrf_token_name(), $this->security->get_csrf_hash()) ?>
<div class="form-grid"><label>Jenis pemohon<select name="profile_kind"><option value="individual" <?= $profile->profile_kind === 'individual' ? 'selected' : '' ?>>Perorangan</option><option value="organization" <?= $profile->profile_kind === 'organization' ? 'selected' : '' ?>>Instansi / perusahaan</option></select></label>
<label>Nama instansi / perusahaan <span class="optional">bila ada</span><input name="organization_name" maxlength="190" value="<?= e($profile->organization_name) ?>"></label>
<label>Nama penanggung jawab<input name="contact_person" required minlength="3" value="<?= e($profile->contact_person) ?>"></label>
<label>Kecamatan<input name="district" value="<?= e($profile->district) ?>"></label>
<label>Desa / kelurahan<input name="village" value="<?= e($profile->village) ?>"></label>
<label class="span-2">Alamat lengkap<textarea name="address" required minlength="10" rows="3"><?= e($profile->address) ?></textarea></label></div>
<div class="form-actions"><button class="button primary" type="submit">Simpan profil</button><a class="button outline" href="<?= site_url('permohonan') ?>">Kembali</a></div></form></div>
