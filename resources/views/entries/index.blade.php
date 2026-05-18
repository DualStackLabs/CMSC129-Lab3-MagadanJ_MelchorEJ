@extends('layouts.app')

@section('content')

<div class="bg-white p-4 rounded-2xl border border-pink-100 shadow-lg shadow-pink-200/40 mb-8 flex gap-3">
   <form action="/entries" method="GET" class="flex-1 flex gap-3">
        <input type="text" name="search" placeholder="Search your thoughts..." value="{{ request('search') }}" 
            class="flex-1 border border-slate-200 bg-slate-50 rounded-xl px-4 py-2 focus:ring-2 focus:ring-[#e22161] outline-none text-slate-700 transition-all">
        
        <select name="category_id" class="border border-slate-200 bg-slate-50 rounded-xl px-4 py-2 focus:ring-2 focus:ring-[#e22161] outline-none text-slate-700 cursor-pointer transition-all">
            <option value="">Any Category</option>
            @foreach($categories as $category)
                <option value="{{ $category->id }}" {{ request('category_id') == $category->id ? 'selected' : '' }}>
                    {{ $category->name }}
                </option>
            @endforeach
        </select>
        
        <select name="mood" class="border border-slate-200 bg-slate-50 rounded-xl px-4 py-2 focus:ring-2 focus:ring-[#e22161] outline-none text-slate-700 cursor-pointer transition-all">
            <option value="">Any Mood</option>
            @foreach($moods as $mood)
                <option value="{{ $mood }}" {{ request('mood') == $mood ? 'selected' : '' }}>
                    {{ $mood }}
                </option>
            @endforeach
        </select>
        
        <button type="submit" class="bg-[#e22161] text-white px-6 py-2 rounded-xl hover:opacity-90 transition font-medium shadow-md shadow-pink-200/50">
            Find
        </button>
    </form>
</div>

@if(session('success'))
    <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl mb-6">
        {{ session('success') }}
    </div>
@endif

<div class="space-y-6">
    <h2 class="text-3xl font-extrabold text-[#333] mb-6">Your Entries</h2>

    @forelse($entries as $entry)
        <article class="bg-white p-6 rounded-2xl border border-pink-100 shadow-xl shadow-pink-200/40 hover:shadow-2xl hover:shadow-pink-300/50 transition-all duration-300 group">
            <div class="flex justify-between items-start mb-3">
                <div class="flex items-center gap-3">
                    @if($entry->is_favorite)
                        <span class="text-yellow-400 text-lg" title="Favorite">⭐</span>
                    @endif
                    <h3 class="text-xl font-bold text-[#333]">{{ $entry->title }}</h3>
                </div>
                <span class="text-sm font-medium text-slate-400 bg-[#fff0f5] px-3 py-1 rounded-full">
                    {{ $entry->created_at->format('M d, Y') }}
                </span>
            </div>

            @if($entry->image)
                <div class="mb-4 overflow-hidden rounded-xl border border-slate-100">
                    <img src="{{ asset('storage/' . $entry->image) }}" 
                        class="w-full h-48 object-cover group-hover:scale-105 transition-transform duration-500">
                </div>
            @endif
            
            <p class="text-slate-600 leading-relaxed mb-4 line-clamp-2">
                {{ $entry->content }}
            </p>
            
            <div class="flex flex-wrap gap-4 mt-3 text-sm text-slate-500 font-medium">
                @php
                    $theme = $entry->category->color_theme ?? 'slate';
                    $bgClass = match($theme) {
                        'indigo' => 'bg-indigo-50 text-indigo-600',
                        'blue' => 'bg-blue-50 text-blue-600',
                        'pink' => 'bg-pink-50 text-pink-600',
                        'emerald' => 'bg-emerald-50 text-emerald-600',
                        'amber' => 'bg-amber-50 text-amber-600',
                        default => 'bg-slate-50 text-slate-600',
                    };
                @endphp
            
                <span class="flex items-center gap-1.5 {{ $bgClass }} px-2 py-1 rounded-md font-bold">
                    <i class="ph ph-folder text-lg"></i> {{ $entry->category->name ?? 'Uncategorized' }}
                </span>
                <span class="flex items-center gap-1.5">
                    <i class="ph ph-smiley text-lg text-pink-400"></i> {{ $entry->mood }}
                </span>
                @if($entry->location)
                    <span class="flex items-center gap-1.5">
                        <i class="ph ph-map-pin text-lg text-pink-400"></i> {{ $entry->location }}
                    </span>
                @endif
            </div>
                
            <div class="flex justify-end gap-3 opacity-0 group-hover:opacity-100 transition-opacity mt-4">
                <a onclick="window.location.replace('/entries/{{ $entry->id }}/edit')" 
                class="cursor-pointer bg-indigo-50 text-indigo-600 hover:bg-indigo-100 hover:text-indigo-700 px-5 py-2.5 rounded-xl text-sm font-bold transition-colors">
                    Edit
                </a>
                
                <button type="button" 
                        onclick="confirmAction(this, 'Move this to trash?')" 
                        class="bg-red-50 text-red-600 hover:bg-red-100 hover:text-red-700 px-5 py-2.5 rounded-xl text-sm font-bold transition-colors">
                    Delete
                </button>
                <form action="/entries/{{ $entry->id }}" method="POST" class="hidden">
                    @csrf
                    @method('DELETE')
                </form>
            </div>

        </article>
    @empty
        <div class="text-center py-20 bg-white rounded-3xl border border-dashed border-slate-300">
            @if(request()->has('is_favorite'))
                {{-- Icons for Empty Favorites --}}
                <i class="ph ph-star text-slate-300 text-6xl mb-4 block mx-auto"></i>
                <p class="text-slate-500 mb-4 text-lg font-medium">You haven't favorited any entries yet.</p>
                <a href="/entries" class="text-[#e22161] font-semibold hover:underline">View all entries</a>
            
            @elseif(request()->filled('search') || request()->filled('mood') || request()->filled('category_id'))
                {{-- Icons for No Search Results --}}
                <i class="ph ph-magnifying-glass text-slate-300 text-6xl mb-4 block mx-auto"></i>
                <p class="text-slate-500 mb-4 text-lg font-medium">No results found for your search.</p>
                <a href="/entries" class="text-[#e22161] font-semibold hover:underline">Clear all filters</a>
            
            @else
                {{-- Icons for Brand New Journal --}}
                <i class="ph ph-notebook text-slate-300 text-6xl mb-4 block mx-auto"></i>
                <p class="text-slate-500 mb-4 text-lg font-medium">Your journal is empty.</p>
                <a href="/entries/create" class="text-[#e22161] font-semibold hover:underline">Write your first entry</a>
            @endif
        </div>
    @endforelse
</div>

@endsection
