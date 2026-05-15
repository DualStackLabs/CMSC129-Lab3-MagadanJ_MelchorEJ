import './bootstrap';

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;')
        .replace(/\n/g, '<br>');
}

function appendMessage(box, message, sender = 'assistant') {
    const isUser = sender === 'user';
    const classes = isUser
        ? 'bg-[#e22161] text-white p-3 rounded-2xl rounded-br-none max-w-[85%] ml-auto text-sm shadow-sm'
        : 'bg-white border border-pink-50 p-3 rounded-2xl rounded-bl-none max-w-[85%] text-sm text-slate-700 shadow-sm';

    box.insertAdjacentHTML('beforeend', `<div class="${classes}">${escapeHtml(message)}</div>`);
    box.scrollTop = box.scrollHeight;
}

async function loadChatHistory() {
    const chatWindow = document.getElementById('chat-window');
    const box = document.getElementById('chat-box');
    const historyRoute = chatWindow?.dataset.historyRoute;

    if (!box || !historyRoute) return;

    try {
        const response = await fetch(historyRoute, {
            headers: { Accept: 'application/json' },
        });
        const data = await response.json();
        const messages = Array.isArray(data.messages) ? data.messages : [];

        if (messages.length === 0) return;

        box.innerHTML = '';
        messages.forEach((message) => {
            appendMessage(box, message.content, message.role === 'user' ? 'user' : 'assistant');
        });
    } catch (error) {
        // History is helpful, but the chat should still work if loading it fails.
    }
}

function appendConfirmationControls(box, chatRoute, csrfToken) {
    const id = 'confirm-' + Date.now();

    box.insertAdjacentHTML('beforeend', `
        <div id="${id}" class="flex gap-2 text-xs">
            <button type="button" class="bg-emerald-600 text-white px-3 py-2 rounded-lg font-bold hover:bg-emerald-700">Confirm</button>
            <button type="button" class="bg-slate-200 text-slate-700 px-3 py-2 rounded-lg font-bold hover:bg-slate-300">Cancel</button>
        </div>
    `);

    const controls = document.getElementById(id);
    const [confirmButton, cancelButton] = controls.querySelectorAll('button');
    confirmButton.addEventListener('click', () => {
        controls.remove();
        window.sendChat(chatRoute, csrfToken, 'yes');
    });
    cancelButton.addEventListener('click', () => {
        controls.remove();
        window.sendChat(chatRoute, csrfToken, 'cancel');
    });
}

window.toggleChat = function toggleChat() {
    const chatWindow = document.getElementById('chat-window');
    const toggleIcon = document.getElementById('toggle-icon');

    if (chatWindow.classList.contains('hidden')) {
        chatWindow.classList.remove('hidden');
        toggleIcon.className = 'ph ph-caret-double-down text-2xl';
    } else {
        chatWindow.classList.add('hidden');
        toggleIcon.className = 'ph-fill ph-chats-teardrop text-2xl';
    }
};

window.sendChat = async function sendChat(chatRoute, csrfToken, presetMessage = null) {
    const input = document.getElementById('chat-input');
    const box = document.getElementById('chat-box');
    const message = (presetMessage ?? input.value).trim();

    if (!message) return;

    appendMessage(box, message, 'user');

    if (!presetMessage) {
        input.value = '';
    }

    const loadingId = 'load-' + Date.now();
    box.insertAdjacentHTML('beforeend', `<div id="${loadingId}" class="flex items-center gap-2 text-slate-400 text-xs animate-pulse italic"><i class="ph ph-sparkle animate-spin"></i> Thinking...</div>`);
    box.scrollTop = box.scrollHeight;

    try {
        const response = await fetch(chatRoute, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ message: message }),
        });

        const data = await response.json();
        document.getElementById(loadingId)?.remove();
        appendMessage(box, data.response || 'I did not receive a response.');

        if (data.requires_confirmation) {
            appendConfirmationControls(box, chatRoute, csrfToken);
        }

        if (data.refresh) {
            setTimeout(() => window.location.reload(), 900);
        }
    } catch (error) {
        const loading = document.getElementById(loadingId);
        if (loading) {
            loading.innerText = 'Connection failed. Please try again.';
        }
    }
};

document.addEventListener('DOMContentLoaded', loadChatHistory);
