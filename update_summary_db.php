<?php
// update_summary_db.php
session_start();
require_once 'config/database.php';
if (!isset($_SESSION['user_id'])) { exit; }

$kararId = $_POST['id'] ?? null;
$aiOzetJson = $_POST['ai_ozet'] ?? null;

if ($kararId && $aiOzetJson) {
    $pdo = getDbConnection();
    // Bu kısımda extractEntities'i de çağırıp icerik_etiketli'yi de güncelleyebilirsiniz.
    $stmt = $pdo->prepare("UPDATE kararlar SET ai_ozet = ? WHERE id = ?");
    $stmt->execute([$aiOzetJson, $kararId]);
}
?>