<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ImportController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ==================== RUTAS DE AUTENTICACIÓN ====================

// POST /api/login - Autenticar usuario
// Body: {"email": "user@example.com", "password": "password"}
// Response: {"success": true, "token": "eyJ0eXAiOiJKV1QiLCJhbG...", "user": {...}}

Route::post('login', [AuthController::class, 'login']);

// GET /api/user - Obtener usuario autenticado
// Headers: {"Authorization": "Bearer TOKEN"}
// Response: {"id": 1, "name": "Usuario", "email": "user@example.com", "role": "admin"}

Route::get('user', [AuthController::class, 'getUser']);

// POST /api/logout - Cerrar sesión
// Headers: {"Authorization": "Bearer TOKEN"}
// Response: {"success": true, "message": "Logged out successfully"}

Route::post('logout', [AuthController::class, 'logout']);

// GET /api/admin/users - Listar todos los usuarios (solo admin)
// Response: [{"id": 1, "name": "Usuario 1", "email": "user1@example.com"}, ...]

Route::get('admin/users', [AuthController::class, 'getAllUsers']);




// ==================== RUTAS DE IMPORTACIÓN ====================

// POST /api/importar-json - Importar ventas, facturas e historiales desde JSON
// Body: {"data": [...]} (array con datos de ventas)
// Response: {"success": true, "message": "Datos importados correctamente", "ventas_creadas": 150}
Route::post('importar-json', [ImportController::class, 'importarVentasJson']);

// POST /api/importar-usuarios-json - Importar usuarios desde JSON
// Body: {"users": [...]} (array con datos de usuarios)
// Response: {"success": true, "message": "Usuarios importados", "usuarios_creados": 25}
Route::post('/importar-usuarios-json', [ImportController::class, 'importarUsuariosJson']);

// POST /api/obtener-venta - Obtener venta por ID de comercialización
// Body: {"id_comercializacion": "12345"}
// Response: {"venta": {...}, "facturas": [...], "cliente": {...}}
Route::post('/obtener-venta', [ImportController::class, 'obtenerVentaPorIdComercializacion']);

// ==================== RUTAS DE ANALÍTICAS ====================

// POST /api/generar-estadisticas-pago - Generar estadísticas de tiempo de pago
// Body: {} (sin parámetros requeridos)
// Response: {"success": true, "message": "Estadísticas generadas", "estadisticas_generadas": 1250}
// Nota: Puede tardar varios minutos con datasets grandes

Route::post('/generar-estadisticas-pago', [\App\Http\Controllers\AnaliticasController::class, 'generarEstadisticasPago']);

// GET /api/resumen-estadisticas-pago - Resumen completo de estadísticas
// Response: {"success": true, "data": {"resumen_general": {...}, "estadisticas_tiempo_pago": {...}}}
// Incluye: promedio, mediana, percentiles, desviación estándar, etc.

Route::get('/resumen-estadisticas-pago', [\App\Http\Controllers\AnaliticasController::class, 'obtenerResumenEstadisticas']);

// GET /api/estadisticas-por-cliente - Estadísticas agrupadas por cliente
// Query params: ?limit=50&offset=0&sort_by=promedio_dias&order=desc
// Response: {"success": true, "data": [...], "pagination": {"total": 150, "has_more": true}}

Route::get('/estadisticas-por-cliente', [\App\Http\Controllers\AnaliticasController::class, 'obtenerEstadisticasPorCliente']);

// GET /api/tendencias-temporales - Análisis de tendencias por período
// Query params: ?group_by=month&year=2024
// Response: {"success": true, "data": {"agrupacion": "month", "tendencias": [...]}}

Route::get('/tendencias-temporales', [\App\Http\Controllers\AnaliticasController::class, 'obtenerTendenciasTemporales']);

// GET /api/distribucion-pagos - Distribución de pagos por rangos de tiempo
// Response: {"success": true, "data": {"distribucion": [...], "resumen": {...}}}
// Rangos: 0-7 días, 8-15 días, 16-30 días, 31-60 días, etc.

