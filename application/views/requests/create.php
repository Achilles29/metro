<div class="page-heading"><div><div class="eyebrow">Permohonan baru</div><h1>Ajukan tera / tera ulang</h1><p>Satu permohonan dapat memuat beberapa UTTP. Jumlah dipecah menjadi unit fisik untuk pelacakan.</p></div></div>
<form method="post" enctype="multipart/form-data" action="<?= site_url('permohonan/baru') ?>" id="request-form">
<?= form_hidden($this->security->get_csrf_token_name(), $this->security->get_csrf_hash()) ?>
<section class="card form-card"><div class="form-section-title"><span class="step-number">1</span><div><h2>Detail layanan</h2><p>Pilih jenis dan lokasi pelayanan.</p></div></div><div class="form-grid">
<label>Jenis layanan<select name="service_type" required><option value="tera">Tera</option><option value="tera_ulang">Tera ulang</option></select></label>
<label>Tempat pelayanan<select name="service_mode" required><option value="office">Di kantor UPT</option><option value="field">Di lokasi pemohon</option></select></label>
<label class="span-2">Alamat pelayanan<input name="service_address" required value="<?= e($profile->address) ?>" placeholder="Alamat lengkap tempat UTTP diperiksa"></label>
</div></section>
<section class="section"><div class="section-heading"><div><div class="eyebrow">Data alat</div><h2>UTTP yang diajukan</h2></div><button class="button outline" type="button" id="add-uttp">+ Tambahkan UTTP</button></div><div id="uttp-list"></div></section>
<section class="card form-card"><div class="form-section-title"><span class="step-number">2</span><div><h2>Surat permohonan</h2><p>Unggah PDF maksimal 5 MB.</p></div></div>
<label>Surat permohonan (PDF)<input type="file" name="application_letter" accept="application/pdf,.pdf" required></label>
<label>Catatan tambahan <span class="optional">opsional</span><textarea name="note" rows="3" maxlength="1000"></textarea></label></section>
<div class="submit-bar"><div><strong>Periksa data sebelum mengirim</strong><small>Nomor permohonan dibuat setelah berhasil dikirim.</small></div><button class="button gold" type="submit">Kirim permohonan →</button></div>
</form>
<template id="uttp-template"><article class="card uttp-card"><div class="uttp-head"><h3>UTTP <span class="uttp-number"></span></h3><button type="button" class="text-button remove-uttp">Hapus</button></div><div class="form-grid">
<label>Nama pemilik<input name="owner_name[]" required minlength="3" maxlength="190" placeholder="Nama pemilik UTTP"></label>
<label>Jenis UTTP<select name="uttp_type_id[]" required><option value="">Pilih jenis</option><?php foreach ($types as $t): ?><option value="<?= (int) $t->id ?>"><?= e($t->type_name) ?></option><?php endforeach; ?></select></label>
<label>Merek<input name="brand[]" maxlength="120" placeholder="Contoh: Merek alat"></label>
<label>Tipe<input name="type_name[]" maxlength="120"></label>
<label>Model<input name="model_name[]" maxlength="120"></label>
<label>Kapasitas<input name="capacity[]" maxlength="120" placeholder="Contoh: 30 kg"></label>
<label>Jumlah unit<input type="number" name="quantity[]" min="1" max="100" value="1" required></label>
</div></article></template>
<script src="<?= base_url('assets/request-form.js') ?>" defer></script>
