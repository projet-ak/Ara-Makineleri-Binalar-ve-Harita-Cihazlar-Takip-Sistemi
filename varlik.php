<?php
require_once __DIR__ . '/inc/auth.php';
$d = db();
$id = (int)($_GET['id'] ?? 0);
$v = $d->prepare('SELECT * FROM varliklar WHERE id = ?'); $v->execute([$id]); $v = $v->fetch();
if (!$v) { http_response_code(404); die('Varlık bulunamadı.'); }
$baslik = $v['cins'] . ' — ' . trim($v['marka'] . ' ' . $v['model']);
$topbar_sag = yetki('admin') ? '<a class="btn" href="varlik_form.php?id=' . $id . '"><i class="bi bi-pencil"></i> Düzenle</a>' : '';
require_once __DIR__ . '/inc/header.php';

$hareket = $d->prepare('SELECT * FROM hareketler WHERE varlik_id = ? OR (plaka IS NOT NULL AND plaka = ?) ORDER BY islem_tarihi DESC, id DESC');
$hareket->execute([$id, $v['plaka']]); $hareket = $hareket->fetchAll();
$calisma = $d->prepare('SELECT * FROM calisma_saatleri WHERE varlik_id = ? OR (plaka IS NOT NULL AND plaka = ?) ORDER BY yil DESC, id');
$calisma->execute([$id, $v['plaka']]); $calisma = $calisma->fetchAll();
$dosyalar = $d->prepare('SELECT * FROM dosyalar WHERE varlik_id = ? ORDER BY id DESC'); $dosyalar->execute([$id]); $dosyalar = $dosyalar->fetchAll();
$tur_ad = ['foto' => 'Fotoğraf', 'ruhsat' => 'Ruhsat', 'sigorta' => 'Sigorta Poliçesi', 'muayene' => 'Muayene', 'diger' => 'Diğer'];
$toplam_gelir = array_sum(array_column($hareket, 'gelir'));
$toplam_gider = array_sum(array_column($hareket, 'gider'));
?>
<div class="grid" style="grid-template-columns:1fr 1fr;align-items:start">
    <div class="card">
        <h3 style="margin:0 0 .8rem;font-size:.95rem"><i class="bi bi-info-circle"></i> Kimlik Bilgileri
            <span class="tag" style="float:right"><?= e($v['yil']) ?></span></h3>
        <?php if ($v['foto']): ?><img src="<?= e($v['foto']) ?>" style="width:100%;max-height:260px;object-fit:cover;border-radius:12px;margin-bottom:1rem"><?php endif; ?>
        <table class="tbl">
        <?php
        $alanlar = ['Sahiplik' => 'sahiplik', 'IFS Nesne No' => 'ifs_nesne_no', 'Cins' => 'cins', 'Marka' => 'marka',
            'Model' => 'model', 'Ruhsat No' => 'ruhsat_no', 'Plaka / Seri' => 'plaka', 'Motor No' => 'motor_no',
            'Şasi No' => 'sasi_no', 'Model/Alım Yılı' => 'model_yili', 'Lokasyon' => 'lokasyon',
            'Lokasyon Geçmişi' => 'lokasyon_gecmisi', 'Sevk Tarihi' => 'sevk_tarihi'];
        foreach ($alanlar as $ad => $key): if ($v[$key] === null || $v[$key] === '') continue; ?>
        <tr><th style="width:40%"><?= $ad ?></th><td><?= e($v[$key]) ?></td></tr>
        <?php endforeach; ?>
        </table>
    </div>
    <div style="display:grid;gap:1rem">
        <?php if (yetki('admin','yonetim')): ?>
        <div class="card">
            <h3 style="margin:0 0 .8rem;font-size:.95rem"><i class="bi bi-cash-stack"></i> Mali Bilgiler</h3>
            <table class="tbl">
            <tr><th>Alım Fiyatı</th><td><?= para($v['alim_eur'], '€') ?> / <?= para($v['alim_usd'], '$') ?> / <?= tl($v['alim_tl']) ?></td></tr>
            <tr><th>Güncel Fiyat</th><td><?= para($v['guncel_eur'], '€') ?> / <?= para($v['guncel_usd'], '$') ?> / <?= tl($v['guncel_tl']) ?></td></tr>
            <tr><th>Güncel 2. El</th><td><?= para($v['ikinci_el_eur'], '€') ?> / <?= para($v['ikinci_el_usd'], '$') ?> / <?= tl($v['ikinci_el_tl']) ?></td></tr>
            <tr><th>Aylık Kira (op. dahil)</th><td><?= tl($v['kira_op_dahil']) ?></td></tr>
            <tr><th>Aylık Kira (op. hariç)</th><td><?= tl($v['kira_op_haric']) ?></td></tr>
            <tr><th><?= e($v['yil']) ?> Kira Geliri</th><td style="color:#0B6B4D;font-weight:600"><?= tl($v['kira_geliri']) ?></td></tr>
            <tr><th>Bakım/Onarım Gideri</th><td style="color:#B3261E"><?= tl($v['bakim_gideri']) ?></td></tr>
            <tr><th>Operatör Gideri</th><td style="color:#B3261E"><?= tl($v['operator_gideri']) ?></td></tr>
            <tr><th>Sigorta Gideri</th><td style="color:#B3261E"><?= tl($v['sigorta_gideri']) ?></td></tr>
            <tr><th>Kar / Zarar</th><td style="font-weight:700;color:<?= (float)$v['kar_zarar'] >= 0 ? '#0B6B4D' : '#B3261E' ?>"><?= tl($v['kar_zarar']) ?></td></tr>
            <tr><th>Amortisman (faydalı ömür / süre)</th><td><?= e($v['amortisman_omur'] ?? '—') ?> / <?= e($v['fayda_suresi'] ?? '—') ?> yıl</td></tr>
            <tr><th>12 Aylık Amortisman</th><td><?= para($v['amortisman_eur'], '€') ?> / <?= para($v['amortisman_usd'], '$') ?> / <?= tl($v['amortisman_tl']) ?></td></tr>
            </table>
        </div>
        <?php endif; ?>
        <div class="card">
            <h3 style="margin:0 0 .8rem;font-size:.95rem"><i class="bi bi-folder2-open"></i> Dosyalar &amp; Fotoğraflar</h3>
            <?php if (yetki('admin','saha')): ?>
            <form method="post" action="dosya_yukle.php" enctype="multipart/form-data"
                  style="display:flex;gap:.5rem;margin-bottom:.9rem;flex-wrap:wrap">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="varlik_id" value="<?= $id ?>">
                <select class="frm" name="tur" style="width:auto">
                    <?php foreach ($tur_ad as $tk => $tv): ?><option value="<?= $tk ?>"><?= $tv ?></option><?php endforeach; ?>
                </select>
                <input type="file" name="dosya" class="frm" style="width:auto;flex:1" required
                       accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx">
                <button class="btn pri"><i class="bi bi-upload"></i> Yükle</button>
            </form>
            <?php endif; ?>
            <?php if (!$dosyalar): ?><div style="color:var(--mut);font-size:.83rem">Henüz dosya yüklenmemiş.</div><?php endif; ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:.6rem">
            <?php foreach ($dosyalar as $f): $resim = preg_match('/\.(jpe?g|png|webp)$/i', $f['yol']); ?>
                <div style="border:1px solid var(--line);border-radius:10px;overflow:hidden;font-size:.68rem">
                    <a href="<?= e($f['yol']) ?>" target="_blank">
                    <?php if ($resim): ?><img src="<?= e($f['yol']) ?>" style="width:100%;height:80px;object-fit:cover;display:block">
                    <?php else: ?><div style="height:80px;display:flex;align-items:center;justify-content:center;background:#F7FAF9;font-size:1.6rem;color:var(--ern)"><i class="bi bi-file-earmark-text"></i></div><?php endif; ?>
                    </a>
                    <div style="padding:.3rem .45rem">
                        <span class="tag" style="font-size:.6rem"><?= $tur_ad[$f['tur']] ?></span>
                        <div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($f['dosya_adi']) ?></div>
                        <?php if (yetki('admin')): ?>
                        <a href="dosya_sil.php?id=<?= $f['id'] ?>&csrf=<?= csrf_token() ?>" onclick="return confirm('Dosya silinsin mi?')" style="color:#B3261E">Sil</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div class="card" style="margin-top:1rem">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.8rem">
        <h3 style="margin:0;font-size:.95rem"><i class="bi bi-arrow-left-right"></i> Sevk &amp; Mali Hareketler
            <span style="font-size:.72rem;color:var(--mut);font-weight:400;margin-left:.6rem">
                Toplam gelir: <b style="color:#0B6B4D"><?= tl($toplam_gelir) ?></b> · gider: <b style="color:#B3261E"><?= tl($toplam_gider) ?></b></span></h3>
        <?php if (yetki('admin','saha')): ?><a class="btn pri" href="hareket_form.php?varlik_id=<?= $id ?>"><i class="bi bi-plus-lg"></i> Hareket Ekle</a><?php endif; ?>
    </div>
    <div style="overflow-x:auto;max-height:420px;overflow-y:auto">
    <table class="tbl"><tr><th>Tarih</th><th>İşlem</th><th>Lokasyon</th><th>Açıklama</th><th style="text-align:right">Gelir</th><th style="text-align:right">Gider</th></tr>
    <?php foreach ($hareket as $h): ?>
    <tr><td><?= $h['islem_tarihi'] ? date('d.m.Y', strtotime($h['islem_tarihi'])) : '—' ?></td>
        <td><span class="tag <?= $h['islem_turu'] === 'HAKEDİŞ' ? '' : ($h['islem_turu'] === 'SEVK' ? 'sari' : 'kirmizi') ?>"><?= e($h['islem_turu']) ?></span></td>
        <td><?= e($h['lokasyon']) ?></td><td><?= e($h['aciklama']) ?></td>
        <td style="text-align:right;color:#0B6B4D"><?= $h['gelir'] ? tl($h['gelir']) : '' ?></td>
        <td style="text-align:right;color:#B3261E"><?= $h['gider'] ? tl($h['gider']) : '' ?></td></tr>
    <?php endforeach; ?></table>
    </div>
