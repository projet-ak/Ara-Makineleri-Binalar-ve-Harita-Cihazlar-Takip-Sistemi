<?php
// TCMB güncel kur — 6 saatte bir otomatik yenilenir, kurlar tablosunda saklanır.
require_once __DIR__ . '/db.php';

function guncel_kur(bool $zorla = false): array {
    $son = db()->query('SELECT * FROM kurlar ORDER BY id DESC LIMIT 1')->fetch();
    $eski = !$son || (time() - strtotime($son['guncelleme'])) > 6 * 3600;
    if ($zorla || $eski) {
        $yeni = tcmb_cek();
        if ($yeni) {
            db()->prepare('INSERT INTO kurlar (eur, usd, kaynak) VALUES (?,?,?)')
                ->execute([$yeni['eur'], $yeni['usd'], 'TCMB']);
            $son = db()->query('SELECT * FROM kurlar ORDER BY id DESC LIMIT 1')->fetch();
        }
    }
    return $son ?: ['eur' => 0, 'usd' => 0, 'guncelleme' => null, 'kaynak' => '-'];
}

function tcmb_cek(): ?array {
    $ctx = stream_context_create(['http' => ['timeout' => 8]]);
    $xml = @file_get_contents('https://www.tcmb.gov.tr/kurlar/today.xml', false, $ctx);
    if (!$xml) return null;
    $sx = @simplexml_load_string($xml);
    if (!$sx) return null;
    $eur = $usd = null;
    foreach ($sx->Currency as $c) {
        $kod = (string)$c['CurrencyCode'];
        if ($kod === 'USD') $usd = (float)str_replace(',', '.', (string)$c->ForexSelling);
        if ($kod === 'EUR') $eur = (float)str_replace(',', '.', (string)$c->ForexSelling);
    }
    return ($eur && $usd) ? ['eur' => $eur, 'usd' => $usd] : null;
}
