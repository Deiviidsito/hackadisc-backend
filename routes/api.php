<?php

use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Todas las rutas son públicas para hackathon
Route::post('login', [AuthController::class, 'login']);
Route::get('user', [AuthController::class, 'getUser']);
Route::post('logout', [AuthController::class, 'logout']);
Route::get('admin/users', [AuthController::class, 'getAllUsers']);