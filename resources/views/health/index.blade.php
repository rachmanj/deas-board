@extends('layouts.app')

@section('content')
    <div class="space-y-8" id="health-panel">
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
            'tasks' => $doneWithoutEvidence,
        ])

        @include('partials.health-section', [
            'id' => 'todo-commit-hint',
            'title' => 'Todo indikasi commit',
            'tasks' => $todoCommitHint,
        ])
    </div>
@endsection
