<?php
require_once __DIR__ . '/inc/auth.php';
yetki_zorunlu('admin', 'saha');
csrf_kontrol();
$d = db();
$vid = (int)($_POST['varlik_id'] ?? 0);
$tur = in_array($_POST['tur'] ?? '', ['foto','ruhsat','sigorta','muayene','diger'], true) ? $_POST['tur'] : 'diger';
$st = $d->prepare('SELECT id FROM varliklar WHERE id = ?'); $st->execute([$vid]);
if (!$st->fetch()) die('Varlık bulunamadı.');
if (empty($_FILES['dosya']) || $_FILES['dosya']['error'] !== UPLOAD_ERR_OK) die('Dosya yüklenemedi.');
if ($_FILES['dosya']['size'] > 15 * 1024 * 1024) die('Dosya 15 MB üzerinde.');

$izinli = ['jpg','jpeg','png','webp','pdf','doc','docx','xls','xlsx'];
$orjinal = $_FILES['dosya']['name'];
$uzanti = strtolower(pathinfo($orjinal, PATHINFO_EXTENSION));
if (!in_array($uzanti, $izinli, true)) die('İzin verilmeyen dosya türü.');

$klasor = __DIR__ . '/uploads/' . $vid;
if (!is_dir($klasor)) mkdir($klasor, 0755, true);
$ad = $tur . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $uzanti;
if (!move_uploaded_file($_FILES['dosya']['tmp_name'], "$klasor/$ad")) die('Dosya taşınamadı.');
$yol = 'uploads/' . $vid . '/' . $ad;

$d->prepare('INSERT INTO dosyalar (varlik_id, tur, dosya_adi, yol, yukleyen) VALUES (?,?,?,?,?)')
  ->execute([$vid, $tur, $orjinal, $yol, kullanici()['id']]);
// İlk fotoğrafı kapak yap
if ($tur === 'foto') {
    $d->prepare('UPDATE varliklar SET foto = ? WHERE id = ? AND (foto IS NULL OR foto = "")')->execute([$yol, $vid]);
}
header('Location: varlik.php?id=' . $vid);
