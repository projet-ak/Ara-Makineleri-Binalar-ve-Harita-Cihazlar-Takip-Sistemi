<?php
require_once __DIR__ . '/db.php';
session_name('ernvarlik_sess');
session_start();

function kullanici() { return $_SESSION['kullanici'] ?? null; }
function rol(): string { return $_SESSION['kullanici']['rol'] ?? ''; }
function girisli(): bool { return isset($_SESSION['kullanici']); }

function giris_zorunlu(): void {
    if (!girisli()) { header('Location: login.php'); exit; }
}
// admin: her şey • saha: sevk + çalışma saati girişi • yonetim: sadece görüntüleme/rapor
function yetki(string ...$roller): bool { return in_array(rol(), $roller, true); }
function yetki_zorunlu(string ...$roller): void {
    giris_zorunlu();
    if (!yetki(...$roller)) { http_response_code(403); die('Bu sayfa için yetkiniz yok.'); }
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_kontrol(): void {
    if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '-')) { http_response_code(400); die('Geçersiz istek.'); }
}
