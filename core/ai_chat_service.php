<?php
/**
 * core/ai_chat_service.php - AI Hukuk Asistanı Streaming Uç Noktası
 * 
 * Bu betik, bir AJAX isteği ile çağrılmaz. Bunun yerine, JavaScript'teki 'EventSource'
 * tarafından çağrılır ve Gemini API'sinden gelen yanıtı anlık olarak, parça parça (streaming)
 * tarayıcıya gönderir.
 */

// Oturum başlat (sohbet geçmişini yönetmek için)
session_start();

// Gerekli ayar dosyasını çağır (bir üst dizinde olduğu varsayılarak)
require_once dirname(__DIR__) . '/config/database.php';

// --- Sunucu Ayarları ---
// Bu betik uzun sürebilir, zaman aşımını kaldır
@set_time_limit(0);
// Çıktı tamponlamasını kapat, veriyi anında gönder
if (ob_get_level()) @ob_end_flush();
// Tarayıcıya verinin anlık olarak gönderileceğini bildir
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

/**
 * Gelen bir veri parçasını (chunk) 'text/event-stream' formatına uygun olarak tarayıcıya gönderir.
 * @param string $data Gönderilecek veri.
 * @param string $event (İsteğe bağlı) Olayın adı.
 */
function send_event($data, $event = 'message') {
    echo "event: " . $event . "\n";
    echo "data: " . $data . "\n\n";
    // Tarayıcının veriyi hemen alması için tamponu zorla boşalt
    flush();
}

// --- ANA İŞLEM MANTIĞI ---

// GET parametrelerinden kullanıcı isteğini al
$question = $_GET['message'] ?? '';
$context = $_GET['context'] ?? null;

if (empty($question)) {
    send_event(json_encode(['error' => 'Mesaj boş olamaz.']), 'error');
    exit();
}

// Kullanıcı mesajını oturumdaki geçmişe ekle
if (!isset($_SESSION['chat_history'])) {
    $_SESSION['chat_history'] = [];
}
$_SESSION['chat_history'][] = ['role' => 'user', 'content' => $question];

// --- Gemini API İsteği ---

$apiKey = 'AIzaSyBeW0ijdkaR5rnVjgDWvIgaH3Xh2V_114g'; 
if (empty($apiKey) || $apiKey === 'YOUR_GEMINI_API_KEY') {
    send_event(json_encode(['error' => 'Sunucu tarafında API anahtarı ayarlanmamış.']), 'error');
    exit();
}

$modelName = 'gemini-2.5-flash-preview-09-2025';
// STREAMING için endpoint'i değiştiriyoruz: ':streamGenerateContent'
$apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:streamGenerateContent?key=" . $apiKey;

if ($context) {
    $prompt = "Sen bir uzman hukuk asistanısın. Görevin, sana verilen aşağıdaki 'HUKUKİ METİN'e dayanarak kullanıcının 'SORU'sunu yanıtlamaktır. Cevabını sadece ve sadece bu metindeki bilgilere dayandır. Eğer cevap metinde açıkça belirtilmiyorsa, 'Bu sorunun cevabı incelenen karar metninde bulunmuyor.' şeklinde yanıt ver. Yorum yapma, sadece metindeki bilgiyi aktar. Cevabını kısa ve net tut.\n\n--- HUKUKİ METİN ---\n" . mb_substr($context, 0, 15000) . "\n\n--- SORU ---\n{$question}";
} else {
    $prompt = "Sen genel konularda yardımcı olan bir hukuk asistanısın. Kullanıcının aşağıdaki sorusunu kısa ve net bir şekilde, genel hukuki bilgi çerçevesinde yanıtla. Finansal veya tıbbi tavsiye verme. Vereceğin bilginin yasal tavsiye niteliğinde olmadığını belirt.\n\nSORU: {$question}";
}

$postData = [
    'contents' => [['parts' => [['text' => $prompt]]]],
    'safetySettings' => [
        ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_NONE'],
        ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_NONE'],
        ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_NONE'],
        ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_NONE'],
    ]
];

// cURL'ü streaming için ayarla
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, false); // Yanıtı doğrudan çıktıya yaz
curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($curl, $data) {
    // Gelen her veri parçasını (chunk) doğrudan tarayıcıya gönder
    echo $data;
    flush();
    return strlen($data);
});

// İsteği gerçekleştir. Veri geldikçe yukarıdaki fonksiyon çalışacak.
curl_exec($ch);

// Hata olup olmadığını kontrol et
if(curl_errno($ch)) {
    $error_msg = curl_error($ch);
    send_event(json_encode(['error' => 'API bağlantı hatası: ' . $error_msg]), 'error');
}

curl_close($ch);

// Akışın bittiğini bildirmek için özel bir olay gönder
send_event('{}', 'finished');
?>