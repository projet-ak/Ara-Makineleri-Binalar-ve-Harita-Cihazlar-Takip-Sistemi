-- ERN Varlık Yönetim ve Takip — veritabanı şeması
-- MySQL 5.7+ / MariaDB 10.3+
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS kullanicilar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kullanici_adi VARCHAR(60) NOT NULL UNIQUE,
    ad_soyad VARCHAR(120) NOT NULL,
    sifre_hash VARCHAR(255) NOT NULL,
    rol ENUM('admin','saha','yonetim') NOT NULL DEFAULT 'saha',
    aktif TINYINT(1) NOT NULL DEFAULT 1,
    son_giris DATETIME NULL,
    olusturma DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

CREATE TABLE IF NOT EXISTS varliklar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    yil SMALLINT NOT NULL DEFAULT 2026,          -- kayıt yılı (2025 arşiv / 2026 güncel)
    s_no DECIMAL(8,1) NULL,
    sahiplik VARCHAR(120) NULL,
    ifs_nesne_no VARCHAR(80) NULL,
    cins VARCHAR(150) NOT NULL,
    marka VARCHAR(150) NULL,
    model VARCHAR(150) NULL,
    ruhsat_no VARCHAR(60) NULL,
    plaka VARCHAR(60) NULL,
    motor_no VARCHAR(80) NULL,
    sasi_no VARCHAR(80) NULL,
    model_yili VARCHAR(20) NULL,
    lokasyon_gecmisi TEXT NULL,
    lokasyon VARCHAR(200) NULL,
    sevk_tarihi VARCHAR(60) NULL,
    alim_eur DECIMAL(18,2) NULL,
    alim_usd DECIMAL(18,2) NULL,
    alim_tl DECIMAL(18,2) NULL,
    guncel_eur DECIMAL(18,2) NULL,
    guncel_usd DECIMAL(18,2) NULL,
    guncel_tl DECIMAL(18,2) NULL,
    ikinci_el_eur DECIMAL(18,2) NULL,
    ikinci_el_usd DECIMAL(18,2) NULL,
    ikinci_el_tl DECIMAL(18,2) NULL,
    kira_op_dahil DECIMAL(18,2) NULL,            -- operatör dahil yakıt hariç aylık kira
    kira_op_haric DECIMAL(18,2) NULL,            -- operatör ve yakıt hariç aylık kira
    kira_geliri DECIMAL(18,2) NULL,              -- yıl kira geliri (TL)
    bakim_gideri DECIMAL(18,2) NULL,
    operator_gideri DECIMAL(18,2) NULL,
    operatorsuz_kalan DECIMAL(18,2) NULL,
    sigorta_gideri DECIMAL(18,2) NULL,
    kar_zarar DECIMAL(18,2) NULL,
    amortisman_omur DECIMAL(8,1) NULL,
    fayda_suresi DECIMAL(8,1) NULL,
    amortisman_eur DECIMAL(18,2) NULL,
    amortisman_usd DECIMAL(18,2) NULL,
    amortisman_tl DECIMAL(18,2) NULL,
    faiz_gideri DECIMAL(18,2) NULL,
    foto VARCHAR(255) NULL,                      -- kapak fotoğrafı
    notlar TEXT NULL,
    aktif TINYINT(1) NOT NULL DEFAULT 1,
    guncelleme DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (yil), INDEX (plaka), INDEX (cins), INDEX (lokasyon(60)), INDEX (sahiplik(60))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

CREATE TABLE IF NOT EXISTS hareketler (
    id INT AUTO_INCREMENT PRIMARY KEY,
    varlik_id INT NULL,
    cins_tam VARCHAR(220) NULL,                  -- Excel'den gelen tam tanım (eşleşmeyenler için)
    plaka VARCHAR(60) NULL,
    lokasyon VARCHAR(200) NULL,
    islem_turu ENUM('SEVK','BAKIM ONARIM','HAKEDİŞ','SİGORTA','MUAYENE','DİĞER') NOT NULL DEFAULT 'DİĞER',
    aciklama TEXT NULL,
    islem_tarihi DATE NULL,
    gelir DECIMAL(18,2) NULL,
    gider DECIMAL(18,2) NULL,
    ekleyen INT NULL,
    olusturma DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (varlik_id), INDEX (islem_turu), INDEX (islem_tarihi),
    FOREIGN KEY (varlik_id) REFERENCES varliklar(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

CREATE TABLE IF NOT EXISTS calisma_saatleri (
    id INT AUTO_INCREMENT PRIMARY KEY,
    varlik_id INT NULL,
    plaka VARCHAR(60) NULL,
    lokasyon VARCHAR(200) NULL,
    yil SMALLINT NOT NULL DEFAULT 2026,
    ay VARCHAR(15) NOT NULL,                     -- OCAK..ARALIK
    guncel_deger VARCHAR(60) NULL,               -- güncel çalışma saati / km
    son_bakim VARCHAR(60) NULL,
    son_bakim_tarihi DATE NULL,
    muayene_tarihi DATE NULL,
    gunluk JSON NULL,                            -- {"1": "8", "2": "7.5", ...}
    olusturma DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (varlik_id), INDEX (yil), INDEX (ay),
    FOREIGN KEY (varlik_id) REFERENCES varliklar(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

CREATE TABLE IF NOT EXISTS dosyalar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    varlik_id INT NOT NULL,
    tur ENUM('foto','ruhsat','sigorta','muayene','diger') NOT NULL DEFAULT 'diger',
    dosya_adi VARCHAR(255) NOT NULL,
    yol VARCHAR(255) NOT NULL,
    yukleyen INT NULL,
    olusturma DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (varlik_id),
    FOREIGN KEY (varlik_id) REFERENCES varliklar(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

CREATE TABLE IF NOT EXISTS kurlar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    eur DECIMAL(12,4) NOT NULL,
    usd DECIMAL(12,4) NOT NULL,
    kaynak VARCHAR(30) NOT NULL DEFAULT 'TCMB',
    guncelleme DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Varsayılan yönetici: kullanıcı adı admin / şifre ernvarlik2026 (girişten sonra değiştirin!)
INSERT INTO kullanicilar (kullanici_adi, ad_soyad, sifre_hash, rol) VALUES
('admin', 'Sistem Yöneticisi', '$2y$10$AbQdxgBrkeZBS54BaC0wWu2Gia.lLBgFhGptISvOWnkCL4l/njGSW', 'admin');
