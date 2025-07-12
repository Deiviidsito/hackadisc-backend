<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * CONTROLADOR ANÁLISIS TIEMPO ENTRE ETAPAS DE VENTA
 * 
 * Especializado en calcular tiempos promedio desde estado 0 (En proceso) hasta estado 1 (Terminada)
 * 
 * FUNCIONALIDADES PRINCIPALES:
 * - Cálculo tiempo promedio entre etapas con estadísticas completas
 * - Análisis por cliente con métricas individuales  
 * - Distribución de tiempos en rangos predefinidos
 * - Filtros por año, mes, exclusión de prefijos (ADI, OTR, SPD)
 * - Manejo de casos complejos con múltiples transiciones de estado
 * 
 * LÓGICA DE CÁLCULO:
 * - Toma primer estado 0 como fecha inicio del proceso
 * - Toma último estado 1 como fecha final (maneja errores humanos)
 * - Calcula diferencia en días entre ambas fechas
 * - Excluye comercializaciones con prefijos ADI*, OTR*, SPD*
 * - Solo considera estados válidos: {0, 1, 3}
 */
class TiempoEtapasController extends Controller
{
    /**
     * CALCULAR TIEMPO PROMEDIO ENTRE ETAPAS
     * 
     * Calcula el tiempo desde estado 0 (En proceso) hasta estado 1 (Terminada)
     * con estadísticas completas y filtros avanzados
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function calcularTiempoPromedioEtapas(Request $request)
    {
        try {
            set_time_limit(300);
            ini_set('memory_limit', '1G');
            
            $startTime = microtime(true);
            Log::info("⏱️ INICIANDO CÁLCULO TIEMPO PROMEDIO ENTRE ETAPAS");
            
            // Parámetros de filtrado
            $año = $request->input('año', null);
            $mesInicio = $request->input('mes_inicio', 1);
            $mesFin = $request->input('mes_fin', 12);
            $incluirDetalles = $request->input('incluir_detalles', false);
            
            Log::info("🔍 Filtros aplicados - Año: " . ($año ?? 'todos') . ", Meses: {$mesInicio}-{$mesFin}");
            
            // Query optimizada para obtener ventas con estados
            $queryBase = "
                SELECT DISTINCT
                    v.idVenta as venta_id,
                    v.idComercializacion as id_comercializacion,
                    v.CodigoCotizacion as codigo_cotizacion,
                    v.FechaInicio as fecha_inicio,
                    v.ClienteId as cliente_id,
                    v.NombreCliente as nombre_cliente,
                    v.ValorFinalComercializacion as valor_final_comercializacion,
                    v.CorreoCreador as correo_creador,
                    
                    -- Estados 0: Primer ocurrencia (fecha más temprana)
                    MIN(CASE WHEN hev.estado_venta_id = 0 THEN hev.fecha END) as fecha_primer_estado_0,
                    
                    -- Estados 1: Última ocurrencia (fecha más tardía) 
                    MAX(CASE WHEN hev.estado_venta_id = 1 THEN hev.fecha END) as fecha_ultimo_estado_1
                    
                FROM ventas v
                LEFT JOIN historial_estados_venta hev ON v.idVenta = hev.venta_id
                WHERE 1=1
                    -- Excluir prefijos específicos
                    AND v.CodigoCotizacion NOT LIKE 'ADI%'
                    AND v.CodigoCotizacion NOT LIKE 'OTR%' 
                    AND v.CodigoCotizacion NOT LIKE 'SPD%'
                    -- Solo estados válidos
                    AND (hev.estado_venta_id IS NULL OR hev.estado_venta_id IN (0, 1, 3))
            ";
            
            // Aplicar filtro de año si se especifica
            if ($año) {
                $queryBase .= " AND YEAR(v.FechaInicio) = {$año}";
                
                // Aplicar filtro de meses
                $queryBase .= " AND MONTH(v.FechaInicio) BETWEEN {$mesInicio} AND {$mesFin}";
            }
            
            $queryBase .= "
                GROUP BY v.idVenta, v.idComercializacion, v.CodigoCotizacion, v.FechaInicio, 
                         v.ClienteId, v.NombreCliente, v.ValorFinalComercializacion, v.CorreoCreador
                HAVING fecha_primer_estado_0 IS NOT NULL 
                   AND fecha_ultimo_estado_1 IS NOT NULL
                   AND fecha_ultimo_estado_1 >= fecha_primer_estado_0
                ORDER BY v.FechaInicio DESC
            ";
            
            Log::info("🔍 Ejecutando consulta optimizada...");
            $ventasConTiempo = DB::select($queryBase);
            
            Log::info("📊 Ventas encontradas con tiempo calculable: " . count($ventasConTiempo));
            
            if (empty($ventasConTiempo)) {
                return response()->json([
                    'success' => true,
                    'mensaje' => 'No se encontraron ventas con tiempo calculable para los filtros especificados',
                    'estadisticas' => [
                        'total_ventas_analizadas' => 0,
                        'ventas_con_tiempo_calculado' => 0,
                        'tiempo_promedio_dias' => 0,
                        'tiempo_mediano_dias' => 0,
                        'tiempo_min_dias' => null,
                        'tiempo_max_dias' => null
                    ],
                    'filtros_aplicados' => [
                        'año' => $año,
                        'mes_inicio' => $mesInicio,
                        'mes_fin' => $mesFin
                    ]
                ]);
            }
            
            // Procesar resultados y calcular estadísticas
            $tiemposCalculados = [];
            $diasArray = [];
            
            foreach ($ventasConTiempo as $venta) {
                $fechaInicio = Carbon::parse($venta->fecha_primer_estado_0);
                $fechaFin = Carbon::parse($venta->fecha_ultimo_estado_1);
                $diasTranscurridos = $fechaInicio->diffInDays($fechaFin);
                
                $diasArray[] = $diasTranscurridos;
                
                if ($incluirDetalles) {
                    $tiemposCalculados[] = [
                        'venta_id' => $venta->venta_id,
                        'id_comercializacion' => $venta->id_comercializacion,
                        'codigo_cotizacion' => $venta->codigo_cotizacion,
                        'cliente_id' => $venta->cliente_id,
                        'nombre_cliente' => $venta->nombre_cliente,
                        'valor_comercializacion' => $venta->valor_final_comercializacion,
                        'fecha_inicio_proceso' => $venta->fecha_primer_estado_0,
                        'fecha_fin_proceso' => $venta->fecha_ultimo_estado_1,
                        'dias_transcurridos' => $diasTranscurridos,
                        'fecha_inicio_venta' => $venta->fecha_inicio,
                        'correo_creador' => $venta->correo_creador
                    ];
                }
            }
            
            // Calcular estadísticas
            $totalVentas = count($ventasConTiempo);
            $tiempoPromedio = round(array_sum($diasArray) / $totalVentas, 2);
            
            sort($diasArray);
            $tiempoMediano = $totalVentas % 2 == 0 
                ? ($diasArray[$totalVentas/2 - 1] + $diasArray[$totalVentas/2]) / 2
                : $diasArray[floor($totalVentas/2)];
            
            $tiempoMin = min($diasArray);
            $tiempoMax = max($diasArray);
            
            $executionTime = microtime(true) - $startTime;
            
            $response = [
                'success' => true,
                'mensaje' => 'Cálculo de tiempo promedio entre etapas completado',
                'estadisticas' => [
                    'total_ventas_analizadas' => $totalVentas,
                    'ventas_con_tiempo_calculado' => $totalVentas,
                    'tiempo_promedio_dias' => $tiempoPromedio,
                    'tiempo_mediano_dias' => round($tiempoMediano, 2),
                    'tiempo_min_dias' => $tiempoMin,
                    'tiempo_max_dias' => $tiempoMax,
                    'desviacion_estandar_dias' => round($this->calcularDesviacionEstandar($diasArray, $tiempoPromedio), 2)
                ],
                'filtros_aplicados' => [
                    'año' => $año,
                    'mes_inicio' => $mesInicio,
                    'mes_fin' => $mesFin,
                    'incluir_detalles' => $incluirDetalles,
                    'prefijos_excluidos' => ['ADI', 'OTR', 'SPD'],
                    'estados_considerados' => [0 => 'En proceso', 1 => 'Terminada']
                ],
                'tiempo_ejecucion_segundos' => round($executionTime, 2)
            ];
            
            if ($incluirDetalles) {
                $response['detalles_ventas'] = $tiemposCalculados;
            }
            
            Log::info("✅ Cálculo completado - Promedio: {$tiempoPromedio} días, Total ventas: {$totalVentas}");
            
            return response()->json($response);
            
        } catch (\Exception $e) {
            Log::error("❌ Error en cálculo de tiempo entre etapas: " . $e->getMessage());
            Log::error($e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor',
                'mensaje' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ANALIZAR TIEMPOS POR CLIENTE
     * 
     * Calcula el tiempo promedio entre etapas agrupado por cliente
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function analizarTiemposPorCliente(Request $request)
    {
        try {
            set_time_limit(300);
            ini_set('memory_limit', '1G');
            
            Log::info("👥 INICIANDO ANÁLISIS TIEMPOS POR CLIENTE");
            
            // Obtener datos básicos del análisis general
            $request->merge(['incluir_detalles' => true]);
            $analisisGeneral = $this->calcularTiempoPromedioEtapas($request);
            $data = $analisisGeneral->getData(true);
            
            if (!$data['success'] || !isset($data['detalles_ventas'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'No se pudieron obtener los datos base'
                ], 400);
            }
            
            $ventasConTiempo = $data['detalles_ventas'];
            
            // Agrupar por cliente
            $tiemposPorCliente = [];
            foreach ($ventasConTiempo as $venta) {
                $clienteId = $venta['cliente_id'];
                $nombreCliente = $venta['nombre_cliente'];
                $dias = $venta['dias_transcurridos'];
                
                if (!isset($tiemposPorCliente[$clienteId])) {
                    $tiemposPorCliente[$clienteId] = [
                        'cliente_id' => $clienteId,
                        'nombre_cliente' => $nombreCliente,
                        'total_ventas' => 0,
                        'dias_acumulados' => 0,
                        'tiempo_min_dias' => PHP_INT_MAX,
                        'tiempo_max_dias' => 0,
                        'tiempos_individuales' => []
                    ];
                }
                
                $tiemposPorCliente[$clienteId]['total_ventas']++;
                $tiemposPorCliente[$clienteId]['dias_acumulados'] += $dias;
                $tiemposPorCliente[$clienteId]['tiempo_min_dias'] = min($tiemposPorCliente[$clienteId]['tiempo_min_dias'], $dias);
                $tiemposPorCliente[$clienteId]['tiempo_max_dias'] = max($tiemposPorCliente[$clienteId]['tiempo_max_dias'], $dias);
                $tiemposPorCliente[$clienteId]['tiempos_individuales'][] = $dias;
            }
            
            // Calcular promedios y estadísticas por cliente
            $estadisticasClientes = [];
            foreach ($tiemposPorCliente as $cliente) {
                $tiempoPromedio = round($cliente['dias_acumulados'] / $cliente['total_ventas'], 2);
                
                $estadisticasClientes[] = [
                    'cliente_id' => $cliente['cliente_id'],
                    'nombre_cliente' => $cliente['nombre_cliente'],
                    'total_ventas' => $cliente['total_ventas'],
                    'tiempo_promedio_dias' => $tiempoPromedio,
                    'tiempo_min_dias' => $cliente['tiempo_min_dias'],
                    'tiempo_max_dias' => $cliente['tiempo_max_dias'],
                    'desviacion_estandar_dias' => round($this->calcularDesviacionEstandar($cliente['tiempos_individuales'], $tiempoPromedio), 2)
                ];
            }
            
            // Ordenar por tiempo promedio descendente
            usort($estadisticasClientes, function($a, $b) {
                return $b['tiempo_promedio_dias'] <=> $a['tiempo_promedio_dias'];
            });
            
            return response()->json([
                'success' => true,
                'mensaje' => 'Análisis de tiempos por cliente completado',
                'total_clientes_analizados' => count($estadisticasClientes),
                'estadisticas_generales' => $data['estadisticas'],
                'tiempos_por_cliente' => $estadisticasClientes,
                'filtros_aplicados' => $data['filtros_aplicados']
            ]);
            
        } catch (\Exception $e) {
            Log::error("❌ Error en análisis por cliente: " . $e->getMessage());
            Log::error($e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor',
                'mensaje' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * OBTENER DISTRIBUCIÓN DE TIEMPOS
     * 
     * Agrupa los tiempos en rangos predefinidos para análisis de distribución
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function obtenerDistribucionTiempos(Request $request)
    {
        try {
            set_time_limit(300);
            ini_set('memory_limit', '1G');
            
            Log::info("📊 INICIANDO ANÁLISIS DISTRIBUCIÓN DE TIEMPOS");
            
            // Obtener datos básicos del análisis general
            $request->merge(['incluir_detalles' => true]);
            $analisisGeneral = $this->calcularTiempoPromedioEtapas($request);
            $data = $analisisGeneral->getData(true);
            
            if (!$data['success'] || !isset($data['detalles_ventas'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'No se pudieron obtener los datos base'
                ], 400);
            }
            
            $ventasConTiempo = $data['detalles_ventas'];
            
            // Definir rangos de tiempo (en días)
            $rangos = [
                '0-7' => ['min' => 0, 'max' => 7, 'descripcion' => 'Muy rápido (1 semana)'],
                '8-15' => ['min' => 8, 'max' => 15, 'descripcion' => 'Rápido (2 semanas)'],
                '16-30' => ['min' => 16, 'max' => 30, 'descripcion' => 'Normal (1 mes)'],
                '31-60' => ['min' => 31, 'max' => 60, 'descripcion' => 'Lento (2 meses)'],
                '61-90' => ['min' => 61, 'max' => 90, 'descripcion' => 'Muy lento (3 meses)'],
                '91+' => ['min' => 91, 'max' => PHP_INT_MAX, 'descripcion' => 'Extremadamente lento (+3 meses)']
            ];
            
            // Inicializar contadores
            $distribucion = [];
            foreach ($rangos as $rango => $config) {
                $distribucion[$rango] = [
                    'rango' => $rango,
                    'descripcion' => $config['descripcion'],
                    'cantidad' => 0,
                    'porcentaje' => 0,
                    'valor_total_comercializaciones' => 0
                ];
            }
            
            $totalVentas = count($ventasConTiempo);
            
            // Clasificar cada venta en su rango correspondiente
            foreach ($ventasConTiempo as $venta) {
                $dias = $venta['dias_transcurridos'];
                $valor = $venta['valor_comercializacion'] ?? 0;
                
                foreach ($rangos as $rango => $config) {
                    if ($dias >= $config['min'] && $dias <= $config['max']) {
                        $distribucion[$rango]['cantidad']++;
                        $distribucion[$rango]['valor_total_comercializaciones'] += $valor;
                        break;
                    }
                }
            }
            
            // Calcular porcentajes
            foreach ($distribucion as $rango => &$datos) {
                $datos['porcentaje'] = $totalVentas > 0 
                    ? round(($datos['cantidad'] / $totalVentas) * 100, 2) 
                    : 0;
                $datos['valor_total_comercializaciones'] = number_format($datos['valor_total_comercializaciones'], 0, ',', '.');
            }
            
            return response()->json([
                'success' => true,
                'mensaje' => 'Análisis de distribución de tiempos completado',
                'estadisticas_generales' => $data['estadisticas'],
                'distribucion_por_rangos' => array_values($distribucion),
                'total_ventas_analizadas' => $totalVentas,
                'filtros_aplicados' => $data['filtros_aplicados']
            ]);
            
        } catch (\Exception $e) {
            Log::error("❌ Error en distribución de tiempos: " . $e->getMessage());
            Log::error($e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor',
                'mensaje' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * MÉTODO HELPER: Calcular desviación estándar
     */
    private function calcularDesviacionEstandar($valores, $promedio)
    {
        if (count($valores) <= 1) return 0;
        
        $sumaCuadrados = 0;
        foreach ($valores as $valor) {
            $sumaCuadrados += pow($valor - $promedio, 2);
        }
        
        return sqrt($sumaCuadrados / count($valores));
    }
    
