<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\VentasTotalesController;
use App\Http\Controllers\PagoInicioVentaController;
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

// Ruta para calcular desde base de datos
Route::post('/api/ventas/calcular-julio-2024', [VentasTotalesController::class, 'calcularVentasTotalesJulio2024']);

// Ruta para calcular desde archivo JSON
Route::post('/api/ventas/calcular-desde-json', [VentasTotalesController::class, 'calcularVentasTotalesDesdeJson']);

// ==================== RUTAS DE VENTAS TOTALES ====================

// POST /api/ventas/calcular-por-mes - CÁLCULO DE VENTAS TOTALES POR MES
// 🧮 CARACTERÍSTICAS:
// - Mismos filtros optimizados (ADI*, OTR*, SPD* excluidos)
// - Estados válidos: {0,1,3}
// - Agrupación por año-mes con totales formateados
// - Parámetros opcionales: año, mes_inicio, mes_fin
Route::post('ventas/calcular-por-mes', [VentasTotalesController::class, 'calcularVentasTotalesPorMes']);

// GET /api/ventas/resumen-anual - RESUMEN EJECUTIVO POR AÑO
// 📈 CARACTERÍSTICAS:
// - Vista agregada con totales anuales
// - Identificación del mejor mes por año
// - Estadísticas de actividad mensual
// - Promedios y métricas de rendimiento
Route::get('ventas/resumen-anual', [VentasTotalesController::class, 'resumenVentasPorAño']);

// ==================== RUTAS DE ANÁLISIS DE PAGO ====================

// POST /api/pagos/analizar-tiempo-completo - ANÁLISIS DE TIEMPO DE PAGO
// ⏱️ CARACTERÍSTICAS:
// - Calcula tiempo promedio desde FechaInicio hasta pago completo
// - Filtros por año, rango de fechas (sin estado_venta - filtros internos automáticos)
// - Estadísticas: mediana, mínimo, máximo de días
// - Interpretación del análisis para mejor comprensión
Route::post('pagos/analizar-tiempo-completo', [PagoInicioVentaController::class, 'analizarTiempoPagoCompleto']);
