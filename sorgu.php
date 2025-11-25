<?php
// --- PHP Sunucu Taraflı Mantık ---

// Başlangıçta değişkenleri boş olarak ayarlayalım
$sorgu_sonucu = null;
$aciklama_sonucu = null;
$hata_mesaji = null;
$kullanici_girisi = '';

// Eğer sayfa POST metodu ile (form gönderilerek) yüklendiyse
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $kullanici_girisi = $_POST['userInput'] ?? '';

    if (!empty($kullanici_girisi)) {
        try {
            // Gemini API'yi çağıran fonksiyonu çalıştır
            $sonuc = callGeminiAPI($kullanici_girisi);
            
            // Gelen JSON yanıtını işle
            if (isset($sonuc['olusturulan_sorgu']) && isset($sonuc['sorgu_aciklamasi'])) {
                $sorgu_sonucu = $sonuc['olusturulan_sorgu'];
                $aciklama_sonucu = $sonuc['sorgu_aciklamasi'];
            } else if (isset($sonuc['error']['message'])) {
                // API'den bir hata mesajı geldiyse
                $hata_mesaji = "API Hatası: " . $sonuc['error']['message'];
            } else {
                // Beklenmedik bir yanıt formatı
                $hata_mesaji = "Yapay zekadan geçerli bir yanıt alınamadı. Yanıt: " . json_encode($sonuc);
            }
        } catch (Exception $e) {
            // cURL veya başka bir PHP hatası oluşursa
            $hata_mesaji = "Sunucu Hatası: " . $e->getMessage();
        }
    } else {
        $hata_mesaji = "Lütfen ne aramak istediğinizi yazın.";
    }
}

/**
 * Gemini API'ye cURL kullanarak istek gönderen fonksiyon
 * @param string $userQuery Kullanıcının girdiği doğal dil sorgusu
 * @return array Yanıt olarak gelen JSON'un decode edilmiş hali (array)
 * @throws Exception cURL hatası olursa
 */
function callGeminiAPI($userQuery) {
    // ÖNEMLİ: API Anahtarını boş bırakın. Sistem çalışma zamanında otomatik olarak dolduracaktır.
    $apiKey = "AIzaSyAd7X-E-DWH6kDjm3bmx3aIoOEcVJmCh_c"; 
    $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-09-2025:generateContent?key=" . $apiKey;

    // JavaScript'teki systemPrompt'un PHP versiyonu
    $systemPrompt = <<<PROMPT
Sen, karmaşık bir hukuki/resmi doküman arama sistemi için uzman bir "sorgu çevirmeni" yapay zekasısın.
Görevin, kullanıcıdan gelen doğal dil taleplerini, aşağıdaki kurallara göre özel bir arama operatörü söz dizimine (syntax) dönüştürmektir.

ARAMA OPERATÖRÜ KURALLARI:
1.  **Basit OR (veya):** Kelimeler arasına boşluk koymak, kelimelerden herhangi birini (OR) getirir.
    * Örnek: `arsa payı` -> 'arsa' VEYA 'payı' içerenler.
2.  **Tam İfade (Exact Phrase):** Çift tırnak (`"..."`) içindeki ifadeyi tam olarak arar.
    * Örnek: `"arsa payı"` -> 'arsa payı' kelime öbeğini tam olarak içerenler.
3.  **İfadeler Arası OR (veya):** Birden fazla tırnaklı ifade, aralarında boşlukla yazılırsa, bu ifadelerden herhangi birini (OR) getirir.
    * Örnek: `"arsa payı" "bozma sebebi"` -> 'arsa payı' VEYA 'bozma sebebi' içerenler.
4.  **AND (ve) Operatörü (+):** İfadenin başına artı (`+`) koymak, o ifadenin dokümanda BULUNMASI ZORUNLU (AND) demektir.
    * Örnek: `+"arsa payı" +"bozma sebebi"` -> 'arsa payı' AND 'bozma sebebi' kelime öbeklerini içerenler.
5.  **NOT (değil) Operatörü (-):** İfadenin başına eksi (`-`) koymak, o ifadenin dokümanda BULUNMAMASI ZORUNLU (NOT) demektir.
    * Örnek: `+"arsa payı" -"bozma sebebi"` -> 'arsa payı' içeren AMA 'bozma sebebi' İÇERMEYEN evraklar.

SENİN GÖREVİN:
Bu isteği analiz et ve SADECE ve SADECE aşağıdaki JSON formatında bir yanıt üret. Yanıtının başına veya sonuna asla ```json ... ``` bloğu ekleme. Sadece saf JSON metni döndür.

JSON FORMATI:
{
  "olusturulan_sorgu": "Buraya +\"ifade\" -\"ifade\" formatındaki sorguyu yaz",
  "sorgu_aciklamasi": "Bu sorgunun kullanıcı için ne anlama geldiğini basitçe açıkla."
}
PROMPT;

    // Gemini API için gönderilecek veri (payload)
    $payload = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $userQuery]
                ]
            ]
        ],
        'systemInstruction' => [
            'parts' => [
                ['text' => $systemPrompt]
            ]
        ],
        'generationConfig' => [
            'responseMimeType' => "application/json",
            'responseSchema' => [
                'type' => "OBJECT",
                'properties' => [
                    "olusturulan_sorgu" => ["type" => "STRING"],
                    "sorgu_aciklamasi" => ["type" => "STRING"]
                ],
                'required' => ["olusturulan_sorgu", "sorgu_aciklamasi"]
            ]
        ]
    ];

    // cURL oturumunu başlat
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Gerekirse SSL doğrulamayı kapatın (güvenlik için önerilmez, ancak yerel sunucularda gerekebilir)

    // İsteği gönder ve yanıtı al
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    // cURL hatası olup olmadığını kontrol et
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception("cURL Hatası: " . $error);
    }
    
    curl_close($ch);

    // Yanıtı PHP dizisine dönüştür
    $result = json_decode($response, true);
    
    // HTTP kodu 200 değilse (hata varsa)
    if ($http_code != 200) {
        // API'den gelen hata mesajını veya genel bir mesajı ayarla
        $result['error']['message'] = $result['error']['message'] ?? "API isteği başarısız oldu. HTTP Kodu: $http_code";
    }

    return $result;
}

