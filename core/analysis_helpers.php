<?php
/**
 * core/analysis_helpers.php
 * 
 * Bu dosya, projenin tüm paylaşılan analiz fonksiyonlarını merkezi bir yerde toplar.
 * Bu sürüm, geçici sunucu hatalarına karşı "Otomatik Yeniden Deneme" mekanizması içerir.
 */

/**
 * Bir metni Google Gemini API'sine göndererek özetini ve anahtar kelimelerini alır.
 */
/**
 * Bir metni Google Gemini API'sine göndererek özetini ve anahtar kelimelerini alır.
 * Bu sürüm, geçici sunucu hatalarına karşı "Otomatik Yeniden Deneme" ve
 * betiğin çökmesini önlemek için özel cURL zaman aşımı ayarları içerir.
 * @param string $text Özetlenecek ham metin.
 * @return array|null ['ozet' => '...', 'anahtar_kelimeler' => '...'] veya hata durumunda null.
 */
function getAiSummary($text) {
    // Google AI Studio'dan aldığınız API anahtarını buraya girin.
    $apiKey = 'AIzaSyBeW0ijdkaR5rnVjgDWvIgaH3Xh2V_114g'; 
    if (empty($apiKey) || $apiKey === 'YOUR_GEMINI_API_KEY') {
        error_log("Gemini API anahtarı (getAiSummary) ayarlanmamış.");
        return null;
    }
    
    $modelName = 'gemini-2.5-flash-preview-09-2025';
    $apiUrl = "https://generativelanguage.googleapis.com/v1/models/{$modelName}:generateContent";

    $prompt = "Aşağıdaki T.C. Yargıtay/BAM karar metnini analiz et. Yanıtını sadece ve sadece geçerli bir JSON formatında ver. JSON objesi şu iki anahtarı içermeli: 'ozet' ve 'anahtar_kelimeler'. 'ozet' anahtarı, kararın ana fikrini, tarafların taleplerini ve mahkemenin sonucunu içeren 2-3 cümlelik tarafsız bir özet olmalı. 'anahtar_kelimeler' anahtarı ise kararda geçen en önemli 3 hukuki kavramı içeren bir metin olmalı (virgülle ayrılmış). Başka hiçbir açıklama veya metin ekleme. İşte metin:\n\n" . mb_substr($text, 0, 15000);

    $postData = [
        'contents' => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => [
            'responseMimeType' => 'application/json',
            'temperature' => 0.3
        ],
        'safetySettings' => [
            ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_NONE'],
            ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_NONE'],
            ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_NONE'],
            ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_NONE'],
        ]
    ];

    // Otomatik Yeniden Deneme (Exponential Backoff) Mantığı
    $maxRetries = 3; $delay = 1; $response = null; $httpCode = 0;
    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'x-goog-api-key: ' . $apiKey]);
        
        // --- ZAMAN AŞIMI AYARLARI ---
        // Toplam işlem süresi 90 saniye ile sınırlandırıldı.
        curl_setopt($ch, CURLOPT_TIMEOUT, 90); 
        // Sunucuya bağlanma deneme süresi 20 saniye ile sınırlandırıldı.
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) break; 
        if ($attempt < $maxRetries && $httpCode >= 500) { sleep($delay); $delay *= 2; } 
        else { break; }
    }
    
    if ($response === false || $httpCode !== 200) {
        error_log("Gemini API (getAiSummary) Hatası ({$maxRetries} deneme sonrası) - HTTP Kodu: {$httpCode}, Yanıt: {$response}");
        return null; // Başarısız olursa null döndür
    }

    $decoded = json_decode($response, true);
    
    if (isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
        $jsonStringFromText = $decoded['candidates'][0]['content']['parts'][0]['text'];
        $aiData = json_decode($jsonStringFromText, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($aiData['ozet']) && isset($aiData['anahtar_kelimeler'])) {
            return $aiData;
        }
    }
    
    return null; // Yanıt formatı bozuksa veya başka bir sorun varsa null döndür
}
/**
 * Metin içindeki Kanun Maddeleri, Tarihler gibi varlıkları tanır ve HTML <span> ile etiketler.
 */
function extractEntities($html) {
    if(empty($html)) return $html;
    $pattern = '/((\d+\s+sayılı\s+)?([a-zA-ZğüşıöçĞÜŞİÖÇ\s]+\sKanunu)(\'nun|\'nun)?\s\d+(\/\w+)?\.?\s+maddesi)/ui';
    $html = preg_replace_callback($pattern, function($matches) {
        $kanunAdi = urlencode(trim($matches[3]));
        $link = "https://www.mevzuat.gov.tr/arama?aranacak=" . $kanunAdi;
        return "<a href='{$link}' target='_blank' class='entity entity-law' title='İlgili Mevzuatı Görüntüle'>{$matches[0]}</a>";
    }, $html);
    $pattern = '/(\d{2}\.\d{2}\.\d{4})/ui';
    $html = preg_replace($pattern, "<span class='entity entity-date'>$1</span>", $html);
    return $html;
}
?>