# ERN Varlık Yönetim ve Takip

Araç, iş makineleri, binalar ve harita cihazları takip sistemi.
PHP 8 + MySQL — aaPanel / Ubuntu VPS için tasarlandı.

## Özellikler

- **Varlık envanteri** — cins, marka/model, plaka/şasi, IFS nesne no, sahiplik, lokasyon ve lokasyon geçmişi
- **Mali takip** — alım / güncel / 2. el fiyatları (EUR-USD-TL), kira geliri, bakım-onarım, operatör, sigorta giderleri, kar/zarar, amortisman
- **Sevk & mali hareket defteri** — SEVK, BAKIM ONARIM, HAKEDİŞ, SİGORTA, MUAYENE kayıtları; sevk kaydı varlığın lokasyonunu otomatik günceller
- **Çalışma saatleri** — ay ay saat/KM, son bakım, muayene geçerlilik tarihi (60 gün kala panelde uyarı)
- **Dosya arşivi** — varlık başına fotoğraf, ruhsat, sigorta poliçesi, muayene belgesi
- **TCMB kurları** — EUR/USD 6 saatte bir otomatik, güncelleme tarihi görünür
- **Rol bazlı yetki** — `admin` (her şey) · `saha` (sevk + çalışma saati girişi) · `yonetim` (rapor/mali görünüm)

## Kurulum (aaPanel)

1. **Site**: aaPanel → Web Sitesi → ernsaha.com.tr dizini altına yükleyin:
   `/www/wwwroot/ernsaha.com.tr/ernvarlik`
2. **Veritabanı**: aaPanel → Veritabanları → `ernvarlik` adında DB + kullanıcı oluşturun (utf8mb4).
3. **Şema**: phpMyAdmin'den `install/schema.sql` dosyasını içe aktarın.
4. **Veri**: Excel verilerini içeren `install/seed.sql` dosyasını içe aktarın
   (repo'da yoktur — `python install/import_excel.py "tablo.xlsx"` ile üretilir).
5. **Ayar**: `config.example.php` → `config.php` kopyalayıp DB bilgilerini girin.
6. **İzin**: `uploads/` klasörüne yazma izni verin: `chown -R www:www uploads`
7. Giriş: `admin / ernvarlik2026` — **girişten sonra mutlaka şifreyi değiştirin.**

## Excel içe aktarma

```bash
pip install openpyxl
cd install
python import_excel.py "Araç Ve İş Makineleri Genel Tablo.xlsx"
# seed.sql üretilir -> phpMyAdmin'den içe aktarın
```

2026 + 2025 (arşiv) varlıkları, sevk/mali hareketler ve çalışma saatleri içe aktarılır.
Hareket ve çalışma kayıtları plaka/seri no üzerinden varlıklarla eşleştirilir.

---
Fikir sahibi ve geliştirici: **Tayyar Akbulut** — ERN Holding © 2026
