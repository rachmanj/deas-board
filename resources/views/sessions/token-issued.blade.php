@extends('layouts.app')

@section('content')
    <div class="mx-auto max-w-2xl space-y-4">
        <p class="text-sm text-zinc-400">
            Token API untuk <span class="text-zinc-200">{{ $session->label }}</span> ({{ $session->topic_id }}).
            Nama token: <span class="text-zinc-300">{{ $tokenName }}</span>.
        </p>

        <div class="rounded-lg border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm text-amber-200" role="alert">
            Simpan token ini sekarang. Nilai lengkap tidak akan ditampilkan lagi setelah Anda meninggalkan halaman ini.
        </div>

        <div>
            <label for="issued-token" class="mb-1 block text-sm text-zinc-400">Token</label>
            <div class="flex flex-col gap-2 sm:flex-row">
                <input id="issued-token" type="text" readonly value="{{ $plainTextToken }}"
                       class="min-h-11 flex-1 rounded-lg border border-zinc-800 bg-zinc-950 px-3 font-mono text-xs text-zinc-100 focus:outline-none focus:ring-2 focus:ring-teal-500/40">
                <button type="button" id="copy-token"
                        class="inline-flex min-h-11 shrink-0 items-center justify-center whitespace-nowrap rounded-lg border border-zinc-700 px-4 text-center text-sm text-zinc-200 hover:border-zinc-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500">
                    Salin
                </button>
            </div>
        </div>

        <a href="{{ route('sessions') }}"
           class="inline-flex min-h-11 items-center justify-center rounded-lg border border-zinc-800 px-4 text-center text-sm text-zinc-400 hover:border-zinc-700 hover:text-zinc-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500">
            Kembali ke daftar sesi
        </a>
    </div>
@endsection

@push('scripts')
    <script>
        document.getElementById('copy-token')?.addEventListener('click', function () {
            const input = document.getElementById('issued-token');
            if (!input) return;
            input.select();
            input.setSelectionRange(0, input.value.length);
            navigator.clipboard?.writeText(input.value).catch(function () {
                document.execCommand('copy');
            });
            this.textContent = 'Tersalin';
        });
    </script>
@endpush
