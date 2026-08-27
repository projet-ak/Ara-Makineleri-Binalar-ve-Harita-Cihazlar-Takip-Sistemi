<?php
$baslik = 'Lokasyonlar';
require_once __DIR__ . '/inc/auth.php';
giris_zorunlu();
$d = db();

// Tablo yoksa oluştur ve mevcut varlık lokasyonlarından doldur
$d->exec("CREATE TABLE IF NOT EXISTS lokasyonlar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ad VARCHAR(200) NOT NULL UNIQUE,
    aciklama VARCHAR(255) NULL,
    aktif TINYINT(1) NOT NULL DEFAULT 1,
    olusturma DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci");
if ((int)$d->query("SELECT COUNT(*) c FROM lokasyonlar")->fetch()['c'] === 0) {
    $d->exec("INSERT IGNORE INTO lokasyonlar (ad)
        SELECT DISTINCT TRIM(lokasyon) FROM varliklar
        WHERE lokasyon IS NOT NULL AND TRIM(lokasyon) <> ''");
}

$mesaj = ''; $tip = 'ok';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_kontrol();
    $islem = $_POST['islem'] ?? '';
    if ($islem === 'ekle' && yetki('admin', 'saha')) {
        $ad = trim($_POST['ad'] ?? '');
        if ($ad === '') { $mesaj = 'Lokasyon adı boş olamaz.'; $tip = 'err'; }
        else {
            try {
                $d->prepare('INSERT INTO lokasyonlar (ad, aciklama) VALUES (?,?)')
                  ->execute([$ad, trim($_POST['aciklama'] ?? '') ?: null]);
                $mesaj = 'Lokasyon eklendi: ' . $ad;
            } catch (PDOException $ex) { $mesaj = 'Bu lokasyon zaten kayıtlı.'; $tip = 'err'; }
        }
    } elseif ($islem === 'duzenle' && yetki('admin')) {
        $id = (int)$_POST['id']; $ad = trim($_POST['ad'] ?? '');
        if ($ad === '') { $mesaj = 'Lokasyon adı boş olamaz.'; $tip = 'err'; }
        else {
            $st = $d->prepare('SELECT ad FROM lokasyonlar WHERE id = ?'); $st->execute([$id]);
            $eski = $st->fetch()['ad'] ?? null;
            try {
                $d->prepare('UPDATE lokasyonlar SET ad = ?, aciklama = ? WHERE id = ?')
                  ->execute([$ad, trim($_POST['aciklama'] ?? '') ?: null, $id]);
                // Varlık ve hareket kayıtlarındaki adı da güncelle
                if ($eski !== null && $eski !== $ad) {
                    $d->prepare('UPDATE varliklar SET lokasyon = ? WHERE lokasyon = ?')->execute([$ad, $eski]);
                    $d->prepare('UPDATE hareketler SET lokasyon = ? WHERE lokasyon = ?')->execute([$ad, $eski]);
                    $d->prepare('UPDATE calisma_saatleri SET lokasyon = ? WHERE lokasyon = ?')->execute([$ad, $eski]);
                }
                $mesaj = 'Lokasyon güncellendi.';
            } catch (PDOException $ex) { $mesaj = 'Bu isimde başka bir lokasyon var.'; $tip = 'err'; }
        }
    } elseif ($islem === 'durum' && yetki('admin')) {
        $d->prepare('UPDATE lokasyonlar SET aktif = 1 - aktif WHERE id = ?')->execute([(int)$_POST['id']]);
        $mesaj = 'Durum değiştirildi.';
    }
}

$mali_gorur = yetki('admin', 'yonetim');
$liste = $d->query("SELECT l.*,
    (SELECT COUNT(*) FROM varliklar v WHERE v.lokasyon = l.ad AND v.yil = 2026 AND v.aktif = 1) adet" .
    ($mali_gorur ? ",
    (SELECT SUM(v.kira_geliri) FROM varliklar v WHERE v.lokasyon = l.ad AND v.yil = 2026 AND v.aktif = 1) kira,
    (SELECT SUM(v.bakim_gideri) FROM varliklar v WHERE v.lokasyon = l.ad AND v.yil = 2026 AND v.aktif = 1) bakim,
    (SELECT SUM(v.kar_zarar) FROM varliklar v WHERE v.lokasyon = l.ad AND v.yil = 2026 AND v.aktif = 1) kz" : '') . "
    FROM lokasyonlar l ORDER BY adet DESC, l.ad")->fetchAll();

$duzenle_id = (int)($_GET['duzenle'] ?? 0);
$duzenle = null;
if ($duzenle_id && yetki('admin')) {
    $st = $d->prepare('SELECT * FROM lokasyonlar WHERE id = ?'); $st->execute([$duzenle_id]); $duzenle = $st->fetch();
}
require_once __DIR__ . '/inc/header.php';
?>
<?php if ($mesaj): ?><div class="mesaj <?= $tip ?>"><?= e($mesaj) ?></div><?php endif; ?>
<div class="grid" style="grid-template-columns:1fr 340px;align-items:start">
<div class="card">
    <h3 style="margin:0 0 .8rem;font-size:.95rem"><i class="bi bi-geo-alt" style="color:var(--ern-light)"></i>
        Lokasyonlar <span style="font-size:.72rem;color:var(--mut);font-weight:400"><?= count($liste) ?> kayıt</span></h3>
    <div style="overflow-x:auto">
    <table class="tbl">
    <tr><th>Lokasyon</th><th>Açıklama</th><th style="text-align:right">Varlık</th>
        <?php if ($mali_gorur): ?><th style="text-align:right">Kira Geliri</th><th style="text-align:right">Bakım</th><th style="text-align:right">Kar/Zarar</th><?php endif; ?>
        <th>Durum</th><?php if (yetki('admin')): ?><th></th><?php endif; ?></tr>
    <?php foreach ($liste as $l): ?>
    <tr>
        <td style="font-weight:600;max-width:260px">
            <a href="varliklar.php?lok=<?= urlencode($l['ad']) ?>"><?= e($l['ad']) ?></a></td>
        <td style="font-size:.75rem;color:var(--mut)"><?= e($l['aciklama'] ?? '') ?></td>
        <td style="text-align:right"><span class="tag gri"><?= $l['adet'] ?></span></td>
        <?php if ($mali_gorur): ?>
        <td style="text-align:right;color:#0B6B4D;font-size:.78rem"><?= $l['adet'] ? tl($l['kira']) : '—' ?></td>
        <td style="text-align:right;color:#B3261E;font-size:.78rem"><?= $l['adet'] ? tl($l['bakim']) : '—' ?></td>
        <td style="text-align:right;font-weight:600;font-size:.78rem;color:<?= (float)($l['kz'] ?? 0) >= 0 ? '#0B6B4D' : '#B3261E' ?>">
            <?= $l['adet'] ? tl($l['kz']) : '—' ?></td>
        <?php endif; ?>
        <td><span class="tag <?= $l['aktif'] ? '' : 'kirmizi' ?>"><?= $l['aktif'] ? 'Aktif' : 'Pasif' ?></span></td>
        <?php if (yetki('admin')): ?>
        <td style="white-space:nowrap">
            <a class="btn" style="padding:.3rem .6rem" href="?duzenle=<?= $l['id'] ?>" title="Düzenle"><i class="bi bi-pencil"></i></a>
            <form method="post" style="display:inline">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="islem" value="durum">
                <input type="hidden" name="id" value="<?= $l['id'] ?>">
                <button class="btn <?= $l['aktif'] ? 'tehlike' : '' ?>" style="padding:.3rem .6rem" title="Aktif/Pasif"><i class="bi bi-power"></i></button>
            </form>
        </td>
        <?php endif; ?>
    </tr>
    <?php endforeach; ?>
    </table>
    </div>
    <p style="font-size:.72rem;color:var(--mut);margin:.8rem 0 0">
        Pasif lokasyonlar yeni kayıt formlarındaki listede görünmez; mevcut kayıtlar etkilenmez.
        Lokasyon adını düzenlerseniz o lokasyona bağlı tüm varlık ve hareket kayıtları da otomatik güncellenir.</p>
</div>
<div style="display:grid;gap:1rem">
    <?php if ($duzenle): ?>
    <div class="card" style="border-color:var(--ern-light)">
        <h3 style="margin:0 0 .6rem;font-size:.95rem"><i class="bi bi-pencil" style="color:var(--ern-light)"></i> Lokasyon Düzenle</h3>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="islem" value="duzenle">
            <input type="hidden" name="id" value="<?= $duzenle['id'] ?>">
            <label class="flbl">Lokasyon Adı</label>
            <input class="frm" name="ad" value="<?= e($duzenle['ad']) ?>" required>
            <label class="flbl">Açıklama</label>
            <input class="frm" name="aciklama" value="<?= e($duzenle['aciklama'] ?? '') ?>">
            <div style="margin-top:.9rem;display:flex;gap:.5rem">
                <button class="btn pri" style="flex:1"><i class="bi bi-check-lg"></i> Kaydet</button>
                <a class="btn" href="lokasyonlar.php">Vazgeç</a>
            </div>
        </form>
    </div>
    <?php endif; ?>
    <?php if (yetki('admin', 'saha')): ?>
    <div class="card">
        <h3 style="margin:0 0 .6rem;font-size:.95rem"><i class="bi bi-plus-circle" style="color:var(--ern-light)"></i> Yeni Lokasyon</h3>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="islem" value="ekle">
            <label class="flbl">Lokasyon Adı</label>
            <input class="frm" name="ad" placeholder="Örn: U056 ADANA ŞANTİYE" required>
            <label class="flbl">Açıklama (isteğe bağlı)</label>
            <input class="frm" name="aciklama" placeholder="Örn: Adana yeni altyapı projesi">
            <button class="btn pri" style="margin-top:.9rem;width:100%"><i class="bi bi-check-lg"></i> Ekle</button>
        </form>
    </div>
    <?php endif; ?>
</div>
</div>
<?php require __DIR__ . '/inc/footer.php'; ?>
