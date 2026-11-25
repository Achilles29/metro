<?php
defined('BASEPATH') OR exit('No direct script access allowed');

if (!function_exists('metro_coordinates')) {
    function metro_coordinates($latitude, $longitude)
    {
        $lat=trim((string)$latitude); $lng=trim((string)$longitude);
        if ($lat==='' && $lng==='') return NULL;
        if ($lat==='' || $lng==='' || !is_numeric($lat) || !is_numeric($lng)) return FALSE;
        $lat=(float)$lat; $lng=(float)$lng;
        if (!is_finite($lat) || !is_finite($lng) || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180)
            return FALSE;
        return array(round($lat,7),round($lng,7));
    }
}

function e($value) { return html_escape((string) $value); }

function master_option_map($group)
{
    static $cache = array();
    if (!isset($cache[$group])) {
        $cache[$group] = array();
        $ci =& get_instance();
        foreach ($ci->master->options($group, FALSE) as $option) $cache[$group][$option->option_code] = $option;
    }
    return $cache[$group];
}

function master_label($group, $code)
{
    $options = master_option_map($group);
    return isset($options[$code]) ? $options[$code]->option_label : (string) $code;
}

function status_label($code) { return master_label('status', $code); }

function status_description($code)
{
    $options=master_option_map('status');
    return isset($options[$code]) ? (string)$options[$code]->description : '';
}

function service_types_label($codes)
{
    $labels=array();
    foreach (array_unique(array_filter(explode(',',(string)$codes))) as $code)
        $labels[]=master_label('service_type',$code);
    return implode(' / ',$labels);
}

function metro_terminal_statuses()
{
    return array('valid','invalid','returned','not_suitable','completed','rejected','cancelled');
}

function metro_is_terminal($code)
{
    return in_array($code,metro_terminal_statuses(),TRUE);
}

function metro_is_valid($code)
{
    return in_array($code,array('valid','completed'),TRUE);
}

function status_class($code)
{
    if ($code==='not_suitable' || $code==='rejected') return 'not-suitable';
    $options = master_option_map('status');
    $tone = isset($options[$code]) ? $options[$code]->tone : 'info';
    return in_array($tone, array('info','warning','success','danger'), TRUE) ? $tone : 'info';
}

function admin_icon($name)
{
    $paths = array(
        'database' => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14c0 1.7 4 3 9 3s9-1.3 9-3V5M3 12c0 1.7 4 3 9 3s9-1.3 9-3"/>',
        'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'inbox' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 14h5l2 3h4l2-3h5"/>',
        'ctt' => '<rect x="4" y="5" width="16" height="15" rx="2"/><path d="M9 5V3h6v2M8 11h8M8 15h5"/>',
        'chart' => '<path d="M4 20V10m5 10V4m5 16v-7m5 7V8"/>',
        'gallery' => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8" cy="9" r="1.5"/><path d="m3 17 5-5 4 4 3-3 6 5"/>',
        'users' => '<path d="M16 20v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 20v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'person' => '<circle cx="12" cy="7" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/>',
        'message' => '<path d="M20 11.5a8 8 0 0 1-8 8 9 9 0 0 1-3.6-.7L4 20l1.2-4.3A8 8 0 1 1 20 11.5Z"/><path d="M9 10.5h6M9 13.5h4"/>',
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

function email_html($email)
{
    $parts = explode('@', (string) $email, 2);
    return count($parts) === 2 ? e($parts[0]) . '<span>@</span>' . e($parts[1]) : e($email);
}

function metro_page($total, $requested, $per_page = 20)
{
    $total = max(0, (int) $total);
    $per_page = max(1, (int) $per_page);
    $pages = max(1, (int) ceil($total / $per_page));
    $page = min($pages, max(1, (int) $requested));
    return array('total'=>$total, 'page'=>$page, 'pages'=>$pages,
        'per_page'=>$per_page, 'offset'=>($page - 1) * $per_page);
}

function metro_pager($pagination, $path, $params = array(), $key = 'page')
{
    if (empty($pagination) || $pagination['pages'] < 2) return '';
    $current = (int) $pagination['page'];
    $last = (int) $pagination['pages'];
    $link = function ($number, $label, $class = '') use ($path, $params, $key, $current) {
        $query = $params;
        unset($query[$key]);
        if ($number > 1) $query[$key] = $number;
        $url = site_url($path) . ($query ? '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '');
        return '<a class="pager-link'.($number === $current ? ' current' : '').$class.'" href="'.e($url).'"'.($number === $current ? ' aria-current="page"' : '').'>'.e($label).'</a>';
    };
    $html = '<nav class="metro-pager" aria-label="Halaman daftar"><span>'.number_format($pagination['total'], 0, ',', '.').' data · Halaman '.$current.' dari '.$last.'</span><div>';
    if ($current > 1) $html .= $link($current - 1, '← Sebelumnya');
    for ($n = max(1, $current - 2); $n <= min($last, $current + 2); $n++) $html .= $link($n, (string) $n);
    if ($current < $last) $html .= $link($current + 1, 'Berikutnya →');
    return $html . '</div></nav>';
}
