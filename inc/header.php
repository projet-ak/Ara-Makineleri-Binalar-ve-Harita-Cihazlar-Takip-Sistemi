<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/kur.php';
giris_zorunlu();
$kur = guncel_kur();
$sayfa = basename($_SERVER['SCRIPT_NAME']);
$menu = [
    ['index.php',       'bi-grid-1x2',        'Panel',            ['admin','saha','yonetim']],
    ['varliklar.php',   'bi-truck',           'Varlıklar',        ['admin','saha','yonetim']],
    ['hareketler.php',  'bi-arrow-left-right','Sevk & Hareket',   ['admin','saha','yonetim']],
    ['calisma.php',     'bi-speedometer2',    'Çalışma Saatleri', ['admin','saha','yonetim']],
    ['raporlar.php',    'bi-bar-chart-line',  'Raporlar',         ['admin','yonetim']],
    ['kullanicilar.php','bi-people',          'Kullanıcılar',     ['admin']],
];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($baslik ?? 'Panel') ?> — ERN Varlık Yönetim ve Takip</title>
<link rel="icon" type="image/png" href="https://ern.com.tr/favicon.png">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap">
<style>
:root { --ern:#00584E; --ern-dark:#003D35; --ern-light:#007A6A; --ern-teal:#00C9B1; --ern-gold:#C9A84C;
  --bg:#F2F5F4; --card:#fff; --line:#E4E9E8; --txt:#1C2A28; --mut:#7A8B88; }
*, *::before, *::after { box-sizing:border-box; }
body { font-family:'Outfit',system-ui,sans-serif; margin:0; background:var(--bg); color:var(--txt); min-height:100vh; }
a { color:var(--ern); text-decoration:none; }
/* Sidebar */
.side { position:fixed; inset:0 auto 0 0; width:232px; background:linear-gradient(180deg,var(--ern-dark),var(--ern)); color:#fff;
  display:flex; flex-direction:column; z-index:50; transition:transform .25s; }
.side-logo { padding:1.3rem 1.2rem 1rem; display:flex; align-items:center; gap:.7rem; }
.side-logo-icon { width:38px; height:38px; border-radius:11px; background:rgba(255,255,255,.12); display:flex; align-items:center; justify-content:center; font-size:1.15rem; color:var(--ern-teal); }
.side-logo strong { font-size:.92rem; display:block; line-height:1.15; }
.side-logo span { font-size:.66rem; opacity:.6; }
.side nav { flex:1; padding:.6rem; overflow-y:auto; }
.side nav a { display:flex; align-items:center; gap:.7rem; padding:.65rem .9rem; margin-bottom:.2rem; border-radius:10px;
  color:rgba(255,255,255,.72); font-size:.87rem; font-weight:500; transition:background .15s,color .15s; }
.side nav a:hover { background:rgba(255,255,255,.08); color:#fff; }
.side nav a.on { background:rgba(0,201,177,.16); color:#fff; font-weight:600; }
.side nav a.on i { color:var(--ern-teal); }
.side-kur { margin:.6rem; padding:.7rem .8rem; background:rgba(0,0,0,.18); border-radius:12px; font-size:.72rem; line-height:1.5; }
.side-kur b { color:var(--ern-teal); }
.side-kur .tarih { opacity:.55; font-size:.63rem; }
.side-user { padding:.9rem 1.2rem; border-top:1px solid rgba(255,255,255,.1); display:flex; align-items:center; gap:.6rem; font-size:.8rem; }
.side-user .rolTag { font-size:.6rem; text-transform:uppercase; letter-spacing:.06em; background:var(--ern-gold); color:#0D2E28; padding:.08rem .45rem; border-radius:20px; font-weight:700; }
/* Main */
.main { margin-left:232px; padding:1.5rem 1.8rem 3rem; }
.topbar { display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:1.4rem; flex-wrap:wrap; }
.topbar h1 { font-size:1.35rem; font-weight:800; letter-spacing:-.02em; margin:0; }
.card { background:var(--card); border:1px solid var(--line); border-radius:16px; padding:1.2rem 1.4rem; }
.grid { display:grid; gap:1rem; }
.btn { display:inline-flex; align-items:center; gap:.45rem; padding:.55rem 1rem; border-radius:10px; border:1.5px solid var(--line);
  background:#fff; color:var(--txt); font-family:inherit; font-size:.85rem; font-weight:600; cursor:pointer; transition:all .15s; }
.btn:hover { border-color:var(--ern-light); color:var(--ern); }
.btn.pri { background:linear-gradient(135deg,var(--ern),var(--ern-light)); color:#fff; border:0; }
.btn.pri:hover { box-shadow:0 8px 18px rgba(0,88,78,.3); transform:translateY(-1px); }
.btn.tehlike { color:#B3261E; } .btn.tehlike:hover { border-color:#B3261E; }
table.tbl { width:100%; border-collapse:collapse; font-size:.83rem; }
.tbl th { text-align:left; padding:.6rem .7rem; font-size:.68rem; text-transform:uppercase; letter-spacing:.05em; color:var(--mut); border-bottom:2px solid var(--line); white-space:nowrap; }
.tbl td { padding:.55rem .7rem; border-bottom:1px solid var(--line); vertical-align:middle; }
.tbl tr:hover td { background:#F7FAF9; }
.tag { display:inline-block; padding:.14rem .55rem; border-radius:20px; font-size:.68rem; font-weight:600; background:#E8F2F0; color:var(--ern); white-space:nowrap; }
.tag.sari { background:#F7EFD8; color:#8a6d1d; } .tag.kirmizi { background:#FBE9E7; color:#B3261E; } .tag.gri { background:#EEF1F0; color:#667; }
input.frm, select.frm, textarea.frm { width:100%; padding:.6rem .8rem; border:1.5px solid var(--line); border-radius:10px;
  font-family:inherit; font-size:.87rem; outline:none; background:#fff; transition:border-color .15s, box-shadow .15s; }
.frm:focus { border-color:var(--ern-light); box-shadow:0 0 0 3px rgba(0,122,106,.1); }
label.flbl { display:block; font-size:.72rem; font-weight:600; color:var(--mut); margin:.7rem 0 .3rem; }
.mesaj { padding:.8rem 1rem; border-radius:12px; font-size:.85rem; margin-bottom:1rem; }
.mesaj.ok { background:#E5F5EF; color:#0B6B4D; border:1px solid #BFE5D6; }
.mesaj.err { background:#FBE9E7; color:#B3261E; border:1px solid #F3C6C1; }
.stat { display:flex; align-items:center; gap:.9rem; }
.stat-icon { width:46px; height:46px; border-radius:13px; display:flex; align-items:center; justify-content:center; font-size:1.25rem;
  background:linear-gradient(135deg,rgba(0,88,78,.1),rgba(0,201,177,.14)); color:var(--ern); flex-shrink:0; }
.stat b { font-size:1.35rem; display:block; letter-spacing:-.02em; }
.stat span { font-size:.72rem; color:var(--mut); }
.menu-btn { display:none; }
@media (max-width:900px) {
  .side { transform:translateX(-100%); } .side.acik { transform:none; }
  .main { margin-left:0; padding:1rem; }
  .menu-btn { display:inline-flex; }
}
</style>
</head>
<body>
<aside class="side" id="side">
    <div class="side-logo">
        <div class="side-logo-icon"><i class="bi bi-truck-front-fill"></i></div>
        <div><strong>ERN Varlık</strong><span>Yönetim ve Takip</span></div>
    </div>
    <nav>
        <?php foreach ($menu as $m): if (!in_array(rol(), $m[3], true)) continue; ?>
        <a href="<?= $m[0] ?>" class="<?= $sayfa === $m[0] ? 'on' : '' ?>"><i class="bi <?= $m[1] ?>"></i> <?= $m[2] ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="side-kur">
        <i class="bi bi-currency-exchange"></i> TCMB Kurları
        <div>EUR: <b><?= number_format((float)$kur['eur'], 4, ',', '.') ?></b> &nbsp; USD: <b><?= number_format((float)$kur['usd'], 4, ',', '.') ?></b></div>
        <div class="tarih">Güncelleme: <?= $kur['guncelleme'] ? date('d.m.Y H:i', strtotime($kur['guncelleme'])) : '—' ?>
        <a href="kur_guncelle.php" style="color:var(--ern-teal)" title="Şimdi güncelle"><i class="bi bi-arrow-clockwise"></i></a></div>
    </div>
    <div class="side-user">
        <i class="bi bi-person-circle" style="font-size:1.4rem;opacity:.8"></i>
        <div style="flex:1;line-height:1.3"><?= e(kullanici()['ad']) ?><br><span class="rolTag"><?= e(rol()) ?></span></div>
        <a href="logout.php" style="color:rgba(255,255,255,.6)" title="Çıkış"><i class="bi bi-box-arrow-right"></i></a>
    </div>
</aside>
<main class="main">
<div class="topbar">
    <h1><button class="btn menu-btn" onclick="document.getElementById('side').classList.toggle('acik')"><i class="bi bi-list"></i></button>
    <?= e($baslik ?? 'Panel') ?></h1>
    <div><?= $topbar_sag ?? '' ?></div>
</div>
