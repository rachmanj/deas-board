<?php

use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\TaskAttachmentController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TaskUpdateController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:api-token'])->group(function () {
    Route::get('/me', [MeController::class, 'show']);

    Route::get('/sessions', [SessionController::class, 'index']);

    Route::get('/tasks', [TaskController::class, 'index']);
    Route::post('/tasks', [TaskController::class, 'store']);
    Route::get('/tasks/{task}', [TaskController::class, 'show']);
    Route::patch('/tasks/{task}', [TaskController::class, 'update']);
    Route::post('/tasks/{task}/updates', [TaskUpdateController::class, 'store']);
    Route::post('/tasks/{task}/comments', [CommentController::class, 'store']);
    Route::post('/tasks/{task}/attachments', [TaskAttachmentController::class, 'store']);

    Route::post('/comments/{comment}/reply', [CommentController::class, 'reply']);
    Route::patch('/comments/{comment}', [CommentController::class, 'update']);
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
});
