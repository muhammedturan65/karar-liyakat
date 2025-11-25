<?php
/**
 * search.php - Gelişmiş Filtreleme Sonuçları v5.0 (Sayfalamalı)
 * 
 * Bu sürüm, arama sonuçlarını sayfalara böler ve kullanıcının tüm sonuçlar
 * arasında gezinmesini sağlayan sayfalama linkleri oluşturur.
 */

// Oturumu başlat ve güvenlik kontrolü yap
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?error=auth');
    exit();
}

require_once 'config/database.php';

// --- Sayfalama için Değişkenler ---
$sayfa = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($sayfa < 1) $sayfa = 1;
$kayitSayisi = 25; // Sayfa başına gösterilecek sonuç sayısı

// Formdan gelen tüm olası parametreleri al
$params = [
    'arananKelime' => $_GET['arananKelime'] ?? '',
    'birimYrgKurulDaire' => $_GET['birimYrgKurulDaire'] ?? 'ALL',
    'kararYil' => $_GET['kararYil'] ?? '',
    'baslangicTarihi' => $_GET['baslangicTarihi'] ?? '',
    'bitisTarihi' => $_GET['bitisTarihi'] ?? '',
    'pageNumber' => $sayfa
];

// Eğer hiçbir arama kriteri girilmemişse, kullanıcıyı bir hata mesajıyla ana sayfaya geri yönlendir.
$isSearchEmpty = true;
foreach ($params as $key => $value) {
    if (!empty($value) && $key !== 'pageNumber' && $value !== 'ALL') {
        $isSearchEmpty = false;
        break;
    }
}
if ($isSearchEmpty) {
    header('Location: dashboard.php?error=empty_search');
    exit();
}

/**
 * Yargıtay'ın arama API'sine zenginleştirilmiş bir POST isteği gönderir ve sonuçları döndürür.
 */
function searchYargitayApi($searchParams, $limit) {
    $api_url = "https://karararama.yargitay.gov.tr/aramadetaylist";

    $payloadData = [
        'arananKelime' => $searchParams['arananKelime'],
        'birimYrgKurulDaire' => $searchParams['birimYrgKurulDaire'],
        'kararYil' => $searchParams['kararYil'],
        'baslangicTarihi' => $searchParams['baslangicTarihi'],
        'bitisTarihi' => $searchParams['bitisTarihi'],
        'pageSize' => $limit,
        'pageNumber' => $searchParams['pageNumber'],
        'esasYil' => '', 'esasIlkSiraNo' => '', 'esasSonSiraNo' => '',
        'kararIlkSiraNo' => '', 'kararSonSiraNo' => '',
    ];
    
    $finalPayload = ['data' => $payloadData];
    $jsonPayload = json_encode($finalPayload);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json; charset=UTF-8", "Accept: application/json, text/plain, */*", "X-Requested-With: XMLHttpRequest", "Referer: https://karararama.yargitay.gov.tr/"]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode != 200 || empty($response)) return null;
    
    $decoded = json_decode($response, true);
    return (json_last_error() === JSON_ERROR_NONE && isset($decoded['data']['data'])) ? $decoded['data'] : null;
}

/**
 * Sayfalama linklerini oluşturan HTML'i üretir.
 */
function createPaginationLinks($toplamKayit, $mevcutSayfa, $limit, $baseUrl, $maxLinks = 7) {
    if ($toplamKayit <= $limit) return '';

    $toplamSayfa = ceil($toplamKayit / $limit);
    if ($toplamSayfa == 1) return '';

    $html = '<nav class="pagination-container"><ul class="pagination">';

    // Önceki Butonu
    if ($mevcutSayfa > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '&page=' . ($mevcutSayfa - 1) . '">&laquo; Önceki</a></li>';
    }

    // Sayfa Numaraları
    $start = max(1, $mevcutSayfa - floor($maxLinks / 2));
    $end = min($toplamSayfa, $start + $maxLinks - 1);
    if($end - $start < $maxLinks - 1) {
        $start = max(1, $end - $maxLinks + 1);
    }

    if ($start > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '&page=1">1</a></li>';
        if ($start > 2) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }

    for ($i = $start; $i <= $end; $i++) {
        if ($i == $mevcutSayfa) {
            $html .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
        } else {
            $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '&page=' . $i . '">' . $i . '</a></li>';
        }
    }
    
    if ($end < $toplamSayfa) {
        if ($end < $toplamSayfa - 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '&page=' . $toplamSayfa . '">' . $toplamSayfa . '</a></li>';
    }

    // Sonraki Butonu
    if ($mevcutSayfa < $toplamSayfa) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '&page=' . ($mevcutSayfa + 1) . '">Sonraki &raquo;</a></li>';
    }

    $html .= '</ul></nav>';
    return $html;
}

// Ana arama fonksiyonunu çağır
$sonuclarData = searchYargitayApi($params, $kayitSayisi);
$sonuclar = $sonuclarData ? $sonuclarData['data'] : [];
$toplamKayit = $sonuclarData ? $sonuclarData['recordsFiltered'] : 0;

// Sayfalama linkleri için temel URL'i oluştur (page parametresi hariç)
$queryParams = $_GET;
unset($queryParams['page']);
$baseUrl = 'search.php?' . http_build_query($queryParams);

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Arama Sonuçları</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="container">
        <div class="header">
            <h1>Arama Sonuçları</h1>
            <p><strong><?php echo number_format($toplamKayit, 0, ',', '.'); ?></strong> sonuç bulundu. (Sayfa <?php echo $sayfa; ?> / <?php echo ceil($toplamKayit / $kayitSayisi); ?>)</p>
        </div>
        
        <?php if (!empty($sonuclar)): ?>
            <ul class="search-results-list">
                <?php foreach ($sonuclar as $sonuc): ?>
                    <li>
                        <a href="karar.php?id=<?php echo htmlspecialchars($sonuc['id']); ?>" target="_blank">
                            <strong><?php echo htmlspecialchars($sonuc['daire']); ?></strong> - Karar ID: <?php echo htmlspecialchars($sonuc['id']); ?>
                        </a>
                        <p>
                            <strong>Esas No:</strong> <?php echo htmlspecialchars($sonuc['esasNo']); ?> |
                            <strong>Karar No:</strong> <?php echo htmlspecialchars($sonuc['kararNo']); ?> |
                            <strong>Tarih:</strong> <?php echo htmlspecialchars(date('d.m.Y', strtotime($sonuc['kararTarihi']))); ?>
                        </p>
                    </li>
                <?php endforeach; ?>
            </ul>

            <!-- Sayfalama Linklerini Göster -->
            <?php echo createPaginationLinks($toplamKayit, $sayfa, $kayitSayisi, $baseUrl); ?>

        <?php else: ?>
            <p class="error">Girdiğiniz kriterlerle eşleşen bir karar bulunamadı veya API'ye bağlanırken bir sorun oluştu.</p>
        <?php endif; ?>
    </div>
    
    <!-- Gezgin Sohbet Asistanı Bileşeni -->
    <?php include 'chat_component.php'; ?>
    <script src="assets/js/main.js"></script>
    <script src="assets/js/ai.js"></script>
</body>
</html>