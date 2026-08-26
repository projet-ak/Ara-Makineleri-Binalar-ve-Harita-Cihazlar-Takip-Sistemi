<?php
$baslik = 'Raporlar';
require_once __DIR__ . '/inc/auth.php';
yetki_zorunlu('admin', 'yonetim');
require_once __DIR__ . '/inc/header.php';
$d = db();
$yil = (int)($_GET['yil'] ?? 2026);
$grup = in_array($_GET['grup'] ?? '', ['lokasyon', 'cins', 'sahiplik'], true) ? $_GET['grup'] : 'lokasyon';
$st = $d->prepare("SELECT $grup grup_ad, COUNT(*) adet,
    SUM(kira_geliri) kira, SUM(bakim_gideri) bakim, SUM(operator_gideri) operator_g,
    SUM(sigorta_gideri) sigorta, SUM(kar_zarar) kz, SUM(guncel_tl) deger
    FROM varliklar WHERE yil = ? AND aktif = 1 GROUP BY $grup ORDER BY kz DESC");
$st->execute([$yil]); $rows = $st->fetchAll();
$gen = $d->prepare("SELECT COUNT(*) adet, SUM(kira_geliri) kira, SUM(bakim_gideri) bakim, SUM(operator_gideri) op,
    SUM(sigorta_gideri) sig, SUM(kar_zarar) kz, SUM(amortisman_tl) amort FROM varliklar WHERE yil = ? AND aktif = 1");
$gen->execute([$yil]); $gen = $gen->fetch();
$aylik = $d->query("SELECT DATE_FORMAT(islem_tarihi, '%Y-%m') ay, SUM(gelir) g, SUM(gider) x
    FROM hareketler WHERE islem_tarihi IS NOT NULL GROUP BY ay ORDER BY ay DESC LIMIT 12")->fetchAll();
?>
<div class="card" style="margin-bottom:1rem">
<form method="get" style="display:flex;gap:.6rem;align-items:end">
    <div><label class="flbl">Yıl</label><select class="frm" name="yil">
        <option value="2026" <?= $yil === 2026 ? 'selected' : '' ?>>2026</option>
        <option value="2025" <?= $yil === 2025 ? 'selected' : '' ?>>2025</option></select></div>
    <div><label class="flbl">Gruplama</label><select class="frm" name="grup">
        <option value="lokasyon" <?= $grup === 'lokasyon' ? 'selected' : '' ?>>Lokasyon</option>
        <option value="cins" <?= $grup === 'cins' ? 'selected' : '' ?>>Cins</option>
        <option value="sahiplik" <?= $grup === 'sahiplik' ? 'selected' : '' ?>>Sahiplik</option></select></div>
    <button class="btn pri"><i class="bi bi-arrow-repeat"></i> Uygula</button>
</form>
</div>
<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(190px,1fr));margin-bottom:1rem">
    <div class="card stat"><div class="stat-icon"><i class="bi bi-truck"></i></div><div><b><?= $gen['adet'] ?></b><span>Varlık</span></div></div>
    <div class="card stat"><div class="stat-icon" style="color:#0B6B4D"><i class="bi bi-graph-up"></i></div><div><b><?= tl($gen['kira']) ?></b><span>Kira Geliri</span></div></div>
    <div class="card stat"><div class="stat-icon" style="color:#B3261E"><i class="bi bi-wrench"></i></div><div><b><?= tl($gen['bakim']) ?></b><span>Bakım Gideri</span></div></div>
    <div class="card stat"><div class="stat-icon" style="color:#B3261E"><i class="bi bi-person-gear"></i></div><div><b><?= tl($gen['op']) ?></b><span>Operatör Gideri</span></div></div>
    <div class="card stat"><div class="stat-icon"><i class="bi bi-shield-check"></i></div><div><b><?= tl($gen['sig']) ?></b><span>Sigorta</span></div></div>
    <div class="card stat"><div class="stat-icon"><i class="bi bi-cash-coin"></i></div><div><b><?= tl($gen['kz']) ?></b><span>Kar / Zarar</span></div></div>
</div>
<div class="grid" style="grid-template-columns:2fr 1fr;align-items:start">
    <div class="card">
        <h3 style="margin:0 0 .8rem;font-size:.95rem"><i class="bi bi-bar-chart-line"></i> <?= ucfirst($grup) ?> Bazında Mali Özet (<?= $yil ?>)</h3>
        <div style="overflow-x:auto">
        <table class="tbl">
        <tr><th><?= ucfirst($grup) ?></th><th style="text-align:right">Adet</th><th style="text-align:right">Kira Geliri</th>
            <th style="text-align:right">Bakım</th><th style="text-align:right">Operatör</th><th style="text-align:right">Sigorta</th>
            <th style="text-align:right">Kar/Zarar</th></tr>
        <?php foreach ($rows as $r): ?>
        <tr><td style="max-width:220px"><?= e($r['grup_ad'] ?: '(boş)') ?></td>
            <td style="text-align:right"><?= $r['adet'] ?></td>
            <td style="text-align:right;color:#0B6B4D"><?= tl($r['kira']) ?></td>
            <td style="text-align:right;color:#B3261E"><?= tl($r['bakim']) ?></td>
            <td style="text-align:right;color:#B3261E"><?= tl($r['operator_g']) ?></td>
            <td style="text-align:right"><?= tl($r['sigorta']) ?></td>
            <td style="text-align:right;font-weight:700;color:<?= (float)$r['kz'] >= 0 ? '#0B6B4D' : '#B3261E' ?>"><?= tl($r['kz']) ?></td></tr>
        <?php endforeach; ?>
        </table>
        </div>
    </div>
    <div class="card">
        <h3 style="margin:0 0 .8rem;font-size:.95rem"><i class="bi bi-calendar3"></i> Aylık Hareket Özeti</h3>
        <table class="tbl">
        <tr><th>Ay</th><th style="text-align:right">Gelir</th><th style="text-align:right">Gider</th></tr>
        <?php foreach ($aylik as $a): ?>
        <tr><td><?= e($a['ay']) ?></td>
            <td style="text-align:right;color:#0B6B4D"><?= tl($a['g']) ?></td>
            <td style="text-align:right;color:#B3261E"><?= tl($a['x']) ?></td></tr>
        <?php endforeach; ?>
        </table>
    </div>
</div>
<?php require __DIR__ . '/inc/footer.php'; ?>
