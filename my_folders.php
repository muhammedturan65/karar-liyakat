<?php
/**
 * my_folders.php - Kullanıcı Paneli v3.0
 * 
 * Bu sürüm, URL'deki 'view' parametresine göre "Klasörlerim" veya 
 * "Notlarım & Vurgularım" sekmelerini doğru bir şekilde gösterme
 * mantığını içerir.
 */

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?error=auth');
    exit();
}

require_once 'config/database.php';
$pdo = getDbConnection();
$user_id = $_SESSION['user_id'];

// Hangi sekmenin aktif olduğunu URL'den al (varsayılan: klasörler)
$view = $_GET['view'] ?? 'folders';

// --- Veri Çekme Mantığı (Koşullu) ---
if ($view === 'folders') {
    $folderStmt = $pdo->prepare("SELECT f.id, f.folder_name, COUNT(sd.id) as decision_count FROM folders f LEFT JOIN saved_decisions sd ON f.id = sd.folder_id WHERE f.user_id = ? GROUP BY f.id ORDER BY f.folder_name ASC");
    $folderStmt->execute([$user_id]);
    $folders = $folderStmt->fetchAll();
    $selectedFolderId = $_GET['folder_id'] ?? ($folders[0]['id'] ?? null);
    $selectedFolderName = '';
    $savedDecisions = [];
    if ($selectedFolderId) {
        $decisionStmt = $pdo->prepare("SELECT * FROM saved_decisions WHERE user_id = ? AND folder_id = ? ORDER BY saved_at DESC");
        $decisionStmt->execute([$user_id, $selectedFolderId]);
        $savedDecisions = $decisionStmt->fetchAll();
        foreach($folders as $folder) { if ($folder['id'] == $selectedFolderId) { $selectedFolderName = $folder['folder_name']; break; } }
    }
} elseif ($view === 'annotations') {
    $annoStmt = $pdo->prepare("SELECT * FROM annotations WHERE user_id = ? ORDER BY decision_id, created_at DESC");
    $annoStmt->execute([$user_id]);
    $allAnnotations = $annoStmt->fetchAll();
    $annotationsByDecision = [];
    foreach ($allAnnotations as $anno) {
        $annotationsByDecision[$anno['decision_id']][] = $anno;
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Panelim - Karar Arama</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="dashboard-container panel-container">
        <div class="panel-sidebar">
            <h3>Panelim</h3>
            <ul class="folder-list-menu">
                <li class="<?php echo ($view === 'folders') ? 'active' : ''; ?>">
                    <a href="my_folders.php?view=folders">Klasörlerim</a>
                </li>
                <li class="<?php echo ($view === 'annotations') ? 'active' : ''; ?>">
                    <a href="my_folders.php?view=annotations">Notlarım & Vurgularım</a>
                </li>
		   </ul>
        </div>
        <div class="panel-content">

            <!-- DÜZELTME: PHP if/else bloğu ile doğru içeriği göster -->
            <?php if ($view === 'folders'): ?>
                <!-- KLASÖRLERİM GÖRÜNÜMÜ -->
                <h2>Klasörlerim</h2>
                <p>Kaydettiğiniz kararlara buradan ulaşabilirsiniz.</p>
                <hr class="form-divider">
                <div class="folder-view-container">
                    <div class="folder-list-column">
                        <h4>Tüm Klasörler</h4>
                        <ul class="folder-list-menu">
                            <?php if (empty($folders)): ?>
                                <li class="empty-folder-list">Henüz hiç klasörünüz yok.</li>
                            <?php else: ?>
                                <?php foreach ($folders as $folder): ?>
                                    <li class="<?php echo ($folder['id'] == $selectedFolderId) ? 'active' : ''; ?>">
                                        <a href="my_folders.php?view=folders&folder_id=<?php echo $folder['id']; ?>">
                                            <span><?php echo htmlspecialchars($folder['folder_name']); ?></span>
                                            <span class="decision-count"><?php echo $folder['decision_count']; ?></span>
                                        </a>
                                        <button class="delete-folder-btn" data-folder-id="<?php echo $folder['id']; ?>" title="Klasörü Sil">&times;</button>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <div class="decision-list-column">
                        <?php if ($selectedFolderId): ?>
                            <h4>'<?php echo htmlspecialchars($selectedFolderName); ?>' Klasöründeki Kararlar</h4>
                            <?php if (!empty($savedDecisions)): ?>
                                <ul class="search-results-list saved-decisions-list">
                                    <?php foreach($savedDecisions as $decision): ?>
                                        <li id="saved-decision-<?php echo $decision['id']; ?>">
                                            <a href="karar.php?id=<?php echo htmlspecialchars($decision['decision_id']); ?>" target="_blank"><strong>Karar ID:</strong> <?php echo htmlspecialchars($decision['decision_id']); ?></a>
                                            <div class="decision-meta"><span>Kaydedilme: <?php echo date('d.m.Y', strtotime($decision['saved_at'])); ?></span><button class="remove-decision-btn" data-saved-id="<?php echo $decision['id']; ?>">Klasörden Çıkar</button></div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p>Bu klasörde henüz kaydedilmiş bir karar yok.</p>
                            <?php endif; ?>
                        <?php else: ?>
                            <h4>Klasör Seçilmedi</h4>
                            <p>Görüntülemek için sol taraftan bir klasör seçin.</p>
                        <?php endif; ?>
                    </div>
                </div>

            <?php elseif ($view === 'annotations'): ?>
                <!-- NOTLARIM & VURGULARIM GÖRÜNÜMÜ -->
                <h2>Notlarım & Vurgularım</h2>
                <p>Farklı kararlar üzerine aldığınız tüm notlar ve yaptığınız vurgular aşağıda listelenmiştir.</p>
                <hr class="form-divider">
                <?php if (empty($annotationsByDecision)): ?>
                    <p>Henüz hiç not almadınız veya bir metni vurgulamadınız.</p>
                <?php else: ?>
                    <div class="annotations-list">
                        <?php foreach ($annotationsByDecision as $decision_id => $annotations): ?>
                            <div class="annotation-group">
                                <h3 class="annotation-decision-title">
                                    Karar ID: <a href="karar.php?id=<?php echo $decision_id; ?>" target="_blank"><?php echo $decision_id; ?></a>
                                </h3>
                                <?php foreach ($annotations as $anno): ?>
                                    <div class="annotation-item">
                                        <p class="highlighted-text">"<?php echo htmlspecialchars($anno['selected_text']); ?>"</p>
                                        <?php if (!empty($anno['note_text'])): ?>
                                            <p class="note-text"><?php echo htmlspecialchars($anno['note_text']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

        </div>
    </div>
    
    <script src="assets/js/main.js"></script>
    <script src="assets/js/folder-system.js"></script>
</body>
</html>