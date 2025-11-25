/**
 * help-tips.js - Arama İpuçları Akordeon Mantığı
 * 
 * Bu dosya, dashboard sayfasındaki "Arama İpuçlarını Göster/Gizle"
 * linkinin işlevselliğini yönetir.
 */

document.addEventListener('DOMContentLoaded', function() {
    
    const toggleLink = document.getElementById('toggle-help-box');
    const contentBox = document.getElementById('help-box-content');

    // Eğer bu elementler sayfada mevcut değilse, script'in geri kalanını çalıştırma.
    if (!toggleLink || !contentBox) {
        return;
    }

    toggleLink.addEventListener('click', function(event) {
        // a etiketinin varsayılan davranışını engelle (sayfanın başına zıplamasını önler)
        event.preventDefault(); 
        
        const linkText = this.querySelector('span');
        const icon = this.querySelector('svg');

        // İçerik kutusunun görünürlüğünü kontrol et
        const isHidden = contentBox.style.display === "none";

        if (isHidden) {
            // Eğer gizliyse, göster
            contentBox.style.display = "block";
            linkText.innerText = "Arama İpuçlarını Gizle";
            if(icon) icon.style.transform = 'rotate(180deg)';
        } else {
            // Eğer görünürse, gizle
            contentBox.style.display = "none";
            linkText.innerText = "Arama İpuçlarını Göster";
            if(icon) icon.style.transform = 'rotate(0deg)';
        }
    });

});