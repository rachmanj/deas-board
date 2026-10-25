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

    <div class="mx-auto max-w-2xl rounded-xl border border-zinc-800 bg-zinc-900 p-4 sm:p-6">
        <p class="mb-1 font-mono text-xs text-zinc-400">{{ $task->code }}</p>
        <h2 class="mb-4 text-base font-semibold text-zinc-100">Ubah task</h2>
        @include('partials.task-form', [
            'task' => $task,
            'statusLabels' => $statusLabels,
            'priorityLabels' => $priorityLabels,
            'sessions' => $sessions,
            'projects' => $projects,
            'labels' => $labels,
        ])
    </div>
@endsection
