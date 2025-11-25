<?php
/**
 * ai_test_page.php - İzole AI Sorgu Tercümanı Test Sayfası
 * Bu sayfa, sadece 'core/ai_service.php' dosyasındaki fonksiyonun
 * doğru çalışıp çalışmadığını test eder.
 */

// Test edilecek olan yeni AI servisimizi çağırıyoruz.
require_once 'core/ai_service.php';

$kullaniciSorgusu = '';
$teknikSorgu = null; // Başlangıçta null
$calismaSuresi = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kullaniciSorgusu = $_POST['query'] ?? '';

    if (!empty($kullaniciSorgusu)) {
        $baslangicZamani = microtime(true);
        // Test edilecek ana fonksiyonu çağır
        $teknikSorgu = translateQueryToAdvancedSearch($kullaniciSorgusu);
        $bitisZamani = microtime(true);
        $calismaSuresi = round(($bitisZamani - $baslangicZamani), 2);
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>İzole AI Sorgu Tercümanı Testi</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .test-container { padding: 20px; }
        .result-box { margin-top: 20px; padding: 15px; border: 1px solid #ccc; border-radius: 8px; background-color: #f8f9fa; }
        .result-box h3 { margin-top: 0; }
        .result-box code { font-size: 1.2em; color: #d63384; font-weight: bold; }
        body.dark-mode .result-box { background-color: #2c3e50; border-color: #495057; }
        body.dark-mode .result-box code { color: #ff8ab4; }
    </style>
</head>
<body>
    <div class="container test-container">
        <h1>İzole AI Sorgu Tercümanı Testi</h1>
        <p>Aşağıdaki kutuya doğal dilde bir arama cümlesi yazın ve AI'nin bunu teknik bir sorguya çevirip çeviremediğini test edin.</p>

        <form action="ai_test_page.php" method="POST" class="search-form">
            <textarea name="query" rows="3" placeholder="örn: arsa payı karşılığı inşaat sözleşmesi davaları ancak bozma sebebi olmasın" style="width:100%; font-size:1.1em; margin-bottom: 10px;"><?php echo htmlspecialchars($kullaniciSorgusu); ?></textarea>
            <button type="submit">Çeviriyi Test Et</button>
        </form>

        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
        <div class="result-box">
            <h3>Test Sonucu:</h3>
            <p><strong>Gönderilen Cümle:</strong> <?php echo htmlspecialchars($kullaniciSorgusu); ?></p>
            <p><strong>AI Tarafından Üretilen Sorgu:</strong> <code><?php echo htmlspecialchars($teknikSorgu ?? 'NULL (Hata Oluştu)'); ?></code></p>
            <p><small>İşlem Süresi: <?php echo $calismaSuresi; ?> saniye.</small></p>

            <?php if ($teknikSorgu && $kullaniciSorgusu !== $teknikSorgu): ?>
                <p style="color:green; font-weight:bold; margin-top:15px;"><strong>SONUÇ: BAŞARILI!</strong><br>AI, metni başarıyla teknik bir sorguya çevirdi.</p>
            <?php else: ?>
                <p class="error" style="margin-top:15px;"><strong>SONUÇ: BAŞARISIZ!</strong><br>AI, metni çeviremedi. Fonksiyon `null` veya orijinal metni geri döndürdü. Lütfen `core/ai_service.php` dosyasındaki API anahtarınızı ve Gemini bağlantısını kontrol edin.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>