<?php
/**
 * chat_component.php - Gezgin Sohbet Asistanı Arayüzü v3.0
 * 
 * Bu sürüm, sohbet giriş alanının üzerine, bağlama duyarlı olarak
 * "Bu Kararı Detaylı Özetle" gibi özel aksiyon butonlarının
 * gösterileceği bir alan ekler.
 */
?>

<!-- Gezgin Sohbet Balonu -->
<button id="chat-bubble" class="chat-bubble" title="Hukuk Asistanı'nı Aç">
    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
    </svg>
</button>

<!-- Sohbet Arayüzü (Başlangıçta Gizli) -->
<div id="chat-container" class="chat-container hidden">
    <!-- Sohbet Başlığı ve Kontrol Butonları -->
    <div class="chat-header">
        <h3>Hukuk Asistanı</h3>
        <div class="chat-actions">
            <a href="chat_service.php?action=export_txt" id="export-chat-btn" title="Sohbeti .txt Olarak İndir" download>
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
            </a>
            <button id="reset-chat-btn" title="Sohbeti Sıfırla">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="23 4 23 10 17 10"></polyline>
                    <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                </svg>
            </button>
            <button id="close-chat-btn" title="Kapat">&times;</button>
        </div>
    </div>

    <!-- Mesajların Gösterileceği Alan -->
    <div id="chat-messages" class="chat-messages">
        <div id="sample-questions-container" class="sample-questions-container"></div>
        <div class="chat-loader-container hidden">
            <div class="chat-loader"></div>
        </div>
    </div>

    <!-- Aksiyon Butonları Alanı -->
    <div id="chat-action-buttons" class="chat-action-buttons">
        <!-- Bu buton başlangıçta gizli olacak ve sadece karar sayfasında görünecek -->
        <button id="summarize-decision-btn" class="sample-question-btn hidden">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path>
                <polyline points="13 2 13 9 20 9"></polyline>
            </svg>
            <span>Bu Kararı Detaylı Özetle</span>
        </button>
    </div>

    <!-- Metin Giriş Alanı ve Gönder Butonu -->
    <div class="chat-input-area">
        <textarea id="chat-input" placeholder="Bir soru sorun..." rows="1"></textarea>
        <button id="chat-send-btn" title="Gönder">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="22" y1="2" x2="11" y2="13"></line>
                <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
            </svg>
        </button>
    </div>
</div>

<!-- 
    Bu karar sayfasının metnini saklamak için gizli bir div.
    Bu div, sadece karar.php'de ve sadece $hamMetin değişkeni varsa doldurulur.
    index.php gibi diğer sayfalarda bu div boş kalır ve sohbet asistanı genel modda çalışır.
-->
<div id="decision-context" style="display: none;">
<?php
if (isset($hamMetin) && !empty($hamMetin)) {
    echo htmlspecialchars($hamMetin);
}
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/showdown/2.1.0/showdown.min.js"></script>
</div>