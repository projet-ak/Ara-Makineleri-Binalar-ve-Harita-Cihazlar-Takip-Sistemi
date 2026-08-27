<?php
// Veritabanı yedekleme — saf PHP (mysqldump/exec gerektirmez), gzip sıkıştırmalı.
require_once __DIR__ . '/db.php';

const YEDEK_DIZIN = __DIR__ . '/../backups';
const YEDEK_AYRAC = "\n-- ERN_STMT --\n";   // geri yükleme için güvenli ifade ayracı
const YEDEK_SAKLA = 30;                      // en fazla saklanan yedek sayısı

function yedek_al(string $etiket = 'otomatik'): string {
    if (!is_dir(YEDEK_DIZIN)) mkdir(YEDEK_DIZIN, 0750, true);
    $d = db();
    $sql = "-- ERN Varlık Yönetim ve Takip — veritabanı yedeği\n-- Tarih: " . date('Y-m-d H:i:s') .
           "\n-- Etiket: $etiket\n" . YEDEK_AYRAC .
           "SET NAMES utf8mb4" . YEDEK_AYRAC .
           "SET FOREIGN_KEY_CHECKS=0" . YEDEK_AYRAC;
    $tablolar = $d->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tablolar as $t) {
        $create = $d->query("SHOW CREATE TABLE `$t`")->fetch();
        $sql .= "DROP TABLE IF EXISTS `$t`" . YEDEK_AYRAC;
        $sql .= $create['Create Table'] . YEDEK_AYRAC;
        $st = $d->query("SELECT * FROM `$t`");
        while ($satirlar = $st->fetchAll(PDO::FETCH_NUM)) {
            foreach (array_chunk($satirlar, 200) as $parca) {
                $degerler = [];
                foreach ($parca as $r) {
                    $degerler[] = '(' . implode(',', array_map(
                        fn($v) => $v === null ? 'NULL' : $d->quote((string)$v), $r)) . ')';
                }
                $sql .= "INSERT INTO `$t` VALUES " . implode(',', $degerler) . YEDEK_AYRAC;
            }
            break; // fetchAll zaten hepsini aldı
        }
    }
    $sql .= "SET FOREIGN_KEY_CHECKS=1" . YEDEK_AYRAC;
    $ad = 'yedek_' . date('Y-m-d_His') . '_' . $etiket . '.sql.gz';
    file_put_contents(YEDEK_DIZIN . '/' . $ad, gzencode($sql, 6));
    yedek_temizle();
    return $ad;
}

function yedek_listesi(): array {
    if (!is_dir(YEDEK_DIZIN)) return [];
    $liste = [];
    foreach (glob(YEDEK_DIZIN . '/yedek_*.sql.gz') as $f) {
        $liste[] = ['ad' => basename($f), 'boyut' => filesize($f), 'tarih' => filemtime($f)];
    }
    usort($liste, fn($a, $b) => $b['tarih'] <=> $a['tarih']);
    return $liste;
}

function yedek_temizle(): void {
    $liste = yedek_listesi();
    foreach (array_slice($liste, YEDEK_SAKLA) as $eski) {
        @unlink(YEDEK_DIZIN . '/' . $eski['ad']);
    }
}

/** Sadece bu sistemin ürettiği .sql.gz yedeklerini geri yükler. */
function yedek_geri_yukle(string $dosyaAdi): array {
    if (!preg_match('/^yedek_[\w\-\.]+\.sql\.gz$/', $dosyaAdi)) return [false, 'Geçersiz dosya adı.'];
    $yol = YEDEK_DIZIN . '/' . $dosyaAdi;
    if (!is_file($yol)) return [false, 'Yedek dosyası bulunamadı.'];
    $sql = gzdecode(file_get_contents($yol));
    if ($sql === false || !str_contains($sql, YEDEK_AYRAC)) return [false, 'Dosya bu sistemin yedeği değil veya bozuk.'];
    $d = db();
    $ifadeler = array_filter(array_map('trim', explode(YEDEK_AYRAC, $sql)));
    $sayac = 0;
    try {
        foreach ($ifadeler as $ifade) {
            if ($ifade === '' || str_starts_with($ifade, '--')) continue;
            $d->exec($ifade);
            $sayac++;
        }
    } catch (PDOException $ex) {
        return [false, 'Geri yükleme hatası (' . $sayac . '. ifade): ' . $ex->getMessage()];
    }
    return [true, $sayac . ' ifade çalıştırıldı, veritabanı geri yüklendi.'];
}

/** Günlük otomatik yedek: bugün alınmış yedek yoksa alır (sayfa yüklenişinde tetiklenir). */
function yedek_gunluk_kontrol(): void {
    $liste = yedek_listesi();
    $bugun = date('Y-m-d');
    foreach ($liste as $y) {
        if (str_starts_with($y['ad'], 'yedek_' . $bugun)) return; // bugün alınmış
    }
    try { yedek_al('otomatik'); } catch (Throwable $ex) { /* sayfayı bozma */ }
}
