<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ImportController;
<<<<<<< HEAD
=======
use App\Http\Controllers\VentasTotalesController;
use App\Http\Controllers\PagoInicioVentaController;
use App\Http\Controllers\ConsultarFiabilidadCliente;
>>>>>>> dev
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TiempoEtapasController;
use App\Http\Controllers\TiempoFacturacionController;
use App\Http\Controllers\TiempoPagoController;
use App\Http\Controllers\TipoFlujoController;
use App\Http\Controllers\DebugController;
use App\Http\Controllers\ClienteAnalyticsController;

// ==================== RUTAS DE AUTENTICACIÓN ====================
<<<<<<< HEAD

// POST /api/login - Autenticar usuario
// Body: {"email": "user@example.com", "password": "password"}
// Response: {"success": true, "token": "eyJ0eXAiOiJKV1QiLCJhbG...", "user": {...}}

=======
>>>>>>> dev
Route::post('login', [AuthController::class, 'login']);

// GET /api/user - Obtener usuario autenticado
// Headers: {"Authorization": "Bearer TOKEN"}
// Response: {"id": 1, "name": "Usuario", "email": "user@example.com", "role": "admin"}

Route::get('user', [AuthController::class, 'getUser']);

// POST /api/logout - Cerrar sesión
// Headers: {"Authorization": "Bearer TOKEN"}
// Response: {"success": true, "message": "Logged out successfully"}

Route::post('logout', [AuthController::class, 'logout']);
<<<<<<< HEAD

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
=======
Route::get('admin/users', [AuthController::class, 'getAllUsers']);

Route::post('importarUsuariosJson', [ImportController::class, 'importarUsuariosJson']);

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

// POST /api/clientes/consultar-fiabilidad - ANÁLISIS DE FIABILIDAD DEL CLIENTE
// 🎯 CARACTERÍSTICAS:
// - Análisis específico de patrones de pago por cliente individual
// - Predicción de pagos pendientes basado en comportamiento histórico
// - Estadísticas personalizadas: promedio, mediana, desviación estándar
// - Validación de pagos parciales (estadoFactura = 4)
// - Estimación de fechas de pago para facturas pendientes
// Body: {"nombre_cliente": "Nombre del Cliente", "anio": 2024}
Route::post('clientes/consultar-fiabilidad', [ConsultarFiabilidadCliente::class, 'analizarFiabilidadCliente']);
// ==================== RUTAS DE ANÁLISIS TIEMPO ENTRE ETAPAS ====================

// POST /api/tiempo-etapas/promedio - ANÁLISIS TIEMPO PROMEDIO ENTRE ETAPAS
// ⏱️ FUNCIONALIDADES:
// - Calcula tiempo desde estado 0 (En proceso) hasta estado 1 (Terminada)
// - Maneja casos complejos con múltiples transiciones de estado
// - Toma la última fecha del estado 1 como referencia final
// - Filtros por año, mes, y exclusión de prefijos (ADI, OTR, SPD)
// - Estadísticas: promedio, mediana, min, max
// - Opción de incluir detalles individuales por venta
// Body: {"año": 2024, "mes_inicio": 1, "mes_fin": 12, "incluir_detalles": false}
Route::post('tiempo-etapas/promedio', [TiempoEtapasController::class, 'calcularTiempoPromedioEtapas']);

// POST /api/tiempo-etapas/por-cliente - ANÁLISIS TIEMPOS AGRUPADO POR CLIENTE
// 👥 CARACTERÍSTICAS:
// - Tiempo promedio de procesamiento por cliente
// - Estadísticas individuales: min, max, total ventas
// - Ordenado por tiempo promedio descendente
// - Identifica clientes con procesos más lentos/rápidos
// Body: {"año": 2024, "mes_inicio": 1, "mes_fin": 12}
Route::post('tiempo-etapas/por-cliente', [TiempoEtapasController::class, 'analizarTiemposPorCliente']);

