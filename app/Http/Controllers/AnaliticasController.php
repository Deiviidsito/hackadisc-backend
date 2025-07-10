<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DatosEstadistica;
use App\Models\Venta;
use App\Models\Factura;
use App\Models\HistorialEstadoFactura;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AnaliticasController extends Controller
{
    /**
     * Generar estadísticas de tiempo de pago para todas las facturas
     */
    public function generarEstadisticasPago()
    {
        try {
            // Aumentar tiempo límite de ejecución para datasets grandes
            set_time_limit(600); // 10 minutos
            ini_set('memory_limit', '512M'); // Aumentar memoria disponible
            
            // Limpiar tabla de estadísticas para regenerar
            DatosEstadistica::truncate();
            
            $estadisticasGeneradas = 0;
            
            // Procesar facturas en chunks para optimizar memoria
            $chunkSize = 100; // Procesar 100 facturas a la vez
            $totalFacturas = Factura::count();
            
            Log::info("Iniciando generación de estadísticas para {$totalFacturas} facturas");
            
            // Procesar facturas en chunks
            Factura::chunk($chunkSize, function ($facturas) use (&$estadisticasGeneradas) {
                $datosParaInsertar = [];
                
                foreach ($facturas as $factura) {
                    // Obtener el historial de estados de esta factura de manera optimizada
                    $historiales = HistorialEstadoFactura::where('factura_numero', $factura->numero)
                        ->orderBy('fecha')
                        ->get();
                    
                    if ($historiales->isEmpty()) {
                        continue; // No hay historial para esta factura
                    }
                    
                    $datosEstadistica = $this->procesarFacturaParaEstadisticas($factura, $historiales);
                    
                    if ($datosEstadistica) {
                        $datosParaInsertar[] = $datosEstadistica;
                        $estadisticasGeneradas++;
                    }
                }
                
                // Insertar en lotes para mejor rendimiento
                if (!empty($datosParaInsertar)) {
                    DatosEstadistica::insert($datosParaInsertar);
                    Log::info("Procesadas " . count($datosParaInsertar) . " facturas. Total procesadas: {$estadisticasGeneradas}");
                }
            });
            
            return response()->json([
                'success' => true,
                'message' => 'Estadísticas de pago generadas correctamente.',
                'estadisticas_generadas' => $estadisticasGeneradas,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error al generar estadísticas', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error al generar estadísticas: ' . $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Procesar una factura individual para generar datos de estadísticas
     */
    private function procesarFacturaParaEstadisticas($factura, $historiales)
    {
        // Buscar el primer estado 1 (emisión de factura)
        $fechaEmision = null;
        foreach ($historiales as $historial) {
            if ($historial->estado_id == 1) {
                $fechaEmision = $this->parsearFecha($historial->fecha);
                if ($fechaEmision) {
                    break;
                }
            }
        }
        
        if (!$fechaEmision) {
            return null; // No encontramos estado 1, saltamos esta factura
        }
        
        // Buscar el último estado 3 (factura pagada)
        $fechaPago = null;
        $facturaPagada = false;
        
        // Recorrer desde el final para encontrar el último estado 3
        for ($i = count($historiales) - 1; $i >= 0; $i--) {
            if ($historiales[$i]->estado_id == 3) {
                $fechaPago = $this->parsearFecha($historiales[$i]->fecha);
                if ($fechaPago) {
                    $facturaPagada = true;
                    break;
                }
            }
        }
        
        // Calcular días y meses si está pagada
        $diasParaPago = null;
        $mesesParaPago = null;
        
        if ($facturaPagada && $fechaPago) {
            $diasParaPago = $fechaEmision->diffInDays($fechaPago);
            $mesesParaPago = round($fechaEmision->diffInDays($fechaPago) / 30.44, 2); // Promedio de días por mes
        }
        
        // Obtener datos de la venta relacionada
        $venta = null;
        $clienteNombre = null;
        $idComercializacion = null;
        
        // Buscar la venta por el idComercializacion en los historiales
        $historialConId = $historiales->first();
        if ($historialConId && $historialConId->idComercializacion) {
            $idComercializacion = $historialConId->idComercializacion;
            $venta = Venta::where('idComercializacion', $idComercializacion)->first();
            if ($venta) {
                $clienteNombre = $venta->NombreCliente;
            }
        }
        
        return [
            'idComercializacion' => $idComercializacion,
            'factura_numero' => $factura->numero,
            'fecha_emision_factura' => $fechaEmision,
            'fecha_pago_final' => $fechaPago,
            'dias_para_pago' => $diasParaPago,
            'meses_para_pago' => $mesesParaPago,
            'factura_pagada' => $facturaPagada,
            'monto_factura' => null, // Se puede calcular después si es necesario
            'cliente_nombre' => $clienteNombre,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
    
    /**
     * Obtener resumen de estadísticas de pago
     */
    public function obtenerResumenEstadisticas()
    {
        try {
            // Aumentar tiempo límite para cálculos complejos
            set_time_limit(300); // 5 minutos
            
            $totalFacturas = DatosEstadistica::count();
            $facturasPagadas = DatosEstadistica::where('factura_pagada', true)->count();
            $facturasPendientes = $totalFacturas - $facturasPagadas;
            
            // Estadísticas básicas de tiempo de pago (solo facturas pagadas)
            $estadisticasBasicas = DatosEstadistica::where('factura_pagada', true)
                ->whereNotNull('dias_para_pago')
                ->selectRaw('
                    AVG(dias_para_pago) as promedio_dias,
                    AVG(meses_para_pago) as promedio_meses,
                    MIN(dias_para_pago) as minimo_dias,
                    MAX(dias_para_pago) as maximo_dias,
                    COUNT(*) as total_pagadas,
                    STDDEV(dias_para_pago) as desviacion_estandar_dias
                ')
                ->first();
            
            // Para estadísticas avanzadas, obtenemos los tiempos ordenados
            $tiemposDias = DatosEstadistica::where('factura_pagada', true)
                ->whereNotNull('dias_para_pago')
                ->orderBy('dias_para_pago')
                ->pluck('dias_para_pago')
                ->toArray();
            
            // Calcular estadísticas avanzadas
            $estadisticasAvanzadas = $this->calcularEstadisticasAvanzadas($tiemposDias);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'resumen_general' => [
                        'total_facturas' => $totalFacturas,
                        'facturas_pagadas' => $facturasPagadas,
                        'facturas_pendientes' => $facturasPendientes,
                        'porcentaje_pagadas' => $totalFacturas > 0 ? round(($facturasPagadas / $totalFacturas) * 100, 2) : 0,
                    ],
                    'estadisticas_tiempo_pago' => [
                        'promedio' => [
                            'dias' => round($estadisticasBasicas->promedio_dias ?? 0, 2),
                            'meses' => round($estadisticasBasicas->promedio_meses ?? 0, 2),
                        ],
                        'mediana_dias' => $estadisticasAvanzadas['mediana'],
                        'moda_dias' => $estadisticasAvanzadas['moda'],
                        'minimo_dias' => $estadisticasBasicas->minimo_dias ?? 0,
                        'maximo_dias' => $estadisticasBasicas->maximo_dias ?? 0,
                        'desviacion_estandar_dias' => round($estadisticasBasicas->desviacion_estandar_dias ?? 0, 2),
                        'percentiles' => $estadisticasAvanzadas['percentiles'],
                        'iqr' => [
                            'q1' => $estadisticasAvanzadas['percentiles']['p25'],
                            'q3' => $estadisticasAvanzadas['percentiles']['p75'],
                            'valor' => $estadisticasAvanzadas['iqr']
                        ],
                        'total_facturas_analizadas' => $estadisticasBasicas->total_pagadas ?? 0
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error al obtener resumen estadísticas', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas: ' . $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Obtener estadísticas de pago agrupadas por cliente
     */
    public function obtenerEstadisticasPorCliente(Request $request)
    {
        try {
            set_time_limit(180); // 3 minutos
            
            $limite = $request->get('limit', 50); // Límite por defecto
            $offset = $request->get('offset', 0);
            $ordenarPor = $request->get('sort_by', 'promedio_dias'); // promedio_dias, total_facturas, porcentaje_pagadas
            $orden = $request->get('order', 'desc'); // asc, desc
            
            // Obtener estadísticas agrupadas por cliente usando consultas optimizadas
            $estadisticasClientes = DatosEstadistica::selectRaw('
                cliente_nombre,
                COUNT(*) as total_facturas,
                SUM(CASE WHEN factura_pagada = 1 THEN 1 ELSE 0 END) as facturas_pagadas,
                AVG(CASE WHEN factura_pagada = 1 THEN dias_para_pago END) as promedio_dias,
                MIN(CASE WHEN factura_pagada = 1 THEN dias_para_pago END) as minimo_dias,
                MAX(CASE WHEN factura_pagada = 1 THEN dias_para_pago END) as maximo_dias,
                STDDEV(CASE WHEN factura_pagada = 1 THEN dias_para_pago END) as desviacion_estandar
            ')
            ->whereNotNull('cliente_nombre')
            ->groupBy('cliente_nombre')
            ->orderBy($ordenarPor, $orden)
            ->offset($offset)
            ->limit($limite)
            ->get();
            
            // Procesar los resultados
            $resultados = $estadisticasClientes->map(function ($cliente) {
                $porcentajePagadas = $cliente->total_facturas > 0 
                    ? round(($cliente->facturas_pagadas / $cliente->total_facturas) * 100, 2) 
                    : 0;
                
                return [
                    'cliente_nombre' => $cliente->cliente_nombre,
                    'total_facturas' => $cliente->total_facturas,
                    'facturas_pagadas' => $cliente->facturas_pagadas,
                    'facturas_pendientes' => $cliente->total_facturas - $cliente->facturas_pagadas,
                    'porcentaje_pagadas' => $porcentajePagadas,
                    'promedio_dias_pago' => round($cliente->promedio_dias ?? 0, 2),
                    'minimo_dias_pago' => $cliente->minimo_dias ?? null,
                    'maximo_dias_pago' => $cliente->maximo_dias ?? null,
                    'desviacion_estandar_dias' => round($cliente->desviacion_estandar ?? 0, 2),
                ];
            });
            
            // Obtener total de clientes para paginación
            $totalClientes = DatosEstadistica::whereNotNull('cliente_nombre')
                ->distinct('cliente_nombre')
                ->count('cliente_nombre');
            
            return response()->json([
                'success' => true,
                'data' => $resultados,
                'pagination' => [
                    'total' => $totalClientes,
                    'limit' => $limite,
                    'offset' => $offset,
                    'has_more' => ($offset + $limite) < $totalClientes
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error al obtener estadísticas por cliente', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas por cliente: ' . $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Obtener análisis de tendencias temporales de pagos
     */
    public function obtenerTendenciasTemporales(Request $request)
    {
        try {
            set_time_limit(180); // 3 minutos
            
            $agrupacion = $request->get('group_by', 'month'); // month, quarter, year
            $año = $request->get('year', null);
            
            $query = DatosEstadistica::where('factura_pagada', true)
                ->whereNotNull('fecha_pago_final');
            
            // Filtrar por año si se especifica
            if ($año) {
                $query->whereYear('fecha_pago_final', $año);
            }
            
            // Construir la consulta según el tipo de agrupación
            switch ($agrupacion) {
                case 'month':
                    $resultados = $query->selectRaw('
                        YEAR(fecha_pago_final) as año,
                        MONTH(fecha_pago_final) as mes,
                        COUNT(*) as facturas_pagadas,
                        AVG(dias_para_pago) as promedio_dias,
                        MIN(dias_para_pago) as minimo_dias,
                        MAX(dias_para_pago) as maximo_dias,
                        STDDEV(dias_para_pago) as desviacion_estandar
                    ')
                    ->groupBy('año', 'mes')
                    ->orderBy('año')
                    ->orderBy('mes')
                    ->get();
                    break;
                    
                case 'quarter':
                    $resultados = $query->selectRaw('
                        YEAR(fecha_pago_final) as año,
                        QUARTER(fecha_pago_final) as trimestre,
                        COUNT(*) as facturas_pagadas,
                        AVG(dias_para_pago) as promedio_dias,
                        MIN(dias_para_pago) as minimo_dias,
                        MAX(dias_para_pago) as maximo_dias,
                        STDDEV(dias_para_pago) as desviacion_estandar
                    ')
                    ->groupBy('año', 'trimestre')
                    ->orderBy('año')
                    ->orderBy('trimestre')
                    ->get();
                    break;
                    
                case 'year':
                    $resultados = $query->selectRaw('
                        YEAR(fecha_pago_final) as año,
                        COUNT(*) as facturas_pagadas,
                        AVG(dias_para_pago) as promedio_dias,
                        MIN(dias_para_pago) as minimo_dias,
                        MAX(dias_para_pago) as maximo_dias,
                        STDDEV(dias_para_pago) as desviacion_estandar
                    ')
                    ->groupBy('año')
                    ->orderBy('año')
                    ->get();
                    break;
                    
                default:
                    throw new \Exception('Tipo de agrupación no válido. Use: month, quarter, year');
            }
            
            // Formatear los resultados
            $tendencias = $resultados->map(function ($item) use ($agrupacion) {
                $periodo = $item->año;
                
                if ($agrupacion === 'month') {
                    $periodo .= '-' . str_pad($item->mes, 2, '0', STR_PAD_LEFT);
                } elseif ($agrupacion === 'quarter') {
                    $periodo .= '-Q' . $item->trimestre;
                }
                
                return [
                    'periodo' => $periodo,
                    'facturas_pagadas' => $item->facturas_pagadas,
                    'promedio_dias_pago' => round($item->promedio_dias, 2),
                    'minimo_dias_pago' => $item->minimo_dias,
                    'maximo_dias_pago' => $item->maximo_dias,
                    'desviacion_estandar_dias' => round($item->desviacion_estandar ?? 0, 2),
                ];
            });
            
            return response()->json([
                'success' => true,
                'data' => [
                    'agrupacion' => $agrupacion,
                    'año_filtro' => $año,
                    'tendencias' => $tendencias,
                    'total_periodos' => $tendencias->count()
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error al obtener tendencias temporales', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener tendencias temporales: ' . $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Obtener análisis de distribución de tiempos de pago por rangos
     */
    public function obtenerDistribucionPagos(Request $request)
    {
        try {
            set_time_limit(180); // 3 minutos
            
            // Rangos personalizables
            $rangosPorDefecto = [
                ['min' => 0, 'max' => 7, 'etiqueta' => '0-7 días (Muy rápido)'],
                ['min' => 8, 'max' => 15, 'etiqueta' => '8-15 días (Rápido)'],
                ['min' => 16, 'max' => 30, 'etiqueta' => '16-30 días (Normal)'],
                ['min' => 31, 'max' => 60, 'etiqueta' => '31-60 días (Lento)'],
                ['min' => 61, 'max' => 90, 'etiqueta' => '61-90 días (Muy lento)'],
                ['min' => 91, 'max' => null, 'etiqueta' => '90+ días (Crítico)']
            ];
            
            // Obtener datos de pagos
            $facturasPagadas = DatosEstadistica::where('factura_pagada', true)
                ->whereNotNull('dias_para_pago')
                ->get();
            
            $totalFacturas = $facturasPagadas->count();
            
            if ($totalFacturas === 0) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'distribucion' => [],
                        'total_facturas_analizadas' => 0,
                        'mensaje' => 'No hay facturas pagadas para analizar'
                    ]
                ]);
            }
            
            // Calcular distribución por rangos
            $distribucion = [];
            
            foreach ($rangosPorDefecto as $rango) {
                $query = $facturasPagadas->where('dias_para_pago', '>=', $rango['min']);
                
                if ($rango['max'] !== null) {
                    $query = $query->where('dias_para_pago', '<=', $rango['max']);
                }
                
                $cantidad = $query->count();
                $porcentaje = round(($cantidad / $totalFacturas) * 100, 2);
                
                // Calcular estadísticas del rango
                $tiemposRango = $query->pluck('dias_para_pago')->toArray();
                $promedioRango = count($tiemposRango) > 0 ? round(array_sum($tiemposRango) / count($tiemposRango), 2) : 0;
                
                $distribucion[] = [
                    'rango' => $rango['etiqueta'],
                    'min_dias' => $rango['min'],
                    'max_dias' => $rango['max'],
                    'cantidad_facturas' => $cantidad,
                    'porcentaje' => $porcentaje,
                    'promedio_dias_rango' => $promedioRango
                ];
            }
            
            // Calcular estadísticas adicionales de la distribución
            $tiempoPromedio = round($facturasPagadas->avg('dias_para_pago'), 2);
            $desviacionEstandar = $this->calcularDesviacionEstandar($facturasPagadas->pluck('dias_para_pago')->toArray());
            
            // Identificar rango más común
            $rangoMasComun = collect($distribucion)->sortByDesc('cantidad_facturas')->first();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'distribucion' => $distribucion,
                    'resumen' => [
                        'total_facturas_analizadas' => $totalFacturas,
                        'tiempo_promedio_global' => $tiempoPromedio,
                        'desviacion_estandar_global' => round($desviacionEstandar, 2),
                        'rango_mas_comun' => $rangoMasComun['rango'] ?? 'N/A',
                        'porcentaje_rango_mas_comun' => $rangoMasComun['porcentaje'] ?? 0
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error al obtener distribución de pagos', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener distribución de pagos: ' . $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Obtener análisis comparativo entre períodos
     */
    public function obtenerAnalisisComparativo(Request $request)
    {
        try {
            set_time_limit(180); // 3 minutos
            
            $fechaInicio1 = $request->get('fecha_inicio_periodo1');
            $fechaFin1 = $request->get('fecha_fin_periodo1');
            $fechaInicio2 = $request->get('fecha_inicio_periodo2');
            $fechaFin2 = $request->get('fecha_fin_periodo2');
            
            // Validar que se proporcionen las fechas
            if (!$fechaInicio1 || !$fechaFin1 || !$fechaInicio2 || !$fechaFin2) {
                return response()->json([
                    'success' => false,
                    'message' => 'Debe proporcionar fecha_inicio_periodo1, fecha_fin_periodo1, fecha_inicio_periodo2, fecha_fin_periodo2'
                ], 400);
            }
            
            // Obtener estadísticas del período 1
            $periodo1 = $this->obtenerEstadisticasPeriodo($fechaInicio1, $fechaFin1, 'Período 1');
            
            // Obtener estadísticas del período 2
            $periodo2 = $this->obtenerEstadisticasPeriodo($fechaInicio2, $fechaFin2, 'Período 2');
            
            // Calcular comparaciones
            $comparacion = $this->calcularComparacion($periodo1, $periodo2);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'periodo_1' => array_merge($periodo1, ['fechas' => "$fechaInicio1 a $fechaFin1"]),
                    'periodo_2' => array_merge($periodo2, ['fechas' => "$fechaInicio2 a $fechaFin2"]),
                    'comparacion' => $comparacion
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error al obtener análisis comparativo', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener análisis comparativo: ' . $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Parsear fecha manejando diferentes formatos
     */
    private function parsearFecha($fecha)
    {
        try {
            // Si ya es un objeto Carbon o DateTime, devolverlo
            if ($fecha instanceof Carbon || $fecha instanceof \DateTime) {
                return $fecha instanceof \DateTime ? Carbon::parse($fecha) : $fecha;
            }
            
            // Si es string, intentar diferentes formatos
            if (is_string($fecha)) {
                $fecha = trim($fecha);
                
                // Formato d/m/Y (ej: 27/12/2024)
                if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $fecha)) {
                    return Carbon::createFromFormat('d/m/Y', $fecha);
                }
                
                // Formato Y-m-d (ej: 2024-12-27)
                if (preg_match('/^\d{4}-\d{1,2}-\d{1,2}$/', $fecha)) {
                    return Carbon::createFromFormat('Y-m-d', $fecha);
                }
                
                // Formato Y-m-d H:i:s (ej: 2024-12-27 00:00:00)
                if (preg_match('/^\d{4}-\d{1,2}-\d{1,2} \d{1,2}:\d{1,2}:\d{1,2}$/', $fecha)) {
                    return Carbon::createFromFormat('Y-m-d H:i:s', $fecha);
                }
                
                // Intentar parse automático como último recurso
                return Carbon::parse($fecha);
            }
            
            return null;
        } catch (\Exception $e) {
            Log::warning('Error al parsear fecha', ['fecha' => $fecha, 'error' => $e->getMessage()]);
            return null;
        }
    }
    
    /**
     * Calcular estadísticas avanzadas de manera eficiente
     * Optimizado para manejar grandes volúmenes de datos
     */
    private function calcularEstadisticasAvanzadas(array $datos)
    {
        $n = count($datos);
        
        if ($n === 0) {
            return [
                'mediana' => 0,
                'moda' => null,
                'percentiles' => [
                    'p5' => 0,
                    'p10' => 0,
                    'p25' => 0,
                    'p50' => 0,
                    'p75' => 0,
                    'p90' => 0,
                    'p95' => 0,
                ],
                'iqr' => 0
            ];
        }
        
        // Los datos ya vienen ordenados de la consulta
        
        // Calcular mediana
        $mediana = $this->calcularPercentil($datos, 50);
        
        // Calcular percentiles
        $percentiles = [
            'p5' => $this->calcularPercentil($datos, 5),
            'p10' => $this->calcularPercentil($datos, 10),
            'p25' => $this->calcularPercentil($datos, 25),
            'p50' => $mediana,
            'p75' => $this->calcularPercentil($datos, 75),
            'p90' => $this->calcularPercentil($datos, 90),
            'p95' => $this->calcularPercentil($datos, 95),
        ];
        
        // Calcular IQR (Rango Intercuartílico)
        $iqr = $percentiles['p75'] - $percentiles['p25'];
        
        // Calcular moda (valor más frecuente)
        $moda = $this->calcularModa($datos);
        
        return [
            'mediana' => round($mediana, 2),
            'moda' => $moda ? round($moda, 2) : null,
            'percentiles' => array_map(function($valor) {
                return round($valor, 2);
            }, $percentiles),
            'iqr' => round($iqr, 2)
        ];
    }
    
    /**
     * Calcular percentil usando método de interpolación lineal
     */
    private function calcularPercentil(array $datos, int $percentil)
    {
        $n = count($datos);
        
        if ($n === 0) return 0;
        if ($n === 1) return $datos[0];
        
        // Calcular posición
        $posicion = ($percentil / 100) * ($n - 1);
        $indiceInferior = floor($posicion);
        $indiceSuperior = ceil($posicion);
        
        // Si la posición es exacta
        if ($indiceInferior === $indiceSuperior) {
            return $datos[$indiceInferior];
        }
        
        // Interpolación lineal
        $peso = $posicion - $indiceInferior;
        return $datos[$indiceInferior] * (1 - $peso) + $datos[$indiceSuperior] * $peso;
    }
    
    /**
     * Calcular moda (valor más frecuente) de manera eficiente
     */
    private function calcularModa(array $datos)
    {
        if (empty($datos)) return null;
        
        // Contar frecuencias usando array_count_values (muy eficiente)
        $frecuencias = array_count_values($datos);
        
        // Encontrar la frecuencia máxima
        $frecuenciaMaxima = max($frecuencias);
        
        // Si todos los valores aparecen solo una vez, no hay moda
        if ($frecuenciaMaxima === 1 && count($frecuencias) === count($datos)) {
            return null;
        }
        
        // Encontrar el valor con la frecuencia máxima
        // Si hay múltiples modas, devolvemos la primera (menor valor)
        foreach ($frecuencias as $valor => $frecuencia) {
            if ($frecuencia === $frecuenciaMaxima) {
                return $valor;
            }
        }
        
        return null;
    }
    
    /**
     * Calcular desviación estándar manualmente
     */
    private function calcularDesviacionEstandar(array $datos)
    {
        $n = count($datos);
        if ($n <= 1) return 0;
        
        $media = array_sum($datos) / $n;
        $sumaCuadrados = array_sum(array_map(function($x) use ($media) {
            return pow($x - $media, 2);
        }, $datos));
        
        return sqrt($sumaCuadrados / ($n - 1));
    }
    
    /**
     * Obtener estadísticas para un período específico
     */
    private function obtenerEstadisticasPeriodo($fechaInicio, $fechaFin, $nombrePeriodo)
    {
        $estadisticas = DatosEstadistica::where('factura_pagada', true)
            ->whereNotNull('fecha_pago_final')
            ->whereBetween('fecha_pago_final', [$fechaInicio, $fechaFin])
            ->selectRaw('
                COUNT(*) as total_facturas,
                AVG(dias_para_pago) as promedio_dias,
                MIN(dias_para_pago) as minimo_dias,
                MAX(dias_para_pago) as maximo_dias,
                STDDEV(dias_para_pago) as desviacion_estandar
            ')
            ->first();
        
        // Obtener datos para percentiles
        $tiemposDias = DatosEstadistica::where('factura_pagada', true)
            ->whereNotNull('fecha_pago_final')
            ->whereBetween('fecha_pago_final', [$fechaInicio, $fechaFin])
            ->orderBy('dias_para_pago')
            ->pluck('dias_para_pago')
            ->toArray();
        
        $estadisticasAvanzadas = $this->calcularEstadisticasAvanzadas($tiemposDias);
        
        return [
            'nombre' => $nombrePeriodo,
            'total_facturas' => $estadisticas->total_facturas ?? 0,
            'promedio_dias' => round($estadisticas->promedio_dias ?? 0, 2),
            'mediana_dias' => $estadisticasAvanzadas['mediana'],
            'minimo_dias' => $estadisticas->minimo_dias ?? 0,
            'maximo_dias' => $estadisticas->maximo_dias ?? 0,
            'desviacion_estandar' => round($estadisticas->desviacion_estandar ?? 0, 2),
            'percentiles' => $estadisticasAvanzadas['percentiles']
        ];
    }
    
    /**
     * Calcular comparaciones entre dos períodos
     */
    private function calcularComparacion($periodo1, $periodo2)
    {
        $comparacion = [];
        
        // Comparar total de facturas
        $diferenciaFacturas = $periodo2['total_facturas'] - $periodo1['total_facturas'];
        $porcentajeCambioFacturas = $periodo1['total_facturas'] > 0 
            ? round(($diferenciaFacturas / $periodo1['total_facturas']) * 100, 2) 
            : 0;
        
        // Comparar promedio de días
        $diferenciaDias = $periodo2['promedio_dias'] - $periodo1['promedio_dias'];
        $porcentajeCambioDias = $periodo1['promedio_dias'] > 0 
            ? round(($diferenciaDias / $periodo1['promedio_dias']) * 100, 2) 
            : 0;
        
        // Comparar medianas
        $diferenciaMediana = $periodo2['mediana_dias'] - $periodo1['mediana_dias'];
        $porcentajeCambioMediana = $periodo1['mediana_dias'] > 0 
            ? round(($diferenciaMediana / $periodo1['mediana_dias']) * 100, 2) 
            : 0;
        
        return [
            'facturas' => [
                'diferencia_absoluta' => $diferenciaFacturas,
                'porcentaje_cambio' => $porcentajeCambioFacturas,
                'interpretacion' => $diferenciaFacturas > 0 ? 'Aumento' : ($diferenciaFacturas < 0 ? 'Disminución' : 'Sin cambio')
            ],
            'tiempo_promedio' => [
                'diferencia_dias' => round($diferenciaDias, 2),
                'porcentaje_cambio' => $porcentajeCambioDias,
                'interpretacion' => $diferenciaDias > 0 ? 'Aumento (peor)' : ($diferenciaDias < 0 ? 'Disminución (mejor)' : 'Sin cambio')
            ],
            'mediana' => [
                'diferencia_dias' => round($diferenciaMediana, 2),
                'porcentaje_cambio' => $porcentajeCambioMediana,
                'interpretacion' => $diferenciaMediana > 0 ? 'Aumento (peor)' : ($diferenciaMediana < 0 ? 'Disminución (mejor)' : 'Sin cambio')
            ],
            'resumen' => [
                'mejora_general' => ($diferenciaDias < 0 && $diferenciaMediana < 0),
                'empeora_general' => ($diferenciaDias > 0 && $diferenciaMediana > 0),
                'resultado_mixto' => (($diferenciaDias < 0 && $diferenciaMediana > 0) || ($diferenciaDias > 0 && $diferenciaMediana < 0))
            ]
        ];
    }
}
