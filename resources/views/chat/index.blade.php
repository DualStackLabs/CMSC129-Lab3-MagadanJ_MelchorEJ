@extends('layouts.app')

@section('content')
<div class="bg-white rounded-3xl border border-pink-100 shadow-2xl overflow-hidden h-[calc(100vh-150px)] flex flex-col">
    <div class="bg-[#e22161] p-6 text-white flex items-center justify-between">
        <div class="flex items-center gap-4">
            <div class="bg-white/20 p-3 rounded-2xl">
                <i class="ph-fill ph-robot text-3xl"></i>
            </div>
            <div>
                <h2 class="text-2xl font-bold tracking-tight">AI Journal Assistant</h2>
                <p class="text-pink-100 text-sm">Dedicated Workspace</p>
            </div>
        </div>
        <i class="ph ph-sparkle text-3xl opacity-50"></i>
    </div>
    
    <div id="main-chat-box" class="flex-1 overflow-y-auto p-8 space-y-6 bg-slate-50/50">
        <div class="bg-pink-100 text-pink-800 p-5 rounded-2xl rounded-bl-none max-w-[75%] shadow-sm">
            <p class="font-semibold mb-1">Daily Draft AI:</p>
            Welcome to the full workspace! I can help you summarize entries, find patterns in your mood, or brainstorm new ideas. What's on your mind?
        </div>
    </div>

    <div class="p-6 bg-white border-t border-slate-100">
        <div class="max-w-4xl mx-auto flex gap-4">
            <input type="text" id="main-chat-input" 
                   onkeypress="if(event.key === 'Enter') sendMainChat()"
                   placeholder="e.g., Summarize my productivity last week..." 
                   class="flex-1 border border-slate-200 rounded-2xl px-6 py-4 outline-none focus:ring-2 focus:ring-pink-400 text-lg shadow-inner">
            <button onclick="sendMainChat()" class="bg-[#e22161] text-white px-10 rounded-2xl hover:bg-[#ce0d4d] transition-all font-bold shadow-lg flex items-center gap-2">
                <span>Ask AI</span>
                <i class="ph ph-paper-plane-tilt text-xl"></i>
            </button>
        </div>
    </div>
</div>

<script>
function escapeMainChatHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;')
        .replace(/\n/g, '<br>');
}

function appendMainChatConfirmation(box) {
    const id = 'main-confirm-' + Date.now();
    box.insertAdjacentHTML('beforeend', `
        <div id="${id}" class="flex gap-2 text-sm">
            <button type="button" class="bg-emerald-600 text-white px-4 py-2 rounded-xl font-bold hover:bg-emerald-700">Confirm</button>
            <button type="button" class="bg-slate-200 text-slate-700 px-4 py-2 rounded-xl font-bold hover:bg-slate-300">Cancel</button>
        </div>
    `);

    const controls = document.getElementById(id);
    const [confirmButton, cancelButton] = controls.querySelectorAll('button');
    confirmButton.addEventListener('click', () => {
        controls.remove();
        sendMainChat('yes');
    });
    cancelButton.addEventListener('click', () => {
        controls.remove();
        sendMainChat('cancel');
    });
}

function appendMainChatMessage(box, message, sender = 'assistant') {
    const isUser = sender === 'user';
    const wrapper = isUser ? 'flex justify-end' : '';
    const bubble = isUser
        ? 'bg-[#e22161] text-white p-5 rounded-2xl rounded-br-none max-w-[75%] shadow-md'
        : 'bg-white border border-pink-100 p-5 rounded-2xl rounded-bl-none max-w-[75%] shadow-sm text-slate-700 leading-relaxed';
    const label = isUser
        ? ''
        : '<p class="font-bold text-pink-600 mb-2 flex items-center gap-2"><i class="ph ph-sparkle"></i> Assistant:</p>';

    box.insertAdjacentHTML('beforeend', `
        <div class="${wrapper}">
            <div class="${bubble}">
                ${label}
                ${escapeMainChatHtml(message)}
            </div>
        </div>
    `);
    box.scrollTop = box.scrollHeight;
}

async function loadMainChatHistory() {
    const box = document.getElementById('main-chat-box');

    try {
        const response = await fetch('{{ route('chat.history') }}', {
            headers: { Accept: 'application/json' },
        });
        const data = await response.json();
        const messages = Array.isArray(data.messages) ? data.messages : [];

        if (messages.length === 0) return;

        box.innerHTML = '';
        messages.forEach((message) => {
            appendMainChatMessage(box, message.content, message.role === 'user' ? 'user' : 'assistant');
        });
    } catch (e) {
        // The workspace can still send messages even if saved history cannot load.
    }
}

async function sendMainChat() {
    const input = document.getElementById('main-chat-input');
    const box = document.getElementById('main-chat-box');
    const presetMessage = arguments[0] || null;
    const message = (presetMessage || input.value).trim();
    if(!message) return;

    appendMainChatMessage(box, message, 'user');
    
    if (!presetMessage) {
        input.value = '';
    }
    box.scrollTop = box.scrollHeight;

    // Loading
    const loadingId = 'main-loading-' + Date.now();
    box.innerHTML += `
        <div id="${loadingId}" class="flex items-center gap-3 text-slate-400 italic py-2">
            <i class="ph ph-circle-notch animate-spin text-2xl text-pink-500"></i>
            <span>Consulting your journal entries...</span>
        </div>`;
    box.scrollTop = box.scrollHeight;

    try {
        const response = await fetch('{{ route('chat.send') }}', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json', 
                'X-CSRF-TOKEN': '{{ csrf_token() }}' 
            },
            body: JSON.stringify({ message: message })
        });

        const data = await response.json();
        document.getElementById(loadingId).remove();
        
        appendMainChatMessage(box, data.response || 'I did not receive a response.');

        if (data.requires_confirmation) {
            appendMainChatConfirmation(box);
        }

        if (data.refresh) {
            setTimeout(() => window.location.reload(), 900);
        }
    } catch(e) {
        document.getElementById(loadingId).innerHTML = '<span class="text-red-500">Sorry, something went wrong. Check your API key.</span>';
    }
    box.scrollTop = box.scrollHeight;
}

document.addEventListener('DOMContentLoaded', loadMainChatHistory);
</script>
@endsection
