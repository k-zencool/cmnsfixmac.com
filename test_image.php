<?php
// test_image.php
declare(strict_types=1);

// รับ path แบบเว็บ เช่น /uploads/shops/iphone13-1.jpg
$p = isset($_GET['p']) ? trim((string)$_GET['p']) : '';
if ($p === '') $p = '/uploads/shops/iphone13-128-starlight-1.jpg'; // ตั้งค่า default ที่มึงมีจริง

$doc = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
$abs = $doc.$p;

$exists    = $abs !== '' && is_file($abs);
$readable  = $exists && is_readable($abs);
$size      = $exists ? filesize($abs) : 0;
$mime      = $exists ? (finfo_open(FILEINFO_MIME_TYPE) ? finfo_file(finfo_open(FILEINFO_MIME_TYPE), $abs) : 'unknown') : 'none';

header('Content-Type: text/plain; charset=UTF-8');
echo "DOCUMENT_ROOT : $doc\n";
echo "Web path      : $p\n";
echo "Absolute path : $abs\n";
echo "file_exists   : ".($exists?'true':'false')."\n";
echo "is_readable   : ".($readable?'true':'false')."\n";
echo "filesize      : $size\n";
echo "mime          : $mime\n";
echo "\nTips:\n- ถ้า file_exists=false = ไฟล์ไม่มีจริงหรือ path ผิด\n- ถ้า is_readable=false = permission/SELinux พัง\n- ถ้า filesize=0 = ไฟล์ว่าง\n";

// กดดูรูปจริงได้โดยใส่ ?show=1
if (isset($_GET['show']) && $readable) {
  header('Content-Type: '.$mime);
  readfile($abs);
}
