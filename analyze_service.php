<?php
/**
 * analyze_service.php - AI Destekli Hukuki Veri Seti Üretici v2.2
 * 
 * Bu sürüm, Gemini API'sinden gelen ve ```json ... ``` gibi Markdown kod blokları
 * veya &quot; gibi HTML entity kodları içeren hatalı yanıtları temizleyerek
 * JSON parse hatasını giderir.
 */

session_start();
require_once 'config/database.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403); // Forbidden
    echo json_encode(['success' => false, 'message' => 'Bu işlem için giriş yapmalısınız.']);
    exit;
}

$kararId = $_POST['id'] ?? null;
if (!$kararId) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Karar ID eksik.']);
    exit;
}

try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT icerik_ham FROM kararlar WHERE id = ?");
    $stmt->execute([$kararId]);
    $karar = $stmt->fetch();
    if (!$karar || empty($karar['icerik_ham'])) {
        http_response_code(404); // Not Found
        echo json_encode(['success' => false, 'message' => 'Veritabanında bu ID\'ye ait karar metni bulunamadı.']);
        exit;
    }

    $hamMetin = $karar['icerik_ham'];

    // --- Yapay Zeka ile Soru-Cevap Üretme ---
    
    $apiKey = 'AIzaSyCa-asxekx57nDQ6L0HUTpRErGFym5oREM'; 
    $modelName = 'gemini-2.5-flash-lite';
    $apiUrl = "https://generativelanguage.googleapis.com/v1/models/{$modelName}:generateContent";

    $prompt = "Sen bir hukuk profesörüsün. Görevin, sana verilen HUKUKİ METNİ analiz ederek, bu metinden bir hukuk öğrencisinin sorabileceği 5 adet önemli ve spesifik soru ve bu soruların cevaplarını çıkarmaktır. Cevaplar, sadece ve sadece metnin içindeki bilgilere dayanmalıdır. Yanıtını, başka hiçbir açıklama yapmadan, doğrudan bir JSON dizisi (array of objects) formatında ver. Her obje 'soru' ve 'cevap' anahtarlarını içermelidir.
--- HUKUKİ METİN ---\n" . mb_substr($hamMetin, 0, 15000) . "\n\n--- JSON ÇIKTISI ---";

    $postData = [
        'contents' => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => [
            'temperature' => 0.6,
            'maxOutputTokens' => 4096
        ],
        'safetySettings' => [
            ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_NONE'],
            ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_NONE'],
            ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_NONE'],
            ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_NONE'],
        ]
    ];
    
    // cURL isteği ve yeniden deneme mantığı
    $maxRetries = 3; $delay = 1; $response = null; $httpCode = 0;
    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'x-goog-api-key: ' . $apiKey]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 90);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode === 200) break;
        if ($attempt < $maxRetries && $httpCode >= 500) { sleep($delay); $delay *= 2; } 
        else { break; }
    }

    if ($httpCode !== 200 || $response === false) {
        throw new Exception("Yapay zeka servisine bağlanırken bir hata oluştu. HTTP Kodu: {$httpCode}");
    }

    $decodedResponse = json_decode($response, true);
    $raw_text = $decodedResponse['candidates'][0]['content']['parts'][0]['text'] ?? '';
    
    // --- Yanıt Temizleme Mantığı ---
    // 1. Baştaki ve sondaki ```json ve ``` kısımlarını temizle.
    $trimmed_text = str_replace(['```json', '```'], '', $raw_text);
    // 2. Kalan metindeki &quot; gibi HTML kodlarını normal karakterlere geri çevir.
    $cleaned_text = htmlspecialchars_decode($trimmed_text);
    // 3. Tamamen temizlenmiş metni JSON olarak çözmeyi dene.
    $qa_pairs = json_decode($cleaned_text, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($qa_pairs)) {
        throw new Exception("Yapay zekadan geçerli formatta Soru-Cevap çiftleri alınamadı. Ham Yanıt: " . htmlspecialchars($cleaned_text));
    }
    
    $insertStmt = $pdo->prepare("INSERT INTO qa_dataset (decision_id, question, answer) VALUES (?, ?, ?)");
    $savedCount = 0;
    foreach ($qa_pairs as $pair) {
        if (isset($pair['soru']) && isset($pair['cevap'])) {
            $insertStmt->execute([$kararId, trim($pair['soru']), trim($pair['cevap'])]);
            $savedCount++;
        }
    }

    if ($savedCount === 0) {
        throw new Exception("Soru-Cevap çiftleri üretildi ancak veritabanına kaydedilemedi.");
    }

    echo json_encode(['success' => true, 'message' => "$savedCount adet Soru-Cevap çifti başarıyla oluşturuldu ve veritabanına kaydedildi!"]);

} catch (Exception $e) {
    error_log("analyze_service.php HATA: " . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(500); // Sunucu Hatası
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>