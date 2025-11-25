/**
 * ai.js - Gelişmiş AI Sohbet Asistanı Mantığı v3.2 (Markdown Destekli)
 * 
 * Bu sürüm, Showdown.js kütüphanesini kullanarak, AI asistanından gelen
 * Markdown formatındaki yanıtları zengin HTML olarak ekrana basar.
 */

document.addEventListener('DOMContentLoaded', function() {

    // --- Element Seçimleri ---
    const chatBubble = document.getElementById('chat-bubble');
    const chatContainer = document.getElementById('chat-container');
    const closeChatBtn = document.getElementById('close-chat-btn');
    const resetChatBtn = document.getElementById('reset-chat-btn');
    const chatMessages = document.getElementById('chat-messages');
    const sampleQuestionsContainer = document.getElementById('sample-questions-container');
    const chatInput = document.getElementById('chat-input');
    const chatSendBtn = document.getElementById('chat-send-btn');
    const decisionContextEl = document.getElementById('decision-context');
    const chatLoader = document.querySelector('.chat-loader-container');
    const summarizeBtn = document.getElementById('summarize-decision-btn');
    
    // Eğer sohbet elementleri sayfada yoksa, script'i çalıştırma.
    if (!chatBubble || !chatContainer) {
        return;
    }

    // --- Markdown Dönüştürücüsünü Başlat ---
    // Bu, chat_component.php'ye eklediğimiz Showdown.js kütüphanesini kullanır.
    const markdownConverter = new showdown.Converter();

    // --- Örnek Sorular Verisi ---
    const sampleQuestions = {
        general: [ "Yargıtay nedir?", "Boşanma davası nasıl açılır?", "Temyiz süresi ne kadar?" ],
        decision: [ "Karardaki taraflar kimlerdir?", "Uygulanan kanun maddeleri nelerdir?", "Davanın sonucu ne olmuş?" ]
    };
    
    // --- Olay Dinleyicileri (Event Listeners) ---
    chatBubble.addEventListener('click', toggleChat);
    closeChatBtn.addEventListener('click', () => chatContainer.classList.add('hidden'));
    resetChatBtn.addEventListener('click', resetChat);
    chatSendBtn.addEventListener('click', sendMessage);
    chatInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });
    if (summarizeBtn) {
        summarizeBtn.addEventListener('click', handleSummarizeClick);
    }

    // --- Ana Fonksiyonlar ---

    function toggleChat() {
        chatContainer.classList.toggle('hidden');
        if (!chatContainer.classList.contains('hidden')) {
            loadChatHistory();
            displayContextualButtons();
            chatInput.focus();
        }
    }

    function displayContextualButtons() {
        if (!sampleQuestionsContainer || !summarizeBtn) return;
        const context = decisionContextEl ? decisionContextEl.textContent.trim() : null;
        const onDecisionPage = context && context.length > 0;
        const questions = onDecisionPage ? sampleQuestions.decision : sampleQuestions.general;
        sampleQuestionsContainer.innerHTML = '';
        questions.forEach(q => {
            const btn = document.createElement('button');
            btn.className = 'sample-question-btn';
            btn.textContent = q;
            btn.onclick = () => {
                chatInput.value = q;
                sendMessage();
            };
            sampleQuestionsContainer.appendChild(btn);
        });
        if (onDecisionPage) {
            summarizeBtn.classList.remove('hidden');
        } else {
            summarizeBtn.classList.add('hidden');
        }
    }
    
    async function handleSummarizeClick() {
        const userActionText = "Bu kararı detaylı özetle.";
        appendMessage({ role: 'user', content: userActionText });
        setLoading(true);
        const context = decisionContextEl ? decisionContextEl.textContent.trim() : null;
        if (!context) {
            appendMessage({ role: 'assistant', content: 'Özetlenecek bir karar metni bulunamadı.' });
            setLoading(false);
            return;
        }
        const data = await sendRequest('send_message', { message: userActionText, context: context });
        setLoading(false);
        if (data && data.history) {
            const lastMessage = data.history[data.history.length - 1];
            if (lastMessage && lastMessage.role === 'assistant') {
                appendMessage(lastMessage);
            }
        }
    }

    async function sendMessage() {
        const userMessage = chatInput.value.trim();
        if (!userMessage) return;
        appendMessage({ role: 'user', content: userMessage });
        chatInput.value = '';
        setLoading(true);
        const context = decisionContextEl ? decisionContextEl.textContent.trim() : null;
        const data = await sendRequest('send_message', { message: userMessage, context: context });
        setLoading(false);
        if (data && data.history) {
            const lastMessage = data.history[data.history.length - 1];
            if (lastMessage && lastMessage.role === 'assistant') {
                appendMessage(lastMessage);
            }
        }
    }

    // --- Yardımcı Fonksiyonlar ---

    async function sendRequest(action, data = {}) {
        try {
            const response = await fetch('chat_service.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action, ...data })
            });
            if (!response.ok) throw new Error('Sunucu hatası: ' + response.statusText);
            return await response.json();
        } catch (error) {
            console.error("Sohbet servisi hatası:", error);
            appendMessage({ role: 'assistant', content: 'Üzgünüm, bir bağlantı hatası oluştu.' });
            return null;
        }
    }

    async function loadChatHistory() {
        setLoading(true);
        const data = await sendRequest('get_history');
        if (data && data.history) {
            renderMessages(data.history);
        }
        setLoading(false);
    }

    async function resetChat() {
        setLoading(true);
        const data = await sendRequest('reset_chat');
        if (data && data.history) {
            renderMessages(data.history);
            displayContextualButtons();
        }
        setLoading(false);
    }

    function renderMessages(history) {
        chatMessages.innerHTML = '';
        const loaderDiv = '<div class="chat-loader-container hidden"><div class="chat-loader"></div></div>';
        const sampleDiv = '<div id="sample-questions-container" class="sample-questions-container"></div>';
        chatMessages.insertAdjacentHTML('beforeend', sampleDiv);
        chatMessages.insertAdjacentHTML('beforeend', loaderDiv);
        history.forEach(msg => appendMessage(msg));
    }

    /**
     * GÜNCELLENMİŞ FONKSİYON: Tek bir mesaj objesini alır ve HTML olarak sohbet penceresine ekler.
     * Asistan mesajlarını Markdown'dan HTML'e dönüştürür.
     */
    function appendMessage(message) {
        if (sampleQuestionsContainer) {
            sampleQuestionsContainer.innerHTML = '';
        }
        const messageDiv = document.createElement('div');
        messageDiv.classList.add('chat-message', message.role);

        // EĞER MESAJ ASİSTANDAN GELİYORSA, Markdown'ı HTML'e dönüştür
        if (message.role === 'assistant' && message.content) {
            // Showdown kütüphanesini kullanarak dönüştür
            const htmlContent = markdownConverter.makeHtml(message.content);
            // textContent yerine innerHTML kullanarak HTML'i bas
            messageDiv.innerHTML = htmlContent; 
            
            // Kopyalama butonu oluştur
            const copyBtn = document.createElement('button');
            copyBtn.className = 'copy-chat-btn';
            copyBtn.title = 'Yanıtı Kopyala';
            const copyIconSVG = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>';
            copyBtn.innerHTML = copyIconSVG;

            copyBtn.addEventListener('click', function(event) {
                event.stopPropagation();
                // Orijinal Markdown metnini kopyala, HTML'i değil.
                navigator.clipboard.writeText(message.content)
                    .then(() => {
                        copyBtn.innerHTML = 'Kopyalandı!';
                        copyBtn.classList.add('copied-text');
                        setTimeout(() => {
                            copyBtn.innerHTML = copyIconSVG;
                            copyBtn.classList.remove('copied-text');
                        }, 2000);
                    }).catch(err => {
                        console.error('Kopyalama başarısız oldu:', err);
                    });
            });
            messageDiv.appendChild(copyBtn);

        } else {
            // Kullanıcı mesajları için normal metin olarak ekle
            messageDiv.textContent = message.content;
        }

        const loaderContainer = chatMessages.querySelector('.chat-loader-container');
        chatMessages.insertBefore(messageDiv, loaderContainer);
        scrollToBottom();
        return messageDiv;
    }

    function setLoading(isLoading) {
        chatInput.disabled = isLoading;
        chatSendBtn.disabled = isLoading;
        const loaderContainer = chatMessages.querySelector('.chat-loader-container');
        if (isLoading) {
            loaderContainer.classList.remove('hidden');
            scrollToBottom();
        } else {
            loaderContainer.classList.add('hidden');
            chatInput.focus();
        }
    }

    function scrollToBottom() {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
});