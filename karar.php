<?php
/**
 * karar.php - Akıllı Karar Görüntüleyici v18.0 (Canlı Arama Destekli)
 * 
 * Bu sürüm, projenin tüm özelliklerini bir araya getirir:
 * - Kullanıcı girişi ve yetki kontrolü.
 * - Modern başlık ve aksiyon butonu yerleşimi.
 * - Metin içinde canlı arama ve vurgulama.
 * - AI destekli karar özeti (asenkron oluşturma).
 * - Benzer emsal karar önerme.
 * - Klasöre kaydetme ve not/vurgu ekleme işlevselliği.
 * - Bağlama duyarlı gezgin sohbet asistanı.
 */

// Oturumu her zaman en başta başlat
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- GÜVENLİK KONTROLÜ ---
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?error=auth');
    exit(); 
}

// Gerekli ayar ve yardımcı dosyalarını çağır
require_once 'config/database.php';
require_once 'core/analysis_helpers.php';

// --- FONKSİYONLAR ---

function fetchKararHTML($id) { if (empty($id)) return null; $baseURL = 'https://karararama.yargitay.gov.tr/getDokuman?id='; $hedefURL = $baseURL . $id; $ch = curl_init(); curl_setopt($ch, CURLOPT_URL, $hedefURL); curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); curl_setopt($ch, CURLOPT_TIMEOUT, 30); curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'); $response = curl_exec($ch); $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch); if ($httpCode != 200 || empty($response)) return null; $firstChar = isset(trim($response)[0]) ? trim($response)[0] : ''; if ($firstChar === '{') { $decodedJson = json_decode($response, true); return (json_last_error() === JSON_ERROR_NONE && isset($decodedJson['data'])) ? $decodedJson['data'] : null; } elseif ($firstChar === '<') { $pattern = '/<data>(.*?)<\/data>/sU'; return preg_match($pattern, $response, $matches) ? trim($matches[1]) : null; } return null; }
function formatKararMetni($rawHtml) { if (empty($rawHtml)) return null; $text = strip_tags($rawHtml, '<br>'); $text = str_replace(['<br>', '<br/>', '<br />'], "\n", $text); $lines = explode("\n", $text); $formattedHtml = ''; foreach ($lines as $line) { $trimmedLine = trim($line); if (empty($trimmedLine)) continue; if (strpos($trimmedLine, 'Esas-Karar No:') !== false) { $parts = explode('Esas-Karar No:', $trimmedLine); $courtName = trim($parts[0]); $caseNumbers = 'Esas-Karar No:' . trim($parts[1]); $formattedHtml .= "<div class='official-header'><div class='court-name'>" . htmlspecialchars($courtName) . "</div><div class='case-numbers'>" . htmlspecialchars($caseNumbers) . "</div></div>"; } elseif (strpos($trimmedLine, 'TÜRK MİLLETİ ADINA') !== false) { $formattedHtml .= "<h2 class='main-title'>" . htmlspecialchars($trimmedLine) . "</h2>"; } elseif (strpos($trimmedLine, 'BÖLGE ADLİYE MAHKEMESİ KARARI') !== false || strpos($trimmedLine, 'YARGITAY İLAMI') !== false) { $formattedHtml .= "<h3 class='sub-title'>" . htmlspecialchars($trimmedLine) . "</h3>"; } elseif (strpos($trimmedLine, 'İNCELENEN KARARIN') !== false || strpos($trimmedLine, 'GEREĞİ DÜŞÜNÜLDÜ') !== false) { $formattedHtml .= "<h4 class='section-title'>" . htmlspecialchars($trimmedLine) . "</h4>"; } elseif (strpos($trimmedLine, ':') !== false) { list($key, $value) = explode(':', $trimmedLine, 2); $formattedHtml .= "<p class='detail-item'><strong>" . htmlspecialchars(trim($key)) . ":</strong> " . htmlspecialchars(trim($value)) . "</p>"; } elseif (mb_strtoupper($trimmedLine, 'UTF-8') === $trimmedLine && !strpos($trimmedLine, ' ')) { $formattedHtml .= "<p class='court-header-center'>" . htmlspecialchars($trimmedLine) . "</p>"; } elseif (mb_strtoupper($trimmedLine, 'UTF-8') === $trimmedLine) { $formattedHtml .= "<p class='court-header'>" . htmlspecialchars($trimmedLine) . "</p>"; } else { $formattedHtml .= "<p class='paragraph'>" . htmlspecialchars($trimmedLine) . "</p>"; } } return $formattedHtml; }
function getSimilarDecisions($pdo, $keywords, $currentId) { if (empty($keywords) || !$pdo) return []; $searchQuery = ''; $keywordList = explode(',', $keywords); foreach($keywordList as $keyword) { $trimmedKeyword = trim($keyword); if (mb_strlen($trimmedKeyword) > 2) { $searchQuery .= '+' . $trimmedKeyword . '* '; } } $searchQuery = trim($searchQuery); if (empty($searchQuery)) return []; try { $stmt = $pdo->prepare("SELECT id, icerik_ham FROM kararlar WHERE MATCH(icerik_ham) AGAINST(? IN BOOLEAN MODE) AND id != ? LIMIT 5"); $stmt->execute([$searchQuery, $currentId]); return $stmt->fetchAll(); } catch (PDOException $e) { error_log("Benzer karar arama hatası: " . $e->getMessage()); return []; } }

// --- ANA MANTIK ---
$kararId = isset($_GET['id']) ? filter_var(trim($_GET['id']), FILTER_SANITIZE_NUMBER_INT) : null;
$aiOzet = null; $anahtarKelimeler = null; $icerikHTML = null; $benzerKararlar = []; $hamMetin = '';
if (!$kararId) { die("Geçerli bir Karar ID'si belirtilmedi."); }
try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT icerik_ham, icerik_formatli, ai_ozet, icerik_etiketli FROM kararlar WHERE id = ?");
    $stmt->execute([$kararId]);
    $karar = $stmt->fetch();
    if ($karar) {
        $hamMetin = $karar['icerik_ham'];
        $icerikHTML = $karar['icerik_etiketli'] ?? $karar['icerik_formatli'];
        if (!empty($karar['ai_ozet']) && $karar['ai_ozet'] !== 'null') {
            $aiData = json_decode($karar['ai_ozet'], true);
            $aiOzet = $aiData['ozet'] ?? null;
            $anahtarKelimeler = $aiData['anahtar_kelimeler'] ?? null;
        }
    } else {
        $hamHTML = fetchKararHTML($kararId);
        if ($hamHTML) {
            $icerikFormatli = formatKararMetni($hamHTML);
            $hamMetin = strip_tags($hamHTML);
            $icerikEtiketli = extractEntities($icerikFormatli);
            $stmt = $pdo->prepare("INSERT INTO kararlar (id, icerik_ham, icerik_formatli, icerik_etiketli) VALUES (?, ?, ?, ?)");
            $stmt->execute([$kararId, $hamMetin, $icerikFormatli, $icerikEtiketli]);
            $icerikHTML = $icerikEtiketli;
        }
    }
    if ($anahtarKelimeler) {
        $benzerKararlar = getSimilarDecisions($pdo, $anahtarKelimeler, $kararId);
    }
} catch (PDOException $e) { $icerikHTML = null; error_log($e->getMessage()); }
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Karar Detayı | ID: <?php echo htmlspecialchars($kararId); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="container">
        <div class="page-header">
            <div class="header-main">
                <h1>Karar Detayı</h1>
                <p>Karar ID: <?php echo htmlspecialchars($kararId); ?></p>
            </div>
            <div class="header-actions">
                <button id="dark-mode-toggle" class="action-btn" title="Temayı Değiştir">
                    <svg class="icon-light" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 9a3 3 0 0 1 3-3m-2 15h4m0-3c0-4.1 4-4.9 4-9A6 6 0 1 0 6 9c0 4 4 5 4 9h4Z"/></svg>
                    <svg class="icon-dark" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24"><path fill-rule="evenodd" d="M7.05 4.05A7 7 0 0 1 19 9c0 2.407-1.197 3.874-2.186 5.084l-.04.048C15.77 15.362 15 16.34 15 18a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1c0-1.612-.77-2.613-1.78-3.875l-.045-.056C6.193 12.842 5 11.352 5 9a7 7 0 0 1 2.05-4.95ZM9 21a1 1 0 0 1 1-1h4a1 1 0 1 1 0 2h-4a1 1 0 0 1-1-1Zm1.586-13.414A2 2 0 0 1 12 7a1 1 0 1 0 0-2 4 4 0 0 0-4 4 1 1 0 0 0 2 0 2 2 0 0 1 .586-1.414Z" clip-rule="evenodd"/></svg>
                </button>
                <button id="font-decrease" class="action-btn" title="Yazı Tipi Küçült"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7V4h16v3"/><path d="M5 20h14"/><path d="M12 4v16"/></svg></button>
                <button id="font-increase" class="action-btn" title="Yazı Tipi Büyüt"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7V4h16v3"/><path d="M5 20h14"/><path d="M12 4v16"/><path d="M8 12h8"/></svg></button>
                <button id="copy-link" class="action-btn" title="Sayfa Linkini Kopyala"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.72"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.72-1.72"/></svg></button>
                <a href="generate_pdf.php?id=<?php echo htmlspecialchars($kararId); ?>" class="download-button-main" target="_blank">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    <span>PDF İndir</span>
                </a>
                <button id="save-to-folder-btn" class="action-btn" title="Kararı Klasöre Kaydet">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                        <polyline points="7 3 7 8 15 8"></polyline>
                    </svg>
                </button>
                <span class="action-divider"></span>
                <button id="toggle-find-bar-btn" class="action-btn" title="Metin İçinde Ara">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                </button>
            </div>
        </div>

        <div id="find-in-text-bar" class="find-bar hidden">
            <input type="text" id="find-input" placeholder="Metinde aranacak kelime...">
            <span id="find-counter" class="find-counter">0 sonuç</span>
            <button id="find-close-btn" class="find-close-btn">&times;</button>
        </div>
        
        <div id="ai-summary-container">
            <?php if ($aiOzet): ?>
                <div class="ai-summary-box">
                    <h4><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-zap"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg> Karar Özeti (Yapay Zeka)</h4>
                    <p id="ai-summary-text"><?php echo htmlspecialchars($aiOzet); ?></p>
                    <?php if ($anahtarKelimeler): ?>
                    <div class="keywords"><strong>Anahtar Kavramlar:</strong> <span id="ai-keywords-text"><?php echo htmlspecialchars($anahtarKelimeler); ?></span></div>
                    <?php endif; ?>
                     <?php if ($icerikHTML): ?>
                        <div class="ai-actions">
                            <button id="generate-qa-btn" class="ai-action-btn" data-id="<?php echo htmlspecialchars($kararId); ?>">
                                <span>AI için Veri Seti Oluştur</span>
                                <div class="mini-spinner hidden" style="margin-left: 8px;"></div>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php elseif ($icerikHTML): ?>
                <div class="ai-summary-placeholder">
                    <p>Bu karar için henüz yapay zeka özeti oluşturulmamış.</p>
                    <button id="generate-summary-btn" class="ai-action-btn" data-id="<?php echo htmlspecialchars($kararId); ?>">
                        <svg class="icon-magic" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a1 1 0 0 1 1 1v2a1 1 0 0 1-2 0V3a1 1 0 0 1 1-1zM5.636 5.636a1 1 0 0 1 1.414 0l1.414 1.414a1 1 0 0 1-1.414 1.414L5.636 7.05a1 1 0 0 1 0-1.414zM2 12a1 1 0 0 1 1-1h2a1 1 0 0 1 0 2H3a1 1 0 0 1-1-1zM5.636 18.364a1 1 0 0 1 0-1.414l1.414-1.414a1 1 0 0 1 1.414 1.414l-1.414 1.414a1 1 0 0 1-1.414 0zM12 22a1 1 0 0 1-1-1v-2a1 1 0 0 1 2 0v2a1 1 0 0 1-1-1zM18.364 18.364a1 1 0 0 1-1.414 0l-1.414-1.414a1 1 0 0 1 1.414-1.414l1.414 1.414a1 1 0 0 1 0 1.414zM22 12a1 1 0 0 1-1 1h-2a1 1 0 0 1 0-2h2a1 1 0 0 1 1 1zM18.364 5.636a1 1 0 0 1 0 1.414l-1.414 1.414a1 1 0 0 1-1.414-1.414l1.414-1.414a1 1 0 0 1 1.414 0z"/></svg>
                        <span>Şimdi Özet Oluştur</span>
                        <div class="mini-spinner hidden"></div>
                    </button>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="karar-icerik">
            <?php
            if ($icerikHTML) {
                echo $icerikHTML;
            } else {
                echo '<p class="error">Belirtilen ID\'ye sahip bir karar bulunamadı veya sunucudan yanıt alınamadı.</p>';
            }
            ?>
        </div>

        <?php if (!empty($benzerKararlar)): ?>
            <div class="similar-decisions">
                <h4><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg> Benzer Kararlar (AI Destekli)</h4>
                <ul class="search-results-list">
                    <?php foreach($benzerKararlar as $benzerKarar): ?>
                        <li>
                            <a href="karar.php?id=<?php echo $benzerKarar['id']; ?>"><strong>Emsal Karar</strong> - ID: <?php echo $benzerKarar['id']; ?></a>
                            <p><?php echo htmlspecialchars(mb_substr($benzerKarar['icerik_ham'], 0, 200)) . '...'; ?></p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
    
    <div id="folder-modal" class="modal-overlay hidden">
        <div class="modal-content" style="max-width: 500px;">
            <button id="close-folder-modal-btn" class="modal-close-button">&times;</button>
            <h3>Kararı Kaydet</h3>
            <div id="folder-list"></div>
            <hr class="form-divider">
            <form id="new-folder-form" class="search-form" style="gap: 10px;">
                <input type="text" id="new-folder-name" placeholder="Veya yeni bir klasör oluşturun..." required>
                <button type="submit">Yeni Klasör Oluştur ve Kaydet</button>
            </form>
        </div>
    </div>

    <?php include 'chat_component.php'; ?>
    <button class="back-to-top" title="Yukarı Çık">&uarr;</button>
    <script src="assets/js/main.js"></script>
    <script src="assets/js/ai.js"></script>
    <script src="assets/js/folder-system.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/mark.js/8.11.1/mark.min.js"></script>
    <script src="assets/js/annotation.js"></script>
    <script src="assets/js/find-in-text.js"></script>
</body>
</html>