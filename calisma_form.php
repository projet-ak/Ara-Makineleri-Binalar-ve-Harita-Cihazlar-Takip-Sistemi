<?php
require_once __DIR__ . '/inc/auth.php';
yetki_zorunlu('admin', 'saha');
$d = db();
$varliklar = $d->query("SELECT id, cins, marka, model, plaka, lokasyon FROM varliklar WHERE yil=2026 AND aktif=1 ORDER BY cins, marka")->fetchAll();
$aylar = ['OCAK','ŞUBAT','MART','NİSAN','MAYIS','HAZİRAN','TEMMUZ','AĞUSTOS','EYLÜL','EKİM','KASIM','ARALIK'];
$mesaj = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_kontrol();
    $vid = (int)($_POST['varlik_id'] ?? 0);
    if (!$vid) { $mesaj = 'Varlık seçiniz.'; }
    else {
        $st = $d->prepare('SELECT plaka, lokasyon FROM varliklar WHERE id = ?'); $st->execute([$vid]); $vr = $st->fetch();
        $gunluk = [];
        for ($g = 1; $g <= 31; $g++) {
            $deger = trim($_POST['gun' . $g] ?? '');
            if ($deger !== '') $gunluk[(string)$g] = $deger;
        }
        $d->prepare('INSERT INTO calisma_saatleri (varlik_id, plaka, lokasyon, yil, ay, guncel_deger, son_bakim, son_bakim_tarihi, muayene_tarihi, gunluk)
            VALUES (?,?,?,?,?,?,?,?,?,?)')->execute([
            $vid, $vr['plaka'] ?? null, $vr['lokasyon'] ?? null,
            (int)($_POST['yil'] ?? date('Y')), $_POST['ay'] ?? 'OCAK',
            trim($_POST['guncel_deger'] ?? '') ?: null, trim($_POST['son_bakim'] ?? '') ?: null,
            $_POST['son_bakim_tarihi'] ?: null, $_POST['muayene_tarihi'] ?: null,
            $gunluk ? json_encode($gunluk, JSON_UNESCAPED_UNICODE) : null]);
        header('Location: calisma.php'); exit;
    }
}
$baslik = 'Çalışma Saati Kaydı';
require_once __DIR__ . '/inc/header.php';
?>
<?php if ($mesaj): ?><div class="mesaj err"><?= e($mesaj) ?></div><?php endif; ?>
<div class="card" style="max-width:860px">
<form method="post">
<input type="hidden" name="csrf" value="<?= csrf_token() ?>">
<div class="grid" style="grid-template-columns:2fr 1fr 1fr">
    <div><label class="flbl">Araç / Makine *</label>
    <select class="frm" name="varlik_id" required><option value="">Seçiniz...</option>
        <?php foreach ($varliklar as $va): ?>
        <option value="<?= $va['id'] ?>"><?= e(trim($va['cins'] . ' ' . $va['marka'] . ' ' . $va['model']) . ' — ' . ($va['plaka'] ?: 'plakasız')) ?></option>
        <?php endforeach; ?></select></div>
    <div><label class="flbl">Yıl</label><input class="frm" type="number" name="yil" value="<?= date('Y') ?>"></div>
    <div><label class="flbl">Ay</label><select class="frm" name="ay">
        <?php foreach ($aylar as $a): ?><option><?= $a ?></option><?php endforeach; ?></select></div>
</div>
<div class="grid" style="grid-template-columns:repeat(4,1fr)">
    <div><label class="flbl">Güncel Çalışma Saati / KM</label><input class="frm" name="guncel_deger" placeholder="Örn: 190057 veya 12500 SAAT"></div>
    <div><label class="flbl">Son Bakım Saati/KM</label><input class="frm" name="son_bakim"></div>
    <div><label class="flbl">Son Bakım Tarihi</label><input class="frm" type="date" name="son_bakim_tarihi"></div>
    <div><label class="flbl">Muayene Geçerlilik</label><input class="frm" type="date" name="muayene_tarihi"></div>
</div>
<label class="flbl">Günlük Çalışma (isteğe bağlı — gün gün saat/km girin)</label>
<div class="grid" style="grid-template-columns:repeat(auto-fill,minmax(72px,1fr));gap:.4rem">
<?php for ($g = 1; $g <= 31; $g++): ?>
    <div><span style="font-size:.62rem;color:var(--mut)"><?= $g ?></span><input class="frm" style="padding:.35rem .5rem" name="gun<?= $g ?>"></div>
<?php endfor; ?>
</div>
<div style="margin-top:1.2rem">
    <button class="btn pri"><i class="bi bi-check-lg"></i> Kaydet</button>
    <a class="btn" href="calisma.php">Vazgeç</a>
</div>
</form>
</div>
<?php require __DIR__ . '/inc/footer.php'; ?>