// POST /api/tiempo-etapas/distribucion - DISTRIBUCIÓN DE TIEMPOS EN RANGOS
// 📊 CARACTERÍSTICAS:
// - Agrupa tiempos en rangos predefinidos (0-7, 8-15, 16-30, etc.)
// - Porcentajes de distribución
// - Identificación de patrones de tiempo
// - Útil para análisis de eficiencia operacional
// Body: {"año": 2024, "mes_inicio": 1, "mes_fin": 12}
Route::post('tiempo-etapas/distribucion', [TiempoEtapasController::class, 'obtenerDistribucionTiempos']);

// GET /api/tiempo-etapas/verificar-bd - VERIFICACIÓN DE BASE DE DATOS (TESTING)
// 🔍 CARACTERÍSTICAS:
// - Verifica estructura y contenido de las tablas
// - Muestra estadísticas básicas y ejemplos de datos
// - Útil para debugging y validación
Route::get('tiempo-etapas/verificar-bd', [TiempoEtapasController::class, 'verificarBaseDatos']);

// ==================== RUTAS DE ANÁLISIS TIEMPO TERMINACIÓN → FACTURACIÓN ====================

// POST /api/tiempo-facturacion/promedio - ANÁLISIS TIEMPO TERMINACIÓN → PRIMERA FACTURA
// 💰 FUNCIONALIDADES:
// - Calcula tiempo desde estado 1 (Terminada) hasta primera factura emitida
// - Usa fecha más reciente del estado 1 como punto de inicio
// - Identifica primera factura usando FechaFacturacion del JSON
// - Distingue entre facturas SENCE y facturas cliente
// - Filtros por año, mes, tipo de factura
// - Estadísticas completas: promedio, mediana, distribución
// Body: {"año": 2024, "mes": 10, "tipo_factura": "todas|sence|cliente"}
Route::post('tiempo-facturacion/promedio', [TiempoFacturacionController::class, 'calcularTiempoTerminacionFacturacion']);

// POST /api/tiempo-facturacion/por-cliente - ANÁLISIS FACTURACIÓN POR CLIENTE
// 👥 CARACTERÍSTICAS:
// - Tiempo promedio de facturación por cliente
// - Estadísticas por tipo de factura (SENCE vs Cliente)
// - Identificación de clientes con facturación más lenta/rápida
// - Valor total de comercializaciones por cliente
// Body: {"año": 2024, "mes": 10, "tipo_factura": "todas"}
Route::post('tiempo-facturacion/por-cliente', [TiempoFacturacionController::class, 'analizarTiemposPorCliente']);

// POST /api/tiempo-facturacion/distribucion - DISTRIBUCIÓN TIEMPOS FACTURACIÓN
// 📊 CARACTERÍSTICAS:
// - Rangos específicos para facturación (mismo día, 1-3 días, etc.)
// - Porcentajes y ejemplos por rango
// - Análisis de eficiencia en proceso de facturación
// - Identificación de patrones y cuellos de botella
// Body: {"año": 2024, "tipo_factura": "todas"}
Route::post('tiempo-facturacion/distribucion', [TiempoFacturacionController::class, 'obtenerDistribucionTiempos']);

// ==================== RUTAS DE ANÁLISIS TIEMPO FACTURACIÓN → PAGO ====================

// POST /api/tiempo-pago/promedio - ANÁLISIS TIEMPO FACTURACIÓN → PAGO EFECTIVO
// 💵 FUNCIONALIDADES:
// - Calcula tiempo desde emisión de factura hasta recepción de pago efectivo
// - Identifica último estado 3 (Pagado) con monto > 0 como fecha de pago
// - Distingue entre facturas SENCE y facturas cliente
// - Identifica facturas pendientes de pago y morosidad
// - Análisis de flujo de efectivo y tiempos de cobro
// Body: {"año": 2024, "mes": 10, "tipo_factura": "todas", "incluir_pendientes": false}
Route::post('tiempo-pago/promedio', [TiempoPagoController::class, 'calcularTiempoFacturacionPago']);

// POST /api/tiempo-pago/morosidad - ANÁLISIS MOROSIDAD POR CLIENTE
// 🚨 CARACTERÍSTICAS:
// - Comportamiento de pago por cliente individual
// - Porcentaje de facturas pagadas vs pendientes
// - Días promedio de retraso en pagos
// - Clasificación de morosidad (excelente, bueno, regular, malo, crítico)
// - Montos totales pagados y pendientes por cliente
// Body: {"año": 2024, "tipo_factura": "todas"}
Route::post('tiempo-pago/morosidad', [TiempoPagoController::class, 'analizarMorosidadPorCliente']);

