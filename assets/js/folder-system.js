// assets/js/folder-system.js

document.addEventListener('DOMContentLoaded', function() {
    const saveBtn = document.getElementById('save-to-folder-btn');
    const folderModal = document.getElementById('folder-modal');
    const closeFolderModalBtn = document.getElementById('close-folder-modal-btn');
    const folderListDiv = document.getElementById('folder-list');
    const newFolderForm = document.getElementById('new-folder-form');

    if (!saveBtn || !folderModal) return;

    saveBtn.addEventListener('click', async () => {
        folderModal.classList.remove('hidden');
        folderListDiv.innerHTML = '<div class="mini-spinner" style="margin: 20px auto;"></div>';

        const response = await fetch('folder_actions.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=get_folders'
        });
        const data = await response.json();

        folderListDiv.innerHTML = '';
        if (data.success && data.folders.length > 0) {
            // YENİ: Butonları sarmalamak için bir div oluştur
            const buttonWrapper = document.createElement('div');
            buttonWrapper.className = 'folder-buttons-wrapper';

            data.folders.forEach(folder => {
                const folderBtn = document.createElement('button');
                folderBtn.textContent = folder.folder_name;
                folderBtn.className = 'folder-select-btn';
                folderBtn.type = 'button'; // Formun gönderilmesini engelle
                folderBtn.onclick = () => saveDecision(folder.id);
                buttonWrapper.appendChild(folderBtn);
            });
            folderListDiv.appendChild(buttonWrapper);
        } else {
            folderListDiv.innerHTML = '<p style="text-align:center; color: #6c757d;">Henüz hiç klasör oluşturmadınız.</p>';
        }
    });

    closeFolderModalBtn.addEventListener('click', () => folderModal.classList.add('hidden'));
    folderModal.addEventListener('click', (e) => {
        if (e.target === folderModal) folderModal.classList.add('hidden');
    });

    newFolderForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const newFolderName = document.getElementById('new-folder-name').value.trim();
        if (newFolderName) createAndSave(newFolderName);
    });

    async function saveDecision(folderId) {
        const decisionId = new URLSearchParams(window.location.search).get('id');
        const formData = `action=save_decision&folder_id=${folderId}&decision_id=${decisionId}`;
        const response = await fetch('folder_actions.php', { method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: formData });
        const data = await response.json();
        alert(data.message);
        if (data.success) folderModal.classList.add('hidden');
    }

    async function createAndSave(folderName) {
        const decisionId = new URLSearchParams(window.location.search).get('id');
        const formData = `action=create_and_save&folder_name=${encodeURIComponent(folderName)}&decision_id=${decisionId}`;
        const response = await fetch('folder_actions.php', { method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: formData });
        const data = await response.json();
        alert(data.message);
        if (data.success) folderModal.classList.add('hidden');
    }
});
// assets/js/folder-system.js dosyasının sonuna ekleyin

document.addEventListener('DOMContentLoaded', function() {
    // --- my_folders.php sayfasına özel mantık ---
    
    // "Klasörden Çıkar" butonları
    document.querySelectorAll('.remove-decision-btn').forEach(button => {
        button.addEventListener('click', async function() {
            if (!confirm("Bu kararı klasörden çıkarmak istediğinizden emin misiniz?")) return;
            
            const savedId = this.getAttribute('data-saved-id');
            const formData = `action=remove_decision&saved_id=${savedId}`;
            
            const response = await fetch('folder_actions.php', { method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: formData });
            const data = await response.json();

            if (data.success) {
                // Başarılı olursa, o liste elemanını animasyonla kaldır
                document.getElementById(`saved-decision-${savedId}`).style.opacity = '0';
                setTimeout(() => {
                    document.getElementById(`saved-decision-${savedId}`).remove();
                }, 500);
            } else {
                alert("Hata: " + data.message);
            }
        });
    });

    // "Klasörü Sil" butonları
    document.querySelectorAll('.delete-folder-btn').forEach(button => {
        button.addEventListener('click', async function(event) {
            event.stopPropagation(); // Linke tıklamayı engelle
            if (!confirm("Bu klasörü ve içindeki TÜM kayıtlı kararları kalıcı olarak silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.")) return;

            const folderId = this.getAttribute('data-folder-id');
            const formData = `action=delete_folder&folder_id=${folderId}`;

            const response = await fetch('folder_actions.php', { method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: formData });
            const data = await response.json();

            if (data.success) {
                // Başarılı olursa, sayfayı yeniden yükleyerek güncel listeyi göster
                window.location.reload();
            } else {
                alert("Hata: " + data.message);
            }
        });
    });
});