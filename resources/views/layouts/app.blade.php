<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $pageTitle ?? config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="min-h-screen bg-zinc-950 text-zinc-100 antialiased">
    <div class="flex min-h-screen flex-col md:flex-row">
        <aside class="border-b border-zinc-800 bg-zinc-900 md:flex md:w-52 md:shrink-0 md:flex-col md:border-b-0 md:border-r">
            <div class="px-4 py-4 text-sm font-semibold text-zinc-100">{{ config('app.name') }}</div>
            <nav class="flex gap-1 px-2 pb-3 md:flex-col" aria-label="Navigasi utama">
                <a href="{{ route('board') }}"
                   class="min-h-11 rounded-lg px-3 py-2 text-sm {{ request()->routeIs('board') ? 'bg-teal-500/15 text-teal-500' : 'text-zinc-400 hover:bg-zinc-800 hover:text-zinc-100' }} focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500">
                    Papan
                </a>
                <a href="{{ route('tasks.index') }}"
                   class="min-h-11 rounded-lg px-3 py-2 text-sm {{ request()->routeIs('tasks.*') ? 'bg-teal-500/15 text-teal-500' : 'text-zinc-400 hover:bg-zinc-800 hover:text-zinc-100' }} focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500">
                    Daftar
                </a>
                <a href="{{ route('sessions') }}"
                   class="min-h-11 rounded-lg px-3 py-2 text-sm {{ request()->routeIs('sessions*') ? 'bg-teal-500/15 text-teal-500' : 'text-zinc-400 hover:bg-zinc-800 hover:text-zinc-100' }} focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500">
                    Sesi
                </a>
            </nav>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="flex flex-wrap items-center gap-3 border-b border-zinc-800 bg-zinc-900 px-4 py-3">
                <h1 class="text-lg font-semibold text-zinc-100">{{ $pageTitle ?? config('app.name') }}</h1>
                <a href="{{ route('tasks.create') }}"
                   class="min-h-11 rounded-lg bg-teal-500 px-3 text-sm font-medium text-zinc-950 hover:bg-teal-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500">
                    Task baru
                </a>
                <form method="GET" action="{{ request()->url() }}" class="ml-auto flex min-w-0 flex-1 basis-full sm:basis-auto sm:max-w-xs">
                    @foreach(request()->except('q', 'page') as $key => $value)
                        @if(is_array($value))
                            @foreach($value as $v)
                                <input type="hidden" name="{{ $key }}[]" value="{{ $v }}">
                            @endforeach
                        @else
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endif
                    @endforeach
                    <label for="header-q" class="sr-only">Cari</label>
                    <input id="header-q" name="q" type="search" value="{{ request('q') }}"
                           placeholder="Cari task"
                           class="min-h-11 w-full rounded-lg border border-zinc-800 bg-zinc-950 px-3 text-sm text-zinc-100 placeholder:text-zinc-400 focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/40">
                </form>
                <div class="flex items-center gap-2 text-sm text-zinc-400">
                    <span>{{ auth()->user()->name }}</span>
                    <a href="{{ route('profile') }}"
                       class="min-h-11 rounded-lg border border-zinc-800 px-3 leading-[2.75rem] text-zinc-400 hover:border-zinc-700 hover:text-zinc-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500">
                        Profil
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                                class="min-h-11 rounded-lg border border-zinc-800 px-3 text-zinc-400 hover:border-zinc-700 hover:text-zinc-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500">
                            Keluar
                        </button>
                    </form>
                </div>
            </header>

            <main class="flex-1 p-4">
                @if(session('info'))
                    <div class="mb-4 rounded-lg border border-zinc-800 bg-zinc-900 px-4 py-3 text-sm text-zinc-400" role="status">
                        {{ session('info') }}
                    </div>
                @endif
                @yield('content')
            </main>
        </div>
    </div>
    @stack('scripts')
</body>
</html>
