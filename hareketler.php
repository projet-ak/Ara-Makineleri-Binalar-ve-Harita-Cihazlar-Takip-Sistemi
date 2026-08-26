<?php
$baslik = 'Sevk & Mali Hareketler';
require_once __DIR__ . '/inc/auth.php';
$topbar_sag = yetki('admin', 'saha') ? '<a class="btn pri" href="hareket_form.php"><i class="bi bi-plus-lg"></i> Yeni Hareket</a>' : '';
require_once __DIR__ . '/inc/header.php';
$d = db();
$tur = $_GET['tur'] ?? '';
$q = trim($_GET['q'] ?? '');
$w = ['1=1']; $p = [];
if ($tur) { $w[] = 'h.islem_turu = ?'; $p[] = $tur; }
if ($q) { $w[] = '(h.plaka LIKE ? OR h.cins_tam LIKE ? OR h.aciklama LIKE ? OR v.cins LIKE ? OR v.marka LIKE ?)';
          array_push($p, "%$q%", "%$q%", "%$q%", "%$q%", "%$q%"); }
$where = implode(' AND ', $w);
$sayfa_no = max(1, (int)($_GET['s'] ?? 1)); $limit = 60; $off = ($sayfa_no - 1) * $limit;
$toplam = $d->prepare("SELECT COUNT(*) c, SUM(h.gelir) g, SUM(h.gider) x FROM hareketler h LEFT JOIN varliklar v ON v.id=h.varlik_id WHERE $where");
$toplam->execute($p); $toplam = $toplam->fetch();
$st = $d->prepare("SELECT h.*, v.cins, v.marka, v.model FROM hareketler h LEFT JOIN varliklar v ON v.id=h.varlik_id
    WHERE $where ORDER BY h.islem_tarihi DESC, h.id DESC LIMIT $limit OFFSET $off");
$st->execute($p); $rows = $st->fetchAll();
?>
<div class="card" style="margin-bottom:1rem">
<form method="get" style="display:flex;gap:.6rem;align-items:end;flex-wrap:wrap">
    <div style="flex:1;min-width:200px"><label class="flbl">Ara</label><input class="frm" name="q" value="<?= e($q) ?>" placeholder="Plaka, açıklama, cins..."></div>
    <div><label class="flbl">İşlem Türü</label><select class="frm" name="tur"><option value="">Tümü</option>
        <?php foreach (['SEVK','BAKIM ONARIM','HAKEDİŞ','SİGORTA','MUAYENE','DİĞER'] as $t): ?>
        <option <?= $t === $tur ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select></div>
    <button class="btn pri"><i class="bi bi-search"></i> Filtrele</button>
</form>
</div>
<div class="card">
<div style="display:flex;gap:1.4rem;margin-bottom:.8rem;font-size:.8rem;color:var(--mut)">
    <span><?= $toplam['c'] ?> kayıt</span>
    <span>Toplam gelir: <b style="color:#0B6B4D"><?= tl($toplam['g']) ?></b></span>
    <span>Toplam gider: <b style="color:#B3261E"><?= tl($toplam['x']) ?></b></span>
</div>
<div style="overflow-x:auto">
<table class="tbl">
<tr><th>Tarih</th><th>Varlık</th><th>Plaka</th><th>Lokasyon</th><th>İşlem</th><th>Açıklama</th><th style="text-align:right">Gelir</th><th style="text-align:right">Gider</th></tr>
<?php foreach ($rows as $h): ?>
<tr>
    <td><?= $h['islem_tarihi'] ? date('d.m.Y', strtotime($h['islem_tarihi'])) : '—' ?></td>
    <td><?php if ($h['varlik_id']): ?><a href="varlik.php?id=<?= $h['varlik_id'] ?>"><?= e(trim(($h['cins'] ?? '') . ' ' . ($h['marka'] ?? ''))) ?></a>
        <?php else: ?><?= e(mb_substr($h['cins_tam'] ?? '—', 0, 36)) ?><?php endif; ?></td>
    <td><span class="tag gri"><?= e($h['plaka'] ?: '—') ?></span></td>
    <td style="font-size:.75rem"><?= e($h['lokasyon']) ?></td>
    <td><span class="tag <?= $h['islem_turu'] === 'HAKEDİŞ' ? '' : ($h['islem_turu'] === 'SEVK' ? 'sari' : 'kirmizi') ?>"><?= e($h['islem_turu']) ?></span></td>
    <td style="max-width:260px"><?= e($h['aciklama']) ?></td>
    <td style="text-align:right;color:#0B6B4D"><?= $h['gelir'] ? tl($h['gelir']) : '' ?></td>
    <td style="text-align:right;color:#B3261E"><?= $h['gider'] ? tl($h['gider']) : '' ?></td>
</tr>
<?php endforeach; ?>
</table>
</div>
<?php $ts = ceil($toplam['c'] / $limit); if ($ts > 1): ?>
<div style="margin-top:1rem;display:flex;gap:.3rem;flex-wrap:wrap">
<?php for ($i = 1; $i <= $ts; $i++): $g = $_GET; $g['s'] = $i; ?>
    <a class="btn <?= $i === $sayfa_no ? 'pri' : '' ?>" style="padding:.3rem .7rem" href="?<?= e(http_build_query($g)) ?>"><?= $i ?></a>
<?php endfor; ?>
</div>
<?php endif; ?>
</div>
<?php require __DIR__ . '/inc/footer.php'; ?>