// POST /api/tiempo-pago/distribucion - DISTRIBUCIÓN TIEMPOS DE PAGO
// 📊 CARACTERÍSTICAS:
// - Rangos específicos para tiempos de pago (inmediato, 1-7 días, 8-15, etc.)
// - Identificación facturas críticas (>90 días sin pago)
// - Análisis de eficiencia en cobros
// - Patrones de comportamiento de pago por tipo de factura
// Body: {"año": 2024, "tipo_factura": "todas"}
Route::post('tiempo-pago/distribucion', [TiempoPagoController::class, 'obtenerDistribucionTiemposPago']);

// ==================== RUTAS DE ANÁLISIS TIPOS DE FLUJO COMERCIALIZACIÓN ====================

// POST /api/tipo-flujo/analizar - ANÁLISIS COMPARATIVO TIPOS DE FLUJO
// 🔄 FUNCIONALIDADES:
// - Detecta automáticamente tipo de flujo: Completo (0→3→1) vs Simple (0→1)
// - Compara tiempos promedio entre flujos con/sin financiamiento SENCE
// - Analiza valores promedio y número de facturas por tipo
// - Identifica preferencias de clientes por tipo de financiamiento
// - Métricas de eficiencia y adopción de cada flujo
// Body: {"año": 2024, "mes": 10}
Route::post('tipo-flujo/analizar', [TipoFlujoController::class, 'analizarTiposFlujo']);

// POST /api/tipo-flujo/preferencias - ANÁLISIS PREFERENCIAS CLIENTES POR FLUJO
// 👥 CARACTERÍSTICAS:
// - Comportamiento individual de cada cliente por tipo de flujo
// - Clasificación de preferencias: fuerte/leve hacia cada flujo o mixto
// - Valores promedio por cliente según tipo de flujo elegido
// - Identificación clientes que solo usan un tipo vs mixtos
// - Estadísticas de adopción de financiamiento SENCE por cliente
// Body: {"año": 2024, "mes": 10}
Route::post('tipo-flujo/preferencias', [TipoFlujoController::class, 'analizarPreferenciasClientes']);

// POST /api/tipo-flujo/eficiencia - ANÁLISIS EFICIENCIA POR TIPO DE FLUJO
// ⚡ CARACTERÍSTICAS:
// - Comparativa eficiencia operacional entre flujos
// - Tiempos desarrollo, facturación y pago por tipo
// - Tasas de pago y morosidad comparativas
// - Recomendaciones basadas en eficiencia
// - Impacto del financiamiento SENCE en el proceso
// Body: {"año": 2024, "mes": 10}
Route::post('tipo-flujo/eficiencia', [TipoFlujoController::class, 'analizarEficienciaPorFlujo']);

// ==================== RUTAS DE ANÁLISIS DE CLIENTES ====================

// GET /api/clientes/listar - LISTA COMPLETA DE CLIENTES CON ESTADÍSTICAS
// 📋 CARACTERÍSTICAS:
// - Lista todos los clientes con información básica
// - Incluye estadísticas: total ventas, facturas, valor comercializaciones
// - Estado de actividad basado en última venta
// - Resumen general del sistema
// - Ideal para selector de clientes en dashboard
Route::get('clientes/listar', [ClienteAnalyticsController::class, 'listarClientes']);

// GET /api/clientes/{id}/analytics - DASHBOARD COMPLETO POR CLIENTE
// 📊 CARACTERÍSTICAS:
// - Analíticas completas y personalizadas por cliente
// - Resumen general, ventas históricas, análisis de tiempos
// - Comportamiento de facturación y pagos
// - Tendencias temporales y comparativa con mercado
// - Análisis de flujos comerciales (SENCE vs directo)
// - Métricas de morosidad y clasificaciones
Route::get('clientes/{id}/analytics', [ClienteAnalyticsController::class, 'analyticsCliente']);