Route::get('/distribucion-pagos', [\App\Http\Controllers\AnaliticasController::class, 'obtenerDistribucionPagos']);

// GET /api/analisis-comparativo - Comparar estadísticas entre dos períodos
// Query params: ?fecha_inicio_periodo1=2024-01-01&fecha_fin_periodo1=2024-06-30
//               &fecha_inicio_periodo2=2024-07-01&fecha_fin_periodo2=2024-12-31
// Response: {"success": true, "data": {"periodo_1": {...}, "periodo_2": {...}, "comparacion": {...}}}

Route::get('/analisis-comparativo', [\App\Http\Controllers\AnaliticasController::class, 'obtenerAnalisisComparativo']);

// GET /api/linea-tiempo-comercializacion - Línea de tiempo de estados de facturas por cliente
// Query params: ?cliente=NombreCliente&fecha_inicio=2024-01-01&fecha_fin=2024-12-31&agrupar_por=mes
// Response: {"success": true, "data": {"cliente_nombre": "...", "periodos": [...], "resumen": {...}}}
// Incluye: progresión temporal de estados, métricas de tiempo, valor por período
Route::get('/linea-tiempo-comercializacion', [\App\Http\Controllers\AnaliticasController::class, 'obtenerLineaTiempoComercializacion']);

// ==================== RUTAS DE DASHBOARD POR CLIENTE ====================

// GET /api/clientes-dashboard - Lista de todos los clientes con estadísticas básicas
// Response: {"success": true, "data": [...], "total_clientes": 25}
// Incluye: total_ventas, total_ingresos, ventas_canceladas, porcentaje_facturas_pagadas, estado_actividad
Route::get('/clientes-dashboard', [\App\Http\Controllers\AnaliticasController::class, 'obtenerListaClientesDashboard']);

// GET /api/clientes-dashboard-avanzado - Lista de clientes con filtros y paginación
// Parámetros opcionales: limit, offset, sort_by, order, estado_actividad, monto_minimo, ventas_minimas
// Response: {"success": true, "data": [...], "pagination": {...}, "filtros_aplicados": {...}}
Route::get('/clientes-dashboard-avanzado', [\App\Http\Controllers\AnaliticasController::class, 'obtenerListaClientesDashboardAvanzado']);

// GET /api/cliente-dashboard/{nombre} - Dashboard completo de un cliente específico
// Parámetro: nombre del cliente (URL encoded)
// Response: {"success": true, "data": {"cliente_nombre": "...", "total_ventas": 143, ...}}
// Incluye: total_ventas, dias_comercializacion, facturas_estadisticas, ventas_canceladas, total_ingresos
Route::get('/cliente-dashboard/{nombreCliente}', [\App\Http\Controllers\AnaliticasController::class, 'obtenerDashboardCliente']);

// ==================== NOTAS PARA FRONTEND ====================
/*
ESTRUCTURA COMÚN DE RESPUESTAS:
- Éxito: {"success": true, "data": {...}, "message": "..."}
- Error: {"success": false, "error": "Mensaje de error", "code": 400}

HEADERS REQUERIDOS:
- Content-Type: application/json

CÓDIGOS DE ESTADO HTTP:
- 200: Éxito
- 400: Error de validación
- 401: No autenticado
- 403: Sin permisos
- 404: No encontrado
- 500: Error del servidor

PARÁMETROS DE PAGINACIÓN ESTÁNDAR:
- limit: Número de resultados (default: 50, max: 200)
- offset: Desplazamiento (default: 0)
- sort_by: Campo para ordenar
- order: asc o desc

FORMATO DE FECHAS:
- Entrada: YYYY-MM-DD (ej: 2024-12-31)
- Salida: YYYY-MM-DD HH:MM:SS

ENDPOINTS QUE PUEDEN TARDAR:
- /generar-estadisticas-pago: 30-300 segundos
- /analisis-comparativo: 5-15 segundos
- Otros endpoints de analíticas: <5 segundos
*/