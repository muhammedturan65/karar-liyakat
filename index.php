<?php
/**
 * index.php - Karşılama Sayfası (Landing Page)
 * 
 * Bu sayfa, giriş yapmamış kullanıcılara veya giriş yapmış ancak henüz
 * panele gitmemiş kullanıcılara gösterilir. Sadece bu sayfaya özel stil
 * dosyası olan 'landing.css'i çağırır.
 */

// Oturumu başlat (kullanıcının giriş yapıp yapmadığını kontrol etmek için)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yapay Zeka Destekli Hukuki Araştırma Platformu</title>
    
    <!-- Projenin genel stil dosyasını çağır (fontlar, temel renkler vb. için) -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <!-- Sadece bu sayfaya özel stil dosyasını çağır -->
    <link rel="stylesheet" href="assets/css/landing.css">
</head>
<body>
    
    <!-- Karanlık Mod Butonu -->
    <button id="dark-mode-toggle" class="theme-toggle-button" title="Temayı Değiştir">
        <svg class="icon-light" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 9a3 3 0 0 1 3-3m-2 15h4m0-3c0-4.1 4-4.9 4-9A6 6 0 1 0 6 9c0 4 4 5 4 9h4Z"/></svg>
        <svg class="icon-dark" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24"><path fill-rule="evenodd" d="M7.05 4.05A7 7 0 0 1 19 9c0 2.407-1.197 3.874-2.186 5.084l-.04.048C15.77 15.362 15 16.34 15 18a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1c0-1.612-.77-2.613-1.78-3.875l-.045-.056C6.193 12.842 5 11.352 5 9a7 7 0 0 1 2.05-4.95ZM9 21a1 1 0 0 1 1-1h4a1 1 0 1 1 0 2h-4a1 1 0 0 1-1-1Zm1.586-13.414A2 2 0 0 1 12 7a1 1 0 1 0 0-2 4 4 0 0 0-4 4 1 1 0 0 0 2 0 2 2 0 0 1 .586-1.414Z" clip-rule="evenodd"/></svg>
    </button>
    
    <!-- Navigasyon Barı -->
    <?php include 'header.php'; ?>

    <!-- Karşılama İçeriği -->
    <div class="landing-container">
        <h1 class="landing-title">Yapay Zeka Destekli Hukuki Araştırma Platformu</h1>
        <p class="landing-subtitle">
            Gelişmiş arama özellikleri, AI destekli özetler ve kişisel çalışma alanınız ile emsal karar araştırmasını bir üst seviyeye taşıyın.
        </p>
        <div>
            <?php if (isset($_SESSION['user_id'])): ?>
                <!-- Eğer kullanıcı giriş yapmışsa, onu paneline yönlendir -->
                <a href="dashboard.php" class="landing-button">Arama Paneline Git &rarr;</a>
            <?php else: ?>
                <!-- Eğer misafirse, giriş yapmaya teşvik et -->
                <a href="login.php" class="landing-button">Başlamak İçin Giriş Yapın</a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Sadece temel işlevler için main.js'i çağırıyoruz (örn: Karanlık Mod) -->
    <script src="assets/js/main.js"></script>
    
</body>
</html>