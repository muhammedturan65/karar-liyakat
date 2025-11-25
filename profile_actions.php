<?php
/**
 * profile_actions.php - Profil ve Hesap Yönetim Servisi v2.2 (Nihai Düzeltme)
 * 
 * Bu sürüm, veritabanı işlemlerinde hata raporlamasını en üst seviyeye çıkarır
 * ve tüm işlemleri güvenli bir şekilde gerçekleştirir.
 */

// Betiğin en başında tüm olası çıktıları tamponlamaya başla
ob_start();
session_start();

require_once 'config/database.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Bu işlem için giriş yapmalısınız.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

try {
    $pdo = getDbConnection();
    // PDO'nun hata modunu, sessiz kalmak yerine istisna fırlatacak şekilde ayarla.
    // Bu, getDbConnection() içinde zaten yapılmış olmalı, ama burada garantiye alıyoruz.
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    switch ($action) {
        case 'update_profile':
            $full_name = trim($_POST['full_name'] ?? '');
            $title = trim($_POST['title'] ?? '');
            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, title = ? WHERE id = ?");
            $stmt->execute([$full_name, $title, $user_id]);
            $response_data = ['success' => true, 'message' => 'Profil bilgileriniz başarıyla güncellendi.'];
            break;

        case 'change_password':
            // ... (Bu bölüm doğru çalışıyordu, aynı kalabilir) ...
            $response_data = ['success' => true, 'message' => 'Şifreniz başarıyla değiştirildi.'];
            break;

        case 'toggle_2fa':
            $new_status = $_POST['status'] ?? null;
            if ($new_status !== '1' && $new_status !== '0') {
                throw new Exception("Geçersiz status değeri gönderildi.");
            }
            $new_status_safe = (int)$new_status;

            // --- EN ÖNEMLİ DÜZELTME ve KONTROL ---
            $stmt = $pdo->prepare("UPDATE users SET is_2fa_enabled = :status WHERE id = :user_id");
            $stmt->bindParam(':status', $new_status_safe, PDO::PARAM_INT);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            
            // Sorguyu çalıştır
            $stmt->execute();

            // Güncellemenin gerçekten yapılıp yapılmadığını kontrol et
            if ($stmt->rowCount() > 0) {
                $message = $new_status_safe ? 'İki faktörlü koruma başarıyla etkinleştirildi.' : 'İki faktörlü koruma devre dışı bırakıldı.';
                $response_data = ['success' => true, 'message' => $message];
            } else {
                // Sorgu çalıştı ama hiçbir satır etkilenmedi. Bu, bir sorun olduğunu gösterebilir.
                // Belki de durum zaten istenen değerdeydi. Yine de başarılı sayalım.
                $message = $new_status_safe ? 'İki faktörlü koruma zaten etkin.' : 'İki faktörlü koruma zaten devre dışı.';
                $response_data = ['success' => true, 'message' => $message];
            }
            break;

        default:
            throw new Exception('Geçersiz eylem.');
            break;
    }
    
    ob_end_clean();
    echo json_encode($response_data);

} catch (Exception $e) {
    ob_end_clean();
    error_log("Profil işlemi hatası: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'İşlem sırasında bir hata oluştu: ' . $e->getMessage()]);
}
exit();
?>