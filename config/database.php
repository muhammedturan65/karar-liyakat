<?php
/**
 * config/database.php - Proje Yapılandırma Dosyası
 * 
 * Bu sürüm, Türkçe karakter sorunlarını kesin olarak çözmek için
 * bağlantı kurulduktan hemen sonra "SET NAMES utf8mb4" komutunu çalıştırır.
 */

// --- Veritabanı Bağlantı Bilgileri ---
define('DB_HOST', 'localhost');
define('DB_USER', 'liyakatn_krr');
define('DB_PASS', 'Muhammed123.');
define('DB_NAME', 'liyakatn_karar');

// --- Dış Servis (API) Ayarları ---
define('PDF_API_URL', 'https://v2.api2pdf.com/chrome/html');
define('PDF_API_KEY', '06796bfc-cf1e-478b-949d-9f821a3cf97f');


/**
 * PDO kullanarak veritabanına bir bağlantı nesnesi oluşturur ve döndürür.
 */
function getDbConnection() {
    static $pdo = null; 

    if ($pdo === null) {
        // DSN'de charset belirtmek iyidir, ancak bazen yeterli olmaz.
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            
            // --- EN ÖNEMLİ DÜZELTME BURADA ---
            // Bağlantı kurulduktan hemen sonra, bu bağlantı üzerindeki
            // karakter setini zorla utf8mb4 olarak ayarla.
            // Bu, sunucu varsayılanlarını geçersiz kılar.
            $pdo->exec("SET NAMES 'utf8mb4'");

        } catch (PDOException $e) {
            error_log("Veritabanı bağlantı hatası: " . $e->getMessage());
            die("Sistemsel bir hata oluştu. Lütfen daha sonra tekrar deneyin.");
        }
    }
    return $pdo;
}
?>