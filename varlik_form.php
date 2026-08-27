<?php
require_once __DIR__ . '/inc/auth.php';
yetki_zorunlu('admin');
$d = db();
$id = (int)($_GET['id'] ?? 0);
$v = null;
if ($id) { $st = $d->prepare('SELECT * FROM varliklar WHERE id = ?'); $st->execute([$id]); $v = $st->fetch(); }

$sayilar = ['s_no','alim_eur','alim_usd','alim_tl','guncel_eur','guncel_usd','guncel_tl','ikinci_el_eur','ikinci_el_usd','ikinci_el_tl',
    'kira_op_dahil','kira_op_haric','kira_geliri','bakim_gideri','operator_gideri','operatorsuz_kalan','sigorta_gideri','kar_zarar',
    'amortisman_omur','fayda_suresi','amortisman_eur','amortisman_usd','amortisman_tl','faiz_gideri'];
$metinler = ['sahiplik','ifs_nesne_no','cins','marka','model','ruhsat_no','plaka','motor_no','sasi_no','model_yili',
    'lokasyon_gecmisi','lokasyon','sevk_tarihi','notlar'];
$mesaj = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_kontrol();
    $veri = ['yil' => (int)($_POST['yil'] ?? 2026)];
    foreach ($metinler as $a) $veri[$a] = trim($_POST[$a] ?? '') ?: null;
    foreach ($sayilar as $a) {
        $x = str_replace([' ', '₺', '.'], '', $_POST[$a] ?? '');
        $x = str_replace(',', '.', $x);
        $veri[$a] = is_numeric($x) ? $x : (is_numeric($_POST[$a] ?? '') ? $_POST[$a] : null);
    }
    if (!$veri['cins']) { $mesaj = 'Cins alanı zorunludur.'; }
    else {
        $kolon = array_keys($veri);
        if ($id) {
            $set = implode(',', array_map(fn($k) => "$k = ?", $kolon));
            $d->prepare("UPDATE varliklar SET $set WHERE id = ?")->execute([...array_values($veri), $id]);
        } else {
            $d->prepare('INSERT INTO varliklar (' . implode(',', $kolon) . ') VALUES (' . rtrim(str_repeat('?,', count($kolon)), ',') . ')')
              ->execute(array_values($veri));
            $id = (int)$d->lastInsertId();
        }
        header('Location: varlik.php?id=' . $id); exit;
    }
}
try { $lok_listesi = $d->query("SELECT ad FROM lokasyonlar WHERE aktif=1 ORDER BY ad")->fetchAll(PDO::FETCH_COLUMN); }
catch (PDOException $ex) { $lok_listesi = []; }
$baslik = $id ? 'Varlık Düzenle' : 'Yeni Varlık';
require_once __DIR__ . '/inc/header.php';
function alan($ad, $etiket, $v, $tip = 'text') {
    echo '<div><label class="flbl">' . $etiket . '</label><input class="frm" type="' . $tip . '" name="' . $ad . '" value="' . e($v[$ad] ?? '') . '"></div>';
}
?>
<?php if ($mesaj): ?><div class="mesaj err"><?= e($mesaj) ?></div><?php endif; ?>
<form method="post">
<input type="hidden" name="csrf" value="<?= csrf_token() ?>">
<div class="card" style="margin-bottom:1rem">
    <h3 style="margin:0 0 .5rem;font-size:.95rem">Kimlik Bilgileri</h3>
    <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr))">
        <div><label class="flbl">Yıl</label><select class="frm" name="yil">
            <option value="2026" <?= ($v['yil'] ?? 2026) == 2026 ? 'selected' : '' ?>>2026</option>
            <option value="2025" <?= ($v['yil'] ?? 0) == 2025 ? 'selected' : '' ?>>2025 (arşiv)</option></select></div>
        <?php alan('s_no', 'Sıra No', $v); alan('sahiplik', 'Sahiplik', $v); alan('ifs_nesne_no', 'IFS Nesne No', $v);
        alan('cins', 'Cins *', $v); alan('marka', 'Marka / Özellik', $v); alan('model', 'Model', $v);
        alan('ruhsat_no', 'Ruhsat No', $v); alan('plaka', 'Plaka / Seri No', $v); alan('motor_no', 'Motor Seri No', $v);
        alan('sasi_no', 'Şasi No', $v); alan('model_yili', 'Model / Alım Yılı', $v); ?>
        <div><label class="flbl">Lokasyon</label>
            <select class="frm" name="lokasyon">
                <option value="">Seçiniz...</option>
                <?php $mevcut_lok = $v['lokasyon'] ?? '';
                if ($mevcut_lok !== '' && !in_array($mevcut_lok, $lok_listesi, true)): ?>
                <option value="<?= e($mevcut_lok) ?>" selected><?= e($mevcut_lok) ?> (listede yok)</option>
                <?php endif;
                foreach ($lok_listesi as $la): ?>
                <option value="<?= e($la) ?>" <?= $la === $mevcut_lok ? 'selected' : '' ?>><?= e($la) ?></option>
                <?php endforeach; ?>
            </select></div>
        <?php alan('lokasyon_gecmisi', 'Lokasyon Geçmişi', $v); alan('sevk_tarihi', 'Lokasyona Sevk Tarihi', $v); ?>
    </div>
