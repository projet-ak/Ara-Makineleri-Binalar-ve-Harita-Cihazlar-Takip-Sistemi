<?php
$baslik = 'Yedekleme';
require_once __DIR__ . '/inc/auth.php';
yetki_zorunlu('admin');
require_once __DIR__ . '/inc/yedek.php';
$mesaj = ''; $tip = 'ok';

// İndirme
if (isset($_GET['indir'])) {
    $ad = $_GET['indir'];
    if (preg_match('/^yedek_[\w\-\.]+\.sql\.gz$/', $ad) && is_file(YEDEK_DIZIN . '/' . $ad)) {
        header('Content-Type: application/gzip');
        header('Content-Disposition: attachment; filename="' . $ad . '"');
        header('Content-Length: ' . filesize(YEDEK_DIZIN . '/' . $ad));
        readfile(YEDEK_DIZIN . '/' . $ad);
        exit;
    }
    http_response_code(404); die('Yedek bulunamadı.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_kontrol();
    $islem = $_POST['islem'] ?? '';
    if ($islem === 'al') {
        $ad = yedek_al('manuel');
        $mesaj = 'Yedek alındı: ' . $ad;
    } elseif ($islem === 'geri') {
        yedek_al('guvenlik'); // geri yükleme öncesi mevcut durumun güvenlik yedeği
        [$ok, $m] = yedek_geri_yukle($_POST['dosya'] ?? '');
        $mesaj = $m; $tip = $ok ? 'ok' : 'err';
        if ($ok) $mesaj .= ' Geri yükleme öncesi güvenlik yedeği de alındı.';
    } elseif ($islem === 'sil') {
        $ad = $_POST['dosya'] ?? '';
        if (preg_match('/^yedek_[\w\-\.]+\.sql\.gz$/', $ad) && is_file(YEDEK_DIZIN . '/' . $ad)) {
            unlink(YEDEK_DIZIN . '/' . $ad);
            $mesaj = 'Yedek silindi.';
        } else { $mesaj = 'Dosya bulunamadı.'; $tip = 'err'; }
    } elseif ($islem === 'yukle') {
        if (!empty($_FILES['dosya']) && $_FILES['dosya']['error'] === UPLOAD_ERR_OK) {
            $orjinal = $_FILES['dosya']['name'];
            if (preg_match('/^yedek_[\w\-\.]+\.sql\.gz$/', $orjinal)) {
                if (!is_dir(YEDEK_DIZIN)) mkdir(YEDEK_DIZIN, 0750, true);
                move_uploaded_file($_FILES['dosya']['tmp_name'], YEDEK_DIZIN . '/' . $orjinal);
                $mesaj = 'Yedek dosyası yüklendi: ' . $orjinal . ' — listeden geri yükleyebilirsiniz.';
            } else { $mesaj = 'Sadece bu sistemin ürettiği yedek_*.sql.gz dosyaları kabul edilir.'; $tip = 'err'; }
        } else { $mesaj = 'Dosya yüklenemedi.'; $tip = 'err'; }
    }
}
$liste = yedek_listesi();
require_once __DIR__ . '/inc/header.php';
?>
<?php if ($mesaj): ?><div class="mesaj <?= $tip ?>"><?= e($mesaj) ?></div><?php endif; ?>
<div class="grid" style="grid-template-columns:1fr 340px;align-items:start">
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.9rem">
        <h3 style="margin:0;font-size:.95rem"><i class="bi bi-clock-history" style="color:var(--ern-light)"></i>
            Yedekler <span style="font-size:.7rem;color:var(--mut);font-weight:400">(son <?= YEDEK_SAKLA ?> adet saklanır)</span></h3>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="islem" value="al">
            <button class="btn pri"><i class="bi bi-database-add"></i> Şimdi Yedek Al</button>
        </form>
    </div>
    <?php if (!$liste): ?><div style="color:var(--mut);font-size:.85rem">Henüz yedek yok. İlk otomatik yedek bir sonraki sayfa açılışında alınır.</div><?php endif; ?>
    <table class="tbl">
    <?php if ($liste): ?><tr><th>Dosya</th><th>Tür</th><th style="text-align:right">Boyut</th><th>Tarih</th><th style="text-align:right">İşlem</th></tr><?php endif; ?>
    <?php foreach ($liste as $y):
        $manuel = str_contains($y['ad'], '_manuel');
        $guvenlik = str_contains($y['ad'], '_guvenlik'); ?>
    <tr>
        <td style="font-size:.78rem;font-family:monospace"><?= e($y['ad']) ?></td>
        <td><span class="tag <?= $manuel ? 'sari' : ($guvenlik ? 'kirmizi' : '') ?>">
            <?= $manuel ? 'Manuel' : ($guvenlik ? 'Güvenlik' : 'Otomatik') ?></span></td>
        <td style="text-align:right"><?= number_format($y['boyut'] / 1024, 0, ',', '.') ?> KB</td>
        <td><?= date('d.m.Y H:i', $y['tarih']) ?></td>
        <td style="text-align:right;white-space:nowrap">
            <a class="btn" style="padding:.3rem .6rem" href="?indir=<?= e($y['ad']) ?>" title="İndir"><i class="bi bi-download"></i></a>
            <form method="post" style="display:inline"
                  onsubmit="return confirm('DİKKAT: Mevcut tüm veriler bu yedekteki verilerle DEĞİŞTİRİLECEK.\nÖnce otomatik güvenlik yedeği alınacak.\n\nDevam edilsin mi?')">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="islem" value="geri">
                <input type="hidden" name="dosya" value="<?= e($y['ad']) ?>">
                <button class="btn" style="padding:.3rem .6rem" title="Geri yükle"><i class="bi bi-arrow-counterclockwise"></i></button>
            </form>
            <form method="post" style="display:inline" onsubmit="return confirm('Yedek silinsin mi?')">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="islem" value="sil">
                <input type="hidden" name="dosya" value="<?= e($y['ad']) ?>">
                <button class="btn tehlike" style="padding:.3rem .6rem" title="Sil"><i class="bi bi-trash"></i></button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
    </table>
</div>
<div style="display:grid;gap:1rem">
    <div class="card">
        <h3 style="margin:0 0 .6rem;font-size:.95rem"><i class="bi bi-info-circle" style="color:var(--ern-light)"></i> Otomatik Yedekleme</h3>
        <div style="font-size:.8rem;color:var(--mut);line-height:1.6">
            Sistem her gün <b>ilk sayfa açılışında</b> otomatik yedek alır — ayrıca bir şey yapmanız gerekmez.<br><br>
            Garantili saatli yedek isterseniz aaPanel &rarr; Cron'a şu görevi ekleyin (her gece 03:00):<br>
            <code style="font-size:.7rem;background:#F2F5F4;padding:.2rem .4rem;border-radius:6px;display:block;margin-top:.4rem">
            php /www/wwwroot/ernsaha.com.tr/ernvarlik/yedek_cron.php</code>
        </div>
    </div>
    <div class="card">
        <h3 style="margin:0 0 .6rem;font-size:.95rem"><i class="bi bi-upload" style="color:var(--ern-light)"></i> Yedek Dosyası Yükle</h3>
        <p style="font-size:.75rem;color:var(--mut);margin:0 0 .6rem">Daha önce indirdiğiniz <code>yedek_*.sql.gz</code> dosyasını yükleyip listeden geri yükleyebilirsiniz.</p>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="islem" value="yukle">
            <input type="file" name="dosya" class="frm" accept=".gz" required>
            <button class="btn pri" style="margin-top:.7rem;width:100%"><i class="bi bi-upload"></i> Yükle</button>
        </form>
    </div>
    <div class="card" style="border-color:#F3C6C1">
        <h3 style="margin:0 0 .6rem;font-size:.95rem;color:#B3261E"><i class="bi bi-exclamation-triangle"></i> Geri Yükleme Hakkında</h3>
        <div style="font-size:.78rem;color:var(--mut);line-height:1.6">
            Geri yükleme, <b>mevcut tüm verileri</b> seçilen yedekteki verilerle değiştirir.
            İşlem öncesi sistem otomatik bir <b>güvenlik yedeği</b> alır; yanlışlıkla geri yüklerseniz
            o güvenlik yedeğinden tekrar dönebilirsiniz.
        </div>
    </div>
</div>
</div>
<?php require __DIR__ . '/inc/footer.php'; ?>
