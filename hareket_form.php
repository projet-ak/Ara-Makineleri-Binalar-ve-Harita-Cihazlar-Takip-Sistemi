<?php
require_once __DIR__ . '/inc/auth.php';
yetki_zorunlu('admin', 'saha');
$d = db();
$varliklar = $d->query("SELECT id, cins, marka, model, plaka FROM varliklar WHERE yil=2026 AND aktif=1 ORDER BY cins, marka")->fetchAll();
try { $lok_listesi = $d->query("SELECT ad FROM lokasyonlar WHERE aktif=1 ORDER BY ad")->fetchAll(PDO::FETCH_COLUMN); }
catch (PDOException $ex) { $lok_listesi = []; }
$sec = (int)($_GET['varlik_id'] ?? 0);
$mesaj = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_kontrol();
    $vid = (int)($_POST['varlik_id'] ?? 0) ?: null;
    $plaka = null; $lokasyon = trim($_POST['lokasyon'] ?? '') ?: null;
    if ($vid) {
        $st = $d->prepare('SELECT plaka, lokasyon FROM varliklar WHERE id = ?'); $st->execute([$vid]);
        if ($vr = $st->fetch()) { $plaka = $vr['plaka']; }
    }
    $tur = $_POST['islem_turu'] ?? 'DİĞER';
    $num = fn($x) => is_numeric(str_replace(',', '.', str_replace('.', '', $x))) ? str_replace(',', '.', str_replace('.', '', $x)) : (is_numeric($x) ? $x : null);
    if (!$vid) { $mesaj = 'Varlık seçiniz.'; }
    elseif (!in_array($tur, ['SEVK','BAKIM ONARIM','HAKEDİŞ','SİGORTA','MUAYENE','DİĞER'], true)) { $mesaj = 'Geçersiz işlem türü.'; }
    else {
        $d->prepare('INSERT INTO hareketler (varlik_id, plaka, lokasyon, islem_turu, aciklama, islem_tarihi, gelir, gider, ekleyen)
            VALUES (?,?,?,?,?,?,?,?,?)')->execute([
            $vid, $plaka, $lokasyon, $tur, trim($_POST['aciklama'] ?? '') ?: null,
            $_POST['islem_tarihi'] ?: null, $num($_POST['gelir'] ?? ''), $num($_POST['gider'] ?? ''), kullanici()['id']]);
        // Sevk ise varlığın lokasyonunu güncelle
        if ($tur === 'SEVK' && $lokasyon) {
            $d->prepare("UPDATE varliklar SET lokasyon_gecmisi = CONCAT(COALESCE(lokasyon_gecmisi,''), ' > ', COALESCE(lokasyon,'')), lokasyon = ?, sevk_tarihi = ? WHERE id = ?")
              ->execute([$lokasyon, $_POST['islem_tarihi'] ?: date('Y-m-d'), $vid]);
        }
        header('Location: varlik.php?id=' . $vid); exit;
    }
}
$baslik = 'Yeni Hareket Kaydı';
require_once __DIR__ . '/inc/header.php';
?>
<?php if ($mesaj): ?><div class="mesaj err"><?= e($mesaj) ?></div><?php endif; ?>
<div class="card" style="max-width:640px">
<form method="post">
<input type="hidden" name="csrf" value="<?= csrf_token() ?>">
<label class="flbl">Araç / Makine / Cihaz *</label>
<select class="frm" name="varlik_id" required>
    <option value="">Seçiniz...</option>
    <?php foreach ($varliklar as $va): ?>
    <option value="<?= $va['id'] ?>" <?= $sec === (int)$va['id'] ? 'selected' : '' ?>>
        <?= e(trim($va['cins'] . ' ' . $va['marka'] . ' ' . $va['model']) . ' — ' . ($va['plaka'] ?: 'plakasız')) ?></option>
    <?php endforeach; ?>
</select>
<label class="flbl">İşlem Türü *</label>
<select class="frm" name="islem_turu" required>
    <?php foreach (['SEVK','BAKIM ONARIM','HAKEDİŞ','SİGORTA','MUAYENE','DİĞER'] as $t): ?><option><?= $t ?></option><?php endforeach; ?>
</select>
<label class="flbl">Lokasyon (sevk için hedef lokasyon)</label>
<select class="frm" name="lokasyon">
    <option value="">Seçiniz...</option>
    <?php foreach ($lok_listesi as $la): ?><option value="<?= e($la) ?>"><?= e($la) ?></option><?php endforeach; ?>
</select>
<div style="font-size:.68rem;color:var(--mut);margin-top:.2rem">Yeni lokasyonları <a href="lokasyonlar.php">Lokasyonlar</a> sayfasından ekleyebilirsiniz.</div>
<label class="flbl">İşlem Açıklaması</label>
<textarea class="frm" name="aciklama" rows="2" placeholder="Örn: Motor kapak conta onarımı"></textarea>
<label class="flbl">İşlem Tarihi</label>
<input class="frm" type="date" name="islem_tarihi" value="<?= date('Y-m-d') ?>">
<div class="grid" style="grid-template-columns:1fr 1fr">
    <div><label class="flbl">Gelir (TL)</label><input class="frm" name="gelir" placeholder="0,00"></div>
    <div><label class="flbl">Gider (TL)</label><input class="frm" name="gider" placeholder="0,00"></div>
</div>
<div style="margin-top:1.2rem">
    <button class="btn pri"><i class="bi bi-check-lg"></i> Kaydet</button>
    <a class="btn" href="hareketler.php">Vazgeç</a>
</div>
</form>
</div>
<?php require __DIR__ . '/inc/footer.php'; ?>
