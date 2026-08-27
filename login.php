<?php
require_once __DIR__ . '/inc/auth.php';
if (girisli()) { header('Location: index.php'); exit; }
$hata = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $d = db();
    $d->exec("CREATE TABLE IF NOT EXISTS giris_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip VARCHAR(45) NOT NULL,
        kullanici_adi VARCHAR(60) NULL,
        basarili TINYINT(1) NOT NULL DEFAULT 0,
        tarih DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX (ip), INDEX (tarih)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $ip = $_SERVER['REMOTE_ADDR'] ?? '?';
    // Kaba kuvvet koruması: aynı IP'den 15 dakikada 5+ başarısız deneme -> blok
    $st = $d->prepare("SELECT COUNT(*) c FROM giris_log WHERE ip = ? AND basarili = 0 AND tarih > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    $st->execute([$ip]);
    if ((int)$st->fetch()['c'] >= 5) {
        $hata = 'Çok fazla başarısız deneme. Lütfen 15 dakika sonra tekrar deneyin.';
    } else {
        $ka = trim($_POST['username'] ?? '');
        $st = $d->prepare('SELECT * FROM kullanicilar WHERE kullanici_adi = ? AND aktif = 1');
        $st->execute([$ka]);
        $k = $st->fetch();
        $sifre = $_POST['password'] ?? '';
        if ($k && password_verify($sifre, $k['sifre_hash'])) {
            $d->prepare('INSERT INTO giris_log (ip, kullanici_adi, basarili) VALUES (?,?,1)')->execute([$ip, $ka]);
            session_regenerate_id(true);
            $_SESSION['kullanici'] = ['id' => $k['id'], 'ad' => $k['ad_soyad'], 'kullanici_adi' => $k['kullanici_adi'], 'rol' => $k['rol']];
            // Varsayılan şifre hâlâ kullanılıyorsa panelde uyarı göster
            $_SESSION['varsayilan_sifre'] = ($k['kullanici_adi'] === 'admin' && $sifre === 'ernvarlik2026');
            db()->prepare('UPDATE kullanicilar SET son_giris = NOW() WHERE id = ?')->execute([$k['id']]);
            header('Location: index.php'); exit;
        }
        $d->prepare('INSERT INTO giris_log (ip, kullanici_adi, basarili) VALUES (?,?,0)')->execute([$ip, $ka]);
        usleep(400000); // deneme başına yapay gecikme
        $hata = 'Kullanıcı adı veya şifre hatalı.';
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Giriş — ERN Varlık Yönetim ve Takip</title>
<link rel="icon" type="image/png" href="https://ern.com.tr/favicon.png">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap">
<style>
:root { --ern:#00584E; --ern-dark:#003D35; --ern-light:#007A6A; --ern-teal:#00C9B1; --ern-gold:#C9A84C; }
*, *::before, *::after { box-sizing: border-box; }
body { font-family:'Outfit',system-ui,sans-serif; margin:0; min-height:100vh; display:flex; overflow:hidden; background:var(--ern-dark); }
.login-left { flex:1; display:flex; flex-direction:column; justify-content:center; align-items:center; padding:3rem; position:relative; overflow:hidden;
  background:linear-gradient(145deg,var(--ern-dark) 0%,var(--ern) 60%,var(--ern-light) 100%); }
.login-left::before { content:''; position:absolute; width:600px; height:600px; border-radius:50%;
  background:radial-gradient(circle,rgba(0,200,180,.18) 0%,transparent 70%); top:-200px; right:-200px; animation:pulse-bg 8s ease-in-out infinite; }
.login-left::after { content:''; position:absolute; width:400px; height:400px; border-radius:50%;
  background:radial-gradient(circle,rgba(201,168,76,.12) 0%,transparent 70%); bottom:-150px; left:-100px; animation:pulse-bg 10s ease-in-out infinite reverse; }
@keyframes pulse-bg { 0%,100%{transform:scale(1)} 50%{transform:scale(1.15)} }
.login-left-content { position:relative; z-index:2; text-align:center; max-width:540px; width:100%; }
.brand-logo { display:block; margin:0 auto 2rem; height:44px; filter:brightness(0) invert(1); }
.brand-tagline { font-size:1.95rem; font-weight:800; color:#fff; line-height:1.15; letter-spacing:-.03em; margin-bottom:.75rem; }
.brand-tagline span { background:linear-gradient(90deg,var(--ern-teal),#a8f0e8); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text; }
.brand-sub { font-size:.95rem; color:rgba(255,255,255,.55); margin-bottom:2.5rem; line-height:1.6; }
.feature-pills { display:grid; grid-template-columns:1fr 1fr; gap:.6rem; text-align:left; }
.feature-pill { display:flex; align-items:center; gap:.6rem; padding:.6rem .8rem; background:rgba(255,255,255,.07);
  border:1px solid rgba(255,255,255,.1); border-radius:12px; backdrop-filter:blur(8px); animation:slideInLeft .5s ease backwards;
  transition:transform .2s,background .2s,border-color .2s; cursor:default; }
.feature-pill:hover { transform:translateY(-3px); background:rgba(255,255,255,.12); border-color:rgba(0,201,177,.45); box-shadow:0 10px 24px rgba(0,0,0,.22); }
.feature-pill:nth-child(1){animation-delay:.1s} .feature-pill:nth-child(2){animation-delay:.18s}
.feature-pill:nth-child(3){animation-delay:.26s} .feature-pill:nth-child(4){animation-delay:.34s}
.feature-pill:nth-child(5){animation-delay:.42s} .feature-pill:nth-child(6){animation-delay:.5s}
@keyframes slideInLeft { from{opacity:0;transform:translateX(-20px)} to{opacity:1;transform:none} }
.feature-pill-icon { width:36px; height:36px; flex-shrink:0; border-radius:10px; display:flex; align-items:center; justify-content:center;
  background:rgba(0,200,180,.18); color:var(--ern-teal); font-size:1.05rem; transition:transform .2s; }
.feature-pill:hover .feature-pill-icon { transform:scale(1.1) rotate(-4deg); }
.feature-pill-text { font-size:.72rem; color:rgba(255,255,255,.6); line-height:1.35; }
.feature-pill-text strong { display:block; font-size:.8rem; color:#fff; font-weight:600; }
.login-right { width:440px; flex-shrink:0; background:#fff; display:flex; flex-direction:column; justify-content:center; padding:3rem 3.2rem; overflow-y:auto; }
.login-right-logo { display:flex; align-items:center; gap:.8rem; margin-bottom:2.2rem; }
.login-right-logo-icon { width:46px; height:46px; border-radius:13px; background:linear-gradient(135deg,var(--ern),var(--ern-light));
  color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.3rem; }
.login-right-logo-text strong { display:block; font-size:1rem; color:#111; font-weight:700; }
.login-right-logo-text span { font-size:.75rem; color:#888; }
.login-title { font-size:1.6rem; font-weight:800; color:#111; letter-spacing:-.02em; }
.login-sub { color:#888; font-size:.9rem; margin:.3rem 0 1.8rem; }
.lbl { display:block; font-size:.78rem; font-weight:600; color:#444; margin-bottom:.4rem; }
.input-wrap { position:relative; display:flex; align-items:center; }
.input-wrap-icon { position:absolute; left:.9rem; color:#aaa; font-size:1rem; pointer-events:none; }
.input-wrap input { width:100%; padding:.8rem 1rem .8rem 2.6rem; border:1.5px solid #e2e2e2; border-radius:12px;
  font-family:inherit; font-size:.95rem; outline:none; transition:border-color .2s, box-shadow .2s; }
.input-wrap input:focus { border-color:var(--ern-light); box-shadow:0 0 0 4px rgba(0,122,106,.1); }
.input-wrap-btn { position:absolute; right:.7rem; border:0; background:none; color:#aaa; cursor:pointer; font-size:1rem; padding:.3rem; }
.btn-login { width:100%; padding:.9rem; border:0; border-radius:12px; background:linear-gradient(135deg,var(--ern),var(--ern-light));
  color:#fff; font-family:inherit; font-size:1rem; font-weight:700; cursor:pointer; transition:transform .15s, box-shadow .15s; }
.btn-login:hover { transform:translateY(-2px); box-shadow:0 10px 24px rgba(0,88,78,.35); }
.hata { background:#fdeaea; color:#b02a2a; border:1px solid #f3c2c2; padding:.7rem 1rem; border-radius:10px; font-size:.85rem; margin-bottom:1.2rem; }
.login-footer { margin-top:2rem; text-align:center; font-size:.78rem; color:#999; line-height:1.7; }
.waves { position:absolute; bottom:0; left:0; width:100%; z-index:1; }
.waves svg { display:block; width:100%; height:80px; }
@media (max-width:820px){ .login-left{display:none} .login-right{width:100%} }
</style>
</head>
<body>
<div class="login-left">
    <div class="login-left-content">
        <img class="brand-logo" src="https://ernsaha.com.tr/beton/uploads/logo/ERN%20Holding_Logo_Beyaz.png" alt="ERN Holding"
             onerror="this.style.display='none'">
        <div class="brand-tagline">ERN <span>Varlık Yönetim</span> ve Takip</div>
        <div class="brand-sub">Araç, iş makineleri, binalar ve harita cihazları — sevk, mali hareket, çalışma saati ve amortisman takibi tek sistemde.</div>
        <div class="feature-pills">
            <div class="feature-pill">
                <div class="feature-pill-icon"><i class="bi bi-truck"></i></div>
                <div class="feature-pill-text"><strong>Varlık Envanteri</strong>Araç, makine, konteyner ve cihaz kayıtları</div>
            </div>
            <div class="feature-pill">
                <div class="feature-pill-icon"><i class="bi bi-arrow-left-right"></i></div>
                <div class="feature-pill-text"><strong>Sevk Takibi</strong>Lokasyonlar arası sevk ve geçmiş</div>
            </div>
            <div class="feature-pill">
                <div class="feature-pill-icon"><i class="bi bi-wrench-adjustable"></i></div>
                <div class="feature-pill-text"><strong>Bakım / Onarım</strong>Gider kaydı, son bakım ve muayene tarihi</div>
            </div>
            <div class="feature-pill">
                <div class="feature-pill-icon"><i class="bi bi-cash-stack"></i></div>
                <div class="feature-pill-text"><strong>Mali Takip</strong>Kira geliri, hakediş, kar/zarar, amortisman</div>
            </div>
            <div class="feature-pill">
                <div class="feature-pill-icon"><i class="bi bi-speedometer2"></i></div>
                <div class="feature-pill-text"><strong>Çalışma Saatleri</strong>Ay ay saat / km takibi</div>
            </div>
            <div class="feature-pill">
                <div class="feature-pill-icon"><i class="bi bi-currency-exchange"></i></div>
                <div class="feature-pill-text"><strong>TCMB Kurları</strong>EUR / USD otomatik güncel kur</div>
            </div>
        </div>
    </div>
    <div class="waves">
        <svg viewBox="0 0 150 80" preserveAspectRatio="none">
            <defs><path id="wv" d="M-160 16c30 0 58-12 88-12s 58 12 88 12 58-12 88-12 58 12 88 12 v90h-352z"></path></defs>
            <g><use href="#wv" x="48" y="6" fill="rgba(0,201,177,.12)"></use>
               <use href="#wv" x="48" y="20" fill="rgba(255,255,255,.07)"></use>
               <use href="#wv" x="48" y="34" fill="rgba(0,201,177,.10)"></use>
               <use href="#wv" x="48" y="48" fill="rgba(0,61,53,.34)"></use></g>
        </svg>
    </div>
</div>
<div class="login-right">
    <div class="login-right-logo">
        <div class="login-right-logo-icon"><i class="bi bi-truck-front-fill"></i></div>
        <div class="login-right-logo-text"><strong>ERN Holding</strong><span>Varlık Yönetim ve Takip Sistemi</span></div>
    </div>
    <div class="login-title">Hoş Geldiniz</div>
    <p class="login-sub">Hesabınıza giriş yapın</p>
    <?php if ($hata): ?><div class="hata"><i class="bi bi-exclamation-triangle"></i> <?= e($hata) ?></div><?php endif; ?>
    <form method="post" novalidate id="loginForm">
        <div style="margin-bottom:1.1rem">
            <label class="lbl" for="username">Kullanıcı Adı</label>
            <div class="input-wrap">
                <div class="input-wrap-icon"><i class="bi bi-person"></i></div>
                <input type="text" id="username" name="username" value="<?= e($_POST['username'] ?? '') ?>"
                       autocomplete="username" autofocus required placeholder="kullanici_adi">
            </div>
        </div>
        <div style="margin-bottom:1.5rem">
            <label class="lbl" for="password">Şifre</label>
            <div class="input-wrap">
                <div class="input-wrap-icon"><i class="bi bi-lock"></i></div>
                <input type="password" id="password" name="password" autocomplete="current-password" required placeholder="••••••••">
                <button type="button" class="input-wrap-btn" id="togglePwd" tabindex="-1"><i class="bi bi-eye" id="eyeIcon"></i></button>
            </div>
        </div>
        <button type="submit" class="btn-login"><i class="bi bi-box-arrow-in-right"></i> Giriş Yap</button>
    </form>
    <div class="login-footer">
        ERN Holding &copy; <?= date('Y') ?> &nbsp;&mdash;&nbsp; Varlık Yönetim ve Takip Sistemi<br>
        <span style="font-size:.7rem;opacity:.6">Geliştirici: <strong style="color:var(--ern);opacity:1">Tayyar Akbulut</strong></span>
    </div>
</div>
<script>
document.getElementById('togglePwd').addEventListener('click', function () {
    var p = document.getElementById('password'), i = document.getElementById('eyeIcon');
    if (p.type === 'password') { p.type = 'text'; i.className = 'bi bi-eye-slash'; }
    else { p.type = 'password'; i.className = 'bi bi-eye'; }
});
</script>
</body>
</html>
