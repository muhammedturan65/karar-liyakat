<?php
/**
 * simple_api_test.php - En Basit API Bağlantı Testi
 * Bu betiğin tek görevi, Google Gemini'ye bağlanıp ham yanıtı doğrudan ekrana basmaktır.
 * Eğer bu çalışmazsa, sorun kesinlikle hosting ortamındadır.
 */

// Tüm hataları göster
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Tarayıcıya bunun bir metin yanıtı olduğunu söyle
header('Content-Type: text/plain; charset=utf-8');

echo "--- API BAĞLANTI TESTİ BAŞLATILDI ---\n\n";

$apiKey = 'AIzaSyCa-asxekx57nDQ6L0HUTpRErGFym5oREM'; 
$modelName = 'gemini-2.5-flash';
$apiUrl = "https://generativelanguage.googleapis.com/v1/models/{$modelName}:generateContent";
$postData = ['contents' => [['parts' => [['text' => 'Bu bir testtir.']]]]];
$jsonPayload = json_encode($postData);

echo "Hedef URL: " . $apiUrl . "\n";
echo "Gönderilen Veri: " . $jsonPayload . "\n\n";
echo "cURL isteği gönderiliyor...\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'x-goog-api-key: ' . $apiKey]);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "--- cURL İSTEĞİ TAMAMLANDI ---\n\n";

if ($response === false) {
    echo "SONUÇ: BAŞARISIZ!\n";
    echo "cURL Hatası: " . $curlError . "\n";
    echo "Bu hata, genellikle sunucunun dış bağlantıyı engellediği anlamına gelir (Firewall).\n";
} else {
    echo "SONUÇ: BAŞARILI BİR YANIT ALINDI (Görünüşe göre).\n";
    echo "Alınan HTTP Kodu: " . $httpCode . "\n\n";
    echo "Alınan Ham Yanıt:\n";
    echo "------------------------------------\n";
    print_r($response);
    echo "\n------------------------------------\n";
}

echo "\n--- TEST SONLANDI ---";
?>