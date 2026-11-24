<div class="page-heading">
  <div><div class="eyebrow">Peminjaman harian</div><h1>Operasional CTT</h1>
    <p>Daftar ini hanya memuat CTT yang sudah dipilih pada permohonan untuk jadwal tera tanggal terpilih. Centang order yang mengambil alat, lalu catat pengembaliannya.</p></div>
  <a class="button outline" href="<?= site_url('petugas/master/pilihan/ctt') ?>">Lihat master CTT</a>
</div>

<section class="card ctt-ops-filter">
  <form method="get" action="<?= site_url('petugas/ctt') ?>" class="ctt-ops-filter-form">
    <label for="ctt-date">Tanggal pelaksanaan</label>
    <input id="ctt-date" type="date" name="date" value="<?= e($date) ?>" required>
    <button class="button primary" type="submit">Tampilkan</button>
    <?php if ($date!==$today): ?><a class="button outline" href="<?= site_url('petugas/ctt') ?>">Hari ini</a><?php endif; ?>
  </form>
  <div class="ctt-ops-summary"><span><strong><?= count($groups) ?></strong> jenis CTT dipilih</span><span><strong><?= (int)$order_count ?></strong> pilihan order</span><span><strong><?= (int)$open_count ?></strong> pinjaman pada jadwal ini belum kembali</span></div>
</section>

<?php if (!$groups): ?>
<section class="card"><div class="table-empty">Belum ada CTT yang dipilih untuk jadwal <?= e(tanggal_id($date)) ?>. Pilih CTT pada tab CTT permohonan setelah kaji ulang.</div></section>
<?php else: ?>
<div class="ctt-ops-grid">
<?php foreach ($groups as $option_id=>$group): $option=$group['option']; ?>
  <section class="card ctt-ops-card">
    <div class="ctt-ops-card-top"><span class="ctt-ops-code"><?= e($option->option_code) ?></span>
      <?php if (!$option->is_active): ?><span class="badge danger">Nonaktif</span><?php elseif ($group['borrowed_units']): ?><span class="badge warning">Sedang dipinjam</span><?php else: ?><span class="badge info">Siap dipakai</span><?php endif; ?>
    </div>
    <h2><?= e($option->option_label) ?></h2>
    <div class="ctt-ops-metrics">
      <div><strong><?= count($group['orders']) ?></strong><span>Order hari ini</span></div>
      <div><strong><?= (int)$group['planned_units'] ?></strong><span>Sudah dipilih</span></div>
      <div><strong><?= (int)$group['borrowed_units'] ?></strong><span>Belum kembali</span></div>
    </div>
    <div class="ctt-ops-card-bottom"><small>Stok <?= $option->asset_quantity===NULL?'belum diisi':(int)$option->asset_quantity ?> · Tersedia <?= $group['available']===NULL?'—':(int)$group['available'] ?></small>
      <button class="button primary" type="button" data-modal-open="ctt-orders-<?= (int)$option_id ?>">Lihat order →</button></div>
  </section>
<?php endforeach; ?>
</div>

<?php foreach ($groups as $option_id=>$group): $option=$group['option'];
  $has_selectable=FALSE;
  foreach ($group['orders'] as $order) if ($can_borrow && $option->is_active && $date===$today && !$order->completed_at && !$order->loan_id && !$order->overdue_loan_id) $has_selectable=TRUE;