</div>
<div class="card" style="margin-bottom:1rem">
    <h3 style="margin:0 0 .5rem;font-size:.95rem">Mali Bilgiler</h3>
    <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr))">
        <?php alan('alim_eur', 'Alım Fiyatı (EUR)', $v); alan('alim_usd', 'Alım Fiyatı (USD)', $v); alan('alim_tl', 'Alım Fiyatı (TL)', $v);
        alan('guncel_eur', 'Güncel Fiyat (EUR)', $v); alan('guncel_usd', 'Güncel Fiyat (USD)', $v); alan('guncel_tl', 'Güncel Fiyat (TL)', $v);
        alan('ikinci_el_eur', '2. El Fiyat (EUR)', $v); alan('ikinci_el_usd', '2. El Fiyat (USD)', $v); alan('ikinci_el_tl', '2. El Fiyat (TL)', $v);
        alan('kira_op_dahil', 'Aylık Kira — Operatör Dahil (TL)', $v); alan('kira_op_haric', 'Aylık Kira — Operatör Hariç (TL)', $v);
        alan('kira_geliri', 'Yıllık Kira Geliri (TL)', $v); alan('bakim_gideri', 'Bakım/Onarım Gideri (TL)', $v);
        alan('operator_gideri', 'Operatör Gideri (TL)', $v); alan('operatorsuz_kalan', 'Operatörsüz Kalan (TL)', $v);
        alan('sigorta_gideri', 'Sigorta Gideri (TL)', $v); alan('kar_zarar', 'Kar / Zarar (TL)', $v);
        alan('amortisman_omur', 'Amortisman Faydalı Ömür (yıl)', $v); alan('fayda_suresi', 'Fayda Süresi (yıl)', $v);
        alan('amortisman_eur', '12 Aylık Amortisman (EUR)', $v); alan('amortisman_usd', '12 Aylık Amortisman (USD)', $v);
        alan('amortisman_tl', '12 Aylık Amortisman (TL)', $v); alan('faiz_gideri', 'Faiz Giderleri (TL)', $v); ?>
    </div>
    <label class="flbl">Notlar</label>
    <textarea class="frm" name="notlar" rows="3"><?= e($v['notlar'] ?? '') ?></textarea>
</div>
<button class="btn pri"><i class="bi bi-check-lg"></i> Kaydet</button>
<a class="btn" href="<?= $id ? 'varlik.php?id=' . $id : 'varliklar.php' ?>">Vazgeç</a>
</form>
<?php require __DIR__ . '/inc/footer.php'; ?>
