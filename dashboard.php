<?php
/**
 * dashboard.php - Kullanıcı Arama Paneli v3.0
 * 
 * Bu sürüm, kendi özel stil dosyası olan 'dashboard.css'i kullanır
 * ve arayüzü ana karşılama sayfasıyla tutarlı hale getirir.
 * Sadece giriş yapmış kullanıcılar bu sayfaya erişebilir.
 */

// Oturumu başlat
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Güvenlik Kontrolü: Eğer kullanıcı giriş yapmamışsa, login sayfasına yönlendir.
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?error=auth');
    exit();
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Arama Paneli - Karar Arama Platformu</title>
    
    <!-- Ana stil dosyasını çağır -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <!-- Sadece bu sayfaya özel stil dosyasını çağır -->
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body>
    
    <!-- Karanlık Mod Butonu -->
    <button id="dark-mode-toggle" class="theme-toggle-button" title="Temayı Değiştir">
        <svg class="icon-light" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 9a3 3 0 0 1 3-3m-2 15h4m0-3c0-4.1 4-4.9 4-9A6 6 0 1 0 6 9c0 4 4 5 4 9h4Z"/></svg>
        <svg class="icon-dark" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24"><path fill-rule="evenodd" d="M7.05 4.05A7 7 0 0 1 19 9c0 2.407-1.197 3.874-2.186 5.084l-.04.048C15.77 15.362 15 16.34 15 18a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1c0-1.612-.77-2.613-1.78-3.875l-.045-.056C6.193 12.842 5 11.352 5 9a7 7 0 0 1 2.05-4.95ZM9 21a1 1 0 0 1 1-1h4a1 1 0 1 1 0 2h-4a1 1 0 0 1-1-1Zm1.586-13.414A2 2 0 0 1 12 7a1 1 0 1 0 0-2 4 4 0 0 0-4 4 1 1 0 0 0 2 0 2 2 0 0 1 .586-1.414Z" clip-rule="evenodd"/></svg>
    </button>
    
    <!-- Navigasyon Barı -->
    <?php include 'header.php'; ?>

    <!-- Ana Arama İçeriği -->
    <div class="dashboard-container">
        <div class="header-content">
            <h1>Gelişmiş Yargıtay Karar Arama</h1>
            <p>Bir kararı doğrudan ID'si ile görüntüleyin veya detaylı filtreler ile arama yapın.</p>
        </div>
        
        <!-- Sadece ID ile Arama Formu -->
        <h3 class="form-title">Karar ID'si ile Görüntüle</h3>
        <form id="idSearchForm" action="karar.php" method="GET" class="search-form" target="_blank">
            <input type="text" name="id" placeholder="Görüntülenecek Karar ID'sini girin..." required pattern="\d+">
            <button type="submit">Görüntüle</button>
            <div id="spinner" class="spinner hidden"></div>
        </form>

        <hr class="form-divider">

        <!-- Detaylı Arama Formu -->
        <h3 class="form-title">Detaylı Arama</h3>
        <form id="advancedSearchForm" action="search.php" method="GET" class="search-form" target="_blank">
            <div class="ai-assistant-launcher">
                <button type="button" id="open-ai-assistant-btn">
                    <svg class="icon-magic" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a1 1 0 0 1 1 1v2a1 1 0 0 1-2 0V3a1 1 0 0 1 1-1zM5.636 5.636a1 1 0 0 1 1.414 0l1.414 1.414a1 1 0 0 1-1.414 1.414L5.636 7.05a1 1 0 0 1 0-1.414zM2 12a1 1 0 0 1 1-1h2a1 1 0 0 1 0 2H3a1 1 0 0 1-1-1zM5.636 18.364a1 1 0 0 1 0-1.414l1.414-1.414a1 1 0 0 1 1.414 1.414l-1.414 1.414a1 1 0 0 1-1.414 0zM12 22a1 1 0 0 1-1-1v-2a1 1 0 0 1 2 0v2a1 1 0 0 1-1-1zM18.364 18.364a1 1 0 0 1-1.414 0l-1.414-1.414a1 1 0 0 1 1.414-1.414l1.414 1.414a1 1 0 0 1 0 1.414zM22 12a1 1 0 0 1-1 1h-2a1 1 0 0 1 0-2h2a1 1 0 0 1 1 1zM18.364 5.636a1 1 0 0 1 0 1.414l-1.414 1.414a1 1 0 0 1-1.414-1.414l1.414-1.414a1 1 0 0 1 1.414 0z"/></svg>
                    <span>Yapay Zeka ile Sorgu Oluştur</span>
                </button>
            </div>
            <input type="text" id="arananKelimeInput" name="arananKelime" placeholder="Teknik sorguyu buraya yapıştırın veya manuel yazın...">
            <input type="text" name="birimYrgKurulDaire" placeholder="Daire adı (örn: 4. Hukuk Dairesi)...">
            <div class="form-row">
                <input type="number" name="kararYil" min="1900" max="2100" placeholder="Sadece Yıl (örn: 2023)">
                <input type="text" name="baslangicTarihi" onfocus="(this.type='date')" onblur="(this.type='text')" placeholder="Başlangıç Tarihi">
                <input type="text" name="bitisTarihi" onfocus="(this.type='date')" onblur="(this.type='text')" placeholder="Bitiş Tarihi">
            </div>
            <button type="submit">Filtrele ve Ara</button>
        </form>

        <!-- Arama İpuçları Akordeonu -->
                <div class="help-box-container">
            <a href="#" id="toggle-help-box" class="help-box-toggle">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                <span>Arama İpuçlarını Göster</span>
            </a>

            <!-- DÜZELTME: İpuçlarının HTML içeriği buraya eklendi -->
            <div id="help-box-content" class="help-box" style="display: none;">
                <h4>Arama Operatörleri</h4>
                <ol>
                    <li>
                        Kelimeler arasına boşluk konularak arama yapıldığında, yazılan kelimelerden <strong>herhangi birinin</strong> geçtiği kararları getirir.<br>
                        <em>Örnek:</em> <code>arsa payı</code> aratıldığında, içinde "arsa" <em>veya</em> "payı" kelimeleri geçen kararlar listelenir.
                    </li>
                    <li>
                        Çift tırnak <code>" "</code> içerisine yazılarak arama yapıldığında, tırnak içindeki <strong>kelime öbeğinin</strong> tam olarak geçtiği kararları getirir.<br>
                        <em>Örnek:</em> <code>"arsa payı"</code> şeklinde arama yapıldığında, sadece "arsa payı" ifadesinin birebir geçtiği kararlar listelenir.
                    </li>
                    <li>
                        Çift tırnak içinde birden fazla kelime öbeği aralarında boşluk bırakılarak yazıldığında, bu kelime öbeklerinden <strong>herhangi birini</strong> içeren kararları getirir.<br>
                        <em>Örnek:</em> <code>"arsa payı" "bozma sebebi"</code> şeklinde arama yapıldığında, "arsa payı" <em>veya</em> "bozma sebebi" ifadelerinden birini içeren kararlar listelenir.
                    </li>
                    <li>
                        Kelime öbeklerinin başına artı <code>+</code> işareti konularak arama yapıldığında, bu kelime öbeklerinin <strong>hepsinin</strong> geçtiği kararları getirir.<br>
                        <em>Örnek:</em> <code>+"arsa payı" +"bozma sebebi"</code> yazıldığında, içinde hem "arsa payı" <em>hem de</em> "bozma sebebi" ifadelerini içeren kararlar listelenir.
                    </li>
                    <li>
                        Artı <code>+</code> işareti konulanı içeren, eksi <code>-</code> işareti konulanı <strong>içermeyen</strong> kararları getirir.<br>
                        <em>Örnek:</em> <code>+"arsa payı" -"bozma sebebi"</code> yazıldığında, içinde "arsa payı" geçen ancak "bozma sebebi" geçmeyen kararlar listelenir.
                    </li>
                </ol>
            </div>
        </div>
    </div>

    <!-- AI Asistanı Modal (Pop-up) Yapısı -->
    <div id="ai-assistant-modal" class="modal-overlay hidden">
        <div class="modal-content iframe-modal">
            <button id="close-ai-assistant-btn" class="modal-close-button">&times;</button>
            <iframe id="ai-assistant-iframe" src="about:blank" data-src="ai-assistant.html" frameborder="0" style="width: 100%; height: 80vh;"></iframe>
        </div>
    </div>
    
    <!-- Gezgin Sohbet Asistanı Bileşeni -->
    <?php include 'chat_component.php'; ?>

    <script src="assets/js/main.js"></script>
    <script src="assets/js/ai.js"></script>
	<script src="assets/js/help-tips.js"></script>
    <script>
        // Sayfaya özel küçük scriptler (sadece spinner kaldı)
        document.getElementById('idSearchForm').addEventListener('submit', function() {
            const spinner = document.getElementById('spinner');
            if (spinner) {
                spinner.classList.remove('hidden');
            }
        });
    </script>
</body>
</html>