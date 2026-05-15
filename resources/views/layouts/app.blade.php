<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Draft | Personal Journal</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    
    <script>
        tailwind.config = {
            safelist: [
                'bg-indigo-50', 'text-indigo-600', 'bg-blue-50', 'text-blue-600',
                'bg-pink-50', 'text-pink-600', 'bg-emerald-50', 'text-emerald-600',
                'bg-amber-50', 'text-amber-600', 'bg-slate-50', 'text-slate-600'
            ]
        }
    </script>
</head>

<body class="bg-white text-[#333] font-sans antialiased flex h-screen overflow-hidden">

    <aside class="w-64 bg-white border-r border-slate-200 h-full p-6 flex flex-col justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight mb-8">Daily Draft</h1>
            <a onclick="window.location.replace('/entries/create')" class="cursor-pointer block w-full bg-[#e22161] hover:bg-[#ce0d4d] text-white text-center font-semibold py-3 rounded-xl shadow-sm transition mb-8">
                + New Entry
            </a>
            <nav class="space-y-2">
                <a href="/entries" class="flex items-center px-3 py-2 rounded-lg transition font-medium {{ request()->is('entries') && !request()->has('is_favorite') ? 'text-[#e22161] bg-pink-50' : 'text-slate-600 hover:text-[#e22161] hover:bg-pink-50' }}">
                    <i class="ph ph-books mr-3 text-xl"></i> All Entries
                </a>
                <a href="/entries?is_favorite=1" class="flex items-center px-3 py-2 rounded-lg transition font-medium {{ request()->get('is_favorite') == '1' ? 'text-[#e22161] bg-pink-50' : 'text-slate-600 hover:text-[#e22161] hover:bg-pink-50' }}">
                    <i class="ph ph-star mr-3 text-xl"></i> Favorites
                </a>
                <a href="/entries/trash" class="flex items-center px-3 py-2 rounded-lg transition font-medium {{ request()->is('entries/trash') ? 'text-[#e22161] bg-pink-50' : 'text-slate-600 hover:text-[#e22161] hover:bg-pink-50' }}">
                    <i class="ph ph-trash mr-3 text-xl"></i> Trash Bin
                </a>
                <div class="pt-4 mt-4 border-t border-slate-100">
                    <a href="/chat" class="flex items-center px-3 py-2 rounded-lg transition font-medium {{ request()->is('chat') ? 'text-[#e22161] bg-pink-50' : 'text-slate-600 hover:text-[#e22161] hover:bg-pink-50' }}">
                        <i class="ph ph-sparkle mr-3 text-xl"></i> AI Workspace
                    </a>
                </div>
            </nav>
        </div>
    </aside>

    <main class="flex-1 h-full overflow-y-auto p-10 bg-slate-50/30">
        <div class="max-w-4xl mx-auto">
            @yield('content')
        </div>
    </main>
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/chatbot.js'])
    @include('chat.components.chat-widget')

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        /**
         * Because Vite "encapsulates" code, we use 'window.' inside 
         * resources/js/chatbot.js so these functions are reachable here.
         */
        function handleChatSend() {
            if (typeof window.sendChat === 'function') {
                window.sendChat("{{ route('chat.send') }}", "{{ csrf_token() }}");
            } else {
                console.error("sendChat not found. Make sure 'npm run dev' is running!");
            }
        }

        @if(session('success'))
            // Assumes showToast is attached to window in resources/js/app.js
            if (typeof window.showToast === 'function') {
                window.showToast("{{ session('success') }}");
            }
        @endif
    </script>
</body>
</html>
