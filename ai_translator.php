<?php
/**
 * ai_translator.php
 * Bu dosya, AJAX isteği ile gönderilen doğal dil sorgusunu alır,
 * AI'ye çevirtir ve sonucu JSON olarak geri döndürür.
 */

// Gerekli dosyaları çağır
require_once 'config/database.php';
require_once 'core/analysis_helpers.php';

// Güvenlik: Sadece POST isteklerini kabul et
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Geçersiz istek metodu.']);
    exit;
}

// Gelen sorguyu al
$kullaniciSorgusu = $_POST['query'] ?? '';

if (empty($kullaniciSorgusu)) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Sorgu metni boş olamaz.']);
    exit;
}

// AI tercüman fonksiyonunu çağır
$teknikSorgu = translateQueryToAdvancedSearch($kullaniciSorgusu);

// Sonucu JSON olarak tarayıcıya gönder
header('Content-Type: application/json');
echo json_encode(['translated_query' => $teknikSorgu]);