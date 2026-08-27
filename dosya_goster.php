<?php
// Yüklenen dosyaları oturum kontrolüyle sunar — uploads/ web'den direkt erişime kapalıdır.
require_once __DIR__ . '/inc/auth.php';
giris_zorunlu();
$d = db();

if (isset($_GET['foto'])) {              // varlık kapak fotoğrafı
    $st = $d->prepare('SELECT foto FROM varliklar WHERE id = ?');
    $st->execute([(int)$_GET['foto']]);
    $yol = $st->fetch()['foto'] ?? null;
} else {                                  // dosyalar tablosundan
    $st = $d->prepare('SELECT yol FROM dosyalar WHERE id = ?');
    $st->execute([(int)($_GET['id'] ?? 0)]);
    $yol = $st->fetch()['yol'] ?? null;
}
if (!$yol) { http_response_code(404); die('Dosya bulunamadı.'); }

$tam = realpath(__DIR__ . '/' . $yol);
$kok = realpath(__DIR__ . '/uploads');
if (!$tam || !$kok || !str_starts_with($tam, $kok) || !is_file($tam)) {
    http_response_code(404); die('Dosya bulunamadı.');
}
$mime = [
    'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp',
    'pdf' => 'application/pdf',
][strtolower(pathinfo($tam, PATHINFO_EXTENSION))] ?? 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($tam));
if ($mime === 'application/octet-stream') {
    header('Content-Disposition: attachment; filename="' . rawurlencode(basename($tam)) . '"');
}
readfile($tam);
