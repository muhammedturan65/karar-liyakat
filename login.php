<?php
/**
 * login.php - Kullanıcı Giriş Sayfası v3.2 (Kesin 2FA Kontrolü)
 * 
 * Bu sürüm, 2FA ayarını `== 1` ile kesin olarak kontrol eder ve
 * modern, modüler bir tasarıma sahiptir.
 */

// Oturumu her zaman en başta başlat
session_start();

// Eğer kullanıcı zaten tam giriş yapmışsa (user_id varsa), onu tekrar giriş sayfasında tutma.
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}
// Eğer kullanıcı 2FA sürecinin ortasındaysa, onu doğrulama sayfasına geri yönlendir.
if (isset($_SESSION['2fa_user_id'])) {
    header('Location: verify_code.php');
    exit;
}

// Gerekli dosyaları çağır
require_once 'config/database.php';
require_once 'core/email_helper.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'E-posta ve şifre alanları zorunludur.';
    } else {
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                
                if ($user['is_2fa_enabled'] == 1) {
                    $code = random_int(100000, 999999);
                    $expires_at = (new DateTime())->add(new DateInterval('PT10M'))->format('Y-m-d H:i:s');
                    
                    $updateStmt = $pdo->prepare("UPDATE users SET two_factor_code = ?, two_factor_expires_at = ? WHERE id = ?");
                    $updateStmt->execute([password_hash((string)$code, PASSWORD_DEFAULT), $expires_at, $user['id']]);
                    
                    $emailBody = getVerificationEmailTemplate($code);
                    $emailSent = sendEmail($user['email'], $user['full_name'] ?? $user['email'], 'Giriş Doğrulama Kodunuz', $emailBody);

                    if (!$emailSent) {
                        throw new Exception("Doğrulama kodu e-postası gönderilemedi. Lütfen sistem yöneticisiyle iletişime geçin.");
                    }
                    
                    $_SESSION['2fa_user_id'] = $user['id'];
                    header('Location: verify_code.php');
                    exit;

                } else {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_email'] = $user['email'];
                    header('Location: dashboard.php');
                    exit;
                }

            } else {
                $error = 'Geçersiz e-posta veya şifre.';
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
            error_log("Giriş hatası: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş Yap - Karar Arama Platformu</title>
    
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
            <div class="header-actions user-actions">
                <a href="login.php" class="nav-link active">Giriş Yap</a>
                <a href="register.php" class="nav-link highlight-btn">Kayıt Ol</a>
            </div>
        </div>

        <div class="header-content">
            <h1 class="form-title">Giriş Yap</h1>
        </div>

        <?php if(isset($_GET['success'])): ?>
            <p class="form-success">Kayıt başarılı! Lütfen giriş yapın.</p>
        <?php endif; ?>
        <?php if(isset($_GET['verified'])): ?>
            <p class="form-success">Hesabınız doğrulandı. Şimdi giriş yapabilirsiniz.</p>
        <?php endif; ?>
        <?php if(isset($_GET['error']) && $_GET['error'] === 'auth'): ?>
            <p class="error">Bu özelliği kullanabilmek için lütfen giriş yapın.</p>
        <?php endif; ?>
        
        <?php if($error): ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <form method="POST" action="login.php" class="search-form">
            <input type="email" name="email" placeholder="E-posta Adresiniz" required>
            <input type="password" name="password" placeholder="Şifreniz" required>
            <button type="submit">Giriş Yap</button>
            <p class="form-footer-link">
                Hesabınız yok mu? <a href="register.php">Hemen Kayıt Olun</a>
            </p>
        </form>
    </div>
    
    <script src="assets/js/main.js"></script>
</body>
</html>