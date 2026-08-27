<?php
$baslik = 'Panel';
require_once __DIR__ . '/inc/header.php';
$d = db();
$mali_gorur = yetki('admin', 'yonetim');

$t = $d->query("SELECT COUNT(*) c FROM varliklar WHERE yil=2026 AND aktif=1")->fetch()['c'];
$lok = $d->query("SELECT COUNT(DISTINCT lokasyon) c FROM varliklar WHERE yil=2026 AND aktif=1 AND lokasyon IS NOT NULL")->fetch()['c'];
$mali = $d->query("SELECT SUM(kira_geliri) g, SUM(bakim_gideri) b, SUM(operator_gideri) o, SUM(kar_zarar) k, SUM(guncel_tl) deger FROM varliklar WHERE yil=2026 AND aktif=1")->fetch();

// Aylık gelir/gider (son 12 ay, hareketlerden)
$aylik = $d->query("SELECT DATE_FORMAT(islem_tarihi,'%Y-%m') ay, SUM(COALESCE(gelir,0)) g, SUM(COALESCE(gider,0)) x
    FROM hareketler WHERE islem_tarihi IS NOT NULL AND islem_tarihi >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY ay ORDER BY ay")->fetchAll();

// Cins dağılımı (ilk 9 + diğer)
$cins_tum = $d->query("SELECT cins, COUNT(*) c FROM varliklar WHERE yil=2026 AND aktif=1 GROUP BY cins ORDER BY c DESC")->fetchAll();
$cins_grafik = array_slice($cins_tum, 0, 9);
$diger = array_sum(array_column(array_slice($cins_tum, 9), 'c'));
if ($diger > 0) $cins_grafik[] = ['cins' => 'DİĞER', 'c' => $diger];

// Lokasyon bazlı kar/zarar (ilk 10)
$lok_kz = $mali_gorur ? $d->query("SELECT lokasyon, SUM(kar_zarar) kz, COUNT(*) adet FROM varliklar
    WHERE yil=2026 AND aktif=1 AND lokasyon IS NOT NULL GROUP BY lokasyon
    ORDER BY ABS(SUM(kar_zarar)) DESC LIMIT 10")->fetchAll() : [];

// İşlem türü dağılımı (son 6 ay)
$tur_dag = $d->query("SELECT islem_turu, COUNT(*) c, SUM(COALESCE(gelir,0)) g, SUM(COALESCE(gider,0)) x
    FROM hareketler WHERE islem_tarihi >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY islem_turu ORDER BY c DESC")->fetchAll();

// En çok bakım gideri olan 5 varlık (son 6 ayın hareketlerinden)
$bakim_top = $d->query("SELECT h.varlik_id, v.cins, v.marka, v.model, v.plaka, SUM(h.gider) toplam, COUNT(*) adet
    FROM hareketler h JOIN varliklar v ON v.id=h.varlik_id
    WHERE h.islem_turu='BAKIM ONARIM' AND h.islem_tarihi >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY h.varlik_id ORDER BY toplam DESC LIMIT 5")->fetchAll();

$son_hareket = $d->query("SELECT h.*, v.cins, v.marka FROM hareketler h LEFT JOIN varliklar v ON v.id=h.varlik_id
    ORDER BY h.islem_tarihi DESC, h.id DESC LIMIT 8")->fetchAll();
$muayene = $d->query("SELECT c.plaka, c.muayene_tarihi, v.id vid, v.cins, v.marka FROM calisma_saatleri c
    LEFT JOIN varliklar v ON v.id=c.varlik_id
    WHERE c.muayene_tarihi IS NOT NULL AND c.muayene_tarihi < DATE_ADD(CURDATE(), INTERVAL 60 DAY)
    GROUP BY c.plaka ORDER BY c.muayene_tarihi LIMIT 8")->fetchAll();

// Bu ay özeti
$bu_ay = $d->query("SELECT SUM(COALESCE(gelir,0)) g, SUM(COALESCE(gider,0)) x, COUNT(*) c FROM hareketler
    WHERE DATE_FORMAT(islem_tarihi,'%Y-%m') = DATE_FORMAT(CURDATE(),'%Y-%m')")->fetch();
$ay_adlari = [1=>'Oca',2=>'Şub',3=>'Mar',4=>'Nis',5=>'May',6=>'Haz',7=>'Tem',8=>'Ağu',9=>'Eyl',10=>'Eki',11=>'Kas',12=>'Ara'];
$grafik_ay = array_map(fn($a) => $ay_adlari[(int)substr($a['ay'], 5)] . ' ' . substr($a['ay'], 2, 2), $aylik);
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<style>
.kpi { position:relative; overflow:hidden; }
.kpi::after { content:''; position:absolute; right:-24px; top:-24px; width:90px; height:90px; border-radius:50%;
  background:radial-gradient(circle, rgba(0,201,177,.12), transparent 70%); }
.mini-tbl td { padding:.42rem .5rem; font-size:.78rem; }
.chart-box { position:relative; }
.bolum-baslik { margin:0 0 .9rem; font-size:.95rem; display:flex; align-items:center; gap:.45rem; }
.bolum-baslik i { color:var(--ern-light); }
</style>

<!-- KPI kartları -->
<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr));margin-bottom:1rem">
    <div class="card stat kpi"><div class="stat-icon"><i class="bi bi-truck"></i></div>
        <div><b><?= $t ?></b><span>Aktif Varlık (2026)</span></div></div>
    <div class="card stat kpi"><div class="stat-icon"><i class="bi bi-geo-alt"></i></div>
        <div><b><?= $lok ?></b><span>Lokasyon</span></div></div>
    <div class="card stat kpi"><div class="stat-icon" style="color:#0B6B4D"><i class="bi bi-calendar-check"></i></div>
        <div><b><?= $bu_ay['c'] ?></b><span>Bu Ay Hareket · <?= tl($bu_ay['g']) ?> gelir</span></div></div>
    <?php if ($mali_gorur): ?>
    <div class="card stat kpi"><div class="stat-icon" style="color:#0B6B4D"><i class="bi bi-graph-up-arrow"></i></div>
        <div><b><?= tl($mali['g']) ?></b><span>2026 Kira Geliri</span></div></div>
    <div class="card stat kpi"><div class="stat-icon" style="color:#B3261E"><i class="bi bi-wrench-adjustable"></i></div>
        <div><b><?= tl($mali['b']) ?></b><span>2026 Bakım Gideri</span></div></div>
    <div class="card stat kpi"><div class="stat-icon"><i class="bi bi-cash-coin"></i></div>
        <div><b style="color:<?= (float)$mali['k'] >= 0 ? '#0B6B4D' : '#B3261E' ?>"><?= tl($mali['k']) ?></b><span>Kar / Zarar</span></div></div>
    <?php endif; ?>
</div>

<!-- Grafikler: aylık akış + cins dağılımı -->
<div class="grid" style="grid-template-columns:2fr 1fr;margin-bottom:1rem;align-items:stretch">
    <div class="card">
        <h3 class="bolum-baslik"><i class="bi bi-graph-up"></i> Aylık Gelir / Gider Akışı <span style="font-size:.68rem;color:var(--mut);font-weight:400">(hareket kayıtlarından, son 12 ay)</span></h3>
        <div class="chart-box" style="height:280px"><canvas id="akisChart"></canvas></div>
    </div>
    <div class="card">
        <h3 class="bolum-baslik"><i class="bi bi-pie-chart"></i> Varlık Dağılımı</h3>
        <div class="chart-box" style="height:280px"><canvas id="cinsChart"></canvas></div>
    </div>
</div>

<?php if ($mali_gorur): ?>
<!-- Lokasyon kar/zarar + işlem türü -->
<div class="grid" style="grid-template-columns:3fr 2fr;margin-bottom:1rem;align-items:stretch">
    <div class="card">
        <h3 class="bolum-baslik"><i class="bi bi-geo"></i> Lokasyon Bazlı Kar / Zarar (2026)</h3>
        <div class="chart-box" style="height:300px"><canvas id="lokChart"></canvas></div>
    </div>
    <div class="card">
        <h3 class="bolum-baslik"><i class="bi bi-arrow-left-right"></i> İşlem Türü Özeti <span style="font-size:.68rem;color:var(--mut);font-weight:400">(son 6 ay)</span></h3>
        <table class="tbl mini-tbl">
        <tr><th>Tür</th><th style="text-align:right">Adet</th><th style="text-align:right">Gelir</th><th style="text-align:right">Gider</th></tr>
        <?php foreach ($tur_dag as $td): ?>
        <tr><td><span class="tag <?= $td['islem_turu'] === 'HAKEDİŞ' ? '' : ($td['islem_turu'] === 'SEVK' ? 'sari' : 'kirmizi') ?>"><?= e($td['islem_turu']) ?></span></td>
            <td style="text-align:right"><?= $td['c'] ?></td>
            <td style="text-align:right;color:#0B6B4D"><?= (float)$td['g'] ? tl($td['g']) : '—' ?></td>
            <td style="text-align:right;color:#B3261E"><?= (float)$td['x'] ? tl($td['x']) : '—' ?></td></tr>
        <?php endforeach; ?>
        </table>
        <h3 class="bolum-baslik" style="margin-top:1.1rem"><i class="bi bi-tools"></i> En Çok Bakım Gideri (6 ay)</h3>
        <table class="tbl mini-tbl">
        <?php foreach ($bakim_top as $b): ?>
        <tr><td><a href="varlik.php?id=<?= $b['varlik_id'] ?>"><?= e(trim($b['cins'] . ' ' . $b['marka'])) ?></a>
            <span style="color:var(--mut);font-size:.68rem"><?= e($b['plaka']) ?></span></td>
            <td style="text-align:right;white-space:nowrap"><span style="color:#B3261E;font-weight:600"><?= tl($b['toplam']) ?></span>
            <span style="color:var(--mut);font-size:.66rem"> · <?= $b['adet'] ?> işlem</span></td></tr>
        <?php endforeach; ?>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Son hareketler + muayene -->
<div class="grid" style="grid-template-columns:2fr 1fr;align-items:start">
    <div class="card">
        <h3 class="bolum-baslik"><i class="bi bi-clock-history"></i> Son Hareketler</h3>
        <table class="tbl"><tr><th>Tarih</th><th>Varlık</th><th>İşlem</th><th>Açıklama</th><th style="text-align:right">Tutar</th></tr>
        <?php foreach ($son_hareket as $h): ?>
        <tr>
            <td style="white-space:nowrap"><?= $h['islem_tarihi'] ? date('d.m.Y', strtotime($h['islem_tarihi'])) : '—' ?></td>
            <td><?php if ($h['varlik_id']): ?><a href="varlik.php?id=<?= $h['varlik_id'] ?>"><?= e(trim(($h['cins'] ?? '') . ' ' . ($h['marka'] ?? ''))) ?></a>
                <?php else: ?><?= e(mb_substr($h['cins_tam'] ?? '', 0, 28)) ?><?php endif; ?>
                <span style="color:var(--mut);font-size:.7rem"><?= e($h['plaka']) ?></span></td>
            <td><span class="tag <?= $h['islem_turu'] === 'HAKEDİŞ' ? '' : ($h['islem_turu'] === 'SEVK' ? 'sari' : 'kirmizi') ?>"><?= e($h['islem_turu']) ?></span></td>
            <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($h['aciklama']) ?></td>
            <td style="text-align:right;font-weight:600;<?= $h['gelir'] ? 'color:#0B6B4D' : 'color:#B3261E' ?>">
                <?= $h['gelir'] ? '+' . tl($h['gelir']) : '-' . tl($h['gider']) ?></td>
        </tr>
        <?php endforeach; ?></table>
        <div style="margin-top:.8rem"><a href="hareketler.php" class="btn">Tümünü gör <i class="bi bi-arrow-right"></i></a></div>
    </div>
    <div class="card">
        <h3 class="bolum-baslik"><i class="bi bi-exclamation-triangle" style="color:#C9A84C"></i> Muayene Takibi</h3>
        <?php if (!$muayene): ?><div style="color:var(--mut);font-size:.83rem">Önümüzdeki 60 gün içinde muayene yok.</div><?php endif; ?>
        <?php foreach ($muayene as $m): $gecmis = strtotime($m['muayene_tarihi']) < time(); ?>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:.45rem 0;border-bottom:1px solid var(--line);font-size:.8rem">
            <div><?php if ($m['vid']): ?><a href="varlik.php?id=<?= $m['vid'] ?>"><?= e(trim(($m['cins'] ?? '') . ' ' . ($m['marka'] ?? ''))) ?></a><?php else: ?><?= e($m['plaka']) ?><?php endif; ?>
                <br><span style="color:var(--mut);font-size:.68rem"><?= e($m['plaka']) ?></span></div>
            <span class="tag <?= $gecmis ? 'kirmizi' : 'sari' ?>" title="<?= $gecmis ? 'Muayene geçmiş!' : 'Yaklaşıyor' ?>">
                <?= $gecmis ? '⚠ ' : '' ?><?= date('d.m.Y', strtotime($m['muayene_tarihi'])) ?></span>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
Chart.defaults.font.family = "'Outfit', system-ui, sans-serif";
Chart.defaults.color = '#7A8B88';
const tlFmt = v => '₺' + new Intl.NumberFormat('tr-TR', {maximumFractionDigits: 0}).format(v);

new Chart(document.getElementById('akisChart'), {
    data: {
        labels: <?= json_encode($grafik_ay, JSON_UNESCAPED_UNICODE) ?>,
        datasets: [
            { type: 'bar', label: 'Gelir', data: <?= json_encode(array_map(fn($a) => (float)$a['g'], $aylik)) ?>,
              backgroundColor: 'rgba(0,122,106,.75)', borderRadius: 6, maxBarThickness: 26 },
            { type: 'bar', label: 'Gider', data: <?= json_encode(array_map(fn($a) => (float)$a['x'], $aylik)) ?>,
              backgroundColor: 'rgba(201,168,76,.75)', borderRadius: 6, maxBarThickness: 26 },
            { type: 'line', label: 'Net', data: <?= json_encode(array_map(fn($a) => (float)$a['g'] - (float)$a['x'], $aylik)) ?>,
              borderColor: '#00C9B1', backgroundColor: 'rgba(0,201,177,.08)', tension: .35, fill: true, pointRadius: 3 }
        ]
    },
    options: { responsive: true, maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { position: 'top', labels: { boxWidth: 12, boxHeight: 12, borderRadius: 3, useBorderRadius: true } },
            tooltip: { callbacks: { label: c => c.dataset.label + ': ' + tlFmt(c.parsed.y) } } },
        scales: { y: { ticks: { callback: v => tlFmt(v) }, grid: { color: 'rgba(0,0,0,.05)' } },
                  x: { grid: { display: false } } } }
});

new Chart(document.getElementById('cinsChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_map(fn($c) => mb_substr($c['cins'], 0, 24), $cins_grafik), JSON_UNESCAPED_UNICODE) ?>,
        datasets: [{ data: <?= json_encode(array_map(fn($c) => (int)$c['c'], $cins_grafik)) ?>,
            backgroundColor: ['#00584E','#007A6A','#00C9B1','#C9A84C','#4A7C74','#7FB8AE','#2F6B62','#A8D8CF','#8a6d1d','#B8C4C1'],
            borderWidth: 2, borderColor: '#fff' }]
    },
    options: { responsive: true, maintainAspectRatio: false, cutout: '58%',
        plugins: { legend: { position: 'right', labels: { boxWidth: 10, boxHeight: 10, font: { size: 10 } } },
            tooltip: { callbacks: { label: c => ' ' + c.label + ': ' + c.parsed + ' adet' } } } }
});

<?php if ($mali_gorur && $lok_kz): ?>
new Chart(document.getElementById('lokChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_map(fn($l) => mb_substr($l['lokasyon'], 0, 28), $lok_kz), JSON_UNESCAPED_UNICODE) ?>,
        datasets: [{ label: 'Kar / Zarar', data: <?= json_encode(array_map(fn($l) => (float)$l['kz'], $lok_kz)) ?>,
            backgroundColor: <?= json_encode(array_map(fn($l) => (float)$l['kz'] >= 0 ? 'rgba(0,122,106,.8)' : 'rgba(179,38,30,.75)', $lok_kz)) ?>,
            borderRadius: 6, maxBarThickness: 22 }]
    },
    options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false },
            tooltip: { callbacks: { label: c => ' ' + tlFmt(c.parsed.x) } } },
        scales: { x: { ticks: { callback: v => tlFmt(v) }, grid: { color: 'rgba(0,0,0,.05)' } },
                  y: { grid: { display: false }, ticks: { font: { size: 10 } } } } }
});
<?php endif; ?>
</script>
<?php require __DIR__ . '/inc/footer.php'; ?>
