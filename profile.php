<?php
/**
 * profile.php - Kullanıcı Profili ve Ayarlar Sayfası v2.3 (Güvenilir Yenileme)
 * 
 * Bu sürüm, 2FA formu başarılı olduğunda sayfayı yeniden yükler ve 
 * JavaScript dosyalarını "cache busting" ile çağırarak önbellek sorunlarını çözer.
 */

// Oturumu başlat ve güvenlik kontrolü yap
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?error=auth');
    exit();
}

// Gerekli dosyaları ve veritabanı bağlantısını dahil et
require_once 'config/database.php';
$pdo = getDbConnection();
$user_id = $_SESSION['user_id'];

// Hangi sekmenin aktif olduğunu URL'den al (varsayılan: profil)
$view = $_GET['view'] ?? 'profile';

// Görüntülenecek kullanıcı bilgilerini veritabanından çek
try {
    $stmt = $pdo->prepare("SELECT email, full_name, title, is_2fa_enabled FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    if (!$user) {
        session_destroy();
        header('Location: login.php');
        exit;
    }
} catch (PDOException $e) {
    die("Kullanıcı bilgileri alınırken bir hata oluştu.");
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profilim & Ayarlar</title>
    
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="dashboard-container panel-container">
        <!-- Sol Menü (Sidebar) -->
        <div class="panel-sidebar">
            <h3>Ayarlar</h3>
            <ul class="folder-list-menu">
                <li class="<?php echo ($view === 'profile') ? 'active' : ''; ?>">
                    <a href="profile.php?view=profile">Profil Bilgileri</a>
                </li>
                <li class="<?php echo ($view === 'account') ? 'active' : ''; ?>">
                    <a href="profile.php?view=account">Hesap & Güvenlik</a>
                </li>
            </ul>
        </div>

        <!-- Sağ İçerik Alanı -->
        <div class="panel-content">
            <!-- AJAX yanıtları için bildirim alanı -->
            <div id="response-message" style="margin-bottom: 20px;"></div>

            <?php if ($view === 'profile'): ?>
                
                <h2>Profil Bilgileri</h2>
                <p>Adınız ve unvanınız gibi kişisel bilgilerinizi buradan güncelleyebilirsiniz.</p>
                <hr class="form-divider">
                <form id="profile-form" class="search-form" style="max-width: 600px;">
                    <input type="hidden" name="action" value="update_profile">
                    <label>E-posta Adresi (Değiştirilemez)</label>
                    <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                    <label for="full_name">Ad Soyad</label>
                    <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" placeholder="Adınız ve Soyadınız...">
                    <label for="title">Unvan</label>
                    <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($user['title'] ?? ''); ?>" placeholder="Avukat, Öğrenci, Stj. Avukat...">
                    <button type="submit">Bilgileri Güncelle</button>
                </form>

            <?php elseif ($view === 'account'): ?>
                
                <h2>Hesap & Güvenlik</h2>
                <p>Giriş şifrenizi ve ek güvenlik ayarlarınızı buradan yönetebilirsiniz.</p>
                <hr class="form-divider">

                <!-- İki Faktörlü Kimlik Doğrulama Bölümü -->
                <div class="settings-section">
                    <h4>İki Faktörlü Koruma (2FA)</h4>
                    <p>
                        Giriş yaparken e-postanıza gönderilecek tek kullanımlık bir kod ile hesabınızın güvenliğini artırın.
                    </p>
                    <form id="2fa-form">
                        <div class="two-factor-status">
                            <span>Durum:</span>
                            <strong class="<?php echo ($user['is_2fa_enabled'] == 1) ? 'status-active' : 'status-inactive'; ?>">
                                <?php echo ($user['is_2fa_enabled'] == 1) ? 'Aktif' : 'Pasif'; ?>
                            </strong>
                            <input type="hidden" name="action" value="toggle_2fa">
                            <input type="hidden" name="status" value="<?php echo ($user['is_2fa_enabled'] == 1) ? '0' : '1'; ?>">
                            <button type="submit" class="<?php echo ($user['is_2fa_enabled'] == 1) ? 'btn-danger' : 'btn-success'; ?>">
                                <?php echo ($user['is_2fa_enabled'] == 1) ? 'Devre Dışı Bırak' : 'Etkinleştir'; ?>
                            </button>
                        </div>
                    </form>
                </div>

                <hr class="form-divider">

                <!-- Şifre Değiştirme Formu -->
                <h4>Şifre Değiştir</h4>
                <form id="password-form" class="search-form" style="max-width: 600px;">
                    <input type="hidden" name="action" value="change_password">
                    <label for="current_password">Mevcut Şifreniz</label>
                    <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
                    <label for="new_password">Yeni Şifreniz</label>
                    <input type="password" id="new_password" name="new_password" required minlength="6" autocomplete="new-password">
                    <label for="confirm_password">Yeni Şifreniz (Tekrar)</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="6" autocomplete="new-password">
                    <button type="submit">Şifreyi Değiştir</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Önbellek sorunlarını önlemek için JavaScript dosyalarını zaman damgasıyla çağır -->
    <script src="assets/js/main.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/profile.js?v=<?php echo time(); ?>"></script>
</body>
</html>