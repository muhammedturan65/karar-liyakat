<?php
/**
 * cron_jobs/generate_qa.php - Otomatik AI Veri Seti Üretici v2.2 (Güvenlikli)
 * 
 * Bu betik, bir Cron Job tarafından periyodik olarak çalıştırılmak üzere tasarlanmıştır.
 * Çalışması için URL'de "?secret_key=..." şeklinde bir güvenlik anahtarı
 * gönderilmesini zorunlu kılar.
 */

// --- GÜVENLİK KONTROLÜ ---
// Kendinize özel, tahmin edilmesi zor bir anahtar belirleyin.
$allowed_secret_key = 'BenimCokGizliCronAnahtarim12345'; 
$provided_key = $_GET['secret_key'] ?? '';

if ($provided_key !== $allowed_secret_key) {
    // Tarayıcıdan veya yanlış anahtarla erişilirse, yetkisiz erişim hatası ver.
    header('HTTP/1.0 403 Forbidden');
    die('Erisim Engellendi.');
}

// Betiğin ana dizine göre çalışmasını sağla (Cron Job için kritik).
chdir(dirname(__DIR__));

// Gerekli dosyaları çağır
require_once 'config/database.php';

// Her bir çalıştırma için maksimum süreyi ayarla.
set_time_limit(270); // 4.5 dakika

// Betiğin komut satırından mı (CLI) yoksa tarayıcıdan mı çalıştırıldığını algıla.
$is_cli = (php_sapi_name() == 'cli');

if ($is_cli) {
    echo "=====================================================\n";
    echo "Otomatik QA Veri Seti Üretici Başlatıldı - " . date('Y-m-d H:i:s') . "\n";
    echo "=====================================================\n";
} else {
    // Tarayıcıdan erişildiğinde de çıktının düz metin olarak görünmesini sağla
    header('Content-Type: text/plain; charset=utf-8');
    echo "Otomatik QA Veri Seti Üretici Başlatıldı - " . date('Y-m-d H:i:s') . "\n\n";
    @ob_end_flush();
    @flush();
}

try {
    $pdo = getDbConnection();
    
    $limit = 5; 
    $stmt = $pdo->prepare(
        "SELECT id, icerik_ham FROM kararlar 
         WHERE id NOT IN (SELECT DISTINCT decision_id FROM qa_dataset) 
         LIMIT :limit"
    );
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $decisions_to_process = $stmt->fetchAll();

    if (empty($decisions_to_process)) {
        echo "İşlenecek yeni karar bulunamadı. Betik sonlandırılıyor.\n";
        exit;
    }

    echo count($decisions_to_process) . " adet yeni karar bulundu. İşlem başlatılıyor...\n\n";
    $totalSaved = 0;

    $apiKey = 'AIzaSyCa-asxekx57nDQ6L0HUTpRErGFym5oREM'; 
    $modelName = 'gemini-2.5-flash-lite';
    $apiUrl = "https://generativelanguage.googleapis.com/v1/models/{$modelName}:generateContent";

    foreach ($decisions_to_process as $decision) {
        $kararId = $decision['id'];
        $hamMetin = $decision['icerik_ham'];
        echo "-> İşleniyor: Karar ID " . $kararId . "\n";

        if (mb_strlen($hamMetin) < 200) {
            echo "  |-- UYARI: Karar metni çok kısa (< 200 karakter). Atlanıyor...\n";
            continue;
        }

        $prompt = "Sen bir hukuk profesörüsün. Görevin, sana verilen HUKUKİ METNİ analiz ederek, bu metinden bir hukuk öğrencisinin sorabileceği 5 adet önemli ve spesifik soru ve bu soruların cevaplarını çıkarmaktır. Cevaplar, sadece ve sadece metnin içindeki bilgilere dayanmalıdır. Yanıtını, başka hiçbir açıklama yapmadan, doğrudan bir JSON dizisi (array of objects) formatında ver. Her obje 'soru' ve 'cevap' anahtarlarını içermelidir.\n--- HUKUKİ METİN ---\n" . mb_substr($hamMetin, 0, 15000) . "\n\n--- JSON ÇIKTISI ---";
        
        $postData = [
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => [ 'temperature' => 0.6, 'maxOutputTokens' => 4096 ],
            'safetySettings' => [
                ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_NONE'],
                ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_NONE'],
                ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_NONE'],
                ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_NONE'],
            ]
        ];
        
        $maxRetries = 3; $delay = 2; $response = null; $httpCode = 0;
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
            if ($attempt < $maxRetries && $httpCode >= 500) { echo "  |-- DENEME {$attempt}: HTTP {$httpCode} alındı. {$delay} saniye bekleniyor...\n"; sleep($delay); $delay *= 2; } 
            else { break; }
        }

        if ($httpCode !== 200) {
            echo "  |-- HATA: AI servisinden yanıt alınamadı. HTTP Kodu: {$httpCode}. Atlanıyor...\n";
            error_log("generate_qa.php - AI Hatası: Karar ID {$kararId}, HTTP {$httpCode}");
            continue;
        }
        
        $decodedResponse = json_decode($response, true);
        $raw_text = $decodedResponse['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $cleaned_text = str_replace(['```json', '```'], '', $raw_text);
        $qa_pairs = json_decode(htmlspecialchars_decode($cleaned_text), true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($qa_pairs)) {
            echo "  |-- HATA: Geçerli formatta JSON alınamadı. Atlanıyor...\n";
            error_log("generate_qa.php - JSON Parse Hatası: Karar ID {$kararId}, Ham Yanıt: {$raw_text}");
            continue;
        }

        $insertStmt = $pdo->prepare("INSERT INTO qa_dataset (decision_id, question, answer) VALUES (?, ?, ?)");
        $savedCount = 0;
        foreach ($qa_pairs as $pair) {
            if (isset($pair['soru']) && isset($pair['cevap'])) {
                $insertStmt->execute([$kararId, trim($pair['soru']), trim($pair['cevap'])]);
                $savedCount++;
            }
        }
        echo "  |-- BAŞARILI: {$savedCount} adet Soru-Cevap çifti kaydedildi.\n";
        $totalSaved += $savedCount;

        sleep(10);
    }
    
    echo "\nİşlem tamamlandı. Bu çalıştırmada toplam {$totalSaved} adet Soru-Cevap çifti veritabanına eklendi.\n";

} catch (Exception $e) {
    $errorMessage = "KRİTİK HATA: " . $e->getMessage() . "\n";
    echo $errorMessage;
    error_log("generate_qa.php HATA: " . $errorMessage);
}
?>