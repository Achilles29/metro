<?php
$permissions = array(
    'verification' => array('verifier','administrator'), 'revision' => array('verifier','administrator'),
    'scheduled' => array('coordinator','administrator'), 'testing' => array('technician','administrator'),
    'review' => array('technician','issuer','administrator'), 'completed' => array('issuer','administrator'),
    'cancelled' => array('coordinator','administrator'), 'rejected' => array('verifier','issuer','administrator')
);
$transitions = array(
    'submitted' => array('verification'=>'Mulai verifikasi','cancelled'=>'Batalkan'),
    'verification' => array('revision'=>'Minta perbaikan','scheduled'=>'Setujui & jadwalkan','rejected'=>'Tolak'),
    'revision' => array('verification'=>'Verifikasi ulang','rejected'=>'Tolak'),
    'scheduled' => array('testing'=>'Mulai pengujian','cancelled'=>'Batalkan'),
    'testing' => array('review'=>'Kirim pemeriksaan hasil','cancelled'=>'Batalkan'),
    'review' => array('testing'=>'Kembalikan ke pengujian','completed'=>'Selesaikan','rejected'=>'Tolak')
);
?>
<div class="page-heading"><div><a class="back-link" href="<?= site_url('petugas/permohonan') ?>">← Antrean permohonan</a><div class="eyebrow">Detail kerja</div><h1><?= e($request->request_no) ?></h1><p><?= e($request->full_name) ?> · <?= e($request->email) ?> · <?= e($request->phone) ?></p></div><a class="button outline" href="<?= site_url('permohonan/' . $request->id . '/bukti') ?>">↓ Bukti PDF</a></div>
<div class="detail-grid"><div>
<section class="card detail-card"><div class="section-heading compact"><div><div class="eyebrow">Unit UTTP</div><h2>Proses per unit</h2></div><span class="badge <?= status_class($request->request_status) ?>"><?= e(status_label($request->request_status)) ?></span></div>
<?php foreach ($items as $item):
    $assigned_name = NULL;
    foreach ($assignments as $assignment) if ((int) $assignment->request_item_id === (int) $item->id) $assigned_name = $assignment->full_name;
    $available = array();
    foreach ($transitions[$item->item_status] ?? array() as $code => $label) {
        if (!array_intersect($roles, $permissions[$code])) continue;
        if (in_array('technician', $roles, TRUE) && !array_intersect($roles, array('administrator','issuer'))
            && in_array($code, array('testing','review'), TRUE) && !$this->metro->assigned($item->id, $current_user->id)) continue;
        $available[$code] = $label;
    }
?><article class="staff-unit">
<div class="unit-head"><div><strong>Unit #<?= (int) $item->unit_sequence ?> · <?= e($item->uttp_name) ?></strong><small><?= e($item->owner_name) ?> · <?= e($item->brand) ?> · <?= e($item->type_name) ?> · <?= e($item->model_name) ?> · <?= e($item->capacity) ?></small><?php if ($assigned_name): ?><small>Petugas teknis: <?= e($assigned_name) ?></small><?php endif; ?></div><span class="badge <?= status_class($item->item_status) ?>"><?= e(status_label($item->item_status)) ?></span></div>
<?php if ($available): ?><form method="post" action="<?= site_url('staff/status/' . $item->id) ?>" class="unit-form"><?= form_hidden($this->security->get_csrf_token_name(), $this->security->get_csrf_hash()) ?><div class="form-grid"><label>Status berikutnya<select name="status" required><option value="">Pilih tindakan</option><?php foreach ($available as $code=>$label): ?><option value="<?= e($code) ?>"><?= e($label) ?></option><?php endforeach; ?></select></label><label>Catatan / alasan<input name="note" maxlength="1000" placeholder="Wajib untuk perbaikan, penolakan, pembatalan"></label></div><button class="button primary small-button" type="submit">Simpan status</button></form><?php endif; ?>
<?php if (array_intersect($roles, array('coordinator','administrator')) && in_array($item->item_status, array('scheduled','testing','review'), TRUE) && $technicians): ?><form method="post" action="<?= site_url('staff/assign/' . $item->id) ?>" class="unit-form"><?= form_hidden($this->security->get_csrf_token_name(), $this->security->get_csrf_hash()) ?><div class="form-grid"><label>Tugaskan petugas teknis<select name="assigned_to" required><option value="">Pilih petugas</option><?php foreach ($technicians as $tech): ?><option value="<?= (int) $tech->id ?>"><?= e($tech->full_name) ?></option><?php endforeach; ?></select></label><label>Catatan tugas<input name="note" maxlength="1000"></label></div><button class="button outline small-button" type="submit">Simpan penugasan</button></form><?php endif; ?>
<div class="upload-grid">
<?php if (array_intersect($roles, array('technician','administrator')) && (!in_array('technician',$roles,TRUE) || in_array('administrator',$roles,TRUE) || $this->metro->assigned($item->id,$current_user->id))): ?><form method="post" enctype="multipart/form-data" action="<?= site_url('staff/upload/' . $item->id) ?>"><?= form_hidden($this->security->get_csrf_token_name(), $this->security->get_csrf_hash()) ?><input type="hidden" name="document_type" value="cerapan"><label>Cerapan (internal, PDF)<input type="file" name="document" accept="application/pdf,.pdf" required></label><button class="button outline small-button" type="submit">Unggah cerapan</button></form><?php endif; ?>
<?php if (array_intersect($roles, array('issuer','administrator'))): ?><form method="post" enctype="multipart/form-data" action="<?= site_url('staff/upload/' . $item->id) ?>"><?= form_hidden($this->security->get_csrf_token_name(), $this->security->get_csrf_hash()) ?><input type="hidden" name="document_type" value="skhp"><label>Nomor SKHP<input name="certificate_no" maxlength="100" required></label><label>SKHP (PDF)<input type="file" name="document" accept="application/pdf,.pdf" required></label><button class="button outline small-button" type="submit">Unggah SKHP</button></form><?php endif; ?>
</div></article><?php endforeach; ?></section>
<section class="card detail-card"><h2>Riwayat status</h2><?php foreach ($history as $h): ?><div class="timeline-row"><span class="timeline-dot"></span><div><strong><?= e(status_label($h->to_status)) ?></strong><small><?= e(tanggal_id($h->created_at, TRUE)) ?> · <?= e($h->full_name) ?><?= $h->note ? ' · ' . e($h->note) : '' ?></small></div></div><?php endforeach; ?></section>
</div><aside><section class="card detail-card"><h2>Data layanan</h2><dl class="summary"><div><dt>Pemohon</dt><dd><?= e($request->full_name) ?></dd></div><div><dt>Instansi</dt><dd><?= e($request->organization_name ?: '—') ?></dd></div><div><dt>Lokasi</dt><dd><?= $request->service_mode === 'field' ? 'Lapangan' : 'Kantor UPT' ?></dd></div><div><dt>Alamat</dt><dd><?= e($request->service_address) ?></dd></div><div><dt>Catatan pemohon</dt><dd><?= e($request->applicant_note ?: '—') ?></dd></div></dl></section>
<section class="card detail-card"><h2>Dokumen</h2><?php foreach ($documents as $doc): ?><a class="document-link" href="<?= site_url('dokumen/' . $doc->id) ?>">↓ <?= e($doc->document_type === 'application_letter' ? 'Surat permohonan' : ($doc->document_type === 'cerapan' ? 'Cerapan' : 'SKHP')) ?></a><?php endforeach; ?></section></aside></div>
