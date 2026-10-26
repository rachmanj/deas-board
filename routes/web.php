<?php

use App\Http\Controllers\Web\Auth\LoginController;
use App\Http\Controllers\Web\BoardController;
use App\Http\Controllers\Web\CommentController;
use App\Http\Controllers\Web\LabelController;
use App\Http\Controllers\Web\MentionController;
use App\Http\Controllers\Web\ProfileController;
use App\Http\Controllers\Web\SessionController;
use App\Http\Controllers\Web\TaskController;
use App\Http\Controllers\Web\TaskListController;
use App\Http\Controllers\Web\TaskStatusController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

Route::post('/logout', [LoginController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
    Route::patch('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::get('/', [BoardController::class, 'index'])->name('board');
    Route::get('/board/version', [BoardController::class, 'version'])->name('board.version');
    Route::get('/board/fragment', [BoardController::class, 'fragment'])->name('board.fragment');
    Route::get('/tasks', [TaskListController::class, 'index'])->name('tasks.index');
    Route::get('/tasks/create', [TaskController::class, 'create'])->name('tasks.create');
    Route::post('/tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::get('/tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
    Route::get('/tasks/{task}/edit', [TaskController::class, 'edit'])->name('tasks.edit');
    Route::patch('/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::patch('/tasks/{task}/status', [TaskStatusController::class, 'update'])->name('tasks.status');
    Route::post('/tasks/{task}/comments', [CommentController::class, 'store'])->name('tasks.comments.store');
    Route::post('/tasks/{task}/comments/{comment}/reply', [CommentController::class, 'reply'])->name('tasks.comments.reply');
    Route::patch('/tasks/{task}/comments/{comment}', [CommentController::class, 'update'])->name('tasks.comments.update');
    Route::delete('/tasks/{task}/comments/{comment}', [CommentController::class, 'destroy'])->name('tasks.comments.destroy');
    Route::get('/mentions.json', [MentionController::class, 'index'])->name('mentions.json');
    Route::get('/labels.json', [LabelController::class, 'index'])->name('labels.json');
    Route::get('/sessions', [SessionController::class, 'index'])->name('sessions');
    Route::post('/sessions', [SessionController::class, 'store'])->name('sessions.store');
    Route::patch('/sessions/{session}/activate', [SessionController::class, 'activate'])->name('sessions.activate');
    Route::patch('/sessions/{session}/deactivate', [SessionController::class, 'deactivate'])->name('sessions.deactivate');
    Route::post('/sessions/{session}/tokens', [SessionController::class, 'issueToken'])->name('sessions.tokens.issue');
    Route::delete('/sessions/{session}/tokens/{token}', [SessionController::class, 'revokeToken'])->name('sessions.tokens.revoke');
});
