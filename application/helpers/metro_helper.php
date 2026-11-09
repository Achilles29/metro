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

function admin_icon($name)
{
    $paths = array(
        'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'inbox' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 14h5l2 3h4l2-3h5"/>',
        'chart' => '<path d="M4 20V10m5 10V4m5 16v-7m5 7V8"/>',
        'users' => '<path d="M16 20v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 20v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'person' => '<circle cx="12" cy="7" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/>',
        'lock' => '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'chevron' => '<path d="m9 18 6-6-6-6"/>',
        'external' => '<path d="M14 3h7v7M10 14 21 3"/><path d="M21 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h6"/>'
    );
    return '<svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . (isset($paths[$name]) ? $paths[$name] : '') . '</svg>';
}

function tanggal_id($value, $with_time = FALSE)
{
    $time = strtotime((string) $value);
    if ($time === FALSE) return '—';
    $months = array(1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'Mei',6=>'Jun',7=>'Jul',8=>'Agu',9=>'Sep',10=>'Okt',11=>'Nov',12=>'Des');
    return date('d', $time) . ' ' . $months[(int) date('n', $time)] . ' ' . date('Y', $time)
        . ($with_time ? ', ' . date('H:i', $time) . ' WIB' : '');
}

function bulan_id($period)
{
    if (!preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', (string) $period, $matches)) return (string) $period;
    $months = array(1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember');
    return $months[(int) $matches[2]] . ' ' . $matches[1];
}
