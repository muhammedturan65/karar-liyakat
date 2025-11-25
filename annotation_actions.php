<?php
// annotation_actions.php
session_start();
require_once 'config/database.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Bu işlem için giriş yapmalısınız.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$pdo = getDbConnection();

try {
    if ($action === 'get_annotations') {
        $decision_id = $_GET['decision_id'];
        $stmt = $pdo->prepare("SELECT * FROM annotations WHERE user_id = ? AND decision_id = ?");
        $stmt->execute([$user_id, $decision_id]);
        $annotations = $stmt->fetchAll();
        echo json_encode(['success' => true, 'annotations' => $annotations]);

    } elseif ($action === 'add_annotation') {
        $decision_id = $_POST['decision_id'];
        $selected_text = $_POST['selected_text'];
        $note_text = $_POST['note_text'] ?? null; // Not metni boş olabilir
        
        $stmt = $pdo->prepare("INSERT INTO annotations (user_id, decision_id, selected_text, note_text) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $decision_id, $selected_text, $note_text]);
        
        echo json_encode(['success' => true, 'message' => 'Not/Vurgu başarıyla eklendi.']);
    }
    // Gelecekte 'delete_annotation' gibi eylemler de buraya eklenebilir.

} catch (Exception $e) {
    http_response_code(500);
    error_log("Annotation hatası: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Sunucu tarafında bir hata oluştu.']);
}
?>