    /**
     * MÉTODO DE PRUEBA: Verificar estructura de base de datos
     */
    public function verificarBaseDatos(Request $request)
    {
        try {
            Log::info("🔍 VERIFICANDO ESTRUCTURA DE BASE DE DATOS");
            
            // Probar conexión básica
            $ventas = DB::select("SELECT COUNT(*) as total FROM ventas");
            $clientes = DB::select("SELECT COUNT(*) as total FROM clientes");
            $estados = DB::select("SELECT COUNT(*) as total FROM estado_ventas");
            $historial = DB::select("SELECT COUNT(*) as total FROM historial_estados_venta");
            
            // Mostrar algunos registros de ejemplo
            $ventasEjemplo = DB::select("SELECT idVenta, codigo_cotizacion, fecha_inicio, cliente_id FROM ventas LIMIT 5");
            $estadosEjemplo = DB::select("SELECT id, nombre FROM estado_ventas ORDER BY id");
            $historialEjemplo = DB::select("SELECT venta_id, estado_venta_id, fecha FROM historial_estados_venta LIMIT 10");
            
            return response()->json([
                'success' => true,
                'mensaje' => 'Verificación de base de datos completada',
                'estadisticas_tablas' => [
                    'ventas' => $ventas[0]->total,
                    'clientes' => $clientes[0]->total,
                    'estado_ventas' => $estados[0]->total,
                    'historial_estados_venta' => $historial[0]->total
                ],
                'ejemplos' => [
                    'ventas' => $ventasEjemplo,
                    'estados' => $estadosEjemplo,
                    'historial' => $historialEjemplo
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error("❌ Error en verificación: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Error en verificación de base de datos',
                'mensaje' => $e->getMessage()
            ], 500);
        }
    }
}
