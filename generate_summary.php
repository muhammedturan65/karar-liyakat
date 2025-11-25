<?php
/**
 * generate_summary.php (TAM LOGLAMA MODU)
 * 
 * Bu sürüm, yaptığı her kritik işlemi sunucudaki 'debug_summary.log' dosyasına yazar.
 */

$log_file = __DIR__ . '/debug_summary.log';
if (ob_get_level()) ob_end_clean();

function write_log($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[{$timestamp}] " . print_r($message, true) . "\n", FILE_APPEND);
}

if (isset($_GET['clear_log'])) {
    file_put_contents($log_file, '');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'Log dosyası temizlendi.']);
    exit;
}

register_shutdown_function(function () use ($log_file) {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        write_log("ÖLÜMCÜL HATA (FATAL ERROR): " . $error['message'] . " Dosya: " . $error['file'] . " Satır: " . $error['line']);
    }
});

write_log("--- YENİ ÖZET OLUŞTURMA İSTEĞİ BAŞLADI ---");

try {
    write_log("Oturum başlatılıyor...");
    session_start();
    write_log("Dosyalar dahil ediliyor...");
    require_once 'config/database.php';
    require_once 'core/analysis_helpers.php';
    write_log("Dosyalar başarıyla dahil edildi.");

    if (!isset($_SESSION['user_id'])) throw new Exception('Giriş yapılmamış.');
    write_log("Kullanıcı doğrulandı. ID: " . $_SESSION['user_id']);

    $kararId = $_POST['id'] ?? null;
    if (!$kararId) throw new Exception('Karar ID eksik.');
    write_log("İstenen Karar ID: " . $kararId);
    
    $hamMetin = $_POST['ham_metin'] ?? null;
    if (!$hamMetin) throw new Exception('Ham metin gönderilmedi.');
    write_log("Ham metin alındı. Uzunluk: " . strlen($hamMetin) . " karakter.");

    write_log("getAiSummary fonksiyonu çağrılıyor...");
    $aiData = getAiSummary($hamMetin);
    write_log("getAiSummary fonksiyonundan dönen veri: " . var_export($aiData, true));
    
    if (!$aiData) throw new Exception('Yapay zeka servisinden geçerli bir özet alınamadı.');
    
    // Sadece loglama yapıyoruz, veritabanı güncelleme veya HTML oluşturma yok.
    write_log("Başarılı AI verisi alındı.");
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'aiData' => $aiData]);
    write_log("Başarılı JSON yanıtı tarayıcıya gönderildi.");

} catch (Exception $e) {
    $errorMessage = $e->getMessage();
    write_log("YAKALANAN HATA (catch bloğu): " . $errorMessage);
    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $errorMessage]);
        write_log("Hata JSON yanıtı tarayıcıya gönderildi.");
    }
}

write_log("--- İSTEK TAMAMLANDI ---\n");
exit();
?>