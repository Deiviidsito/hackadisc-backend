<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class VentasTotalesController extends Controller
{
    /**
     * CÁLCULO DE VENTAS TOTALES POR MES CON FILTROS OPTIMIZADOS
     * Calcula ventas totales agrupadas por mes usando los mismos filtros
     * 
     * Filtros aplicados:
     * - Excluye códigos que empiecen con: ADI, OTR, SPD  
     * - Solo último estado en {0,1,3}
     * - Agrupado por año y mes
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function calcularVentasTotalesPorMes(Request $request)
    {
        try {
            set_time_limit(300);
            ini_set('memory_limit', '1G');
            
            $startTime = microtime(true);
            
            Log::info("🧮 INICIANDO CÁLCULO VENTAS TOTALES POR MES");
            
            // Parámetros de filtrado (opcional)
            $año = $request->input('año', null); // Si no se especifica, toma todos los años
            $mesInicio = $request->input('mes_inicio', 1);
            $mesFin = $request->input('mes_fin', 12);
            
            // Estados válidos y prefijos a ignorar
            $validStates = [0, 1, 3];
            $ignorePrefixes = ['ADI', 'OTR', 'SPD'];
            
            // Consulta base optimizada
            $query = DB::table('ventas as v')
                ->leftJoin('historial_estados_venta as hev', 'v.idVenta', '=', 'hev.venta_id')
                ->select([
                    'v.idVenta',
                    'v.CodigoCotizacion', 
                    'v.FechaInicio',
                    'v.ValorFinalComercializacion',
                    'hev.estado_venta_id as EstadoComercializacion',
                    'hev.fecha as FechaEstado'
                ]);
            
            // Aplicar filtros de año si se especifica
            if ($año) {
                $query->whereYear('v.FechaInicio', $año);
            }
            
            // Aplicar filtros de rango de meses
            $query->whereMonth('v.FechaInicio', '>=', $mesInicio)
                  ->whereMonth('v.FechaInicio', '<=', $mesFin);
            
            $ventas = $query->get();
            
            Log::info("📊 Ventas obtenidas de BD", [
                'total_registros' => $ventas->count(),
                'filtros' => [
                    'año' => $año ?: 'todos',
                    'mes_inicio' => $mesInicio,
                    'mes_fin' => $mesFin
                ]
            ]);
            
            // Agrupar por venta para procesar estados
            $ventasAgrupadas = $ventas->groupBy('idVenta');
            
            // Array para almacenar resultados por mes
            $ventasPorMes = [];
            
            $contadores = [
                'procesadas' => 0,
                'filtradas_por_codigo' => 0,
                'sin_estados' => 0,
                'estado_invalido' => 0,
                'validas_sumadas' => 0
            ];
            
            foreach ($ventasAgrupadas as $idVenta => $estadosVenta) {
                $contadores['procesadas']++;
                
                // Obtener datos de la venta
                $venta = $estadosVenta->first();
                $codigo = strtoupper(trim($venta->CodigoCotizacion ?? ''));
                
                // FILTRO 1: Ignorar códigos que empiecen con ADI, OTR, SPD
                $debeIgnorar = false;
                foreach ($ignorePrefixes as $prefix) {
                    if (str_starts_with($codigo, $prefix)) {
                        $debeIgnorar = true;
                        break;
                    }
                }
                
                if ($debeIgnorar) {
                    $contadores['filtradas_por_codigo']++;
                    continue;
                }
                
                // FILTRO 2: Obtener último estado por fecha
                $estadosConFecha = $estadosVenta->filter(function($estado) {
                    return !empty($estado->FechaEstado) && !is_null($estado->EstadoComercializacion);
                });
                
                if ($estadosConFecha->isEmpty()) {
                    $contadores['sin_estados']++;
                    continue;
                }
                
                // Encontrar el estado más reciente
                $ultimoEstado = $estadosConFecha->sortByDesc('FechaEstado')->first();
                
                // FILTRO 3: Solo estados válidos {0,1,3}
                if (!in_array($ultimoEstado->EstadoComercializacion, $validStates)) {
                    $contadores['estado_invalido']++;
                    continue;
                }
                
                // Extraer año y mes de la fecha de inicio
                try {
                    $fechaInicio = Carbon::parse($venta->FechaInicio);
                    $claveAñoMes = $fechaInicio->format('Y-m'); // Formato: 2024-07
                    $nombreMes = $fechaInicio->locale('es')->isoFormat('MMMM YYYY'); // Formato: julio 2024
                    
                    // Inicializar mes si no existe
                    if (!isset($ventasPorMes[$claveAñoMes])) {
                        $ventasPorMes[$claveAñoMes] = [
                            'año' => $fechaInicio->year,
                            'mes' => $fechaInicio->month,
                            'nombre_mes' => $nombreMes,
                            'total' => 0,
                            'cantidad_ventas' => 0,
                            'clave' => $claveAñoMes
                        ];
                    }
                    
                    // Sumar valor al mes correspondiente
                    $valor = floatval($venta->ValorFinalComercializacion ?? 0);
                    $ventasPorMes[$claveAñoMes]['total'] += $valor;
                    $ventasPorMes[$claveAñoMes]['cantidad_ventas']++;
                    $contadores['validas_sumadas']++;
                    
                    Log::debug("✅ Venta sumada", [
                        'mes' => $claveAñoMes,
                        'codigo' => $codigo,
                        'valor' => $valor,
                        'total_mes' => $ventasPorMes[$claveAñoMes]['total']
                    ]);
                    
                } catch (\Exception $e) {
                    Log::warning("⚠️ Error parseando fecha", [
                        'fecha' => $venta->FechaInicio,
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }
            
            // Ordenar por año-mes y formatear resultados
            ksort($ventasPorMes);
            
            $resultadosFormateados = [];
            $totalGeneral = 0;
            
            foreach ($ventasPorMes as $mes => $datos) {
                $totalFormateado = '$' . number_format($datos['total'], 0, ',', '.');
                $totalGeneral += $datos['total'];
                
                $resultadosFormateados[] = [
                    'periodo' => $mes,
                    'nombre_mes' => $datos['nombre_mes'],
                    'año' => $datos['año'],
                    'mes' => $datos['mes'],
                    'total_bruto' => $datos['total'],
                    'total_formateado' => $totalFormateado,
                    'cantidad_ventas' => $datos['cantidad_ventas'],
                    'promedio_por_venta' => $datos['cantidad_ventas'] > 0 
                        ? round($datos['total'] / $datos['cantidad_ventas'], 2) : 0
                ];
            }
            
            $totalGeneralFormateado = '$' . number_format($totalGeneral, 0, ',', '.');
            $tiempoTotal = round(microtime(true) - $startTime, 2);
            
            Log::info("🎉 CÁLCULO POR MES COMPLETADO", array_merge($contadores, [
                'meses_procesados' => count($ventasPorMes),
                'total_general' => $totalGeneral,
                'total_general_formateado' => $totalGeneralFormateado,
                'tiempo_segundos' => $tiempoTotal,
                'memoria_mb' => round(memory_get_usage(true) / 1024 / 1024, 2)
            ]));
            
            return response()->json([
                'success' => true,
                'mensaje' => 'Cálculo de ventas totales por mes completado',
                'total_general_bruto' => $totalGeneral,
                'total_general_formateado' => $totalGeneralFormateado,
                'cantidad_meses' => count($ventasPorMes),
                'filtros_aplicados' => [
                    'codigos_excluidos' => $ignorePrefixes,
                    'estados_validos' => $validStates,
                    'año' => $año ?: 'todos',
                    'rango_meses' => "{$mesInicio}-{$mesFin}"
                ],
                'ventas_por_mes' => $resultadosFormateados,
                'estadisticas' => $contadores,
                'tiempo_proceso_segundos' => $tiempoTotal,
                'timestamp' => now()->toISOString()
            ]);
            
        } catch (\Exception $e) {
            Log::error('💥 ERROR EN CÁLCULO VENTAS POR MES', [
                'mensaje' => $e->getMessage(),
                'linea' => $e->getLine(),
                'archivo' => $e->getFile(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'mensaje' => 'Error al calcular ventas totales por mes',
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString()
            ], 500);
        }
    }

    /**
     * RESUMEN EJECUTIVO DE VENTAS POR AÑO
     * Vista agregada con totales anuales y mejores meses
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resumenVentasPorAño(Request $request)
    {
        try {
            set_time_limit(300);
            ini_set('memory_limit', '1G');
            
            $startTime = microtime(true);
            
            Log::info("📈 INICIANDO RESUMEN VENTAS POR AÑO");
            
            // Estados válidos y prefijos a ignorar
            $validStates = [0, 1, 3];
            $ignorePrefixes = ['ADI', 'OTR', 'SPD'];
            
            // Consulta optimizada agrupando por año
            $ventas = DB::table('ventas as v')
                ->leftJoin('historial_estados_venta as hev', 'v.idVenta', '=', 'hev.venta_id')
                ->select([
                    'v.idVenta',
                    'v.CodigoCotizacion', 
                    'v.FechaInicio',
                    'v.ValorFinalComercializacion',
                    'hev.estado_venta_id as EstadoComercializacion',
                    'hev.fecha as FechaEstado'
                ])
                ->get();
            
            Log::info("📊 Ventas obtenidas para resumen anual", ['total_registros' => $ventas->count()]);
            
            // Agrupar por venta para procesar estados
            $ventasAgrupadas = $ventas->groupBy('idVenta');
            
            // Array para almacenar resultados por año
            $ventasPorAño = [];
            $detallesPorAñoMes = [];
            
            $contadores = [
                'procesadas' => 0,
                'filtradas_por_codigo' => 0,
                'sin_estados' => 0,
                'estado_invalido' => 0,
                'validas_sumadas' => 0
            ];
            
            foreach ($ventasAgrupadas as $idVenta => $estadosVenta) {
                $contadores['procesadas']++;
                
                // Aplicar mismos filtros que el método anterior
                $venta = $estadosVenta->first();
                $codigo = strtoupper(trim($venta->CodigoCotizacion ?? ''));
                
                // FILTRO 1: Ignorar códigos
                $debeIgnorar = false;
                foreach ($ignorePrefixes as $prefix) {
                    if (str_starts_with($codigo, $prefix)) {
                        $debeIgnorar = true;
                        break;
                    }
                }
                
                if ($debeIgnorar) {
                    $contadores['filtradas_por_codigo']++;
                    continue;
                }
                
                // FILTRO 2: Estados
                $estadosConFecha = $estadosVenta->filter(function($estado) {
                    return !empty($estado->FechaEstado) && !is_null($estado->EstadoComercializacion);
                });
                
                if ($estadosConFecha->isEmpty()) {
                    $contadores['sin_estados']++;
                    continue;
                }
                
                $ultimoEstado = $estadosConFecha->sortByDesc('FechaEstado')->first();
                
                if (!in_array($ultimoEstado->EstadoComercializacion, $validStates)) {
                    $contadores['estado_invalido']++;
                    continue;
                }
                
                // Extraer año y mes
                try {
                    $fechaInicio = Carbon::parse($venta->FechaInicio);
                    $año = $fechaInicio->year;
                    $mes = $fechaInicio->month;
                    $claveAñoMes = $fechaInicio->format('Y-m');
                    
                    // Inicializar año si no existe
                    if (!isset($ventasPorAño[$año])) {
                        $ventasPorAño[$año] = [
                            'año' => $año,
                            'total' => 0,
                            'cantidad_ventas' => 0,
                            'meses_activos' => []
                        ];
                    }
                    
                    // Inicializar detalle año-mes
                    if (!isset($detallesPorAñoMes[$claveAñoMes])) {
                        $detallesPorAñoMes[$claveAñoMes] = [
                            'año' => $año,
                            'mes' => $mes,
                            'nombre_mes' => $fechaInicio->locale('es')->isoFormat('MMMM'),
                            'total' => 0,
                            'cantidad_ventas' => 0
                        ];
                    }
                    
                    $valor = floatval($venta->ValorFinalComercializacion ?? 0);
                    
                    // Sumar a totales anuales
                    $ventasPorAño[$año]['total'] += $valor;
                    $ventasPorAño[$año]['cantidad_ventas']++;
                    $ventasPorAño[$año]['meses_activos'][$mes] = true;
                    
                    // Sumar a detalles mensuales
                    $detallesPorAñoMes[$claveAñoMes]['total'] += $valor;
                    $detallesPorAñoMes[$claveAñoMes]['cantidad_ventas']++;
                    
                    $contadores['validas_sumadas']++;
                    
                } catch (\Exception $e) {
                    Log::warning("⚠️ Error parseando fecha en resumen", [
                        'fecha' => $venta->FechaInicio,
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }
            
            // Formatear resultados anuales
            $resumenAnual = [];
            $totalGlobal = 0;
            
            foreach ($ventasPorAño as $año => $datos) {
                $totalFormateado = '$' . number_format($datos['total'], 0, ',', '.');
                $totalGlobal += $datos['total'];
                
                // Encontrar el mejor mes del año
                $mejorMes = $this->encontrarMejorMes($detallesPorAñoMes, $año);
                
                $resumenAnual[] = [
                    'año' => $año,
                    'total_bruto' => $datos['total'],
                    'total_formateado' => $totalFormateado,
                    'cantidad_ventas' => $datos['cantidad_ventas'],
                    'meses_con_actividad' => count($datos['meses_activos']),
                    'promedio_mensual' => count($datos['meses_activos']) > 0 
                        ? round($datos['total'] / count($datos['meses_activos']), 2) : 0,
                    'promedio_por_venta' => $datos['cantidad_ventas'] > 0 
                        ? round($datos['total'] / $datos['cantidad_ventas'], 2) : 0,
                    'mejor_mes' => $mejorMes
                ];
            }
            
            // Ordenar por año
            usort($resumenAnual, function($a, $b) {
                return $a['año'] <=> $b['año'];
            });
            
            $totalGlobalFormateado = '$' . number_format($totalGlobal, 0, ',', '.');
            $tiempoTotal = round(microtime(true) - $startTime, 2);
            
            Log::info("🎉 RESUMEN ANUAL COMPLETADO", [
                'años_procesados' => count($ventasPorAño),
                'total_global' => $totalGlobal,
                'tiempo_segundos' => $tiempoTotal
            ]);
            
            return response()->json([
                'success' => true,
                'mensaje' => 'Resumen de ventas por año completado',
                'total_global_bruto' => $totalGlobal,
                'total_global_formateado' => $totalGlobalFormateado,
                'cantidad_años' => count($ventasPorAño),
                'filtros_aplicados' => [
                    'codigos_excluidos' => $ignorePrefixes,
                    'estados_validos' => $validStates
                ],
                'resumen_por_año' => $resumenAnual,
                'estadisticas' => $contadores,
                'tiempo_proceso_segundos' => $tiempoTotal,
                'timestamp' => now()->toISOString()
            ]);
            
        } catch (\Exception $e) {
            Log::error('💥 ERROR EN RESUMEN ANUAL', [
                'mensaje' => $e->getMessage(),
                'linea' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'mensaje' => 'Error al generar resumen anual',
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString()
            ], 500);
        }
    }

    /**
     * MÉTODO AUXILIAR: Encontrar el mejor mes de un año específico
     */
    private function encontrarMejorMes(array $detallesPorAñoMes, int $año)
    {
        $mejorMes = null;
        $mejorTotal = 0;
        
        foreach ($detallesPorAñoMes as $clave => $datos) {
            if ($datos['año'] == $año && $datos['total'] > $mejorTotal) {
                $mejorTotal = $datos['total'];
                $mejorMes = [
                    'mes' => $datos['mes'],
                    'nombre' => $datos['nombre_mes'],
                    'total_bruto' => $datos['total'],
                    'total_formateado' => '$' . number_format($datos['total'], 0, ',', '.'),
                    'cantidad_ventas' => $datos['cantidad_ventas']
                ];
            }
        }
        
        return $mejorMes;
    }
}