?>
<dialog id="ctt-orders-<?= (int)$option_id ?>" class="admin-modal ctt-ops-modal" aria-labelledby="ctt-orders-title-<?= (int)$option_id ?>">
  <div class="modal-head"><div><small><?= e($option->option_code) ?> · <?= e(tanggal_id($date)) ?></small><h2 id="ctt-orders-title-<?= (int)$option_id ?>"><?= e($option->option_label) ?></h2></div><button type="button" class="modal-close" data-modal-close aria-label="Tutup">×</button></div>
  <div class="modal-body">
    <p class="ctt-ops-hint">Order di bawah sudah memilih CTT ini pada tab permohonan. Centang yang mengambil alat hari ini. Stok berkurang saat dipinjam dan tersedia kembali setelah dikembalikan.</p>
    <?php if ($has_selectable): ?><form id="ctt-borrow-<?= (int)$option_id ?>" method="post" action="<?= site_url('petugas/ctt/pinjam') ?>">
      <?= form_hidden($this->security->get_csrf_token_name(),$this->security->get_csrf_hash()) ?>
      <input type="hidden" name="ctt_option_id" value="<?= (int)$option_id ?>"><input type="hidden" name="date" value="<?= e($date) ?>">
    </form><?php endif; ?>
    <?php if (!$group['orders']): ?><div class="table-empty">Belum ada order pada tanggal ini.</div><?php else: ?>
    <div class="ctt-ops-table-tools"><label>Cari order atau pemohon<input type="search" data-ctt-search placeholder="Ketik nomor order atau nama"></label><span data-ctt-count></span></div>
    <div class="table-scroll"><table class="data-table ctt-ops-table"><thead><tr><th class="ctt-ops-check-head">Pilih</th><th>Order / Pemohon</th><th>Jumlah aset</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
    <?php foreach ($group['orders'] as $order):
      $selectable=$can_borrow && $option->is_active && $date===$today && !$order->completed_at && !$order->loan_id && !$order->overdue_loan_id;
      $open_loan=$order->overdue_loan_id || ($order->loan_id && !$order->returned_at);
      $loan_date=$order->overdue_loan_id?$order->overdue_date:$order->loan_date;
    ?>
    <tr>
      <td data-label="Pilih"><?php if ($selectable): ?><input type="checkbox" form="ctt-borrow-<?= (int)$option_id ?>" name="request_ctt_ids[]" value="<?= (int)$order->ctt_entry_id ?>" aria-label="Pilih order <?= e($order->request_no) ?>"><?php else: ?>—<?php endif; ?></td>
      <td data-label="Order / Pemohon"><div class="ctt-ops-order"><a class="table-link" href="<?= site_url('petugas/permohonan/'.$order->request_id.'?tab=ctt') ?>"><strong><?= e($order->request_no) ?></strong></a><small><?= e($order->applicant_name) ?><?php if ($order->penera_name): ?> · <?= e($order->penera_name) ?><?php endif; ?></small><small><?= e(tanggal_id($order->tera_start_date)) ?> – <?= e(tanggal_id($order->tera_end_date)) ?></small></div></td>
      <td data-label="Jumlah aset"><?= (int)$order->asset_units ?></td>
      <td data-label="Status"><?php if ($order->overdue_loan_id): ?><span class="badge danger">Belum kembali sejak <?= e(tanggal_id($order->overdue_date)) ?></span><?php elseif ($open_loan): ?><span class="badge warning">Dipinjam <?= e(substr($order->borrowed_at,11,5)) ?></span><?php elseif ($order->loan_id): ?><span class="badge success">Dikembalikan <?= e(substr($order->returned_at,11,5)) ?></span><?php else: ?><span class="badge info">Siap dipinjam</span><?php endif; ?></td>
      <td data-label="Aksi"><?php if ($can_borrow && $open_loan): ?><form method="post" action="<?= site_url('petugas/ctt/'.$order->ctt_entry_id.'/kembali') ?>" onsubmit="return confirm('Catat pengembalian CTT dari order ini?')"><?= form_hidden($this->security->get_csrf_token_name(),$this->security->get_csrf_hash()) ?><input type="hidden" name="loan_date" value="<?= e($loan_date) ?>"><input type="hidden" name="return_to" value="ctt"><input type="hidden" name="view_date" value="<?= e($date) ?>"><button class="button outline" type="submit">Kembalikan</button></form><?php else: ?>—<?php endif; ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody></table></div><div class="ctt-ops-pager"><button class="button outline small-button" type="button" data-ctt-prev>← Sebelumnya</button><span data-ctt-page-info></span><button class="button outline small-button" type="button" data-ctt-next>Berikutnya →</button></div><?php endif; ?>
  </div>
  <div class="modal-actions"><button type="button" class="button outline" data-modal-close>Tutup</button><?php if ($has_selectable): ?><button class="button primary" type="submit" form="ctt-borrow-<?= (int)$option_id ?>">Tandai pinjam terpilih</button><?php endif; ?></div>
</dialog>
<?php endforeach; endif; ?>
<script src="<?= base_url('assets/ctt-ops.js?v=1') ?>" defer></script>
