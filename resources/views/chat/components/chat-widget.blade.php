<button id="chat-toggle-btn" onclick="toggleChat()" class="fixed bottom-6 right-4 sm:right-6 bg-[#e22161] text-white p-4 rounded-full shadow-2xl z-50 hover:scale-110 transition-all flex items-center justify-center">
    <i id="toggle-icon" class="ph-fill ph-chats-teardrop text-2xl"></i>
</button>

<div id="chat-window" data-history-route="{{ route('chat.history') }}" class="hidden fixed bottom-24 left-4 right-4 sm:left-auto sm:right-6 sm:w-[350px] bg-white border border-pink-100 shadow-2xl rounded-2xl z-50 flex flex-col overflow-hidden transition-all duration-300">
    
    <div class="bg-[#e22161] p-4 text-white font-bold flex justify-between items-center">
        <div class="flex items-center gap-2">
            <i class="ph ph-robot text-xl"></i>
            <span>Daily Draft AI</span>
        </div>
        <button onclick="toggleChat()" class="hover:bg-pink-500 p-1 rounded-md transition-colors">
            <i class="ph ph-x-circle text-2xl"></i>
        </button>
    </div>
    
    <div class="px-4 py-3 bg-white border-b border-slate-100">
        <label for="chat-mode" class="sr-only">AI mode</label>
        <select id="chat-mode" class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-pink-400">
            <option value="query" selected>Query only</option>
            <option value="crud">CRUD Assistant</option>
        </select>
    </div>

    <div id="chat-box" class="h-80 overflow-y-auto p-4 space-y-4 bg-slate-50">
        <div class="bg-pink-100 text-pink-800 p-3 rounded-2xl rounded-bl-none max-w-[85%] text-sm">
            Hi! I'm your AI assistant. Ask me anything about your journal entries!
        </div>
    </div>

    <div class="p-4 bg-white border-t border-slate-100 flex gap-2">
        <input type="text" id="chat-input" 
            onkeypress="if(event.key === 'Enter') handleChatSend()"
            placeholder="Ask about your entries..." 
            class="flex-1 border border-slate-200 rounded-xl px-4 py-2 text-sm outline-none focus:ring-2 focus:ring-pink-400">
        
        <button onclick="handleChatSend()" 
                class="bg-[#e22161] text-white p-2 px-4 rounded-xl hover:bg-[#ce0d4d] transition-colors shadow-sm">
            <i class="ph-bold ph-paper-plane-right"></i>
        </button>
    </div>
</div>
