<?php
$baslik = 'Çalışma Saatleri';
require_once __DIR__ . '/inc/auth.php';
$topbar_sag = yetki('admin', 'saha') ? '<a class="btn pri" href="calisma_form.php"><i class="bi bi-plus-lg"></i> Yeni Kayıt</a>' : '';
require_once __DIR__ . '/inc/header.php';
$d = db();
$q = trim($_GET['q'] ?? '');
$ay = $_GET['ay'] ?? '';
$aylar = ['OCAK','ŞUBAT','MART','NİSAN','MAYIS','HAZİRAN','TEMMUZ','AĞUSTOS','EYLÜL','EKİM','KASIM','ARALIK'];
$w = ['1=1']; $p = [];
if ($q) { $w[] = '(c.plaka LIKE ? OR v.cins LIKE ? OR v.marka LIKE ? OR c.lokasyon LIKE ?)'; array_push($p, "%$q%", "%$q%", "%$q%", "%$q%"); }
if ($ay) { $w[] = 'c.ay = ?'; $p[] = $ay; }
$where = implode(' AND ', $w);
$sayfa_no = max(1, (int)($_GET['s'] ?? 1)); $limit = 60; $off = ($sayfa_no - 1) * $limit;
$toplam = $d->prepare("SELECT COUNT(*) c FROM calisma_saatleri c LEFT JOIN varliklar v ON v.id=c.varlik_id WHERE $where");
$toplam->execute($p); $toplam = $toplam->fetch()['c'];
$st = $d->prepare("SELECT c.*, v.cins, v.marka, v.model, v.id vid FROM calisma_saatleri c LEFT JOIN varliklar v ON v.id=c.varlik_id
    WHERE $where ORDER BY c.yil DESC, FIELD(c.ay,'" . implode("','", $aylar) . "') DESC, c.id DESC LIMIT $limit OFFSET $off");
$st->execute($p); $rows = $st->fetchAll();
?>
<div class="card" style="margin-bottom:1rem">
<form method="get" style="display:flex;gap:.6rem;align-items:end;flex-wrap:wrap">
    <div style="flex:1;min-width:200px"><label class="flbl">Ara</label><input class="frm" name="q" value="<?= e($q) ?>" placeholder="Plaka, cins, lokasyon..."></div>
    <div><label class="flbl">Ay</label><select class="frm" name="ay"><option value="">Tümü</option>
        <?php foreach ($aylar as $a): ?><option <?= $a === $ay ? 'selected' : '' ?>><?= $a ?></option><?php endforeach; ?></select></div>
    <button class="btn pri"><i class="bi bi-search"></i> Filtrele</button>
</form>
</div>
<div class="card">
<span style="font-size:.8rem;color:var(--mut)"><?= $toplam ?> kayıt</span>
<div style="overflow-x:auto">
<table class="tbl">
<tr><th>Varlık</th><th>Plaka</th><th>Lokasyon</th><th>Yıl</th><th>Ay</th><th>Güncel Saat/KM</th><th>Son Bakım</th><th>Son Bakım T.</th><th>Muayene</th></tr>
<?php foreach ($rows as $c): ?>
<tr>
    <td><?php if ($c['vid']): ?><a href="varlik.php?id=<?= $c['vid'] ?>"><?= e(trim($c['cins'] . ' ' . $c['marka'])) ?></a><?php else: ?>—<?php endif; ?></td>
    <td><span class="tag gri"><?= e($c['plaka']) ?></span></td>
    <td style="font-size:.75rem"><?= e($c['lokasyon']) ?></td>
    <td><?= $c['yil'] ?></td><td><?= e($c['ay']) ?></td>
    <td style="font-weight:600"><?= e($c['guncel_deger'] ?: '—') ?></td>
    <td><?= e($c['son_bakim'] ?: '—') ?></td>
    <td><?= $c['son_bakim_tarihi'] ? date('d.m.Y', strtotime($c['son_bakim_tarihi'])) : '—' ?></td>
    <td><?php if ($c['muayene_tarihi']): $gec = strtotime($c['muayene_tarihi']) < time(); ?>
        <span class="tag <?= $gec ? 'kirmizi' : '' ?>"><?= date('d.m.Y', strtotime($c['muayene_tarihi'])) ?></span><?php else: ?>—<?php endif; ?></td>
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
