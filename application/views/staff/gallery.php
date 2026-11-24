<div class="page-heading"><div><div class="eyebrow">Dokumentasi layanan</div><h1>Galeri laporan</h1><p>Foto kegiatan dikelompokkan menurut order. Pilih foto yang dibutuhkan untuk mengunduhnya bersama sebagai ZIP.</p></div></div>

<form class="card filter-card gallery-filter" method="get" action="<?= site_url('petugas/galeri-laporan') ?>">
  <label>Cari order atau pemohon<input type="search" name="q" value="<?= e($query) ?>" placeholder="Nomor order / nama perusahaan"></label>
  <label>Foto dari tanggal<input type="date" name="date_from" value="<?= e($date_from) ?>"></label>
  <label>Sampai tanggal<input type="date" name="date_to" value="<?= e($date_to) ?>"></label>
  <button class="button primary" type="submit">Tampilkan</button>
  <?php if ($query || $date_from || $date_to): ?><a class="button outline" href="<?= site_url('petugas/galeri-laporan') ?>">Reset</a><?php endif; ?>
</form>

<form id="gallery-download-form" method="post" action="<?= site_url('petugas/galeri-laporan/unduh') ?>">
  <?= form_hidden($this->security->get_csrf_token_name(),$this->security->get_csrf_hash()) ?>
  <div class="gallery-actions"><div><strong><?= number_format($pagination['total'],0,',','.') ?> order dengan foto</strong><span id="gallery-selected-count">0 foto dipilih di halaman ini</span></div>
    <?php if ($can_download && $orders): ?><div class="gallery-action-buttons"><label class="gallery-select-all"><input type="checkbox" id="gallery-select-all"> Pilih semua di halaman</label><button class="button primary" id="gallery-download-button" type="submit" disabled>↓ Unduh foto terpilih</button></div><?php endif; ?>
  </div>
  <?php if ($orders): ?>
    <div class="gallery-orders">
      <?php foreach ($orders as $order): ?><section class="card gallery-order">
        <div class="gallery-order-head"><div><span class="eyebrow">Order <?= e($order->request_no) ?></span><h2><?= e($order->organization_name ?: $order->full_name) ?></h2><p><?= (int)$order->photo_count ?> foto · Terakhir <?= e(tanggal_id($order->last_photo,TRUE)) ?></p></div><a class="button outline small-button" href="<?= site_url('petugas/permohonan/'.$order->id.'?tab=process#dokumentasi-kegiatan') ?>">Lihat order</a></div>
        <div class="gallery-photo-grid">
          <?php foreach ($photos[(int)$order->id] ?? array() as $photo): ?><article class="gallery-photo">
            <?php if ($can_download): ?><label class="gallery-photo-choice"><input type="checkbox" name="photo_ids[]" value="<?= (int)$photo->id ?>" aria-label="Pilih foto <?= (int)$photo->id ?>"><span>Pilih</span></label><?php endif; ?>
            <a href="<?= site_url('petugas/foto/'.$photo->id) ?>" target="_blank" rel="noopener" aria-label="Buka foto <?= e($photo->caption ?: $photo->original_name) ?>"><img src="<?= site_url('petugas/foto/'.$photo->id) ?>" loading="lazy" alt="<?= e($photo->caption ?: 'Foto dokumentasi order '.$order->request_no) ?>"></a>
            <div class="gallery-photo-meta"><strong title="<?= e($photo->caption ?: $photo->original_name) ?>"><?= e($photo->caption ?: $photo->original_name) ?></strong><small><?= e(tanggal_id($photo->created_at,TRUE)) ?> · <?= number_format($photo->file_size/1024,0,',','.') ?> KB</small></div>
          </article><?php endforeach; ?>
        </div>
      </section><?php endforeach; ?>
    </div>
  <?php else: ?><div class="card table-empty">Belum ada foto dokumentasi untuk filter ini.</div><?php endif; ?>
</form>
<?= metro_pager($pagination,'petugas/galeri-laporan',array_filter(array('q'=>$query,'date_from'=>$date_from,'date_to'=>$date_to),function($value){return $value!=='';})) ?>
<script src="<?= base_url('assets/gallery.js?v=1') ?>" defer></script>
