/**
 * annotation.js - Metin Üzerinde Not Alma ve Vurgulama Sistemi v2.1 (Mobil Uyumlu)
 * 
 * Bu dosya, kullanıcının karar metni üzerinde yaptığı seçimleri yönetir.
 * - Bu sürüm, hem masaüstü ('mouseup') hem de mobil ('touchend') olaylarını dinleyerek
 *   tüm cihazlarda metin seçimi ve not almayı destekler.
 * - Mark.js kütüphanesini kullanarak, kaydedilmiş notları ve vurguları
 *   metin üzerinde güvenilir bir şekilde gösterir.
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // Sadece karar metninin olduğu sayfalarda çalış
    const kararIcerik = document.querySelector('.karar-icerik');
    if (!kararIcerik) {
        return;
    }

    // Gerekli değişkenleri tanımla
    const decisionId = new URLSearchParams(window.location.search).get('id');
    const markInstance = new Mark(kararIcerik); // Mark.js'den yeni bir nesne oluştur
    let selectionPopup = null; // Metin seçildiğinde çıkan pop-up menüsü

    // --- Başlangıç Fonksiyonu ---
    // Sayfanın tüm içeriği yüklendikten sonra mevcut notları/vurguları uygula.
    window.addEventListener('load', function () {
        setTimeout(loadAndApplyAnnotations, 100);
    });

    // --- Olay Dinleyicileri ---
    
    // 1. Masaüstü için: Fare bırakıldığında çalışır.
    kararIcerik.addEventListener('mouseup', handleSelectionEnd);
    
    // 2. Mobil için: Parmak ekrandan kaldırıldığında çalışır.
    kararIcerik.addEventListener('touchend', handleSelectionEnd);

    // Pop-up dışındaki bir yere tıklandığında veya dokunulduğunda menüyü gizle.
    document.addEventListener('mousedown', closePopupOnOutsideClick);
    document.addEventListener('touchstart', closePopupOnOutsideClick);

    function closePopupOnOutsideClick(event) {
        if (selectionPopup && !selectionPopup.contains(event.target)) {
            selectionPopup.remove();
        }
    }


    /**
     * Hem 'mouseup' hem de 'touchend' tarafından çağrılan merkezi fonksiyon.
     * Bir metin seçimi olup olmadığını kontrol eder ve varsa pop-up'ı gösterir.
     */
    function handleSelectionEnd(event) {
        // Pop-up'ın kendi butonlarına tıklanmasını yoksay, menünün kapanmasını engelle.
        if (selectionPopup && selectionPopup.contains(event.target)) {
            return;
        }
        
        // Kısa bir gecikme, tarayıcının (özellikle mobilde) seçimi tamamlamasına zaman tanır.
        setTimeout(() => {
            if (selectionPopup) selectionPopup.remove();
            
            const selection = window.getSelection();
            const selectedText = selection.toString().trim();

            if (selectedText.length > 2) {
                const range = selection.getRangeAt(0);
                const rect = range.getBoundingClientRect();

                selectionPopup = document.createElement('div');
                selectionPopup.className = 'selection-popup';
                selectionPopup.style.top = `${window.scrollY + rect.top - 50}px`;
                selectionPopup.style.left = `${window.scrollX + rect.left + (rect.width / 2) - 75}px`;
                
                selectionPopup.innerHTML = `
                    <button id="highlight-btn" title="Seçimi sarı renkle vurgula">Vurgula</button>
                    <button id="add-note-btn" title="Seçimle ilgili bir not ekle">Not Ekle</button>
                `;
                
                document.body.appendChild(selectionPopup);

                document.getElementById('highlight-btn').onclick = () => saveAnnotation(selectedText, null);
                document.getElementById('add-note-btn').onclick = () => {
                    const note = prompt('Lütfen notunuzu girin:', '');
                    if (note !== null) {
                        saveAnnotation(selectedText, note);
                    }
                };
            }
        }, 10);
    }

    /**
     * Sunucudan mevcut tüm notları ve vurguları çeker ve Mark.js kullanarak
     * karar metni üzerinde görsel olarak işaretler.
     */
    async function loadAndApplyAnnotations() {
        try {
            const response = await fetch(`annotation_actions.php?action=get_annotations&decision_id=${decisionId}`);
            if (!response.ok) throw new Error('Sunucu yanıt vermiyor.');
            
            const data = await response.json();

            if (data.success && data.annotations.length > 0) {
                markInstance.unmark(); // Önceki vurguları temizle
                data.annotations.forEach(anno => {
                    markInstance.mark(anno.selected_text, {
                        element: 'mark',
                        className: 'highlight',
                        accuracy: 'exactly',
                        separateWordSearch: false,
                        acrossElements: true,
                        each: function(element) {
                            if (anno.note_text) {
                                element.title = anno.note_text;
                            } else {
                                element.title = 'Vurgulandı';
                            }
                        }
                    });
                });
            }
        } catch (error) {
            console.error("Notlar yüklenirken hata oluştu:", error);
        }
    }

    /**
     * Seçilen metni ve (varsa) notu AJAX ile sunucuya kaydederek kalıcı hale getirir.
     */
    async function saveAnnotation(selectedText, noteText = null) {
        if (selectionPopup) selectionPopup.remove();
        const formData = new FormData();
        formData.append('action', 'add_annotation');
        formData.append('decision_id', decisionId);
        formData.append('selected_text', selectedText);
        if (noteText) {
            formData.append('note_text', noteText);
        }
        try {
            const response = await fetch('annotation_actions.php', { method: 'POST', body: formData });
            const data = await response.json();
            if (data.success) {
                // Başarılı kayıttan sonra, yeni notu/vurguyu da gösterebilmek için
                // tüm notları yeniden yükle ve uygula.
                loadAndApplyAnnotations();
            } else {
                throw new Error(data.message || 'Bilinmeyen bir hata oluştu.');
            }
        } catch (error) {
            alert('Hata: ' + error.message);
        }
    }
});