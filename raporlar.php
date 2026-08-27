<?php
$baslik = 'Raporlar';
require_once __DIR__ . '/inc/auth.php';
yetki_zorunlu('admin', 'yonetim');
$d = db();
$yil = (int)($_GET['yil'] ?? 2026);
$grup = in_array($_GET['grup'] ?? '', ['lokasyon', 'cins', 'sahiplik'], true) ? $_GET['grup'] : 'lokasyon';
$grup_ad = ['lokasyon' => 'Lokasyon', 'cins' => 'Cins', 'sahiplik' => 'Sahiplik'][$grup];

// Grup bazlı özet
$st = $d->prepare("SELECT $grup grup_ad, COUNT(*) adet,
    SUM(kira_geliri) kira, SUM(bakim_gideri) bakim, SUM(operator_gideri) operator_g,
    SUM(sigorta_gideri) sigorta, SUM(kar_zarar) kz, SUM(guncel_tl) deger, SUM(amortisman_tl) amort
    FROM varliklar WHERE yil = ? AND aktif = 1 GROUP BY $grup ORDER BY kz DESC");
$st->execute([$yil]); $rows = $st->fetchAll();

// CSV dışa aktarma
if (isset($_GET['csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="rapor_' . $grup . '_' . $yil . '.csv"');
    echo "\xEF\xBB\xBF"; // Excel için BOM
    $f = fopen('php://output', 'w');
    fputcsv($f, [$grup_ad, 'Adet', 'Kira Geliri', 'Bakım', 'Operatör', 'Sigorta', 'Kar/Zarar', 'Filo Değeri (TL)', 'Amortisman (TL)'], ';');
    foreach ($rows as $r)
        fputcsv($f, [$r['grup_ad'], $r['adet'], $r['kira'], $r['bakim'], $r['operator_g'], $r['sigorta'], $r['kz'], $r['deger'], $r['amort']], ';');
    exit;
}

$gen = $d->prepare("SELECT COUNT(*) adet, SUM(kira_geliri) kira, SUM(bakim_gideri) bakim, SUM(operator_gideri) op,
    SUM(sigorta_gideri) sig, SUM(faiz_gideri) faiz, SUM(kar_zarar) kz, SUM(amortisman_tl) amort,
    SUM(guncel_tl) deger_tl, SUM(guncel_eur) deger_eur, SUM(guncel_usd) deger_usd,
    SUM(ikinci_el_tl) iel_tl, SUM(alim_eur) alim_eur FROM varliklar WHERE yil = ? AND aktif = 1");
$gen->execute([$yil]); $gen = $gen->fetch();

// En karlı / zararlı 10 varlık
$karli = $d->prepare("SELECT id, cins, marka, model, plaka, lokasyon, kira_geliri, bakim_gideri, kar_zarar
    FROM varliklar WHERE yil = ? AND aktif = 1 AND kar_zarar IS NOT NULL ORDER BY kar_zarar DESC LIMIT 10");
$karli->execute([$yil]); $karli = $karli->fetchAll();
$zararli = $d->prepare("SELECT id, cins, marka, model, plaka, lokasyon, kira_geliri, bakim_gideri, kar_zarar
    FROM varliklar WHERE yil = ? AND aktif = 1 AND kar_zarar IS NOT NULL ORDER BY kar_zarar ASC LIMIT 10");
$zararli->execute([$yil]); $zararli = $zararli->fetchAll();

// Bakım/gelir oranı en yüksek 10 (gelir > 0 olanlar)
$oran = $d->prepare("SELECT id, cins, marka, plaka, kira_geliri, bakim_gideri,
    bakim_gideri / kira_geliri oran FROM varliklar
    WHERE yil = ? AND aktif = 1 AND kira_geliri > 0 AND bakim_gideri > 0 ORDER BY oran DESC LIMIT 10");
$oran->execute([$yil]); $oran = $oran->fetchAll();

// Aylık hareket akışı + işlem türü özeti
$aylik = $d->query("SELECT DATE_FORMAT(islem_tarihi,'%Y-%m') ay, SUM(COALESCE(gelir,0)) g, SUM(COALESCE(gider,0)) x
    FROM hareketler WHERE islem_tarihi IS NOT NULL GROUP BY ay ORDER BY ay DESC LIMIT 12")->fetchAll();
$aylik = array_reverse($aylik);
$tur_ozet = $d->query("SELECT islem_turu, COUNT(*) c, SUM(COALESCE(gelir,0)) g, SUM(COALESCE(gider,0)) x
    FROM hareketler GROUP BY islem_turu ORDER BY (SUM(COALESCE(gelir,0)) + SUM(COALESCE(gider,0))) DESC")->fetchAll();

require_once __DIR__ . '/inc/header.php';
$ay_adlari = [1=>'Oca',2=>'Şub',3=>'Mar',4=>'Nis',5=>'May',6=>'Haz',7=>'Tem',8=>'Ağu',9=>'Eyl',10=>'Eki',11=>'Kas',12=>'Ara'];
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<style>.bolum-baslik{margin:0 0 .9rem;font-size:.95rem;display:flex;align-items:center;gap:.45rem}.bolum-baslik i{color:var(--ern-light)}</style>

<div class="card" style="margin-bottom:1rem">
<form method="get" style="display:flex;gap:.6rem;align-items:end;flex-wrap:wrap">
    <div><label class="flbl">Yıl</label><select class="frm" name="yil">
        <option value="2026" <?= $yil === 2026 ? 'selected' : '' ?>>2026</option>
        <option value="2025" <?= $yil === 2025 ? 'selected' : '' ?>>2025 (arşiv)</option></select></div>
    <div><label class="flbl">Gruplama</label><select class="frm" name="grup">
        <option value="lokasyon" <?= $grup === 'lokasyon' ? 'selected' : '' ?>>Lokasyon</option>
        <option value="cins" <?= $grup === 'cins' ? 'selected' : '' ?>>Cins</option>
        <option value="sahiplik" <?= $grup === 'sahiplik' ? 'selected' : '' ?>>Sahiplik</option></select></div>
    <button class="btn pri"><i class="bi bi-arrow-repeat"></i> Uygula</button>
    <a class="btn" href="?yil=<?= $yil ?>&grup=<?= $grup ?>&csv=1"><i class="bi bi-filetype-csv"></i> CSV İndir</a>
</form>
</div>

<!-- Genel özet -->
<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(185px,1fr));margin-bottom:1rem">
    <div class="card stat"><div class="stat-icon"><i class="bi bi-truck"></i></div><div><b><?= $gen['adet'] ?></b><span>Varlık</span></div></div>
    <div class="card stat"><div class="stat-icon" style="color:#0B6B4D"><i class="bi bi-graph-up"></i></div><div><b><?= tl($gen['kira']) ?></b><span>Kira Geliri</span></div></div>
    <div class="card stat"><div class="stat-icon" style="color:#B3261E"><i class="bi bi-wrench"></i></div><div><b><?= tl($gen['bakim']) ?></b><span>Bakım Gideri</span></div></div>
    <div class="card stat"><div class="stat-icon" style="color:#B3261E"><i class="bi bi-person-gear"></i></div><div><b><?= tl($gen['op']) ?></b><span>Operatör Gideri</span></div></div>
    <div class="card stat"><div class="stat-icon"><i class="bi bi-cash-coin"></i></div>
        <div><b style="color:<?= (float)$gen['kz'] >= 0 ? '#0B6B4D' : '#B3261E' ?>"><?= tl($gen['kz']) ?></b><span>Kar / Zarar</span></div></div>
    <div class="card stat"><div class="stat-icon"><i class="bi bi-percent"></i></div>
        <div><b><?= $gen['kira'] > 0 ? number_format($gen['bakim'] / $gen['kira'] * 100, 1, ',', '.') . '%' : '—' ?></b><span>Bakım / Gelir Oranı</span></div></div>
</div>

<!-- Filo değeri -->
<div class="card" style="margin-bottom:1rem">
    <h3 class="bolum-baslik"><i class="bi bi-safe"></i> Filo Değeri (<?= $yil ?>)</h3>
    <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr));font-size:.85rem">
        <div><span style="color:var(--mut);font-size:.72rem">Güncel Değer (TL)</span><br><b style="font-size:1.1rem"><?= tl($gen['deger_tl']) ?></b></div>
        <div><span style="color:var(--mut);font-size:.72rem">Güncel Değer (EUR)</span><br><b style="font-size:1.1rem"><?= para($gen['deger_eur'], '€') ?></b></div>
        <div><span style="color:var(--mut);font-size:.72rem">Güncel Değer (USD)</span><br><b style="font-size:1.1rem"><?= para($gen['deger_usd'], '$') ?></b></div>
        <div><span style="color:var(--mut);font-size:.72rem">2. El Değeri (TL)</span><br><b style="font-size:1.1rem"><?= tl($gen['iel_tl']) ?></b></div>
        <div><span style="color:var(--mut);font-size:.72rem">Alım Değeri (EUR)</span><br><b style="font-size:1.1rem"><?= para($gen['alim_eur'], '€') ?></b></div>
        <div><span style="color:var(--mut);font-size:.72rem">12 Aylık Amortisman (TL)</span><br><b style="font-size:1.1rem"><?= tl($gen['amort']) ?></b></div>
        <div><span style="color:var(--mut);font-size:.72rem">Faiz Giderleri (TL)</span><br><b style="font-size:1.1rem"><?= tl($gen['faiz']) ?></b></div>
        <div><span style="color:var(--mut);font-size:.72rem">Sigorta (TL)</span><br><b style="font-size:1.1rem"><?= tl($gen['sig']) ?></b></div>
    </div>
</div>

<!-- Grafikler -->
<div class="grid" style="grid-template-columns:1fr 1fr;margin-bottom:1rem;align-items:stretch">
    <div class="card">
        <h3 class="bolum-baslik"><i class="bi bi-bar-chart"></i> <?= $grup_ad ?> Bazlı Kar / Zarar</h3>
        <div style="position:relative;height:340px"><canvas id="grupChart"></canvas></div>
    </div>
    <div class="card">
        <h3 class="bolum-baslik"><i class="bi bi-graph-up"></i> Aylık Hareket Akışı (son 12 ay)</h3>
        <div style="position:relative;height:340px"><canvas id="akisChart"></canvas></div>
    </div>
</div>

<!-- Grup tablosu -->
<div class="card" style="margin-bottom:1rem">
    <h3 class="bolum-baslik"><i class="bi bi-table"></i> <?= $grup_ad ?> Bazında Mali Özet (<?= $yil ?>)</h3>
    <div style="overflow-x:auto">
    <table class="tbl">
    <tr><th><?= $grup_ad ?></th><th style="text-align:right">Adet</th><th style="text-align:right">Kira Geliri</th>
        <th style="text-align:right">Bakım</th><th style="text-align:right">Bakım/Gelir</th><th style="text-align:right">Operatör</th>
        <th style="text-align:right">Amortisman</th><th style="text-align:right">Filo Değeri</th><th style="text-align:right">Kar/Zarar</th></tr>
    <?php foreach ($rows as $r): ?>
    <tr><td style="max-width:230px"><?= e($r['grup_ad'] ?: '(boş)') ?></td>
        <td style="text-align:right"><?= $r['adet'] ?></td>
        <td style="text-align:right;color:#0B6B4D"><?= tl($r['kira']) ?></td>
        <td style="text-align:right;color:#B3261E"><?= tl($r['bakim']) ?></td>
        <td style="text-align:right"><?= $r['kira'] > 0 ? number_format($r['bakim'] / $r['kira'] * 100, 1, ',', '.') . '%' : '—' ?></td>
        <td style="text-align:right;color:#B3261E"><?= tl($r['operator_g']) ?></td>
        <td style="text-align:right"><?= tl($r['amort']) ?></td>
        <td style="text-align:right"><?= tl($r['deger']) ?></td>
        <td style="text-align:right;font-weight:700;color:<?= (float)$r['kz'] >= 0 ? '#0B6B4D' : '#B3261E' ?>"><?= tl($r['kz']) ?></td></tr>
    <?php endforeach; ?>
    <tr style="background:#F7FAF9;font-weight:700">
        <td>TOPLAM</td><td style="text-align:right"><?= $gen['adet'] ?></td>
        <td style="text-align:right;color:#0B6B4D"><?= tl($gen['kira']) ?></td>
        <td style="text-align:right;color:#B3261E"><?= tl($gen['bakim']) ?></td>
        <td style="text-align:right"><?= $gen['kira'] > 0 ? number_format($gen['bakim'] / $gen['kira'] * 100, 1, ',', '.') . '%' : '—' ?></td>
        <td style="text-align:right;color:#B3261E"><?= tl($gen['op']) ?></td>
        <td style="text-align:right"><?= tl($gen['amort']) ?></td>
        <td style="text-align:right"><?= tl($gen['deger_tl']) ?></td>
        <td style="text-align:right;color:<?= (float)$gen['kz'] >= 0 ? '#0B6B4D' : '#B3261E' ?>"><?= tl($gen['kz']) ?></td></tr>
    </table>
    </div>
</div>

<!-- En karlı / zararlı -->
<div class="grid" style="grid-template-columns:1fr 1fr;margin-bottom:1rem;align-items:start">
    <div class="card">
        <h3 class="bolum-baslik"><i class="bi bi-trophy" style="color:#0B6B4D"></i> En Karlı 10 Varlık</h3>
        <table class="tbl">
        <tr><th>Varlık</th><th style="text-align:right">Kira</th><th style="text-align:right">Bakım</th><th style="text-align:right">Kar</th></tr>
        <?php foreach ($karli as $k): ?>
        <tr><td><a href="varlik.php?id=<?= $k['id'] ?>"><?= e(trim($k['cins'] . ' ' . $k['marka'])) ?></a>
            <span style="color:var(--mut);font-size:.68rem"><?= e($k['plaka']) ?></span></td>
            <td style="text-align:right;font-size:.75rem"><?= tl($k['kira_geliri']) ?></td>
            <td style="text-align:right;font-size:.75rem"><?= tl($k['bakim_gideri']) ?></td>
            <td style="text-align:right;font-weight:700;color:#0B6B4D"><?= tl($k['kar_zarar']) ?></td></tr>
        <?php endforeach; ?>
        </table>
    </div>
    <div class="card">
        <h3 class="bolum-baslik"><i class="bi bi-emoji-frown" style="color:#B3261E"></i> En Zararlı 10 Varlık</h3>
        <table class="tbl">
        <tr><th>Varlık</th><th style="text-align:right">Kira</th><th style="text-align:right">Bakım</th><th style="text-align:right">Zarar</th></tr>
        <?php foreach ($zararli as $k): ?>
        <tr><td><a href="varlik.php?id=<?= $k['id'] ?>"><?= e(trim($k['cins'] . ' ' . $k['marka'])) ?></a>
            <span style="color:var(--mut);font-size:.68rem"><?= e($k['plaka']) ?></span></td>
            <td style="text-align:right;font-size:.75rem"><?= tl($k['kira_geliri']) ?></td>
            <td style="text-align:right;font-size:.75rem"><?= tl($k['bakim_gideri']) ?></td>
            <td style="text-align:right;font-weight:700;color:<?= (float)$k['kar_zarar'] >= 0 ? '#0B6B4D' : '#B3261E' ?>"><?= tl($k['kar_zarar']) ?></td></tr>
        <?php endforeach; ?>
        </table>
    </div>
</div>

<!-- Bakım/gelir oranı + işlem türü -->
<div class="grid" style="grid-template-columns:1fr 1fr;align-items:start">
    <div class="card">
        <h3 class="bolum-baslik"><i class="bi bi-exclamation-diamond" style="color:#C9A84C"></i> Bakım / Gelir Oranı En Yüksek 10
            <span style="font-size:.66rem;color:var(--mut);font-weight:400">(masraflı makineler)</span></h3>
        <table class="tbl">
        <tr><th>Varlık</th><th style="text-align:right">Kira</th><th style="text-align:right">Bakım</th><th style="text-align:right">Oran</th></tr>
        <?php foreach ($oran as $o): ?>
        <tr><td><a href="varlik.php?id=<?= $o['id'] ?>"><?= e(trim($o['cins'] . ' ' . $o['marka'])) ?></a>
            <span style="color:var(--mut);font-size:.68rem"><?= e($o['plaka']) ?></span></td>
            <td style="text-align:right;font-size:.75rem"><?= tl($o['kira_geliri']) ?></td>
            <td style="text-align:right;font-size:.75rem;color:#B3261E"><?= tl($o['bakim_gideri']) ?></td>
            <td style="text-align:right;font-weight:700;color:<?= $o['oran'] > .3 ? '#B3261E' : '#8a6d1d' ?>">
                <?= number_format($o['oran'] * 100, 1, ',', '.') ?>%</td></tr>
        <?php endforeach; ?>
        </table>
    </div>
    <div class="card">
        <h3 class="bolum-baslik"><i class="bi bi-arrow-left-right"></i> İşlem Türü Bazlı Toplamlar (tüm zamanlar)</h3>
        <table class="tbl">
        <tr><th>İşlem Türü</th><th style="text-align:right">Adet</th><th style="text-align:right">Gelir</th><th style="text-align:right">Gider</th><th style="text-align:right">Net</th></tr>
        <?php foreach ($tur_ozet as $to): $net = (float)$to['g'] - (float)$to['x']; ?>
        <tr><td><span class="tag <?= $to['islem_turu'] === 'HAKEDİŞ' ? '' : ($to['islem_turu'] === 'SEVK' ? 'sari' : 'kirmizi') ?>"><?= e($to['islem_turu']) ?></span></td>
            <td style="text-align:right"><?= $to['c'] ?></td>
            <td style="text-align:right;color:#0B6B4D"><?= (float)$to['g'] ? tl($to['g']) : '—' ?></td>
            <td style="text-align:right;color:#B3261E"><?= (float)$to['x'] ? tl($to['x']) : '—' ?></td>
            <td style="text-align:right;font-weight:600;color:<?= $net >= 0 ? '#0B6B4D' : '#B3261E' ?>"><?= tl($net) ?></td></tr>
        <?php endforeach; ?>
        </table>
    </div>
</div>

<script>
Chart.defaults.font.family = "'Outfit', system-ui, sans-serif";
Chart.defaults.color = '#7A8B88';
const tlFmt = v => '₺' + new Intl.NumberFormat('tr-TR', {maximumFractionDigits: 0}).format(v);
<?php
$g15 = array_slice($rows, 0, 15);
$ay_lbl = array_map(fn($a) => $ay_adlari[(int)substr($a['ay'], 5)] . ' ' . substr($a['ay'], 2, 2), $aylik);
?>
new Chart(document.getElementById('grupChart'), {
    type: 'bar',
    data: { labels: <?= json_encode(array_map(fn($r) => mb_substr($r['grup_ad'] ?? '(boş)', 0, 26), $g15), JSON_UNESCAPED_UNICODE) ?>,
        datasets: [{ data: <?= json_encode(array_map(fn($r) => (float)$r['kz'], $g15)) ?>,
            backgroundColor: <?= json_encode(array_map(fn($r) => (float)$r['kz'] >= 0 ? 'rgba(0,122,106,.8)' : 'rgba(179,38,30,.75)', $g15)) ?>,
            borderRadius: 6, maxBarThickness: 20 }] },
    options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => ' ' + tlFmt(c.parsed.x) } } },
        scales: { x: { ticks: { callback: v => tlFmt(v) }, grid: { color: 'rgba(0,0,0,.05)' } },
                  y: { grid: { display: false }, ticks: { font: { size: 10 } } } } }
});
new Chart(document.getElementById('akisChart'), {
    data: { labels: <?= json_encode($ay_lbl, JSON_UNESCAPED_UNICODE) ?>,
        datasets: [
            { type: 'bar', label: 'Gelir', data: <?= json_encode(array_map(fn($a) => (float)$a['g'], $aylik)) ?>,
              backgroundColor: 'rgba(0,122,106,.75)', borderRadius: 6, maxBarThickness: 24 },
            { type: 'bar', label: 'Gider', data: <?= json_encode(array_map(fn($a) => (float)$a['x'], $aylik)) ?>,
              backgroundColor: 'rgba(201,168,76,.75)', borderRadius: 6, maxBarThickness: 24 },
            { type: 'line', label: 'Net', data: <?= json_encode(array_map(fn($a) => (float)$a['g'] - (float)$a['x'], $aylik)) ?>,
              borderColor: '#00C9B1', tension: .35, pointRadius: 3 }] },
    options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
        plugins: { legend: { position: 'top', labels: { boxWidth: 12 } },
            tooltip: { callbacks: { label: c => c.dataset.label + ': ' + tlFmt(c.parsed.y) } } },
        scales: { y: { ticks: { callback: v => tlFmt(v) }, grid: { color: 'rgba(0,0,0,.05)' } }, x: { grid: { display: false } } } }
});
</script>
<?php require __DIR__ . '/inc/footer.php'; ?>
