<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Task;
use App\Support\ActivityLogger;
use App\Support\Actor;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
class TaskAttachmentController extends Controller
{
    public function store(Request $request, Task $task)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:20480'],
        ]);

        $actor = Actor::from($request);
        $file = $request->file('file');
        $contents = file_get_contents($file->getRealPath());
        $sha256 = hash('sha256', $contents);
        $name = $file->getClientOriginalName();
        $path = $file->store('attachments', 'local');

        $attachment = Attachment::create([
            'attachable_type' => Task::class,
            'attachable_id' => $task->id,
            'name' => $name,
            'path' => $path,
            'size' => $file->getSize(),
            'sha256' => $sha256,
            'uploaded_by_type' => Actor::morphType($actor),
            'uploaded_by_id' => $actor->getKey(),
        ]);

        ActivityLogger::log('task.attachment_uploaded', $actor, $task, [
            'attachment_id' => $attachment->id,
            'name' => $name,
            'size' => $attachment->size,
        ]);

        return ApiResponse::data([
            'id' => $attachment->id,
            'name' => $attachment->name,
            'size' => $attachment->size,
            'sha256' => $attachment->sha256,
        ], 201);
    }
}
