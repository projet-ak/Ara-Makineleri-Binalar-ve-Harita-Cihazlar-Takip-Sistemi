<?php
require_once __DIR__ . '/inc/auth.php';
yetki_zorunlu('admin');
if (($_GET['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '-')) die('Geçersiz istek.');
$d = db();
$id = (int)($_GET['id'] ?? 0);
$st = $d->prepare('SELECT * FROM dosyalar WHERE id = ?'); $st->execute([$id]);
$f = $st->fetch();
if ($f) {
    $tam = __DIR__ . '/' . $f['yol'];
    if (is_file($tam) && str_starts_with(realpath($tam), realpath(__DIR__ . '/uploads'))) unlink($tam);
    $d->prepare('DELETE FROM dosyalar WHERE id = ?')->execute([$id]);
    $d->prepare('UPDATE varliklar SET foto = NULL WHERE id = ? AND foto = ?')->execute([$f['varlik_id'], $f['yol']]);
}
header('Location: varlik.php?id=' . ($f['varlik_id'] ?? 0));
