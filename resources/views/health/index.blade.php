@extends('layouts.app')

@section('content')
    <div class="space-y-8" id="health-panel">
        <div class="flex flex-wrap items-center gap-2 text-sm">
            <span class="text-zinc-400">Done tanpa bukti:</span>
            @foreach ($doneWithoutEvidenceDayOptions as $option)
                <a href="{{ route('health', ['days' => $option]) }}"
                   class="rounded-lg px-2.5 py-1 {{ $doneWithoutEvidenceDays === $option ? 'bg-teal-500/15 text-teal-500' : 'text-zinc-400 hover:bg-zinc-800 hover:text-zinc-100' }} focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500/60">
                    {{ $option }} hari
                </a>
            @endforeach
        </div>

        @include('partials.health-section', [
            'id' => 'in-progress-stale',
            'title' => 'In progress basi (> '.App\Support\HealthThresholds::IN_PROGRESS_STALE_HOURS.' jam tanpa update/komentar)',
            'tasks' => $inProgressStale,
        ])

        @include('partials.health-section', [
            'id' => 'review-stale',
            'title' => 'Review basi (> '.App\Support\HealthThresholds::REVIEW_STALE_HOURS.' jam tanpa perubahan)',
            'tasks' => $reviewStale,
        ])

        @include('partials.health-section', [
            'id' => 'done-without-evidence',
            'title' => 'Done tanpa bukti',
            'subtitle' => 'hanya '.$doneWithoutEvidenceDays.' hari terakhir, task hasil impor tanpa aktivitas sesi dikecualikan',
            'tasks' => $doneWithoutEvidence,
        ])

        @include('partials.health-section', [
            'id' => 'todo-commit-hint',
            'title' => 'Todo indikasi commit',
            'tasks' => $todoCommitHint,
        ])
    </div>
@endsection
