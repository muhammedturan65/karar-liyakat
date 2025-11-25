<?php
/**
 * generate_pdf.php - Karar PDF Oluşturma Servisi
 * 
 * Bu betiğin tek görevi, bir GET parametresi olarak aldığı Karar ID'sine
 * ait içeriği veritabanından çekmek, PDF için formatlamak ve Api2Pdf
 * servisini kullanarak PDF olarak tarayıcıya göndermektir.
 */

// Gerekli ayar ve yardımcı dosyalarını çağır
require_once 'config/database.php';
require_once 'core/analysis_helpers.php'; // extractEntities fonksiyonu için

/**
 * PDF için Times New Roman ve 12 punto ile stillendirilmiş resmi evrak formatında
 * tam bir HTML dokümanı oluşturur.
 */
function generateOfficialPdfHtml($content) {
    $pdfCss = " body { font-family: 'Times New Roman', Times, serif; font-size: 12pt; line-height: 1.5; margin: 2.5cm 2cm; } .official-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20pt; } .court-name { text-align: left; } .case-numbers { text-align: right; } h2, h3, h4, .court-header, .court-header-center { text-align: center; font-weight: bold; margin: 15pt 0; line-height: 1.2; } .main-title { font-size: 14pt; letter-spacing: 1px; } .sub-title { font-size: 13pt; } .section-title { text-decoration: underline; } .detail-item { text-align: left; margin: 2pt 0; } .paragraph { text-align: justify; text-indent: 1.25cm; margin: 10pt 0; } .entity { background-color: transparent !important; color: inherit !important; font-weight: normal; padding: 0; border-radius: 0; } a.entity-law { text-decoration: none; color: inherit; border-bottom: 1px solid #333; } ";
    return <<<HTML
    <!DOCTYPE html><html lang="tr"><head><meta charset="UTF-8"><title>Karar Metni</title><style>{$pdfCss}</style></head>
    <body>{$content}</body></html>
    HTML;
}

// --- ANA PDF OLUŞTURMA MANTIĞI ---

// URL'den Karar ID'sini al ve temizle
$kararId = isset($_GET['id']) ? filter_var(trim($_GET['id']), FILTER_SANITIZE_NUMBER_INT) : null;
if (!$kararId) {
    die("Geçersiz veya eksik Karar ID'si.");
}

$icerikHTML = null;

try {
    // Veritabanından kararın formatlanmış metnini çek
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT icerik_etiketli, icerik_formatli FROM kararlar WHERE id = ?");
    $stmt->execute([$kararId]);
    $karar = $stmt->fetch();

    // Varsa etiketli, yoksa normal formatlı metni kullan
    if ($karar) {
        $icerikHTML = $karar['icerik_etiketli'] ?? $karar['icerik_formatli'];
    }

} catch (PDOException $e) {
    error_log("PDF oluşturma için veritabanı hatası: " . $e->getMessage());
    die("Veritabanına bağlanırken bir hata oluştu.");
}

// Eğer veritabanında karar bulunamazsa veya içerik boşsa hata ver
if (empty($icerikHTML)) {
    die("Belirtilen ID'ye sahip bir karar içeriği bulunamadı. Lütfen önce kararı web arayüzünde görüntüleyin.");
}

// 1. Adım: PDF için özel HTML'i oluştur.
$pdfHtml = generateOfficialPdfHtml($icerikHTML);

// 2. Adım: Api2Pdf'ye gönderilecek veriyi JSON formatında hazırla.
$postData = json_encode(['html' => $pdfHtml, 'inline' => true]);

// 3. Adım: cURL ile Api2Pdf'ye POST isteği gönder.
$ch = curl_init(PDF_API_URL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: ' . PDF_API_KEY
]);

$apiResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// 4. Adım: Gelen yanıtı işle.
if ($httpCode === 200) {
    $responseData = json_decode($apiResponse, true);
    if (isset($responseData['pdf']) && !empty($responseData['pdf'])) {
        $pdfContent = @file_get_contents($responseData['pdf']);
        if ($pdfContent === false) {
            die("API'nin oluşturduğu PDF dosyası indirilemedi.");
        }
        
        // 5. Adım: İndirilen PDF içeriğini kullanıcının tarayıcısına gönder.
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="karar-'.$kararId.'.pdf"');
        header('Content-Length: ' . strlen($pdfContent));
        echo $pdfContent;
        exit(); // İşlem bitti.
        
    } else {
        die("API'den PDF linki alınamadı. API Yanıtı: " . $apiResponse);
    }
} else {
    die("PDF oluşturma servisine bağlanırken hata oluştu. Hata Kodu: " . $httpCode);
}