</div>

<div class="card" style="margin-top:1rem">
    <h3 style="margin:0 0 .8rem;font-size:.95rem"><i class="bi bi-speedometer2"></i> Çalışma Saatleri / KM</h3>
    <div style="overflow-x:auto">
    <table class="tbl"><tr><th>Yıl</th><th>Ay</th><th>Güncel Saat/KM</th><th>Son Bakım</th><th>Son Bakım Tarihi</th><th>Muayene Geçerlilik</th><th>Günlük Kayıt</th></tr>
    <?php foreach ($calisma as $c): $g = $c['gunluk'] ? json_decode($c['gunluk'], true) : []; ?>
    <tr><td><?= $c['yil'] ?></td><td><?= e($c['ay']) ?></td><td><?= e($c['guncel_deger']) ?></td>
        <td><?= e($c['son_bakim']) ?></td>
        <td><?= $c['son_bakim_tarihi'] ? date('d.m.Y', strtotime($c['son_bakim_tarihi'])) : '—' ?></td>
        <td><?php if ($c['muayene_tarihi']): $gecmis = strtotime($c['muayene_tarihi']) < time(); ?>
            <span class="tag <?= $gecmis ? 'kirmizi' : '' ?>"><?= date('d.m.Y', strtotime($c['muayene_tarihi'])) ?></span><?php else: ?>—<?php endif; ?></td>
        <td style="font-size:.7rem;max-width:280px"><?php
            if ($g) { $parcalar = []; foreach ($g as $gun => $deger) $parcalar[] = "$gun: " . e($deger); echo implode(' · ', $parcalar); } else echo '—';
        ?></td></tr>
    <?php endforeach; ?></table>
    </div>
</div>
<?php require __DIR__ . '/inc/footer.php'; ?>
