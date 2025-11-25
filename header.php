<?php
/**
 * header.php - Ana Navigasyon Bileşeni v3.1 (Akıllı Kullanıcı Adı)
 * 
 * Bu sürüm, kullanıcının adını veritabanından çeker ve e-posta yerine onu gösterir.
 * Ayrıca, açılır menü işlevselliğini barındırır.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Kullanıcı adını görüntülemek için, eğer giriş yapmışsa, veritabanından bilgilerini çekelim.
$display_name = '';
if (isset($_SESSION['user_id'])) {
    // Veritabanı bağlantısını sadece gerektiğinde kur
    require_once 'config/database.php'; 
    $pdo = getDbConnection();
    
    $stmt = $pdo->prepare("SELECT full_name, email FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    // Eğer ad-soyad girilmişse onu kullan, değilse e-postanın @ işaretinden önceki kısmını kullan.
    if (!empty($user['full_name'])) {
        $display_name = $user['full_name'];
    } else {
        $display_name = explode('@', $user['email'])[0];
    }
}
?>
<!-- Ana Navigasyon Barı -->
<div class="main-nav">
    <div class="nav-left">
        <a href="index.php" class="logo">Karar Arama Platformu</a>
    </div>
    <div class="nav-right">
        <?php if (isset($_SESSION['user_id'])): ?>
            <!-- EĞER KULLANICI GİRİŞ YAPMIŞSA -->
            <a href="dashboard.php" class="nav-link">Arama Paneli</a>
            <a href="my_folders.php" class="nav-link">Klasörlerim</a>
            
            <div class="user-dropdown">
                <button class="user-menu-button">
                    <span><?php echo htmlspecialchars($display_name); ?></span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                        <path fill-rule="evenodd" d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z"/>
                    </svg>
                </button>
                <div class="dropdown-content hidden">
                    <div class="dropdown-header">
                        <small>Giriş Yapan Kullanıcı:</small>
                        <strong><?php echo htmlspecialchars($user['email']); ?></strong>
                    </div>
                    <hr>
                    <a href="profile.php">Profil Ayarları</a>
                    <a href="logout.php" class="logout-link">Çıkış Yap</a>
                </div>
            </div>
            
        <?php else: ?>
            <!-- EĞER KULLANICI MİSAFİRSE -->
            <a href="login.php" class="nav-link">Giriş Yap</a>
            <a href="register.php" class="nav-link highlight-btn">Ücretsiz Kayıt Ol</a>
        <?php endif; ?>
    </div>
</div>