// GET /api/clientes/{id}/comparar?cliente_comparacion={id2} - COMPARAR DOS CLIENTES
// 🔍 CARACTERÍSTICAS:
// - Comparativa detallada entre dos clientes específicos
// - Métricas lado a lado: ventas, valores, tiempos
// - Análisis de diferencias y fortalezas relativas
// - Identificación de patrones diferenciales
// - Útil para benchmarking y análisis competitivo interno
Route::get('clientes/{id}/comparar', [ClienteAnalyticsController::class, 'compararClientes']);

// ==================== RUTAS DE DEBUG ====================
Route::get('debug/test-basico', [DebugController::class, 'testBasico']);
Route::get('debug/test-tablas', [DebugController::class, 'testTablas']);
Route::get('debug/test-join', [DebugController::class, 'testJoin']);
Route::get('debug/analizar-estructura', [DebugController::class, 'analizarEstructuraCompleta']);

// ==================== RUTAS GET PARA DASHBOARD - DATOS COMPLETOS ====================
// 📱 ENDPOINTS GET SIMPLIFICADOS PARA INTEGRACIÓN FRONTEND RÁPIDA
// 🎯 Todos los endpoints retornan TODA la base de datos sin filtros de fecha
// 🚀 Ideales para cargar dashboards con filtrado dinámico en el frontend

// GET /api/dashboard/ventas-mes - VENTAS POR MES (TODOS LOS AÑOS)
// 📊 Datos completos: todos los años y meses disponibles
Route::get('dashboard/ventas-mes', function() {
    return app(VentasTotalesController::class)->calcularVentasTotalesPorMes(new \Illuminate\Http\Request([]));
});

// GET /api/dashboard/resumen-anual - RESUMEN ANUAL COMPLETO
Route::get('dashboard/resumen-anual', function() {
    return app(VentasTotalesController::class)->resumenVentasPorAño(new \Illuminate\Http\Request());
});

// GET /api/dashboard/tiempo-pago-promedio - TIEMPO PROMEDIO DE PAGO (TODOS LOS DATOS)
// 💵 Datos completos: todos los años, todas las facturas, incluye pendientes
Route::get('dashboard/tiempo-pago-promedio', function() {
    return app(TiempoPagoController::class)->calcularTiempoFacturacionPago(new \Illuminate\Http\Request([
        'tipo_factura' => 'todas',
        'incluir_pendientes' => true
    ]));
});

// GET /api/dashboard/distribucion-pagos - DISTRIBUCIÓN DE TIEMPOS DE PAGO (TODOS LOS DATOS)
// 📊 Datos completos: todos los años, todas las facturas
Route::get('dashboard/distribucion-pagos', function() {
    return app(TiempoPagoController::class)->obtenerDistribucionTiemposPago(new \Illuminate\Http\Request([
        'tipo_factura' => 'todas'
    ]));
});

// GET /api/dashboard/morosidad-clientes - ANÁLISIS DE MOROSIDAD POR CLIENTE (TODOS LOS DATOS)
// 🚨 Datos completos: todos los años, todas las facturas
Route::get('dashboard/morosidad-clientes', function() {
    return app(TiempoPagoController::class)->analizarMorosidadPorCliente(new \Illuminate\Http\Request([
        'tipo_factura' => 'todas'
    ]));
});

// GET /api/dashboard/tiempo-etapas - TIEMPO PROMEDIO ENTRE ETAPAS (TODOS LOS DATOS)
// ⏱️ Datos completos: todos los años y meses, sin detalles
Route::get('dashboard/tiempo-etapas', function() {
    return app(TiempoEtapasController::class)->calcularTiempoPromedioEtapas(new \Illuminate\Http\Request([
        'incluir_detalles' => false
    ]));
});

// GET /api/dashboard/etapas-por-cliente - ANÁLISIS DE ETAPAS POR CLIENTE (TODOS LOS DATOS)
// 👥 Datos completos: todos los años y meses
Route::get('dashboard/etapas-por-cliente', function() {
    return app(TiempoEtapasController::class)->analizarTiemposPorCliente(new \Illuminate\Http\Request([]));
});

// GET /api/dashboard/distribucion-etapas - DISTRIBUCIÓN DE TIEMPOS DE ETAPAS (TODOS LOS DATOS)
// 📊 Datos completos: todos los años y meses
Route::get('dashboard/distribucion-etapas', function() {
    return app(TiempoEtapasController::class)->obtenerDistribucionTiempos(new \Illuminate\Http\Request([]));
});

