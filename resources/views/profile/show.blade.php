@extends('layouts.app')

@section('content')
    @if($errors->any())
        <div class="mb-4 rounded-lg border border-red-900/50 bg-red-950/40 px-4 py-3 text-sm text-red-300" role="alert">
            <ul class="list-inside list-disc">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mx-auto max-w-lg space-y-6">
        <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4 sm:p-6">
            <h2 class="mb-3 text-base font-semibold text-zinc-100">Akun</h2>
            <dl class="space-y-2 text-sm">
                <div>
                    <dt class="text-zinc-400">Nama</dt>
                    <dd class="text-zinc-100">{{ auth()->user()->name }}</dd>
                </div>
                <div>
                    <dt class="text-zinc-400">Email</dt>
                    <dd class="text-zinc-100">{{ auth()->user()->email }}</dd>
                </div>
            </dl>
        </div>

        <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4 sm:p-6">
            <h2 class="mb-4 text-base font-semibold text-zinc-100">Ganti kata sandi</h2>
            <form method="POST" action="{{ route('profile.password') }}" class="space-y-4"
                  x-data="submitForm()" @submit="onFormSubmit">
                @csrf
                @method('PATCH')
                <div>
                    <label for="current_password" class="mb-1 block text-sm text-zinc-400">Kata sandi sekarang</label>
                    <input id="current_password" name="current_password" type="password" required autocomplete="current-password"
                           class="min-h-11 w-full rounded-lg border border-zinc-800 bg-zinc-950 px-3 text-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/40 @error('current_password') border-red-800 @enderror">
                    @error('current_password')
                        <p class="mt-1 text-sm text-red-300">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="password" class="mb-1 block text-sm text-zinc-400">Kata sandi baru</label>
                    <input id="password" name="password" type="password" required autocomplete="new-password"
                           class="min-h-11 w-full rounded-lg border border-zinc-800 bg-zinc-950 px-3 text-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/40">
                    <p class="mt-1 text-xs text-zinc-400">Minimal 12 karakter.</p>
                </div>
                <div>
                    <label for="password_confirmation" class="mb-1 block text-sm text-zinc-400">Ulangi kata sandi baru</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                           class="min-h-11 w-full rounded-lg border border-zinc-800 bg-zinc-950 px-3 text-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/40">
                </div>
                <button type="submit" :disabled="processing"
                        class="inline-flex min-h-11 sm:min-h-10 items-center justify-center rounded-xl bg-teal-500/90 px-3.5 text-center text-sm font-medium text-zinc-950 shadow-sm hover:bg-teal-500 active:bg-teal-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500/60 focus-visible:ring-offset-1 focus-visible:ring-offset-zinc-900 disabled:opacity-60"
                        data-submit-label="Simpan kata sandi"
                        x-text="buttonText()">
                    Simpan kata sandi
                </button>
            </form>
        </div>
    </div>
@endsection
