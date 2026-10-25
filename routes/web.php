<?php

use App\Http\Controllers\Web\Auth\LoginController;
use App\Http\Controllers\Web\BoardController;
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
    Route::get('/', [BoardController::class, 'index'])->name('board');
    Route::get('/tasks', [TaskListController::class, 'index'])->name('tasks.index');
    Route::patch('/tasks/{task}/status', [TaskStatusController::class, 'update'])->name('tasks.status');
    Route::get('/sessions', fn () => redirect()->route('board')->with('info', 'Daftar sesi menyusul.'))->name('sessions');
});
