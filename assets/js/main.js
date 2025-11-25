/**
 * main.js - Ana Sayfa ve Karar Sayfası İşlevleri
 * 
 * Bu dosya, projenin temel JavaScript işlevlerini yönetir.
 * DÜZELTME: Manuel özet oluştururken Karar ID'sinin gönderilmesi sağlandı.
 */

// Sayfa ilk yüklendiğinde, FOUC önlemek için temayı hemen uygula.
(function() {
    if (localStorage.getItem('theme') === 'dark') {
        document.body.classList.add('dark-mode');
    }
})();

document.addEventListener('DOMContentLoaded', function() {

    // --- 1. AÇILIR KULLANICI MENÜSÜ ---
    const userMenuButton = document.querySelector('.user-menu-button');
    const dropdownContent = document.querySelector('.dropdown-content');

    if (userMenuButton && dropdownContent) {
        userMenuButton.addEventListener('click', function(event) {
            event.stopPropagation();
            dropdownContent.classList.toggle('hidden');
        });
        dropdownContent.addEventListener('click', function(event) {
            event.stopPropagation();
        });
    }
    document.addEventListener('click', function() {
        if (dropdownContent && !dropdownContent.classList.contains('hidden')) {
            dropdownContent.classList.add('hidden');
        }
    });

    // --- 2. KARANLIK MOD ---
    const darkModeToggle = document.getElementById('dark-mode-toggle');
    if (darkModeToggle) {
        darkModeToggle.addEventListener('click', function() {
            document.body.classList.toggle('dark-mode');
            const newTheme = document.body.classList.contains('dark-mode') ? 'dark' : 'light';
            localStorage.setItem('theme', newTheme);
            
            const aiIframe = document.getElementById('ai-assistant-iframe');
            if (aiIframe && aiIframe.contentWindow) {
                aiIframe.contentWindow.postMessage({ type: 'theme-change', theme: newTheme }, '*');
            }
        });
    }

    // --- 3. AI SORGU ASİSTANI MODALI ---
    const queryModal = document.getElementById('ai-assistant-modal');
    const openAiBtn = document.getElementById('open-ai-assistant-btn');
    const closeAiBtn = document.getElementById('close-ai-assistant-btn');
    const aiQueryIframe = document.getElementById('ai-assistant-iframe');

    if (queryModal && openAiBtn && closeAiBtn && aiQueryIframe) {
        openAiBtn.addEventListener('click', () => {
            if (aiQueryIframe.getAttribute('src') === 'about:blank') {
                aiQueryIframe.setAttribute('src', aiQueryIframe.getAttribute('data-src'));
            }
            queryModal.classList.remove('hidden');
        });
        closeAiBtn.addEventListener('click', () => queryModal.classList.add('hidden'));
        queryModal.addEventListener('click', (event) => {
            if (event.target === queryModal) queryModal.classList.add('hidden');
        });
    }
    
    // --- 4. AI SORGULAMA IFRAME İLETİŞİMİ ---
    window.addEventListener('message', function(event) {
        const data = event.data;
        if (data && data.type === 'ai-query-generated' && typeof data.query !== 'undefined') {
            const arananKelimeInput = document.getElementById('arananKelimeInput');
            if (arananKelimeInput) {
                arananKelimeInput.value = data.query;
                arananKelimeInput.focus();
            }
            if (queryModal) queryModal.classList.add('hidden');
        }
    });

    // --- 5. MANUEL AI ÖZET OLUŞTURMA (DÜZELTİLDİ) ---
    const generateBtn = document.getElementById('generate-summary-btn');
    if (generateBtn) {
        generateBtn.addEventListener('click', async function() {
            this.disabled = true;
            const spinner = this.querySelector('.mini-spinner');
            const buttonText = this.querySelector('span');
            if (spinner) spinner.classList.remove('hidden');
            if (buttonText) buttonText.textContent = 'Oluşturuluyor...';
            
            // Karar ID'sini al
            const kararId = this.getAttribute('data-id');
            
            // Karar Metnini al
            const decisionContextEl = document.getElementById('decision-context');
            const hamMetin = decisionContextEl ? decisionContextEl.textContent.trim() : null;

            if (!hamMetin || !kararId) {
                alert('Hata: Özetlenecek karar metni veya Karar ID bulunamadı.');
                this.disabled = false;
                if (spinner) spinner.classList.add('hidden');
                if (buttonText) buttonText.textContent = 'Şimdi Özet Oluştur';
                return;
            }
            
            const formData = new FormData();
            // --- KRİTİK DÜZELTME: Hem ID'yi hem de Metni gönder ---
            formData.append('id', kararId);        // <--- BU SATIR EKLENDİ
            formData.append('ham_metin', hamMetin);

            try {
                const response = await fetch('generate_summary.php', { method: 'POST', body: formData });
                const data = await response.json();
                
                if (data.success && data.aiData) {
                    const container = document.getElementById('ai-summary-container');
                    container.classList.remove('ai-summary-placeholder');
                    container.classList.add('ai-summary-box');
                    
                    // Butonu kaldırıp yerine özeti koy
                    container.innerHTML = `
                        <h4><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-zap"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg> Karar Özeti (Yapay Zeka)</h4>
                        <p>${data.aiData.ozet}</p>
                        <div class="keywords"><strong>Anahtar Kavramlar:</strong> ${data.aiData.anahtar_kelimeler}</div>
                    `;

                    // Veritabanını güncellemek için sessiz istek (Update DB)
                    // Not: generate_summary.php zaten veritabanını güncelliyor olabilir ama
                    // eğer hafif sürüm kullanıyorsanız bu gerekli olabilir.
                    // Şimdilik generate_summary.php'nin işini yaptığı varsayımıyla burayı geçiyoruz.
                    
                } else {
                    throw new Error(data.message || 'Bilinmeyen bir hata oluştu.');
                }
            } catch (error) {
                alert('Hata: ' + error.message);
                this.disabled = false;
                if (spinner) spinner.classList.add('hidden');
                if (buttonText) buttonText.textContent = 'Şimdi Özet Oluştur';
            }
        });
    }

    // --- 6. "AI VERİ SETİ OLUŞTUR" BUTONU ---
    const generateQaBtn = document.getElementById('generate-qa-btn');
    if (generateQaBtn) {
        generateQaBtn.addEventListener('click', async function() {
            if (!confirm("Bu işlem, karar metnini analiz ederek Soru-Cevap çiftleri oluşturacak ve veritabanına kaydedecektir. Devam etmek istiyor musunuz?")) return;
            this.disabled = true;
            this.querySelector('.mini-spinner').classList.remove('hidden');
            
            const kararId = this.getAttribute('data-id');
            const formData = new FormData();
            formData.append('id', kararId);
            try {
                const response = await fetch('analyze_service.php', { method: 'POST', body: formData });
                const data = await response.json();
                alert(data.message);
            } catch (error) {
                alert('Hata: ' + error.message);
            } finally {
                this.disabled = false;
                this.querySelector('.mini-spinner').classList.add('hidden');
            }
        });
    }

    // --- 7. OKUNABİLİRLİK ve DİĞER ARAÇLAR ---
    const kararIcerik = document.querySelector('.karar-icerik');
    if (kararIcerik) {
        const btnDecrease = document.getElementById('font-decrease');
        const btnIncrease = document.getElementById('font-increase');
        const btnCopyLink = document.getElementById('copy-link');
        const changeFontSize = (amount) => {
            const elements = kararIcerik.querySelectorAll('p, h2, h3, h4, div');
            elements.forEach(el => {
                const currentSize = parseFloat(window.getComputedStyle(el, null).getPropertyValue('font-size'));
                el.style.fontSize = (currentSize + amount) + 'px';
            });
        };
        if (btnDecrease) btnDecrease.addEventListener('click', () => changeFontSize(-1));
        if (btnIncrease) btnIncrease.addEventListener('click', () => changeFontSize(1));
        if (btnCopyLink) {
            const originalIcon = btnCopyLink.innerHTML;
            btnCopyLink.addEventListener('click', function() {
                navigator.clipboard.writeText(window.location.href).then(() => {
                    this.innerHTML = 'Kopyalandı!';
                    setTimeout(() => { this.innerHTML = originalIcon; }, 2000);
                });
            });
        }
    }
    const backToTopButton = document.querySelector('.back-to-top');
    if (backToTopButton) {
        window.addEventListener('scroll', () => {
            if (window.pageYOffset > 300) {
                backToTopButton.classList.add('visible');
            } else {
                backToTopButton.classList.remove('visible');
            }
        });
        backToTopButton.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
    }
});