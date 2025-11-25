<?php
// Hata raporlamayı en üst seviyede açalım ki hiçbir detayı kaçırmayalım.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Yargıtay Bağlantı Testi Başlatıldı</h1>";
echo "<p>Bu test, sunucunuzun Yargıtay sunucusundan tam olarak ne yanıt aldığını gösterecektir.</p>";
echo "<hr>";

// Test edeceğimiz sabit URL
$hedefURL = 'https://karararama.yargitay.gov.tr/getDokuman?id=518759900';
echo "<b>Hedef URL:</b> " . htmlspecialchars($hedefURL) . "<br><br>";

// cURL oturumunu başlat
$ch = curl_init();

// Sunucumuzu normal bir tarayıcı gibi tanıtacak ayarları yapalım
curl_setopt($ch, CURLOPT_URL, $hedefURL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Yanıtı ekrana basmak yerine bir değişkene aktar
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // SSL sertifikasını doğrulama (önemli!)
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
curl_setopt($ch, CURLOPT_HEADER, true); // Yanıt başlıklarını da alalım (çok önemli!)
curl_setopt($ch, CURLOPT_VERBOSE, true); // Daha detaylı bilgi al

// İsteği gönder ve yanıtı al
$response = curl_exec($ch);
$curl_error = curl_error($ch);
$curl_info = curl_getinfo($ch);

// Oturumu kapat
curl_close($ch);

// --- SONUÇLARI EKRANA BASALIM ---

echo "<h2>Test Sonuçları:</h2>";

echo "<h3>1. cURL Hata Bilgisi:</h3>";
echo "<pre>";
if ($curl_error) {
    echo "HATA VAR: " . htmlspecialchars($curl_error);
} else {
    echo "cURL işlemi sırasında doğrudan bir hata oluşmadı.";
}
echo "</pre>";


echo "<h3>2. HTTP Durum Kodu ve Bilgileri:</h3>";
echo "<pre>";
print_r($curl_info);
echo "</pre>";


echo "<h3>3. Sunucudan Gelen Ham Yanıt (Başlıklar ve İçerik Dahil):</h3>";
echo "<p>Eğer bu alan boşsa veya beklenmedik bir HTML sayfası (örn: 'Access Denied', 'Cloudflare', 'Giriş Yap') içeriyorsa, sorun budur.</p>";
echo "<pre style='background-color:#f0f0f0; border:1px solid #ccc; padding:10px; white-space: pre-wrap; word-wrap: break-word;'>";
if ($response) {
    echo htmlspecialchars($response);
} else {
    echo "Sunucudan HİÇBİR YANIT ALINAMADI.";
}
echo "</pre>";

?>