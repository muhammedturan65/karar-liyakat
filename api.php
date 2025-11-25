<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'config/database.php';

function validateApiKey($pdo, $apiKey) {
    $stmt = $pdo->prepare("SELECT 1 FROM api_users WHERE api_key = ?");
    $stmt->execute([$apiKey]);
    return $stmt->fetchColumn();
}

function apiResponse($success, $data, $message = '', $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$apiKey = null;
if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    $apiKey = $matches[1];
}

$pdo = getDbConnection();

if (!$apiKey || !validateApiKey($pdo, $apiKey)) {
    apiResponse(false, null, 'Geçersiz veya eksik API anahtarı.', 401);
}

$id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT) : null;
if (!$id) {
    apiResponse(false, null, 'Geçerli bir "id" parametresi gerekli.', 400);
}

// API isteği için de önbelleği kullanıyoruz.
$stmt = $pdo->prepare("SELECT id, ai_ozet, icerik_formatli, icerik_etiketli, guncellenme_tarihi FROM kararlar WHERE id = ?");
$stmt->execute([$id]);
$karar = $stmt->fetch();

if ($karar) {
    $karar['ai_ozet'] = json_decode($karar['ai_ozet'], true);
    apiResponse(true, $karar, 'Karar başarıyla bulundu.');
} else {
    apiResponse(false, null, 'Belirtilen ID ile önbellekte karar bulunamadı. Lütfen önce bu kararı web arayüzünde görüntüleyin.', 404);
}
?>