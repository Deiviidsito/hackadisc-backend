<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CapiController;
use App\Http\Controllers\CapiVoiceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Todas las rutas son públicas para hackathon
Route::post('login', [AuthController::class, 'login']);
Route::get('user', [AuthController::class, 'getUser']);
Route::post('logout', [AuthController::class, 'logout']);
Route::get('admin/users', [AuthController::class, 'getAllUsers']);

// Rutas para Capi - Agente de IA
Route::prefix('capi')->group(function () {
    Route::post('ask', [CapiController::class, 'ask']);
    Route::get('about', [CapiController::class, 'about']);
    
    // Rutas de voz
    Route::post('voice/chat', [CapiVoiceController::class, 'voiceChat']);
    Route::post('voice/text-to-speech', [CapiVoiceController::class, 'textToVoice']);
    Route::get('voice/audio/{file}', [CapiVoiceController::class, 'serveAudio'])->name('capi.voice.audio');
});