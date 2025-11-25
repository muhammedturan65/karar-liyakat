/**
 * profile.js - Profil ve Hesap Ayarları Sayfası İşlevleri v2.0
 * 
 * Bu dosya, AJAX kullanarak profil bilgilerini, şifreyi ve
 * 2FA (İki Faktörlü Koruma) ayarlarını güncellemeyi yönetir.
 * 2FA formu, başarılı işlem sonrası sayfayı yenilemek yerine arayüzü
 * anında güncelleyerek önbellek sorunlarını çözer.
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // Gerekli formları ve bildirim alanını seç
    const profileForm = document.getElementById('profile-form');
    const passwordForm = document.getElementById('password-form');
    const twoFactorForm = document.getElementById('2fa-form');
    const responseDiv = document.getElementById('response-message');

    /**
     * Profil ve Şifre formları için genel form gönderme işleyicisi.
     * Sayfayı yenilemeden sonucu bir bildirim kutusunda gösterir.
     * @param {HTMLFormElement} form - İşlenecek form elementi.
     * @param {Event} event - Submit olayı.
     */
    const handleAjaxFormSubmit = async (form, event) => {
        event.preventDefault();
        responseDiv.innerHTML = ''; // Önceki mesajları temizle

        const formData = new FormData(form);
        const button = form.querySelector('button[type="submit"]');
        const originalButtonText = button.textContent;

        button.disabled = true;
        button.textContent = 'İşleniyor...';

        try {
            const response = await fetch('profile_actions.php', { method: 'POST', body: formData });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Bilinmeyen bir sunucu hatası oluştu.');
            
            responseDiv.innerHTML = `<div class="form-success">${data.message}</div>`;
            if (form.id === 'password-form') {
                form.reset();
            }

        } catch (error) {
            responseDiv.innerHTML = `<div class="error">${error.message}</div>`;
        } finally {
            button.disabled = false;
            button.textContent = originalButtonText;
        }
    };

    /**
     * 2FA formu için özel form gönderme işleyicisi.
     * Bu işleyici, işlem başarılı olduğunda sayfayı yenilemek yerine
     * arayüzü anında, manuel olarak günceller.
     * @param {HTMLFormElement} form - İşlenecek 2FA formu.
     * @param {Event} event - Submit olayı.
     */
    const handle2faFormSubmit = async (form, event) => {
        event.preventDefault();
        responseDiv.innerHTML = '';
        const formData = new FormData(form);
        const button = form.querySelector('button[type="submit"]');
        
        button.disabled = true;

        try {
            const response = await fetch('profile_actions.php', { method: 'POST', body: formData });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message);
            
            // --- ARAYÜZ GÜNCELLEME MANTIĞI ---
            responseDiv.innerHTML = `<div class="form-success">${data.message}</div>`;
            
            const statusText = form.querySelector('strong');
            const statusInput = form.querySelector('input[name="status"]');
            const newStatus = formData.get('status'); // '1' (etkinleştir) veya '0' (devre dışı bırak)

            if (newStatus === '1') {
                // ETKİNLEŞTİRME BAŞARILI
                statusText.textContent = 'Aktif';
                statusText.className = 'status-active';
                button.textContent = 'Devre Dışı Bırak';
                button.className = 'btn-danger';
                statusInput.value = '0'; // Bir sonraki tıklama 'devre dışı bırak' (0) olacak
            } else {
                // DEVRE DIŞI BIRAKMA BAŞARILI
                statusText.textContent = 'Pasif';
                statusText.className = 'status-inactive';
                button.textContent = 'Etkinleştir';
                button.className = 'btn-success';
                statusInput.value = '1'; // Bir sonraki tıklama 'etkinleştir' (1) olacak
            }

        } catch (error) {
            responseDiv.innerHTML = `<div class="error">${error.message}</div>`;
        } finally {
            button.disabled = false;
        }
    };

    // --- Olay Dinleyicileri ---
    if (profileForm) {
        profileForm.addEventListener('submit', (e) => handleAjaxFormSubmit(profileForm, e));
    }
    if (passwordForm) {
        passwordForm.addEventListener('submit', (e) => handleAjaxFormSubmit(passwordForm, e));
    }
    if (twoFactorForm) {
        twoFactorForm.addEventListener('submit', (e) => handle2faFormSubmit(twoFactorForm, e));
    }
});