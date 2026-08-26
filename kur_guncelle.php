<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/kur.php';
giris_zorunlu();
guncel_kur(true);
header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
