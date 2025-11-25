<?php
/**
 * verify_code.php - İki Faktörlü Kod Doğrulama Sayfası v3.0 (Modern Tasarım)
 * 
 * Bu sayfa, 'login.php' tarafından yönlendirilen ve 2FA'sı aktif olan
 * kullanıcıların, e-postalarına gelen 6 haneli kodu girmelerini sağlar.
 * Bu sürüm, 'auth.css' stil dosyasını kullanarak diğer giriş/kayıt
 * sayfalarıyla tutarlı, modern ve mobil uyumlu bir tasarım sunar.
 */

// Oturumu her zaman en başta başlat
session_start();

// Güvenlik Kontrolü:
// Eğer kullanıcı doğrulama sürecinde değilse veya zaten tam giriş yapmışsa,
// onu ana sayfaya yönlendir.
if (!isset($_SESSION['2fa_user_id']) || isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Gerekli dosyaları çağır ve değişkenleri hazırla
require_once 'config/database.php';
$error = '';
$user_id = $_SESSION['2fa_user_id'];
$user_email = 'e-posta adresinize'; // Varsayılan metin

// E-posta adresini kullanıcıya göstermek için veritabanından çek
try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $fetched_email = $stmt->fetchColumn();
    if ($fetched_email) {
        $user_email = $fetched_email;
    }
} catch (PDOException $e) {
    $error = "Veritabanı hatası oluştu.";
}

// Eğer form gönderilmişse doğrulama mantığını çalıştır
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = $_POST['code'] ?? '';

    if (empty($code) || !is_numeric($code)) {
        $error = 'Lütfen 6 haneli doğrulama kodunu girin.';
    } else {
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("SELECT email, full_name, two_factor_code, two_factor_expires_at FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();

            if (!$user || !$user['two_factor_code'] || !$user['two_factor_expires_at']) {
                 throw new Exception('Doğrulama bilgileri bulunamadı. Lütfen tekrar giriş yapın.');
            }

            $expires_at = new DateTime($user['two_factor_expires_at']);
            $now = new DateTime();

            if ($now > $expires_at) {
                $error = 'Doğrulama kodunun süresi dolmuş. Lütfen tekrar giriş yapmayı deneyin.';
            } elseif (password_verify($code, $user['two_factor_code'])) {
                // BAŞARILI!
                unset($_SESSION['2fa_user_id']);
                
                $updateStmt = $pdo->prepare("UPDATE users SET two_factor_code = NULL, two_factor_expires_at = NULL WHERE id = ?");
                $updateStmt->execute([$user_id]);
                
                $_SESSION['user_id'] = $user_id;
                $_SESSION['user_email'] = $user['email'];
                
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Girdiğiniz kod geçersiz.';
            }
        } catch (Exception $e) {
            $error = "Doğrulama sırasında bir hata oluştu.";
            error_log("2FA doğrulama hatası: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Girişi Doğrula - Karar Arama Platformu</title>
    
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body>
    
    <button id="dark-mode-toggle" class="theme-toggle-button" title="Temayı Değiştir">
        <svg class="icon-light" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 9a3 3 0 0 1 3-3m-2 15h4m0-3c0-4.1 4-4.9 4-9A6 6 0 1 0 6 9c0 4 4 5 4 9h4Z"/></svg>
        <svg class="icon-dark" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24"><path fill-rule="evenodd" d="M7.05 4.05A7 7 0 0 1 19 9c0 2.407-1.197 3.874-2.186 5.084l-.04.048C15.77 15.362 15 16.34 15 18a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1c0-1.612-.77-2.613-1.78-3.875l-.045-.056C6.193 12.842 5 11.352 5 9a7 7 0 0 1 2.05-4.95ZM9 21a1 1 0 0 1 1-1h4a1 1 0 1 1 0 2h-4a1 1 0 0 1-1-1Zm1.586-13.414A2 2 0 0 1 12 7a1 1 0 1 0 0-2 4 4 0 0 0-4 4 1 1 0 0 0 2 0 2 2 0 0 1 .586-1.414Z" clip-rule="evenodd"/></svg>
    </button>

    <div class="container auth-container">
        <div class="page-header integrated-header">
            <div class="header-main">
                <a href="index.php" class="logo">Karar Arama</a>
            </div>
        </div>

        <div class="header-content">
            <h1 class="form-title">Hesabınızı Doğrulayın</h1>
        </div>
        
        <p style="text-align: center; color: #6c757d; margin-bottom: 25px; line-height: 1.6;">
            Güvenliğiniz için, <strong><?php echo htmlspecialchars($user_email); ?></strong> adresine gönderilen 6 haneli doğrulama kodunu girin.
        </p>

        <?php if($error): ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <form method="POST" action="verify_code.php" class="search-form">
            <input type="text" name="code" placeholder=" " required maxlength="6" pattern="\d{6}" inputmode="numeric" autocomplete="one-time-code" style="text-align: center; font-size: 1.5rem; letter-spacing: 0.5em; padding: 15px;">
            <button type="submit">Doğrula ve Giriş Yap</button>
            <p class="form-footer-link">
                Kod gelmedi mi? <a href="login.php">Tekrar giriş yapmayı deneyin.</a>
            </p>
        </form>
    </div>

    <script src="assets/js/main.js"></script>
</body>
</html>