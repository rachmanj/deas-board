<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-zinc-950 px-4 text-zinc-100">
    <div class="w-full max-w-sm rounded-xl border border-zinc-800 bg-zinc-900 p-6">
        <h1 class="mb-6 text-center text-lg font-semibold">Masuk ke {{ config('app.name') }}</h1>

        @if($errors->any())
            <div class="mb-4 rounded-lg border border-red-900/50 bg-red-950/40 px-3 py-2 text-sm text-red-300" role="alert">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf
            <div>
                <label for="email" class="mb-1 block text-sm text-zinc-400">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                       class="min-h-11 w-full rounded-lg border border-zinc-800 bg-zinc-950 px-3 text-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/40">
            </div>
            <div>
                <label for="password" class="mb-1 block text-sm text-zinc-400">Kata sandi</label>
                <input id="password" name="password" type="password" required
                       class="min-h-11 w-full rounded-lg border border-zinc-800 bg-zinc-950 px-3 text-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/40">
            </div>
            <button type="submit"
                    class="inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-teal-500 text-center text-sm font-medium text-zinc-950 hover:bg-teal-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500">
                Masuk
            </button>
        </form>
    </div>
</body>
</html>
