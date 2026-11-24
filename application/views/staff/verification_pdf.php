<!doctype html><html lang="id"><head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans,sans-serif;color:#1b2e3c;font-size:11px;line-height:1.5}
h1{text-align:center;font-size:18px;margin:8px 0 22px}p{margin:9px 0 18px}table{width:100%;border-collapse:collapse;margin:12px 0 20px}
td,th{border:1px solid #5c6770;padding:8px;vertical-align:top}th{background:#edf1f4;text-align:center}
.center{text-align:center}.small{font-size:9px;color:#536b7b}.mark{font-family:DejaVu Sans,sans-serif;font-size:17px;line-height:1}
</style></head><body>
<h1>Kaji ulang permohonan</h1>
<p>Dengan merujuk pada permohonan pemilik UTTP dengan nomor order <strong><?= e($request->request_no) ?></strong>, dilakukan kaji ulang permohonan sebagai berikut:</p>
<table><thead><tr><th style="width:40px">No.</th><th>Unsur kaji ulang</th><th style="width:85px">Sesuai</th></tr></thead><tbody>
<?php foreach ($checks as $index=>$check): ?><tr><td class="center"><?= $index+1 ?>.</td><td><?= e($check->label) ?></td><td class="center mark"><?= $check->result==='ok'?'☑':'☐' ?></td></tr><?php endforeach; ?>
</tbody></table>
<?php if ($exceptions): ?><p>Semua UTTP yang dimohonkan telah sesuai dengan kaji ulang, kecuali UTTP sebagai berikut:</p>
<table><thead><tr><th style="width:40px">No.</th><th>Jenis UTTP</th><th>Merk</th><th>Tipe</th><th>Unsur yang tidak sesuai</th></tr></thead><tbody>
<?php foreach ($exceptions as $index=>$exception): ?><tr><td class="center"><?= $index+1 ?>.</td><td><?= e($exception['item']->uttp_name) ?></td><td><?= e($exception['item']->brand ?: '—') ?></td><td><?= e($exception['item']->type_name ?: '—') ?></td><td><?= e(implode(', ',$exception['labels'])) ?></td></tr><?php endforeach; ?>
</tbody></table><?php endif; ?>
<p class="small">Pemohon: <?= e($request->organization_name ?: $request->full_name) ?> · Penera: <?= e($request->penera_display ?: '—') ?> · Kaji ulang: <?= e(tanggal_id($request->verification_finalized_at,TRUE)) ?></p>
</body></html>
