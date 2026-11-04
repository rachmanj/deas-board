<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\BoardHealth;
use Illuminate\Http\Request;

class HealthController extends Controller
{
    public function index(Request $request)
    {
        $inProgressStale = BoardHealth::inProgressStaleQuery()
            ->with('assigneeSession:id,label,topic_id')
            ->orderBy('code')
            ->get();

        $reviewStale = BoardHealth::reviewStaleQuery()
            ->with('assigneeSession:id,label,topic_id')
            ->orderBy('code')
            ->get();

        $doneWithoutEvidence = BoardHealth::doneWithoutEvidence()
            ->load('assigneeSession:id,label,topic_id');

        $todoCommitHint = BoardHealth::todoCommitHintTasks()
            ->load('assigneeSession:id,label,topic_id');

        return view('health.index', [
            'inProgressStale' => $inProgressStale,
            'reviewStale' => $reviewStale,
            'doneWithoutEvidence' => $doneWithoutEvidence,
            'todoCommitHint' => $todoCommitHint,
            'pageTitle' => 'Kesehatan papan',
        ]);
    }
}
