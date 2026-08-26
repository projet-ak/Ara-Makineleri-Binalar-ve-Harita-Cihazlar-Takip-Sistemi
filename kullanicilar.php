<?php
$baslik = 'Kullanıcılar';
require_once __DIR__ . '/inc/auth.php';
yetki_zorunlu('admin');
require_once __DIR__ . '/inc/header.php';
$d = db();
$mesaj = ''; $tip = 'ok';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_kontrol();
    $islem = $_POST['islem'] ?? '';
    if ($islem === 'ekle') {
        $ka = trim($_POST['kullanici_adi'] ?? ''); $ad = trim($_POST['ad_soyad'] ?? '');
        $sifre = $_POST['sifre'] ?? ''; $rol_yeni = $_POST['rol'] ?? 'saha';
        if (!$ka || !$ad || strlen($sifre) < 6) { $mesaj = 'Tüm alanları doldurun (şifre en az 6 karakter).'; $tip = 'err'; }
        elseif (!in_array($rol_yeni, ['admin','saha','yonetim'], true)) { $mesaj = 'Geçersiz rol.'; $tip = 'err'; }
        else {
            try {
                $d->prepare('INSERT INTO kullanicilar (kullanici_adi, ad_soyad, sifre_hash, rol) VALUES (?,?,?,?)')
                  ->execute([$ka, $ad, password_hash($sifre, PASSWORD_BCRYPT), $rol_yeni]);
                $mesaj = 'Kullanıcı eklendi.';
            } catch (PDOException $ex) { $mesaj = 'Bu kullanıcı adı zaten kayıtlı.'; $tip = 'err'; }
        }
    } elseif ($islem === 'sifre') {
        $uid = (int)$_POST['id']; $sifre = $_POST['sifre'] ?? '';
        if (strlen($sifre) < 6) { $mesaj = 'Şifre en az 6 karakter olmalı.'; $tip = 'err'; }
        else { $d->prepare('UPDATE kullanicilar SET sifre_hash = ? WHERE id = ?')->execute([password_hash($sifre, PASSWORD_BCRYPT), $uid]);
               $mesaj = 'Şifre güncellendi.'; }
    } elseif ($islem === 'durum') {
        $uid = (int)$_POST['id'];
        if ($uid === (int)kullanici()['id']) { $mesaj = 'Kendi hesabınızı pasifleştiremezsiniz.'; $tip = 'err'; }
        else { $d->prepare('UPDATE kullanicilar SET aktif = 1 - aktif WHERE id = ?')->execute([$uid]); $mesaj = 'Durum değişti.'; }
    }
}
$liste = $d->query('SELECT * FROM kullanicilar ORDER BY rol, kullanici_adi')->fetchAll();
$rol_ad = ['admin' => 'Admin — tam yetki', 'saha' => 'Saha — sevk / çalışma girişi', 'yonetim' => 'Yönetim — rapor görüntüleme'];
?>
<?php if ($mesaj): ?><div class="mesaj <?= $tip ?>"><?= e($mesaj) ?></div><?php endif; ?>
<div class="grid" style="grid-template-columns:1fr 340px;align-items:start">
<div class="card">
<table class="tbl">
<tr><th>Kullanıcı Adı</th><th>Ad Soyad</th><th>Rol</th><th>Son Giriş</th><th>Durum</th><th></th></tr>
<?php foreach ($liste as $k): ?>
<tr>
    <td style="font-weight:600"><?= e($k['kullanici_adi']) ?></td>
    <td><?= e($k['ad_soyad']) ?></td>
    <td><span class="tag <?= $k['rol'] === 'admin' ? 'sari' : ($k['rol'] === 'yonetim' ? '' : 'gri') ?>"><?= e($k['rol']) ?></span></td>
    <td style="font-size:.75rem"><?= $k['son_giris'] ? date('d.m.Y H:i', strtotime($k['son_giris'])) : '—' ?></td>
    <td><span class="tag <?= $k['aktif'] ? '' : 'kirmizi' ?>"><?= $k['aktif'] ? 'Aktif' : 'Pasif' ?></span></td>
    <td style="white-space:nowrap">
        <form method="post" style="display:inline" onsubmit="var s=prompt('Yeni şifre (en az 6 karakter):'); if(!s) return false; this.sifre.value=s;">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="islem" value="sifre">
            <input type="hidden" name="id" value="<?= $k['id'] ?>"><input type="hidden" name="sifre" value="">
            <button class="btn" style="padding:.3rem .6rem" title="Şifre değiştir"><i class="bi bi-key"></i></button>
        </form>
        <form method="post" style="display:inline">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="islem" value="durum">
            <input type="hidden" name="id" value="<?= $k['id'] ?>">
            <button class="btn <?= $k['aktif'] ? 'tehlike' : '' ?>" style="padding:.3rem .6rem" title="Aktif/Pasif">
                <i class="bi bi-power"></i></button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
</table>
</div>
<div class="card">
    <h3 style="margin:0 0 .5rem;font-size:.95rem"><i class="bi bi-person-plus"></i> Yeni Kullanıcı</h3>
    <form method="post">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="islem" value="ekle">
        <label class="flbl">Kullanıcı Adı</label><input class="frm" name="kullanici_adi" required>
        <label class="flbl">Ad Soyad</label><input class="frm" name="ad_soyad" required>
        <label class="flbl">Şifre</label><input class="frm" type="password" name="sifre" required minlength="6">
        <label class="flbl">Rol</label>
        <select class="frm" name="rol">
            <?php foreach ($rol_ad as $rk => $rv): ?><option value="<?= $rk ?>"><?= $rv ?></option><?php endforeach; ?>
        </select>
        <button class="btn pri" style="margin-top:1rem;width:100%"><i class="bi bi-check-lg"></i> Ekle</button>
    </form>
</div>
</div>
<?php require __DIR__ . '/inc/footer.php'; ?>
