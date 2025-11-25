// assets/js/find-in-text.js
document.addEventListener('DOMContentLoaded', function() {
    const kararIcerik = document.querySelector('.karar-icerik');
    if (!kararIcerik) return; // Sadece karar sayfasında çalış

    const toggleBtn = document.getElementById('toggle-find-bar-btn');
    const findBar = document.getElementById('find-in-text-bar');
    const findInput = document.getElementById('find-input');
    const findCounter = document.getElementById('find-counter');
    const findCloseBtn = document.getElementById('find-close-btn');

    if (!toggleBtn || !findBar) return;

    // Mark.js'den yeni bir nesne oluştur
    const markInstance = new Mark(kararIcerik);

    // Büyüteç ikonuna tıklandığında arama çubuğunu göster/gizle
    toggleBtn.addEventListener('click', () => {
        findBar.classList.toggle('hidden');
        if (!findBar.classList.contains('hidden')) {
            findInput.focus();
        } else {
            // Arama çubuğu kapatıldığında vurguları temizle
            markInstance.unmark();
            findInput.value = '';
            findCounter.textContent = '0 sonuç';
        }
    });

    // Kapatma (X) butonuna tıklandığında arama çubuğunu gizle
    findCloseBtn.addEventListener('click', () => {
        findBar.classList.add('hidden');
        markInstance.unmark();
        findInput.value = '';
        findCounter.textContent = '0 sonuç';
    });

    // Arama kutusuna her harf yazıldığında arama yap
    findInput.addEventListener('input', function() {
        const searchTerm = this.value;
        
        // Önceki vurguları temizle
        markInstance.unmark({
            done: function() {
                // Sadece arama terimi 2 karakterden uzunsa arama yap
                if (searchTerm.length > 1) {
                    let count = 0;
                    markInstance.mark(searchTerm, {
                        className: 'search-highlight',
                        separateWordSearch: true, // "boşanma davası" aramasında "boşanma" ve "davası"nı ayrı ayrı bulur
                        done: (counter) => {
                            count = counter;
                            findCounter.textContent = `${count} sonuç`;
                        }
                    });
                } else {
                    findCounter.textContent = '0 sonuç';
                }
            }
        });
    });
});