<?php
$baslik = 'Varlıklar';
require_once __DIR__ . '/inc/header.php';
$d = db();
$yil = (int)($_GET['yil'] ?? 2026);
$q   = trim($_GET['q'] ?? '');
$cins = $_GET['cins'] ?? '';
$lok  = $_GET['lok'] ?? '';
$sah  = $_GET['sah'] ?? '';

$w = ['yil = ?', 'aktif = 1']; $p = [$yil];
if ($q)   { $w[] = '(cins LIKE ? OR marka LIKE ? OR model LIKE ? OR plaka LIKE ? OR sasi_no LIKE ? OR ifs_nesne_no LIKE ?)';
            array_push($p, "%$q%", "%$q%", "%$q%", "%$q%", "%$q%", "%$q%"); }
if ($cins) { $w[] = 'cins = ?'; $p[] = $cins; }
if ($lok)  { $w[] = 'lokasyon = ?'; $p[] = $lok; }
if ($sah)  { $w[] = 'sahiplik = ?'; $p[] = $sah; }
$where = implode(' AND ', $w);

$sayfa_no = max(1, (int)($_GET['s'] ?? 1)); $limit = 50; $off = ($sayfa_no - 1) * $limit;
$toplam = $d->prepare("SELECT COUNT(*) c FROM varliklar WHERE $where"); $toplam->execute($p); $toplam = $toplam->fetch()['c'];
$st = $d->prepare("SELECT * FROM varliklar WHERE $where ORDER BY s_no LIMIT $limit OFFSET $off"); $st->execute($p);
$rows = $st->fetchAll();

$cinsler = $d->prepare("SELECT DISTINCT cins FROM varliklar WHERE yil=? AND aktif=1 ORDER BY cins"); $cinsler->execute([$yil]); $cinsler = $cinsler->fetchAll(PDO::FETCH_COLUMN);
$loklar  = $d->prepare("SELECT DISTINCT lokasyon FROM varliklar WHERE yil=? AND aktif=1 AND lokasyon IS NOT NULL ORDER BY lokasyon"); $loklar->execute([$yil]); $loklar = $loklar->fetchAll(PDO::FETCH_COLUMN);
$sahlar  = $d->prepare("SELECT DISTINCT sahiplik FROM varliklar WHERE yil=? AND aktif=1 AND sahiplik IS NOT NULL ORDER BY sahiplik"); $sahlar->execute([$yil]); $sahlar = $sahlar->fetchAll(PDO::FETCH_COLUMN);
?>
<div class="card" style="margin-bottom:1rem">
<form method="get" style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr auto auto;gap:.6rem;align-items:end">
    <div><label class="flbl">Ara (cins, marka, plaka, şasi, IFS no)</label>
        <input class="frm" name="q" value="<?= e($q) ?>" placeholder="Ara..."></div>
    <div><label class="flbl">Cins</label><select class="frm" name="cins"><option value="">Tümü</option>
        <?php foreach ($cinsler as $c): ?><option <?= $c === $cins ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select></div>
    <div><label class="flbl">Lokasyon</label><select class="frm" name="lok"><option value="">Tümü</option>
        <?php foreach ($loklar as $l): ?><option <?= $l === $lok ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div><label class="flbl">Sahiplik</label><select class="frm" name="sah"><option value="">Tümü</option>
        <?php foreach ($sahlar as $s): ?><option <?= $s === $sah ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?></select></div>
    <div><label class="flbl">Yıl</label><select class="frm" name="yil">
        <option value="2026" <?= $yil === 2026 ? 'selected' : '' ?>>2026</option>
        <option value="2025" <?= $yil === 2025 ? 'selected' : '' ?>>2025 (arşiv)</option></select></div>
    <button class="btn pri"><i class="bi bi-search"></i> Filtrele</button>
</form>
</div>
<div class="card">
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.8rem">
    <span style="font-size:.8rem;color:var(--mut)"><?= $toplam ?> kayıt</span>
    <?php if (yetki('admin')): ?><a class="btn pri" href="varlik_form.php"><i class="bi bi-plus-lg"></i> Yeni Varlık</a><?php endif; ?>
</div>
<div style="overflow-x:auto">
<table class="tbl">
<tr><th>No</th><th>Cins</th><th>Marka / Model</th><th>Plaka / Seri</th><th>Sahiplik</th><th>Lokasyon</th>
<?php if (yetki('admin','yonetim')): ?><th style="text-align:right">Kira Geliri</th><th style="text-align:right">Kar/Zarar</th><?php endif; ?><th></th></tr>
<?php foreach ($rows as $r): ?>
<tr>
    <td><?= e(rtrim(rtrim((string)$r['s_no'], '0'), '.')) ?></td>
    <td><a href="varlik.php?id=<?= $r['id'] ?>" style="font-weight:600"><?= e($r['cins']) ?></a></td>
    <td><?= e(trim($r['marka'] . ' ' . $r['model'])) ?></td>
    <td><span class="tag gri"><?= e($r['plaka'] ?: '—') ?></span></td>
    <td style="font-size:.75rem"><?= e($r['sahiplik']) ?></td>
    <td style="font-size:.75rem;max-width:180px"><?= e($r['lokasyon']) ?></td>
    <?php if (yetki('admin','yonetim')): ?>
    <td style="text-align:right"><?= tl($r['kira_geliri']) ?></td>
    <td style="text-align:right;font-weight:600;color:<?= (float)$r['kar_zarar'] >= 0 ? '#0B6B4D' : '#B3261E' ?>"><?= tl($r['kar_zarar']) ?></td>
    <?php endif; ?>
    <td><a href="varlik.php?id=<?= $r['id'] ?>" class="btn" style="padding:.3rem .6rem"><i class="bi bi-eye"></i></a></td>
</tr>
<?php endforeach; ?>
</table>
</div>
<?php $ts = ceil($toplam / $limit); if ($ts > 1): ?>
<div style="margin-top:1rem;display:flex;gap:.3rem;flex-wrap:wrap">
<?php for ($i = 1; $i <= $ts; $i++): $g = $_GET; $g['s'] = $i; ?>
    <a class="btn <?= $i === $sayfa_no ? 'pri' : '' ?>" style="padding:.3rem .7rem" href="?<?= e(http_build_query($g)) ?>"><?= $i ?></a>
<?php endfor; ?>
</div>
<?php endif; ?>
</div>
<?php require __DIR__ . '/inc/footer.php'; ?>