// GET /api/dashboard/tiempo-facturacion - TIEMPO TERMINACIÓN → FACTURACIÓN (TODOS LOS DATOS)
// 💰 Datos completos: todos los años, todas las facturas
Route::get('dashboard/tiempo-facturacion', function() {
    return app(TiempoFacturacionController::class)->calcularTiempoTerminacionFacturacion(new \Illuminate\Http\Request([
        'tipo_factura' => 'todas'
    ]));
});

// GET /api/dashboard/facturacion-por-cliente - FACTURACIÓN POR CLIENTE (TODOS LOS DATOS)
// 👥 Datos completos: todos los años, todas las facturas
Route::get('dashboard/facturacion-por-cliente', function() {
    return app(TiempoFacturacionController::class)->analizarTiemposPorCliente(new \Illuminate\Http\Request([
        'tipo_factura' => 'todas'
    ]));
});

// GET /api/dashboard/distribucion-facturacion - DISTRIBUCIÓN TIEMPOS FACTURACIÓN (TODOS LOS DATOS)
// 📊 Datos completos: todos los años, todas las facturas
Route::get('dashboard/distribucion-facturacion', function() {
    return app(TiempoFacturacionController::class)->obtenerDistribucionTiempos(new \Illuminate\Http\Request([
        'tipo_factura' => 'todas'
    ]));
});

// GET /api/dashboard/tipos-flujo - ANÁLISIS TIPOS DE FLUJO COMERCIALIZACIÓN (TODOS LOS DATOS)
// 🔄 Datos completos: todos los años y meses
Route::get('dashboard/tipos-flujo', function() {
    return app(TipoFlujoController::class)->analizarTiposFlujo(new \Illuminate\Http\Request([]));
});

// GET /api/dashboard/preferencias-flujo - PREFERENCIAS DE CLIENTES POR FLUJO (TODOS LOS DATOS)
// 👥 Datos completos: todos los años y meses
Route::get('dashboard/preferencias-flujo', function() {
    return app(TipoFlujoController::class)->analizarPreferenciasClientes(new \Illuminate\Http\Request([]));
});

// GET /api/dashboard/eficiencia-flujo - EFICIENCIA POR TIPO DE FLUJO (TODOS LOS DATOS)
// ⚡ Datos completos: todos los años y meses
Route::get('dashboard/eficiencia-flujo', function() {
    return app(TipoFlujoController::class)->analizarEficienciaPorFlujo(new \Illuminate\Http\Request([]));
});

// GET /api/dashboard/pago-tiempo-completo - ANÁLISIS TIEMPO DE PAGO COMPLETO (TODOS LOS DATOS)
// ⏱️ Datos completos: todos los años
Route::get('dashboard/pago-tiempo-completo', function() {
    return app(PagoInicioVentaController::class)->analizarTiempoPagoCompleto(new \Illuminate\Http\Request([]));
});

// GET /api/dashboard/clientes-lista - LISTA DE CLIENTES PARA SELECTOR
// 👥 Lista completa de clientes con estadísticas básicas para selector frontend
Route::get('dashboard/clientes-lista', function() {
    return app(ClienteAnalyticsController::class)->listarClientes();
});

