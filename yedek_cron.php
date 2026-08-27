<?php
// aaPanel Cron için: php yedek_cron.php  (her gece 03:00 önerilir)
// Tarayıcıdan çağrılamaz.
if (PHP_SAPI !== 'cli') { http_response_code(403); die('Sadece komut satırından çalıştırılabilir.'); }
require_once __DIR__ . '/inc/yedek.php';
$ad = yedek_al('otomatik');
echo "Yedek alındı: $ad\n";
