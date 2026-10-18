<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\BusinessController;
use App\Http\Controllers\Api\InternalChatController;
use App\Http\Controllers\ChatBoardController;
use Illuminate\Support\Facades\Route;

// Public Landing Page & AI Chat Board
Route::get('/', [ChatBoardController::class, 'index'])->name('home');
Route::post('/chat/init', [ChatBoardController::class, 'init'])->name('chat.init');
Route::post('/chat/message', [ChatBoardController::class, 'sendMessage'])->name('chat.message');

// Authenticated Admin Panel
Route::middleware(['auth'])->group(function (): void {
    Route::get('/dashboard', [BusinessController::class, 'index'])->name('dashboard');
    Route::post('/admin/businesses', [BusinessController::class, 'store'])->name('businesses.store');
    Route::post('/admin/businesses/{business}/rescrape', [BusinessController::class, 'rescrape'])->name('businesses.rescrape');
    Route::delete('/admin/businesses/{business}', [BusinessController::class, 'destroy'])->name('businesses.destroy');
});

// Internal API used by test suites or microservices
Route::prefix('api/internal/chat')->group(function (): void {
    Route::post('/init', [InternalChatController::class, 'initSession'])->name('internal.chat.init');
    Route::post('/message', [InternalChatController::class, 'storeMessage'])->name('internal.chat.message');
});

require __DIR__.'/settings.php';
