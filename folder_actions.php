<?php
/**
 * folder_actions.php - Klasör ve Kayıtlı Karar Yönetim Servisi
 * 
 * Bu dosya, AJAX istekleri aracılığıyla aşağıdaki işlemleri yönetir:
 * 1. get_folders: Kullanıcının tüm klasörlerini listeler.
 * 2. save_decision: Bir kararı mevcut bir klasöre kaydeder.
 * 3. create_and_save: Yeni bir klasör oluşturur ve kararı o klasöre kaydeder.
 * 4. remove_decision: Bir kararı bir klasörden kaldırır.
 * 5. delete_folder: Bir klasörü ve içindeki tüm kayıtlı kararları siler.
 */

// Oturumu başlat
session_start();

// Gerekli dosyaları çağır
require_once 'config/database.php';

// Yanıtın her zaman JSON formatında olacağını belirt
header('Content-Type: application/json');

// --- GÜVENLİK KONTROLÜ ---
// Kullanıcı giriş yapmamışsa, yetkisiz erişim hatası döndür ve betiği sonlandır.
if (!isset($_SESSION['user_id'])) {
    http_response_code(403); // Forbidden
    echo json_encode(['success' => false, 'message' => 'Bu işlem için giriş yapmalısınız.']);
    exit;
}

// Giriş yapmış kullanıcının ID'sini al
$user_id = $_SESSION['user_id'];
// Gelen isteğin eylemini (action) al
$action = $_POST['action'] ?? '';

// Hata yönetimi için try-catch bloğu
try {
    $pdo = getDbConnection();

    // Gelen eyleme göre ilgili işlemi yap
    switch ($action) {
        
        case 'get_folders':
            $stmt = $pdo->prepare("SELECT * FROM folders WHERE user_id = ? ORDER BY folder_name ASC");
            $stmt->execute([$user_id]);
            $folders = $stmt->fetchAll();
            echo json_encode(['success' => true, 'folders' => $folders]);
            break;

        case 'save_decision':
            $folder_id = $_POST['folder_id'] ?? null;
            $decision_id = $_POST['decision_id'] ?? null;
            if (!$folder_id || !$decision_id) throw new Exception("Eksik parametre: Klasör veya Karar ID'si gönderilmedi.");

            $stmt = $pdo->prepare("SELECT id FROM saved_decisions WHERE user_id = ? AND folder_id = ? AND decision_id = ?");
            $stmt->execute([$user_id, $folder_id, $decision_id]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Bu karar zaten bu klasörde mevcut.']);
            } else {
                $stmt = $pdo->prepare("INSERT INTO saved_decisions (user_id, folder_id, decision_id) VALUES (?, ?, ?)");
                $stmt->execute([$user_id, $folder_id, $decision_id]);
                echo json_encode(['success' => true, 'message' => 'Karar başarıyla kaydedildi!']);
            }
            break;

        case 'create_and_save':
            $folder_name = trim($_POST['folder_name'] ?? '');
            $decision_id = $_POST['decision_id'] ?? null;
            if (empty($folder_name) || !$decision_id) throw new Exception("Eksik parametre: Klasör adı veya Karar ID'si gönderilmedi.");

            $stmt = $pdo->prepare("INSERT INTO folders (user_id, folder_name) VALUES (?, ?)");
            $stmt->execute([$user_id, $folder_name]);
            $new_folder_id = $pdo->lastInsertId();
            
            $stmt = $pdo->prepare("INSERT INTO saved_decisions (user_id, folder_id, decision_id) VALUES (?, ?, ?)");
            $stmt->execute([$user_id, $new_folder_id, $decision_id]);
            
            echo json_encode(['success' => true, 'message' => "'{$folder_name}' klasörü oluşturuldu ve karar kaydedildi!"]);
            break;

        case 'remove_decision':
            $saved_id = $_POST['saved_id'] ?? null;
            if (!$saved_id) throw new Exception("Eksik parametre: Kayıt ID'si gönderilmedi.");
            
            $stmt = $pdo->prepare("DELETE FROM saved_decisions WHERE id = ? AND user_id = ?");
            $stmt->execute([$saved_id, $user_id]);
            
            echo json_encode(['success' => true, 'message' => 'Karar klasörden çıkarıldı.']);
            break;

        case 'delete_folder':
            $folder_id = $_POST['folder_id'] ?? null;
            if (!$folder_id) throw new Exception("Eksik parametre: Klasör ID'si gönderilmedi.");
            
            $stmt1 = $pdo->prepare("DELETE FROM saved_decisions WHERE folder_id = ? AND user_id = ?");
            $stmt1->execute([$folder_id, $user_id]);
            
            $stmt2 = $pdo->prepare("DELETE FROM folders WHERE id = ? AND user_id = ?");
            $stmt2->execute([$folder_id, $user_id]);
            
            echo json_encode(['success' => true, 'message' => 'Klasör ve içindeki tüm kararlar silindi.']);
            break;
        
        default:
            http_response_code(400); // Bad Request
            echo json_encode(['success' => false, 'message' => 'Geçersiz eylem.']);
            break;
    }

} catch (PDOException $e) {
    error_log("Klasör işlemi hatası (Veritabanı): " . $e->getMessage());
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => 'Veritabanı sunucusunda bir hata oluştu.']);
} catch (Exception $e) {
    error_log("Klasör işlemi hatası (Genel): " . $e->getMessage());
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// Betiğin sonunda başka bir çıktı olmadığından emin olmak için
exit();
?>