// GET /api/dashboard/clientes-simple - LISTA SIMPLE DE CLIENTES (DEBUGGING)
// 👥 Lista básica solo con nombres para verificar funcionamiento
Route::get('dashboard/clientes-simple', function() {
    try {
        $clientes = \App\Models\Cliente::select('id', 'NombreCliente')->orderBy('NombreCliente')->get();
        return response()->json([
            'success' => true,
            'datos' => $clientes,
            'total' => $clientes->count()
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});

// GET /api/dashboard/cliente-test - TEST ANALÍTICAS DE CLIENTE ESPECÍFICO
// 📊 Endpoint de prueba para verificar analíticas de un cliente
Route::get('dashboard/cliente-test', function() {
    try {
        return app(ClienteAnalyticsController::class)->analyticsCliente(1);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});

// ==================== ENDPOINTS GET CON PARÁMETROS DE CONSULTA ====================
// 🔧 ENDPOINTS GET FLEXIBLES QUE ACEPTAN QUERY PARAMETERS
// 📋 Permiten personalización mediante parámetros en la URL

// GET /api/dashboard/ventas-mes-custom?año=2024&mes_inicio=1&mes_fin=12
Route::get('dashboard/ventas-mes-custom', function(\Illuminate\Http\Request $request) {
    $año = $request->query('año', 2024);
    $mes_inicio = $request->query('mes_inicio', 1);
    $mes_fin = $request->query('mes_fin', 12);
    
    return app(VentasTotalesController::class)->calcularVentasTotalesPorMes(new \Illuminate\Http\Request([
        'año' => $año,
        'mes_inicio' => $mes_inicio,
        'mes_fin' => $mes_fin
    ]));
});

// GET /api/dashboard/tiempo-pago-custom?año=2024&mes=10&tipo_factura=todas&incluir_pendientes=false
Route::get('dashboard/tiempo-pago-custom', function(\Illuminate\Http\Request $request) {
    $año = $request->query('año', 2024);
    $mes = $request->query('mes');
    $tipo_factura = $request->query('tipo_factura', 'todas');
    $incluir_pendientes = $request->query('incluir_pendientes', false);
    
    $params = ['año' => $año, 'tipo_factura' => $tipo_factura, 'incluir_pendientes' => $incluir_pendientes];
    if ($mes) $params['mes'] = $mes;
    
    return app(TiempoPagoController::class)->calcularTiempoFacturacionPago(new \Illuminate\Http\Request($params));
});

// GET /api/dashboard/morosidad-custom?año=2024&tipo_factura=todas
Route::get('dashboard/morosidad-custom', function(\Illuminate\Http\Request $request) {
    $año = $request->query('año', 2024);
    $tipo_factura = $request->query('tipo_factura', 'todas');
    
    return app(TiempoPagoController::class)->analizarMorosidadPorCliente(new \Illuminate\Http\Request([
        'año' => $año,
        'tipo_factura' => $tipo_factura
    ]));
});

// GET /api/dashboard/etapas-custom?año=2024&mes_inicio=1&mes_fin=12&incluir_detalles=false
Route::get('dashboard/etapas-custom', function(\Illuminate\Http\Request $request) {
    $año = $request->query('año', 2024);
    $mes_inicio = $request->query('mes_inicio', 1);
    $mes_fin = $request->query('mes_fin', 12);
    $incluir_detalles = $request->query('incluir_detalles', false);
    
    return app(TiempoEtapasController::class)->calcularTiempoPromedioEtapas(new \Illuminate\Http\Request([
        'año' => $año,
        'mes_inicio' => $mes_inicio,
        'mes_fin' => $mes_fin,
        'incluir_detalles' => $incluir_detalles
    ]));
});

// GET /api/dashboard/facturacion-custom?año=2024&mes=10&tipo_factura=todas
Route::get('dashboard/facturacion-custom', function(\Illuminate\Http\Request $request) {
    $año = $request->query('año', 2024);
    $mes = $request->query('mes', 10);
    $tipo_factura = $request->query('tipo_factura', 'todas');
    
    return app(TiempoFacturacionController::class)->calcularTiempoTerminacionFacturacion(new \Illuminate\Http\Request([
        'año' => $año,
        'mes' => $mes,
        'tipo_factura' => $tipo_factura
    ]));
});

// GET /api/dashboard/tipos-flujo-custom?año=2024&mes=10
Route::get('dashboard/tipos-flujo-custom', function(\Illuminate\Http\Request $request) {
    $año = $request->query('año', 2024);
    $mes = $request->query('mes', 10);
    
    return app(TipoFlujoController::class)->analizarTiposFlujo(new \Illuminate\Http\Request([
        'año' => $año,
        'mes' => $mes
    ]));
});

// ==================== ENDPOINT GET PARA DASHBOARD COMPLETO ====================
// 🎯 ENDPOINT ESPECIAL QUE RETORNA TODOS LOS DATOS DEL DASHBOARD EN UNA SOLA LLAMADA
// 🚀 IDEAL PARA CARGAR EL DASHBOARD COMPLETO CON TODOS LOS DATOS HISTÓRICOS

// GET /api/dashboard/completo - DATOS COMPLETOS DEL DASHBOARD
// 📊 Retorna todas las métricas de TODA la base de datos en una sola respuesta
Route::get('dashboard/completo', function() {
    try {
        // Crear requests sin filtros de fecha para obtener todos los datos
        $requestTodas = new \Illuminate\Http\Request(['tipo_factura' => 'todas', 'incluir_pendientes' => true]);
        $requestVacio = new \Illuminate\Http\Request([]);
        $requestSinDetalles = new \Illuminate\Http\Request(['incluir_detalles' => false]);
        
        // Recopilar todos los datos históricos
        $dashboardData = [
            'timestamp' => now()->toISOString(),
            'alcance' => 'todos_los_datos_historicos',
            'ventas' => [
                'por_mes' => app(VentasTotalesController::class)->calcularVentasTotalesPorMes($requestVacio)->getData(),
                'resumen_anual' => app(VentasTotalesController::class)->resumenVentasPorAño($requestVacio)->getData()
            ],
            'tiempo_pago' => [
                'promedio' => app(TiempoPagoController::class)->calcularTiempoFacturacionPago($requestTodas)->getData(),
                'distribucion' => app(TiempoPagoController::class)->obtenerDistribucionTiemposPago($requestTodas)->getData(),
                'morosidad' => app(TiempoPagoController::class)->analizarMorosidadPorCliente($requestTodas)->getData()
            ],
            'tiempo_etapas' => [
                'promedio' => app(TiempoEtapasController::class)->calcularTiempoPromedioEtapas($requestSinDetalles)->getData(),
                'por_cliente' => app(TiempoEtapasController::class)->analizarTiemposPorCliente($requestVacio)->getData(),
                'distribucion' => app(TiempoEtapasController::class)->obtenerDistribucionTiempos($requestVacio)->getData()
            ],
            'facturacion' => [
                'promedio' => app(TiempoFacturacionController::class)->calcularTiempoTerminacionFacturacion($requestTodas)->getData(),
                'por_cliente' => app(TiempoFacturacionController::class)->analizarTiemposPorCliente($requestTodas)->getData(),
                'distribucion' => app(TiempoFacturacionController::class)->obtenerDistribucionTiempos($requestTodas)->getData()
            ],
            'tipos_flujo' => [
                'analisis' => app(TipoFlujoController::class)->analizarTiposFlujo($requestVacio)->getData(),
                'preferencias' => app(TipoFlujoController::class)->analizarPreferenciasClientes($requestVacio)->getData(),
                'eficiencia' => app(TipoFlujoController::class)->analizarEficienciaPorFlujo($requestVacio)->getData()
            ],
            'pago_completo' => app(PagoInicioVentaController::class)->analizarTiempoPagoCompleto($requestVacio)->getData()
        ];
        
        return response()->json([
            'success' => true,
            'message' => 'Dashboard completo cargado exitosamente con todos los datos históricos',
            'datos' => $dashboardData,
            'metadata' => [
                'endpoints_incluidos' => 13,
                'alcance_datos' => 'completo_sin_filtros_fecha',
                'tiempo_generacion' => now()->toISOString()
            ]
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error al cargar dashboard completo',
            'error' => $e->getMessage()
        ], 500);
    }
});

// ==================== RUTAS DE ANALÍTICAS DE CLIENTES ====================
Route::prefix('clientes-analytics')->group(function () {
    // Listar todos los clientes disponibles para analíticas
    Route::get('/', [ClienteAnalyticsController::class, 'listarClientes']);
    
    // Obtener analíticas detalladas de un cliente específico
    Route::get('/{clienteId}/analytics', [ClienteAnalyticsController::class, 'analyticsCliente']);
    
    // Comparar dos clientes
    Route::get('/{clienteId1}/compare/{clienteId2}', [ClienteAnalyticsController::class, 'compararClientes']);
    
    // 💳 Simulador de Predicción de Tiempo de Pago con IA
    Route::get('/{clienteId}/simulador-prediccion', [ClienteAnalyticsController::class, 'simularPrediccionPagos']);
});
>>>>>>> dev
