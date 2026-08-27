<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/kur.php';
yetki_zorunlu('admin', 'yonetim');
require_once __DIR__ . '/inc/xlsx.php';
$d = db();

$turler = [
    'grup_ozet'     => ['Mali Özet Raporu', 'Lokasyon / cins / sahiplik bazında kira geliri, giderler ve kar/zarar', 'bi-table'],
    'varlik_listesi'=> ['Varlık Envanter Raporu', 'Tüm araç, makine ve cihazların kimlik + mali bilgileri', 'bi-truck'],
    'hareketler'    => ['Sevk & Mali Hareket Raporu', 'Tarih aralığına göre sevk, bakım, hakediş dökümü', 'bi-arrow-left-right'],
    'karli_zararli' => ['Kar / Zarar Analiz Raporu', 'En karlı ve en zararlı varlıklar, bakım/gelir oranları', 'bi-graph-up-arrow'],
    'calisma'       => ['Çalışma Saatleri Raporu', 'Makine bazında aylık çalışma saati / km ve muayene takibi', 'bi-speedometer2'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_kontrol();
    $tur = $_POST['tur'] ?? '';
    if (!isset($turler[$tur])) die('Geçersiz rapor türü.');
    if (!class_exists('ZipArchive')) die('Sunucuda ZIP eklentisi yok — aaPanel > PHP > Eklentiler bölümünden "zip" kurun.');
    $yil = (int)($_POST['yil'] ?? 2026);
    $grup = in_array($_POST['grup'] ?? '', ['lokasyon','cins','sahiplik'], true) ? $_POST['grup'] : 'lokasyon';
    $t1 = $_POST['tarih1'] ?: null; $t2 = $_POST['tarih2'] ?: null;
    $logo_sec = ($_POST['logo'] ?? 'taahhut') === 'holding' ? 'holding' : 'taahhut';
    $logo = __DIR__ . '/assets/ern_' . $logo_sec . '_beyaz_k.png';
    $kur = guncel_kur();

    $x = new ErnXlsx($turler[$tur][0]);
    $x->setLogo($logo, 98, 60);

    // ── Kurumsal başlık bandı (3 satır koyu yeşil) ─────────────
    $bant = function (int $kolonSayisi, string $baslikMetni, string $altMetin) use ($x, $kur, $yil) {
        $bos1 = array_fill(0, $kolonSayisi, ['', 1]);
        $x->addRow($bos1, 26);
        $r2 = array_fill(0, $kolonSayisi, ['', 1]); $r2[2] = [$baslikMetni, 1];
        $x->addRow($r2, 24);
        $r3 = array_fill(0, $kolonSayisi, ['', 2]);
        $r3[2] = [$altMetin . '  ·  Rapor Tarihi: ' . date('d.m.Y H:i') .
                  '  ·  TCMB  EUR: ' . number_format((float)$kur['eur'], 4, ',', '.') .
                  '  USD: ' . number_format((float)$kur['usd'], 4, ',', '.') .
                  '  ·  Hazırlayan: ' . kullanici()['ad'], 2];
        $x->addRow($r3, 18);
        $x->addRow(array_fill(0, $kolonSayisi, ['', 0]), 8);
        $sonKolon = '';
        $i = $kolonSayisi - 1; while ($i >= 0) { $sonKolon = chr(65 + $i % 26) . $sonKolon; $i = intdiv($i, 26) - 1; }
        $x->merge('C2:' . $sonKolon . '2');
        $x->merge('C3:' . $sonKolon . '3');
    };
    $num = fn($v) => $v === null || $v === '' ? ['', 5] : [(float)$v, 5];

    if ($tur === 'grup_ozet') {
        $st = $d->prepare("SELECT $grup g, COUNT(*) adet, SUM(kira_geliri) kira, SUM(bakim_gideri) bakim,
            SUM(operator_gideri) op, SUM(sigorta_gideri) sig, SUM(amortisman_tl) amort, SUM(guncel_tl) deger, SUM(kar_zarar) kz
            FROM varliklar WHERE yil=? AND aktif=1 GROUP BY $grup ORDER BY kz DESC");
        $st->execute([$yil]); $rows = $st->fetchAll();
        $grupAd = ['lokasyon'=>'LOKASYON','cins'=>'CİNS','sahiplik'=>'SAHİPLİK'][$grup];
        $x->setCols([3, 34, 8, 15, 15, 15, 13, 15, 16, 16]);
        $bant(10, 'ERN VARLIK YÖNETİM VE TAKİP — MALİ ÖZET RAPORU (' . $yil . ')', $grupAd . ' bazında gruplama');
        $x->addRow([null, [$grupAd, 3], ['ADET', 3], ['KİRA GELİRİ (TL)', 3], ['BAKIM GİDERİ (TL)', 3],
            ['OPERATÖR (TL)', 3], ['SİGORTA (TL)', 3], ['AMORTİSMAN (TL)', 3], ['FİLO DEĞERİ (TL)', 3], ['KAR / ZARAR (TL)', 3]], 28);
        $T = ['adet'=>0,'kira'=>0,'bakim'=>0,'op'=>0,'sig'=>0,'amort'=>0,'deger'=>0,'kz'=>0];
        foreach ($rows as $r) {
            foreach ($T as $k => $_) $T[$k] += (float)$r[$k === 'adet' ? 'adet' : $k];
            $x->addRow([null, [$r['g'] ?: '(boş)', 4], [(int)$r['adet'], 9], $num($r['kira']), $num($r['bakim']),
                $num($r['op']), $num($r['sig']), $num($r['amort']), $num($r['deger']), $num($r['kz'])], 17);
        }
        $x->addRow([null, ['TOPLAM', 7], [(int)$T['adet'], 6], [$T['kira'], 6], [$T['bakim'], 6],
            [$T['op'], 6], [$T['sig'], 6], [$T['amort'], 6], [$T['deger'], 6], [$T['kz'], 6]], 20);
    }

    elseif ($tur === 'varlik_listesi') {
        $st = $d->prepare("SELECT * FROM varliklar WHERE yil=? AND aktif=1 ORDER BY s_no");
        $st->execute([$yil]); $rows = $st->fetchAll();
        $x->setCols([3, 6, 26, 18, 14, 14, 16, 16, 30, 14, 15, 15, 15, 15, 16]);
        $bant(15, 'ERN VARLIK YÖNETİM VE TAKİP — VARLIK ENVANTER RAPORU (' . $yil . ')', count($rows) . ' kayıt');
        $x->addRow([null, ['NO', 3], ['CİNS', 3], ['MARKA / MODEL', 3], ['PLAKA / SERİ', 3], ['SAHİPLİK', 3],
            ['MODEL YILI', 3], ['ŞASİ NO', 3], ['LOKASYON', 3], ['SEVK TARİHİ', 3], ['ALIM (EUR)', 3],
            ['GÜNCEL (TL)', 3], ['KİRA GELİRİ (TL)', 3], ['BAKIM (TL)', 3], ['KAR/ZARAR (TL)', 3]], 28);
        foreach ($rows as $r) {
            $x->addRow([null, [rtrim(rtrim((string)$r['s_no'], '0'), '.'), 4], [$r['cins'], 4],
                [trim($r['marka'] . ' ' . $r['model']), 4], [$r['plaka'] ?: '', 4], [$r['sahiplik'] ?: '', 4],
                [$r['model_yili'] ?: '', 4], [$r['sasi_no'] ?: '', 4], [$r['lokasyon'] ?: '', 4], [$r['sevk_tarihi'] ?: '', 4],
                $num($r['alim_eur']), $num($r['guncel_tl']), $num($r['kira_geliri']), $num($r['bakim_gideri']), $num($r['kar_zarar'])], 16);
        }
    }

    elseif ($tur === 'hareketler') {
        $w = ['1=1']; $p = [];
        if ($t1) { $w[] = 'h.islem_tarihi >= ?'; $p[] = $t1; }
        if ($t2) { $w[] = 'h.islem_tarihi <= ?'; $p[] = $t2; }
        $st = $d->prepare("SELECT h.*, v.cins vc, v.marka vm, v.model vmo FROM hareketler h
            LEFT JOIN varliklar v ON v.id=h.varlik_id WHERE " . implode(' AND ', $w) . " ORDER BY h.islem_tarihi, h.id");
        $st->execute($p); $rows = $st->fetchAll();
        $aralik = ($t1 ? date('d.m.Y', strtotime($t1)) : 'Başlangıç') . ' — ' . ($t2 ? date('d.m.Y', strtotime($t2)) : 'Bugün');
        $x->setCols([3, 13, 34, 15, 22, 14, 42, 16, 16]);
        $bant(9, 'ERN VARLIK YÖNETİM VE TAKİP — SEVK & MALİ HAREKET RAPORU', $aralik . ' · ' . count($rows) . ' işlem');
        $x->addRow([null, ['TARİH', 3], ['VARLIK', 3], ['PLAKA / SERİ', 3], ['LOKASYON', 3], ['İŞLEM TÜRÜ', 3],
            ['AÇIKLAMA', 3], ['GELİR (TL)', 3], ['GİDER (TL)', 3]], 28);
        $tg = 0; $tx = 0;
        foreach ($rows as $r) {
            $tg += (float)$r['gelir']; $tx += (float)$r['gider'];
            $vt = $r['varlik_id'] ? trim($r['vc'] . ' ' . $r['vm'] . ' ' . $r['vmo']) : ($r['cins_tam'] ?? '');
            $x->addRow([null, [$r['islem_tarihi'] ? date('d.m.Y', strtotime($r['islem_tarihi'])) : '', 4],
                [$vt, 4], [$r['plaka'] ?: '', 4], [$r['lokasyon'] ?: '', 4], [$r['islem_turu'], 4],
                [$r['aciklama'] ?: '', 4], $num($r['gelir']), $num($r['gider'])], 16);
        }
        $x->addRow([null, ['TOPLAM', 7], ['', 7], ['', 7], ['', 7], ['', 7], ['', 7], [$tg, 6], [$tx, 6]], 20);
        $x->addRow([null, ['NET: ' . number_format($tg - $tx, 2, ',', '.') . ' TL', 7], ['', 7], ['', 7], ['', 7], ['', 7], ['', 7], ['', 7], ['', 7]], 18);
    }

    elseif ($tur === 'karli_zararli') {
        $st = $d->prepare("SELECT * FROM varliklar WHERE yil=? AND aktif=1 AND kar_zarar IS NOT NULL ORDER BY kar_zarar DESC");
        $st->execute([$yil]); $rows = $st->fetchAll();
        $x->setCols([3, 6, 30, 16, 30, 16, 16, 16, 12, 16]);
        $bant(10, 'ERN VARLIK YÖNETİM VE TAKİP — KAR / ZARAR ANALİZ RAPORU (' . $yil . ')', 'Kar/zarar sırasına göre tüm varlıklar');
        $x->addRow([null, ['SIRA', 3], ['CİNS / MARKA', 3], ['PLAKA / SERİ', 3], ['LOKASYON', 3],
            ['KİRA GELİRİ (TL)', 3], ['BAKIM (TL)', 3], ['OPERATÖR (TL)', 3], ['BAKIM/GELİR', 3], ['KAR / ZARAR (TL)', 3]], 28);
        $i = 0;
        foreach ($rows as $r) {
            $i++;
            $oran = (float)$r['kira_geliri'] > 0 ? number_format((float)$r['bakim_gideri'] / (float)$r['kira_geliri'] * 100, 1, ',', '.') . '%' : '—';
            $x->addRow([null, [$i, 9], [trim($r['cins'] . ' ' . $r['marka'] . ' ' . $r['model']), 4], [$r['plaka'] ?: '', 4],
                [$r['lokasyon'] ?: '', 4], $num($r['kira_geliri']), $num($r['bakim_gideri']), $num($r['operator_gideri']),
                [$oran, 4], $num($r['kar_zarar'])], 16);
        }
    }

    else { // calisma
        $st = $d->query("SELECT c.*, v.cins vc, v.marka vm FROM calisma_saatleri c LEFT JOIN varliklar v ON v.id=c.varlik_id
            ORDER BY c.plaka, c.yil, FIELD(c.ay,'OCAK','ŞUBAT','MART','NİSAN','MAYIS','HAZİRAN','TEMMUZ','AĞUSTOS','EYLÜL','EKİM','KASIM','ARALIK')");
        $rows = $st->fetchAll();
        $x->setCols([3, 30, 16, 24, 8, 12, 16, 14, 14, 14]);
        $bant(10, 'ERN VARLIK YÖNETİM VE TAKİP — ÇALIŞMA SAATLERİ RAPORU', count($rows) . ' kayıt');
        $x->addRow([null, ['VARLIK', 3], ['PLAKA / SERİ', 3], ['LOKASYON', 3], ['YIL', 3], ['AY', 3],
            ['GÜNCEL SAAT / KM', 3], ['SON BAKIM', 3], ['SON BAKIM TARİHİ', 3], ['MUAYENE GEÇERLİLİK', 3]], 28);
        foreach ($rows as $r) {
            $x->addRow([null, [trim(($r['vc'] ?? '') . ' ' . ($r['vm'] ?? '')) ?: '—', 4], [$r['plaka'] ?: '', 4],
                [$r['lokasyon'] ?: '', 4], [(int)$r['yil'], 9], [$r['ay'], 4], [$r['guncel_deger'] ?: '', 4],
                [$r['son_bakim'] ?: '', 4], [$r['son_bakim_tarihi'] ? date('d.m.Y', strtotime($r['son_bakim_tarihi'])) : '', 4],
                [$r['muayene_tarihi'] ? date('d.m.Y', strtotime($r['muayene_tarihi'])) : '', 4]], 16);
        }
    }

    // Dipnot
    $x->addRow([['', 0]], 6);
    $x->addRow([null, ['ERN Holding © ' . date('Y') . ' — Varlık Yönetim ve Takip Sistemi · Bu rapor sistem tarafından otomatik oluşturulmuştur. · Fikir sahibi ve geliştirici: Tayyar Akbulut', 8]]);
    $x->indir($turler[$tur][0] . ' ' . date('Y-m-d'));
}

$baslik = 'Excel Rapor Oluştur';
require_once __DIR__ . '/inc/header.php';
?>
<style>
.rapor-sec { display:grid; grid-template-columns:repeat(auto-fit,minmax(250px,1fr)); gap:.8rem; }
.rapor-kart { border:2px solid var(--line); border-radius:14px; padding:1rem; cursor:pointer; transition:all .15s; position:relative; }
.rapor-kart:hover { border-color:var(--ern-light); transform:translateY(-2px); box-shadow:0 8px 20px rgba(0,88,78,.1); }
.rapor-kart.secili { border-color:var(--ern); background:#F2FAF8; }
.rapor-kart input { position:absolute; opacity:0; }
.rapor-kart .rk-icon { width:40px; height:40px; border-radius:11px; display:flex; align-items:center; justify-content:center;
  background:linear-gradient(135deg,rgba(0,88,78,.1),rgba(0,201,177,.15)); color:var(--ern); font-size:1.15rem; margin-bottom:.6rem; }
.rapor-kart b { font-size:.88rem; display:block; margin-bottom:.25rem; }
.rapor-kart span { font-size:.72rem; color:var(--mut); line-height:1.4; }
</style>
<form method="post" id="raporForm">
<input type="hidden" name="csrf" value="<?= csrf_token() ?>">
<div class="card" style="margin-bottom:1rem">
    <h3 style="margin:0 0 .9rem;font-size:.95rem"><i class="bi bi-1-circle" style="color:var(--ern-light)"></i> Rapor Türü Seçin</h3>
    <div class="rapor-sec">
        <?php $ilk = true; foreach ($turler as $tk => $tv): ?>
        <label class="rapor-kart <?= $ilk ? 'secili' : '' ?>">
            <input type="radio" name="tur" value="<?= $tk ?>" <?= $ilk ? 'checked' : '' ?>>
            <div class="rk-icon"><i class="bi <?= $tv[2] ?>"></i></div>
            <b><?= e($tv[0]) ?></b><span><?= e($tv[1]) ?></span>
        </label>
        <?php $ilk = false; endforeach; ?>
    </div>
</div>
<div class="card" style="margin-bottom:1rem">
    <h3 style="margin:0 0 .9rem;font-size:.95rem"><i class="bi bi-2-circle" style="color:var(--ern-light)"></i> Rapor Ayarları</h3>
    <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr))">
        <div><label class="flbl">Yıl</label><select class="frm" name="yil">
            <option value="2026">2026</option><option value="2025">2025 (arşiv)</option></select></div>
        <div id="grupAlan"><label class="flbl">Gruplama (mali özet için)</label><select class="frm" name="grup">
            <option value="lokasyon">Lokasyon</option><option value="cins">Cins</option><option value="sahiplik">Sahiplik</option></select></div>
        <div id="tarih1Alan" style="display:none"><label class="flbl">Başlangıç Tarihi</label><input class="frm" type="date" name="tarih1"></div>
        <div id="tarih2Alan" style="display:none"><label class="flbl">Bitiş Tarihi</label><input class="frm" type="date" name="tarih2"></div>
        <div><label class="flbl">Rapor Logosu</label><select class="frm" name="logo">
            <option value="taahhut">ERN Taahhüt</option><option value="holding">ERN Holding</option></select></div>
    </div>
</div>
<button class="btn pri" style="font-size:.95rem;padding:.7rem 1.6rem"><i class="bi bi-file-earmark-excel"></i> Excel Raporu Oluştur ve İndir</button>
<a class="btn" href="raporlar.php"><i class="bi bi-arrow-left"></i> Raporlara Dön</a>
</form>
<script>
document.querySelectorAll('.rapor-kart').forEach(k => k.addEventListener('click', () => {
    document.querySelectorAll('.rapor-kart').forEach(x => x.classList.remove('secili'));
    k.classList.add('secili');
    k.querySelector('input').checked = true;
    const tur = k.querySelector('input').value;
    document.getElementById('grupAlan').style.display = tur === 'grup_ozet' ? '' : 'none';
    const tarihli = tur === 'hareketler';
    document.getElementById('tarih1Alan').style.display = tarihli ? '' : 'none';
    document.getElementById('tarih2Alan').style.display = tarihli ? '' : 'none';
}));
</script>
<?php require __DIR__ . '/inc/footer.php'; ?>
