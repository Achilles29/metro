<?php
// Jalankan sekali setelah migrasi 019. Tidak mengubah kode yang sudah ada.
if (PHP_SAPI!=='cli') exit(1);
define('BASEPATH',dirname(__DIR__).'/system/');
require dirname(__DIR__).'/application/libraries/Booking_codes.php';
$config=require '/etc/metro/database.php';
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
$db=new mysqli('localhost',$config['username'],$config['password'],'metro_app');
$ids=$db->query('SELECT id FROM service_requests WHERE booking_code IS NULL ORDER BY id')->fetch_all(MYSQLI_ASSOC);
$generator=new Booking_codes();
$update=$db->prepare('UPDATE service_requests SET booking_code=? WHERE id=? AND booking_code IS NULL');
$done=0;
foreach ($ids as $row) {
    $id=(int)$row['id'];
    for ($attempt=0;$attempt<10;$attempt++) {
        $code=$generator->generate();
        try {
            $update->bind_param('si',$code,$id);
            $update->execute();
            $done+=$update->affected_rows;
            break;
        } catch (mysqli_sql_exception $error) {
            if ($error->getCode()!==1062 || $attempt===9) throw $error;
        }
    }
}
echo 'Kode booking dibuat: '.$done.PHP_EOL;
