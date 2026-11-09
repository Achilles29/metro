<?php
defined('BASEPATH') OR exit('No direct script access allowed');

function e($value) { return html_escape((string) $value); }

function status_label($code)
{
    $labels = array(
        'submitted' => 'Dikirim', 'verification' => 'Verifikasi berkas',
        'revision' => 'Perlu perbaikan', 'scheduled' => 'Terjadwal',
        'testing' => 'Pengujian', 'review' => 'Pemeriksaan hasil',
        'completed' => 'Selesai', 'cancelled' => 'Dibatalkan',
        'rejected' => 'Ditolak', 'draft' => 'Draf', 'in_progress' => 'Dalam proses'
    );
    return isset($labels[$code]) ? $labels[$code] : $code;
}

function status_class($code)
{
    if ($code === 'completed') return 'success';
    if (in_array($code, array('revision', 'rejected', 'cancelled'), TRUE)) return 'danger';
    if (in_array($code, array('testing', 'review', 'scheduled'), TRUE)) return 'warning';
    return 'info';
}
