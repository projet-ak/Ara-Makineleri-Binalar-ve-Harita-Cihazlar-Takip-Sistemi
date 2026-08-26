<?php
$baslik = 'Panel';
require_once __DIR__ . '/inc/header.php';
$d = db();
$t = $d->query("SELECT COUNT(*) c FROM varliklar WHERE yil=2026 AND aktif=1")->fetch()['c'];
$lok = $d->query("SELECT COUNT(DISTINCT lokasyon) c FROM varliklar WHERE yil=2026 AND aktif=1 AND lokasyon IS NOT NULL")->fetch()['c'];
$mali = $d->query("SELECT SUM(kira_geliri) g, SUM(bakim_gideri) b, SUM(kar_zarar) k FROM varliklar WHERE yil=2026 AND aktif=1")->fetch();
$har = $d->query("SELECT SUM(gelir) g, SUM(gider) x, COUNT(*) c FROM hareketler")->fetch();
$son_hareket = $d->query("SELECT h.*, v.cins, v.marka, v.model FROM hareketler h LEFT JOIN varliklar v ON v.id=h.varlik_id ORDER BY h.islem_tarihi DESC, h.id DESC LIMIT 10")->fetchAll();
$muayene = $d->query("SELECT c.*, v.cins, v.marka, v.model FROM calisma_saatleri c LEFT JOIN varliklar v ON v.id=c.varlik_id
    WHERE c.muayene_tarihi IS NOT NULL AND c.muayene_tarihi < DATE_ADD(CURDATE(), INTERVAL 60 DAY)
    GROUP BY c.plaka ORDER BY c.muayene_tarihi LIMIT 8")->fetchAll();
$cinsler = $d->query("SELECT cins, COUNT(*) c FROM varliklar WHERE yil=2026 AND aktif=1 GROUP BY cins ORDER BY c DESC LIMIT 8")->fetchAll();
?>
<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(210px,1fr));margin-bottom:1rem">
    <div class="card stat"><div class="stat-icon"><i class="bi bi-truck"></i></div><div><b><?= $t ?></b><span>Aktif Varlık (2026)</span></div></div>
    <div class="card stat"><div class="stat-icon"><i class="bi bi-geo-alt"></i></div><div><b><?= $lok ?></b><span>Lokasyon</span></div></div>
    <?php if (yetki('admin','yonetim')): ?>
    <div class="card stat"><div class="stat-icon" style="color:#0B6B4D"><i class="bi bi-graph-up-arrow"></i></div><div><b><?= tl($mali['g']) ?></b><span>2026 Kira Geliri</span></div></div>
    <div class="card stat"><div class="stat-icon" style="color:#B3261E"><i class="bi bi-wrench-adjustable"></i></div><div><b><?= tl($mali['b']) ?></b><span>2026 Bakım Gideri</span></div></div>
    <div class="card stat"><div class="stat-icon"><i class="bi bi-cash-coin"></i></div><div><b><?= tl($mali['k']) ?></b><span>Kar / Zarar</span></div></div>
    <?php endif; ?>
</div>
<div class="grid" style="grid-template-columns:2fr 1fr;align-items:start">
    <div class="card">
        <h3 style="margin:0 0 .8rem;font-size:.95rem"><i class="bi bi-clock-history"></i> Son Hareketler</h3>
        <table class="tbl"><tr><th>Tarih</th><th>Varlık</th><th>İşlem</th><th>Açıklama</th><th style="text-align:right">Tutar</th></tr>
        <?php foreach ($son_hareket as $h): ?>
        <tr>
            <td><?= $h['islem_tarihi'] ? date('d.m.Y', strtotime($h['islem_tarihi'])) : '—' ?></td>
            <td><?php if ($h['varlik_id']): ?><a href="varlik.php?id=<?= $h['varlik_id'] ?>"><?= e($h['cins'] . ' ' . $h['marka']) ?></a>
                <?php else: ?><?= e(mb_substr($h['cins_tam'] ?? '', 0, 30)) ?><?php endif; ?>
                <span style="color:var(--mut);font-size:.72rem"><?= e($h['plaka']) ?></span></td>
            <td><span class="tag <?= $h['islem_turu'] === 'HAKEDİŞ' ? '' : ($h['islem_turu'] === 'SEVK' ? 'sari' : 'kirmizi') ?>"><?= e($h['islem_turu']) ?></span></td>
            <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($h['aciklama']) ?></td>
            <td style="text-align:right;font-weight:600;<?= $h['gelir'] ? 'color:#0B6B4D' : 'color:#B3261E' ?>">
                <?= $h['gelir'] ? '+' . tl($h['gelir']) : '-' . tl($h['gider']) ?></td>
        </tr>
        <?php endforeach; ?></table>
        <div style="margin-top:.8rem"><a href="hareketler.php" class="btn">Tümünü gör <i class="bi bi-arrow-right"></i></a></div>
    </div>
    <div style="display:grid;gap:1rem">
        <div class="card">
            <h3 style="margin:0 0 .8rem;font-size:.95rem"><i class="bi bi-exclamation-triangle" style="color:#C9A84C"></i> Yaklaşan / Geçmiş Muayeneler</h3>
            <?php if (!$muayene): ?><div style="color:var(--mut);font-size:.83rem">Önümüzdeki 60 gün içinde muayene yok.</div><?php endif; ?>
            <?php foreach ($muayene as $m): $gecmis = strtotime($m['muayene_tarihi']) < time(); ?>
            <div style="display:flex;justify-content:space-between;padding:.45rem 0;border-bottom:1px solid var(--line);font-size:.8rem">
                <div><?= e(($m['cins'] ?? '') . ' ' . ($m['marka'] ?? '')) ?><br><span style="color:var(--mut);font-size:.7rem"><?= e($m['plaka']) ?></span></div>
                <span class="tag <?= $gecmis ? 'kirmizi' : 'sari' ?>"><?= date('d.m.Y', strtotime($m['muayene_tarihi'])) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="card">
            <h3 style="margin:0 0 .8rem;font-size:.95rem"><i class="bi bi-pie-chart"></i> Varlık Dağılımı</h3>
            <?php $max = $cinsler ? $cinsler[0]['c'] : 1; foreach ($cinsler as $c): ?>
            <div style="margin-bottom:.5rem;font-size:.76rem">
                <div style="display:flex;justify-content:space-between"><span><?= e($c['cins']) ?></span><b><?= $c['c'] ?></b></div>
                <div style="height:6px;background:var(--line);border-radius:4px;overflow:hidden">
                    <div style="height:100%;width:<?= round($c['c'] / $max * 100) ?>%;background:linear-gradient(90deg,var(--ern),var(--ern-teal))"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php require __DIR__ . '/inc/footer.php'; ?>
