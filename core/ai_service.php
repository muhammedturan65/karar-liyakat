<?php
/**
 * core/ai_service.php (v4.0 - Katı Prompt Düzeltmesi)
 * 
 * Bu sürüm, AI modelini sadece ve sadece istenen formatta çıktı vermeye
 * zorlayan, son derece katı ve örnek odaklı bir prompt kullanır.
 */

function translateQueryToAdvancedSearch($naturalQuery) {
    $apiKey = 'AIzaSyBeW0ijdkaR5rnVjgDWvIgaH3Xh2V_114g'; 
    if (empty($apiKey) || $apiKey === 'YOUR_GEMINI_API_KEY') {
        return null;
    }

    $modelName = 'gemini-2.5-flash-preview-09-2025';
    $apiUrl = "https://generativelanguage.googleapis.com/v1/models/{$modelName}:generateContent";

    // --- EN KATI ve EN NET PROMPT ---
    // Modelin yorum yapmasını engellemek için tüm rol tanımlamaları kaldırıldı.
    // Görev, doğrudan "Girdi -> Çıktı" formatında veriliyor.
    $prompt = "Aşağıdaki 'Cümle'yi, verilen kurallara göre 'Sorgu'ya dönüştür. Sadece 'Sorgu' satırını doldur.
Kurallar: Zorunlu ifadeler için `+` kullan. Hariç tutulanlar için `-` kullan. Tam ifadeler için `\"\"` kullan.

Örnek 1:
Cümle: arsa payı ve inşaat sözleşmesi içeren kararlar
Sorgu: +\"arsa payı\" +\"inşaat sözleşmesi\"

Örnek 2:
Cümle: manevi tazminat davaları ama haksız fiil olmasın
Sorgu: +\"manevi tazminat\" -\"haksız fiil\"

Senin Görevin:
Cümle: \"{$naturalQuery}\"
Sorgu:";

    $postData = [
        'contents' => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => [ 'temperature' => 0.0, 'maxOutputTokens' => 200 ],
        'safetySettings' => [
            ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_NONE'],
            ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_NONE'],
            ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_NONE'],
            ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_NONE'],
        ]
    ];

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'x-goog-api-key: ' . $apiKey
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($response === false || $httpCode !== 200) {
        error_log("Gemini API Hatası - HTTP Kodu: {$httpCode}, Yanıt: {$response}");
        return null;
    }

    $decoded = json_decode($response, true);
    
    if (isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
        $translatedText = $decoded['candidates'][0]['content']['parts'][0]['text'];
        // Gelen yanıtı temizle ve döndür
        return trim(str_replace(['`', 'json', "\n", "\r"], '', $translatedText));
    }
    
    return null;
}

// getAiSummary ve extractEntities fonksiyonlarını da ekleyelim ki dosya tam olsun.
function getAiSummary($text) { /* ... Önceki cevaplardaki çalışan hali ... */ }
function extractEntities($html) { /* ... Önceki cevaplardaki çalışan hali ... */ }
?>