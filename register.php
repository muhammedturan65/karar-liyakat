<?php
/**
 * register.php - Kullanıcı Kayıt Sayfası v3.0
 * 
 * Bu sürüm, 'header.php' kullanmaz, navigasyon linklerini sayfanın içine entegre eder
 * ve sadece bu sayfaya özel stil dosyası olan 'auth.css'i çağırır.
 */

// Oturumu başlat
session_start();

// Eğer kullanıcı zaten giriş yapmışsa, onu tekrar kayıt sayfasında tutma, paneline yönlendir.
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

// Gerekli dosyaları çağır ve hata değişkenini oluştur
require_once 'config/database.php';
$error = '';

// Eğer form gönderilmişse (POST metodu ile)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    // --- Doğrulama Adımları ---
    if (empty($email) || empty($password) || empty($password_confirm)) {
        $error = 'Tüm alanların doldurulması zorunludur.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Lütfen geçerli bir e-posta adresi girin.';
    } elseif (strlen($password) < 6) {
        $error = 'Şifreniz en az 6 karakter olmalıdır.';
    } elseif ($password !== $password_confirm) {
        $error = 'Girdiğiniz şifreler uyuşmuyor.';
    } else {
        try {
            $pdo = getDbConnection();
            
            // Bu e-posta adresinin daha önce kayıt olup olmadığını kontrol et
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Bu e-posta adresi zaten başka bir kullanıcı tarafından kullanılıyor.';
            } else {
                // Yeni kullanıcıyı veritabanına ekle
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
                $stmt->execute([$email, $hashed_password]);
                
                // Başarılı kayıt sonrası kullanıcıyı bir mesajla giriş sayfasına yönlendir
                header('Location: login.php?success=1');
                exit;
            }
        } catch (PDOException $e) {
            $error = "Veritabanı hatası oluştu. Lütfen daha sonra tekrar deneyin.";
            error_log("Kayıt hatası: " . $e->getMessage()); // Hatayı sunucu loguna yaz
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kayıt Ol - Karar Arama Platformu</title>
    
    <!-- Ana stil dosyasını çağır -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <!-- Sadece giriş/kayıt sayfalarına özel stil dosyasını çağır -->
    <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body>
    
    <!-- Karanlık Mod Butonu -->
    <button id="dark-mode-toggle" class="theme-toggle-button" title="Temayı Değiştir">
        <svg class="icon-light" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 9a3 3 0 0 1 3-3m-2 15h4m0-3c0-4.1 4-4.9 4-9A6 6 0 1 0 6 9c0 4 4 5 4 9h4Z"/></svg>
        <svg class="icon-dark" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24"><path fill-rule="evenodd" d="M7.05 4.05A7 7 0 0 1 19 9c0 2.407-1.197 3.874-2.186 5.084l-.04.048C15.77 15.362 15 16.34 15 18a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1c0-1.612-.77-2.613-1.78-3.875l-.045-.056C6.193 12.842 5 11.352 5 9a7 7 0 0 1 2.05-4.95ZM9 21a1 1 0 0 1 1-1h4a1 1 0 1 1 0 2h-4a1 1 0 0 1-1-1Zm1.586-13.414A2 2 0 0 1 12 7a1 1 0 1 0 0-2 4 4 0 0 0-4 4 1 1 0 0 0 2 0 2 2 0 0 1 .586-1.414Z" clip-rule="evenodd"/></svg>
    </button>
    
    <div class="container auth-container">
        <!-- BÜTÜNLEŞİK BAŞLIK ALANI -->
        <div class="page-header integrated-header">
            <div class="header-main">
                <a href="index.php" class="logo">Karar Arama</a>
            </div>
            <div class="header-actions user-actions">
                <a href="login.php" class="nav-link">Giriş Yap</a>
                <a href="register.php" class="nav-link highlight-btn active">Kayıt Ol</a>
            </div>
        </div>

        <div class="header-content">
            <h1 class="form-title">Yeni Hesap Oluştur</h1>
        </div>
        
        <?php if($error): ?>
            <p class="error"><?php echo $error; ?></p>
        <?php endif; ?>

        <form method="POST" action="register.php" class="search-form">
            <input type="email" name="email" placeholder="E-posta Adresiniz" required>
            <input type="password" name="password" placeholder="Şifreniz (en az 6 karakter)" required>
            <input type="password" name="password_confirm" placeholder="Şifreniz (Tekrar)" required>
            <button type="submit">Hesabımı Oluştur</button>
			<p class="form-footer-link">Zaten bir hesabınız var mı? <a href="login.php">Giriş Yapın</a></p>
        </form>
    </div>

    <!-- Sadece Karanlık Mod gibi temel işlevler için main.js'i çağırıyoruz -->
    <script src="assets/js/main.js"></script>
</body>
</html>