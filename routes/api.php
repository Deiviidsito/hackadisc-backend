<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\VentasTotalesController;
use App\Http\Controllers\PagoInicioVentaController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TiempoEtapasController;
use App\Http\Controllers\TiempoFacturacionController;
use App\Http\Controllers\TiempoPagoController;
use App\Http\Controllers\TipoFlujoController;
use App\Http\Controllers\DebugController;

// ==================== RUTAS DE AUTENTICACIÓN ====================
Route::post('login', [AuthController::class, 'login']);
Route::get('user', [AuthController::class, 'getUser']);
Route::post('logout', [AuthController::class, 'logout']);
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

// ==================== RUTAS DE DEBUG ====================
Route::get('debug/test-basico', [DebugController::class, 'testBasico']);
Route::get('debug/test-tablas', [DebugController::class, 'testTablas']);
Route::get('debug/test-join', [DebugController::class, 'testJoin']);
Route::get('debug/analizar-estructura', [DebugController::class, 'analizarEstructuraCompleta']);
