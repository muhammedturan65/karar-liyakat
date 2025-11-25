<?php
/**
 * chat_debug.php - En Basit Sohbet API Bağlantı Testi
 * Bu betiğin tek görevi, Google Gemini'ye bağlanıp tüm süreci ve ham yanıtı 
 * doğrudan ekrana metin olarak basmaktır.
 */

// Tüm PHP hatalarını göster
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Tarayıcıya bunun bir metin yanıtı olduğunu söyle
header('Content-Type: text/plain; charset=utf-8');

echo "--- SOHBET API DEBUG MODU BAŞLATILDI ---\n\n";

// Test için sabit bir soru kullanalım
$question = "Bu kararı detaylı özetle.";
$context = "Bu bir test bağlam metnidir. Davacı, davalıdan alacağını talep etmiştir.";

// --- Gemini API İsteği ---
$apiKey = 'AIzaSyCa-asxekx57nDQ6L0HUTpRErGFym5oREM'; 
$modelName = 'gemini-2.5-flash';
$apiUrl = "https://generativelanguage.googleapis.com/v1/models/{$modelName}:generateContent";

echo "1. Ayarlar Yapılandırıldı:\n";
echo "   - API URL: " . $apiUrl . "\n";
echo "   - Model Adı: " . $modelName . "\n\n";

// Prompt oluşturma
$prompt = "Sen bir uzman hukuk analistisin. Görevin, sana verilen aşağıdaki HUKUKİ METNİ analiz ederek temel unsurlarını açıklamaktır. Yanıtın şunları içermeli:
1. Davanın ana konusu nedir?
2. Tarafların temel argümanları nelerdir?
3. Mahkemenin kararı ne olmuştur?
4. Mahkeme bu karara varırken hangi hukuki prensibi veya gerekçeyi temel almıştır?
Bu dört maddeyi, metindeki ifadeleri doğrudan kopyalamak yerine, kendi kelimelerinle hukuki bir dille açıkla.

--- HUKUKİ METİN ---\n" . $context . "\n\n--- HUKUKİ ANALİZ ---";

$postData = [
    'contents' => [['parts' => [['text' => $prompt]]]],
    'generationConfig' => [ 'temperature' => 0.4, 'maxOutputTokens' => 1024 ],
    'safetySettings' => [
        ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_NONE'],
        ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_NONE'],
        ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_NONE'],
        ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_NONE'],
    ]
];
$jsonPayload = json_encode($postData);

echo "2. Gönderilecek Veri (Payload) Oluşturuldu:\n";
echo "------------------------------------\n";
echo $jsonPayload;
echo "\n------------------------------------\n\n";

echo "3. cURL İsteği Gönderiliyor...\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'x-goog-api-key: ' . $apiKey]);
curl_setopt($ch, CURLOPT_TIMEOUT, 45);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "4. cURL İsteği Tamamlandı.\n\n";

if ($response === false) {
    echo "SONUÇ: BAŞARISIZ!\n";
    echo "cURL Hatası: " . $curlError . "\n";
    echo "Bu hata, genellikle sunucunun dış bağlantıyı engellediği (Firewall) veya zaman aşımına uğradığı anlamına gelir.\n";
} else {
    echo "SONUÇ: BAŞARILI BİR YANIT ALINDI (Görünüşe göre).\n";
    echo "Alınan HTTP Kodu: " . $httpCode . "\n\n";
    echo "Alınan Ham Yanıt:\n";
    echo "------------------------------------\n";
    // JSON'ı daha okunaklı basmak için
    $prettyJson = json_decode($response);
    echo json_encode($prettyJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    echo "\n------------------------------------\n";
}

echo "\n--- TEST SONLANDI ---";
?>