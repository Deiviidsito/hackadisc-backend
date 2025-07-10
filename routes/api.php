<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ImportController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Todas las rutas son públicas para hackathon
Route::post('login', [AuthController::class, 'login']);
Route::get('user', [AuthController::class, 'getUser']);
Route::post('logout', [AuthController::class, 'logout']);
Route::get('admin/users', [AuthController::class, 'getAllUsers']);
Route::post('importar-json', [ImportController::class, 'importarVentasJson']);
Route::post('/importar-usuarios-json', [ImportController::class, 'importarUsuariosJson']);
Route::post('/obtener-venta', [ImportController::class, 'obtenerVentaPorIdComercializacion']);

// Rutas de analíticas
Route::post('/generar-estadisticas-pago', [\App\Http\Controllers\AnaliticasController::class, 'generarEstadisticasPago']);
Route::get('/resumen-estadisticas-pago', [\App\Http\Controllers\AnaliticasController::class, 'obtenerResumenEstadisticas']);