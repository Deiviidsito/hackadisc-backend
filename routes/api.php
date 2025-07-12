<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ImportController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ==================== RUTAS DE AUTENTICACIÓN ====================
Route::post('login', [AuthController::class, 'login']);
Route::get('user', [AuthController::class, 'getUser']);
Route::post('logout', [AuthController::class, 'logout']);
Route::get('admin/users', [AuthController::class, 'getAllUsers']);

// ==================== RUTAS DE IMPORTACIÓN ULTRA-OPTIMIZADA ====================

// POST /api/importarUsuariosJson - SISTEMA ULTRA-OPTIMIZADO PARA DATA CENTER
// 🚀 OPTIMIZACIONES IMPLEMENTADAS:
// - Streaming con buffer circular para archivos de 200MB+
// - Procesamiento vectorizado sin loops PHP lentos
// - Bulk operations con prepared statements (5000 registros/lote)
// - Memory mapping y zero-copy operations
// - Deduplicación O(1) con hash indexing
// - Garbage collection agresivo
// - Objetivo: Reducir de 4+ minutos a <30 segundos
//
// Body: form-data con campo "archivos[]" (máximo 20 archivos de 200MB cada uno)
// Extrae usuarios del campo "CorreoCreador" del JSON
// Nombre: parte antes del @ del email (sanitizado)
// Response: {"success": true, "data": {"usuarios_creados": 5000, "rendimiento": {...}}}
Route::post('importarUsuariosJson', [ImportController::class, 'importarUsuariosJson']);

// POST /api/importarVentasJson - IMPORTACIÓN MASIVA DE DATOS COMPLETOS
// 🚀 ULTRA-OPTIMIZADO PARA DATASETS COMPLEJOS CON RELACIONES:
// - Procesa ventas (comercializaciones) con todas sus relaciones
// - Maneja clientes, facturas, estados de ventas, historial de facturas
// - Filtrado inteligente: Excluye códigos que inician con ADI*, OTR*, SPD*
// - Streaming para archivos masivos (500MB+) con relaciones complejas
// - Bulk operations para múltiples tablas relacionadas (5000 registros/lote)
// - Precarga optimizada de datos existentes con índices hash O(1)
// - Transacciones atómicas para consistencia de datos relacionados
// - Objetivo: Procesar datasets complejos con relaciones en <60 segundos
//
// Body: form-data con campo "archivos[]" (máximo 20 archivos de 500MB cada uno)
// Estructura JSON esperada: Array de objetos con:
// - idComercializacion, CodigoCotizacion, FechaInicio
// - ClienteId, NombreCliente, CorreoCreador
// - ValorFinalComercializacion, ValorFinalCotizacion
// - Estados: Array con EstadoComercializacion, Fecha
// - Facturas: Array con numero, FechaFacturacion, EstadosFactura[]
// Response: {"success": true, "data": {"ventas_creadas": X, "clientes_creados": Y, "facturas_creadas": Z, ...}}
Route::post('importarVentasJson', [ImportController::class, 'importarVentasJson']);