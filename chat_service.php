<?php
/**
 * chat_service.php - AI Hukuk Asistanı API Uç Noktası v4.1 (Akıllı Yorumlama)
 * 
 * Bu sürüm, RAG istemini (prompt) güncelleyerek yapay zekanın, verilen
 * karar metni üzerinden mantıksal çıkarımlar yapmasına izin verir.
 */

// Oturumu başlat (sohbet geçmişini saklamak için)
session_start();

// Gerekli ayar ve yardımcı dosyalarını çağır
require_once 'config/database.php';

/**
 * Bir soruyu ve (isteğe bağlı) bir bağlamı Google Gemini API'sine gönderir.
 * @param string $question Kullanıcının sorusu.
 * @param string|null $context İncelenen karar metni gibi ek bilgi.
 * @return string AI'nin metin yanıtı.
 */
function getChatResponse($question, $context = null) {
    // Google AI Studio'dan aldığınız API anahtarını buraya girin.
    $apiKey = 'AIzaSyBeW0ijdkaR5rnVjgDWvIgaH3Xh2V_114g'; 
    if (empty($apiKey) || $apiKey === 'YOUR_GEMINI_API_KEY') {
        return "HATA: Sunucu tarafında API anahtarı ayarlanmamış.";
    }
    
    $modelName = 'gemini-2.5-flash-preview-09-2025';
    $apiUrl = "https://generativelanguage.googleapis.com/v1/models/{$modelName}:generateContent";

    // --- Akıllı Prompt Seçimi ---

    // 1. Durum: "Detaylı Özetle" özel komutu VE bir karar metni var.
    if (str_contains(strtolower($question), 'detaylı özetle') && $context) {
        $prompt = "Sen bir uzman hukuk analistisin. Görevin, sana verilen aşağıdaki HUKUKİ METNİ analiz ederek temel unsurlarını açıklamaktır. Yanıtın şunları içermeli:
1. Davanın ana konusu nedir?
2. Tarafların temel argümanları nelerdir?
3. Mahkemenin kararı ne olmuştur?
4. Mahkeme bu karara varırken hangi hukuki prensibi veya gerekçeyi temel almıştır?
Bu dört maddeyi, metindeki ifadeleri doğrudan kopyalamak yerine, kendi kelimelerinle hukuki bir dille açıkla.

--- HUKUKİ METİN ---\n" . mb_substr($context, 0, 15000) . "\n\n--- HUKUKİ ANALİZ ---";

    // 2. Durum: Herhangi bir soru VE bir karar metni var (Akıllı RAG Modu).
    } elseif ($context) {
        $prompt = "Sen bir uzman hukuk asistanısın. Görevin, sana verilen aşağıdaki 'HUKUKİ METİN'i bir hukukçu gibi analiz ederek kullanıcının 'SORU'sunu yanıtlamaktır.
- Cevabını öncelikle metindeki bilgilere dayandır.
- Eğer cevap metinde doğrudan geçmiyorsa, metindeki mahkeme türü, karar tarihi, kanun maddeleri gibi ipuçlarını kullanarak mantıksal bir çıkarım yapmaya çalış.
- Yaptığın çıkarımların metne dayalı olduğunu belirt.
- Eğer metinde soruyla ilgili hiçbir ipucu yoksa, o zaman 'Bu sorunun cevabını veya cevaba yönelik bir ipucunu incelenen karar metninde bulamadım.' de.

--- HUKUKİ METİN ---\n" . mb_substr($context, 0, 15000) . "\n\n--- SORU ---\n{$question}";
    
    // 3. Durum: Karar metni yok, sadece genel bir soru var.
    } else {
        $prompt = "Sen genel konularda yardımcı olan bir hukuk asistanısın. Kullanıcının aşağıdaki sorusunu kısa ve net bir şekilde, genel hukuki bilgi çerçevesinde yanıtla. Finansal veya tıbbi tavsiye verme. Vereceğin bilginin yasal tavsiye niteliğinde olmadığını belirt.\n\nSORU: {$question}";
    }

    $postData = [
        'contents' => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => [ 
            'temperature' => 0.5, // Yorumlama ve çıkarım yapabilmesi için yaratıcılığı biraz artırdık
            'maxOutputTokens' => 2048 
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) break; 
        if ($attempt < $maxRetries && $httpCode >= 500) { sleep($delay); $delay *= 2; continue; } 
        else { break; }
    }
    
    if ($response === false || $httpCode !== 200) {
        error_log("Gemini Chat API Hatası ({$maxRetries} deneme sonrası) - HTTP Kodu: {$httpCode}, Yanıt: {$response}");
        return "Üzgünüm, yapay zeka servisi şu anda çok yoğun veya ulaşılamıyor. Lütfen birkaç dakika sonra tekrar deneyin.";
    }

    $decoded = json_decode($response, true);
    
    if (isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
        return $decoded['candidates'][0]['content']['parts'][0]['text'];
    }
    
    $finishReason = $decoded['candidates'][0]['finishReason'] ?? 'UNKNOWN';
    if ($finishReason === 'SAFETY') {
        return "Üzgünüm, isteğiniz güvenlik filtrelerimize takıldığı için bir yanıt oluşturamadım. Lütfen sorunuzu farklı bir şekilde ifade etmeyi deneyin.";
    }

    return "Üzgünüm, yapay zekadan bilinmeyen bir nedenle yanıt alınamadı.";
}

// --- ANA İŞLEM MANTIĞI ---
if (!isset($_SESSION['chat_history'])) {
    $_SESSION['chat_history'] = [['role' => 'assistant', 'content' => 'Merhaba! Ben Hukuk Asistanınız. Genel bir soru sorabilir veya bir karar sayfasındayken o kararla ilgili sorularınızı yöneltebilirsiniz.']];
}
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? $_GET['action'] ?? '';

if ($action === 'send_message') {
    header('Content-Type: application/json; charset=utf-8');
    $userMessage = $input['message'] ?? '';
    $decisionContext = $input['context'] ?? null;
    if (empty($userMessage)) { http_response_code(400); echo json_encode(['error' => 'Mesaj boş olamaz.']); exit; }
    $_SESSION['chat_history'][] = ['role' => 'user', 'content' => $userMessage];
    $aiResponse = getChatResponse($userMessage, $decisionContext);
    $_SESSION['chat_history'][] = ['role' => 'assistant', 'content' => $aiResponse];
    echo json_encode(['history' => $_SESSION['chat_history']]);

} elseif ($action === 'get_history') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['history' => $_SESSION['chat_history']]);

} elseif ($action === 'reset_chat') {
    header('Content-Type: application/json; charset=utf-8');
    $_SESSION['chat_history'] = [['role' => 'assistant', 'content' => 'Merhaba! Sohbet sıfırlandı. Size nasıl yardımcı olabilirim?']];
    echo json_encode(['history' => $_SESSION['chat_history']]);

} elseif ($action === 'export_txt') {
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="sohbet_gecmisi_' . date('Y-m-d') . '.txt"');
    $fullText = "--- Hukuk Asistanı Sohbet Geçmişi ---\n\n";
    foreach($_SESSION['chat_history'] as $msg) { $role = ($msg['role'] === 'user') ? 'Siz' : 'Asistan'; $fullText .= "{$role}:\n{$msg['content']}\n\n-----------------------------------\n\n"; }
    echo $fullText;
    exit();

} else {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(400);
    echo json_encode(['error' => 'Geçersiz eylem.']);
}
?>