// --- HTML ve İstemci Taraflı Mantık ---
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Destekli Sorgu Asistanı (PHP)</title>
    <!-- Tailwind CSS'i yüklüyoruz -->
    <script src="https://karar.liyakat.net/assets/css/tailwind.css"></script>
    <style>
        /* Inter font ailesini Tailwind'e dahil ediyoruz */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        body {
            font-family: 'Inter', sans-serif;
        }
        /* Yükleme animasyonu için stil */
        .loader {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">

    <div class="bg-white shadow-xl rounded-2xl p-8 max-w-3xl w-full">
        <h1 class="text-3xl font-bold text-gray-900 text-center mb-2">
            Yapay Zeka Destekli Sorgu Asistanı
        </h1>
        <p class="text-gray-600 text-center mb-6">
            Ne aramak istediğinizi doğal dilde yazın, yapay zeka sizin için özel arama sorgusunu oluştursun.
        </p>

        <!-- Form POST metodu ile 'index.php' (bu dosyanın kendisine) gönderir -->
        <form id="queryForm" method="POST" action="index.php">
            <!-- Kullanıcı Giriş Alanı -->
            <div class="mb-4">
                <label for="userInput" class="block text-sm font-medium text-gray-700 mb-2">
                    Arama İsteğiniz (Örn: "arsa payı ve inşaat içeren ama bozma sebebi içermeyen evraklar")
                </label>
                <!-- PHP ile önceki girişi 'value' olarak yazdırıyoruz -->
                <textarea id="userInput" name="userInput" rows="4" class="w-full p-3 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition" placeholder="Aramak istediğiniz konuyu buraya yazın..."><?php echo htmlspecialchars($kullanici_girisi); ?></textarea>
            </div>

            <!-- Buton (type="submit" olarak değişti) -->
            <button id="generateButton" type="submit" class="w-full bg-blue-600 text-white font-semibold py-3 px-6 rounded-lg shadow-md hover:bg-blue-700 transition duration-300 disabled:bg-gray-400">
                Sorgu Oluştur
            </button>
        </form> <!-- Form burada biter -->

        <!-- Yükleme Göstergesi (Form gönderildiğinde JS ile gösterilecek) -->
        <div id="loader" class="hidden flex justify-center items-center mt-6">
            <div class="loader"></div>
            <p class="ml-4 text-gray-600">Yapay zeka analiz ediyor...</p>
        </div>

        <!-- Hata Mesajı Alanı (PHP'den gelen hata varsa gösterilir) -->
        <?php if ($hata_mesaji): ?>
            <div id="errorContainer" class="mt-6 p-4 bg-red-100 text-red-700 border border-red-300 rounded-lg">
                <?php echo htmlspecialchars($hata_mesaji); ?>
            </div>
        <?php endif; ?>

        <!-- Sonuç Alanı (PHP'den gelen sonuç varsa gösterilir) -->
        <?php if ($sorgu_sonucu): ?>
            <div id="resultContainer" class="mt-6 border border-gray-200 bg-gray-50 rounded-lg p-6">
                <!-- Başlık ve Kopyala Butonu -->
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Oluşturulan Sorgu:</h3>
                    <button id="copyButton" class="bg-gray-200 text-gray-700 text-sm font-medium py-1 px-3 rounded-lg hover:bg-gray-300 transition">
                        Kopyala
                    </button>
                </div>
                <!-- Sorgu Çıktısı -->
                <div id="queryOutput" class="p-4 bg-gray-900 text-white font-mono text-sm rounded-lg mb-4 break-words">
                    <?php echo htmlspecialchars($sorgu_sonucu); ?>
                </div>
                
                <h3 class="text-lg font-semibold text-gray-800 mb-2">Açıklaması:</h3>
                <p id="explanationOutput" class="text-gray-700">
                    <?php echo htmlspecialchars($aciklama_sonucu); ?>
                </p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Sadece Kopyalama ve Yükleme Göstergesi için İstemci Taraflı JavaScript -->
    <script>
        // DOM elementlerini seçiyoruz
        const copyButton = document.getElementById('copyButton');
        const queryForm = document.getElementById('queryForm');
        const generateButton = document.getElementById('generateButton');
        const loader = document.getElementById('loader');
        const resultContainer = document.getElementById('resultContainer');
        const errorContainer = document.getElementById('errorContainer');

        // Form gönderildiğinde (submit) yükleme göstergesini çalıştır
        if (queryForm) {
            queryForm.addEventListener('submit', () => {
                // Butonu devre dışı bırak ve yükleyicinin görünmesini sağla
                if (generateButton) {
                    generateButton.disabled = true;
                    generateButton.textContent = 'Oluşturuluyor...';
                }
                if (loader) {
                    loader.classList.remove('hidden');
                }
                // Mevcut sonuçları veya hataları gizle
                if (resultContainer) {
                    resultContainer.classList.add('hidden');
                }
                 if (errorContainer) {
                    errorContainer.classList.add('hidden');
                }
            });
        }

        // Kopyala butonu için olay dinleyici
        if (copyButton) {
            copyButton.addEventListener('click', copyQueryToClipboard);
        }

        // Kopyalama fonksiyonu (tarayıcıda çalışır)
        function copyQueryToClipboard() {
            const queryOutput = document.getElementById('queryOutput');
            if (!queryOutput) return;

            const queryText = queryOutput.textContent;
            
            // Güvenli olmayan (iframe) ortamlar için document.execCommand kullanıyoruz.
            const textArea = document.createElement('textarea');
            textArea.value = queryText;
            textArea.style.position = 'fixed'; // Ekran dışında görünmez yap
            textArea.style.left = '-9999px';
            textArea.style.top = '-9999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                document.execCommand('copy');
                // Kullanıcıya geri bildirim ver
                copyButton.textContent = 'Kopyalandı!';
                setTimeout(() => {
                    copyButton.textContent = 'Kopyala';
                }, 2000); // 2 saniye sonra metni geri değiştir
            } catch (err) {
                console.error('Kopyalama başarısız oldu:', err);
                copyButton.textContent = 'Hata!';
                setTimeout(() => {
                    copyButton.textContent = 'Kopyala';
                }, 2000);
            }
            
            document.body.removeChild(textArea);
        }
    </script>
</body>
</html>