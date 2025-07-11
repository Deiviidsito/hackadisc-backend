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
    
    /**
     * Obtener estadísticas de dashboard por cliente específico
     */
    public function obtenerDashboardCliente($nombreCliente)
    {
        try {
            set_time_limit(120);
            ini_set('memory_limit', '256M');
            
            // Validar parámetros de entrada
            $validacion = $this->validarParametrosDashboard($nombreCliente);
            if (!$validacion['valido']) {
                return response()->json([
                    'success' => false,
                    'error' => $validacion['error']
                ], 400);
            }
            
            // Decodificar nombre del cliente si viene por URL
            $nombreCliente = urldecode($nombreCliente);
            
            // Obtener todas las ventas de este cliente
            $ventas = Venta::where('NombreCliente', $nombreCliente)->get();
            
            if ($ventas->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Cliente no encontrado'
                ], 404);
            }
            
            // 1. Total de ventas realizadas a este cliente
            $totalVentas = $ventas->count();
            
            // 2. Días desde inicio de comercialización hasta último cambio de estado en facturas
            $diasComercializacion = $this->calcularDiasComercializacion($ventas);
            
            // 3. Estadísticas completas de facturas
            $estadisticasFacturas = $this->calcularEstadisticasFacturas($ventas);
            
            // 4. Cantidad de ventas canceladas (estado 2)
            $ventasCanceladas = $ventas->where('estado_venta_id', 2)->count();
            
            // 5. Total de ingresos recibidos (suma de ValorFinalComercializacion)
            $totalIngresos = $ventas->sum('ValorFinalComercializacion');
            
            // Estadísticas adicionales útiles para el dashboard
            $estadisticasAdicionales = $this->calcularEstadisticasAdicionales($ventas);
            
            // Información temporal del cliente
            $primeraVenta = $ventas->min('FechaInicio');
            $ultimaVenta = $ventas->max('FechaInicio');
            $estadoActividad = $this->determinarEstadoActividad($ultimaVenta);
            
            // Calcular métricas de rendimiento
            $diasActividad = $primeraVenta && $ultimaVenta ? Carbon::parse($primeraVenta)->diffInDays(Carbon::parse($ultimaVenta)) + 1 : 1;
            $ventasPorMes = $diasActividad > 0 ? round(($totalVentas / max($diasActividad, 1)) * 30, 2) : 0;
            $ingresosPorMes = $diasActividad > 0 ? round(($totalIngresos / max($diasActividad, 1)) * 30, 2) : 0;
            
            return response()->json([
                'success' => true,
                'data' => [
                    'cliente_nombre' => $nombreCliente,
                    'total_ventas' => $totalVentas,
                    'dias_comercializacion' => $diasComercializacion,
                    'facturas_estadisticas' => $estadisticasFacturas,
                    'ventas_canceladas' => $ventasCanceladas,
                    'total_ingresos' => floatval($totalIngresos),
                    'estadisticas_adicionales' => $estadisticasAdicionales,
                    'informacion_temporal' => [
                        'primera_venta' => $primeraVenta,
                        'ultima_venta' => $ultimaVenta,
                        'dias_actividad' => $diasActividad,
                        'estado_actividad' => $estadoActividad
                    ],
                    'metricas_rendimiento' => [
                        'ventas_por_mes' => $ventasPorMes,
                        'ingresos_por_mes' => $ingresosPorMes,
                        'ticket_promedio' => $totalVentas > 0 ? round($totalIngresos / $totalVentas, 2) : 0,
                        'conversion_facturas' => $totalVentas > 0 ? round(($estadisticasFacturas['total_facturas'] / $totalVentas) * 100, 2) : 0
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error en dashboard cliente: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'error' => 'Error al generar estadísticas del cliente'
            ], 500);
        }
    }
    
    /**
     * Obtener lista de todos los clientes con estadísticas básicas
     */
    public function obtenerListaClientesDashboard()
    {
        try {
            set_time_limit(120);
            ini_set('memory_limit', '256M');
            
            // Obtener todos los clientes únicos con sus estadísticas básicas
            $clientesEstadisticas = Venta::selectRaw('
                NombreCliente,
                COUNT(*) as total_ventas,
                SUM(ValorFinalComercializacion) as total_ingresos,
                SUM(CASE WHEN estado_venta_id = 2 THEN 1 ELSE 0 END) as ventas_canceladas,
                MIN(FechaInicio) as primera_comercializacion,
                MAX(FechaInicio) as ultima_comercializacion
            ')
            ->groupBy('NombreCliente')
            ->orderBy('total_ingresos', 'desc')
            ->get();
            
            $clientesConEstadisticas = [];
            
            foreach ($clientesEstadisticas as $cliente) {
                // Calcular porcentaje de facturas pagadas para este cliente
                $ventasCliente = Venta::where('NombreCliente', $cliente->NombreCliente)->get();
                $estadisticasFacturas = $this->calcularEstadisticasFacturas($ventasCliente);
                
                $clientesConEstadisticas[] = [
                    'nombre_cliente' => $cliente->NombreCliente,
                    'total_ventas' => $cliente->total_ventas,
                    'total_ingresos' => $cliente->total_ingresos,
                    'ventas_canceladas' => $cliente->ventas_canceladas,
                    'porcentaje_facturas_pagadas' => $estadisticasFacturas['porcentaje_pagadas'],
                    'primera_comercializacion' => $cliente->primera_comercializacion,
                    'ultima_comercializacion' => $cliente->ultima_comercializacion,
                    'estado_actividad' => $this->determinarEstadoActividad($cliente->ultima_comercializacion)
                ];
            }
            
            return response()->json([
                'success' => true,
                'data' => $clientesConEstadisticas,
                'total_clientes' => count($clientesConEstadisticas)
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error en lista clientes dashboard: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error al obtener lista de clientes'
            ], 500);
        }
    }
    
    /**
     * Obtener lista de clientes con filtros y paginación avanzada
     */
    public function obtenerListaClientesDashboardAvanzado(Request $request)
    {
        try {
            set_time_limit(180);
            ini_set('memory_limit', '512M');
            
            // Parámetros de filtros y paginación
            $limit = min($request->get('limit', 50), 200); // Máximo 200 elementos
            $offset = $request->get('offset', 0);
            $sortBy = $request->get('sort_by', 'total_ingresos'); // total_ventas, total_ingresos, porcentaje_facturas_pagadas, nombre_cliente
            $order = $request->get('order', 'desc');
            $estadoActividad = $request->get('estado_actividad'); // Activo, Poco Activo, Inactivo
            $montoMinimo = $request->get('monto_minimo', 0);
            $ventasMinimas = $request->get('ventas_minimas', 0);
            
            // Query base con filtros
            $query = Venta::selectRaw('
                NombreCliente,
                COUNT(*) as total_ventas,
                SUM(ValorFinalComercializacion) as total_ingresos,
                SUM(CASE WHEN estado_venta_id = 2 THEN 1 ELSE 0 END) as ventas_canceladas,
                MIN(FechaInicio) as primera_comercializacion,
                MAX(FechaInicio) as ultima_comercializacion
            ')
            ->groupBy('NombreCliente')
            ->havingRaw('SUM(ValorFinalComercializacion) >= ?', [$montoMinimo])
            ->havingRaw('COUNT(*) >= ?', [$ventasMinimas]);
            
            // Obtener todos los datos para calcular estadísticas y filtros
            $clientesEstadisticas = $query->get();
            
            $clientesConEstadisticas = [];
            
            foreach ($clientesEstadisticas as $cliente) {
                // Calcular estadísticas de facturas para este cliente
                $ventasCliente = Venta::where('NombreCliente', $cliente->NombreCliente)->get();
                $estadisticasFacturas = $this->calcularEstadisticasFacturas($ventasCliente);
                $estadoActividad = $this->determinarEstadoActividad($cliente->ultima_comercializacion);
                
                $clienteData = [
                    'nombre_cliente' => $cliente->NombreCliente,
                    'total_ventas' => $cliente->total_ventas,
                    'total_ingresos' => floatval($cliente->total_ingresos),
                    'ventas_canceladas' => $cliente->ventas_canceladas,
                    'porcentaje_facturas_pagadas' => $estadisticasFacturas['porcentaje_pagadas'],
                    'primera_comercializacion' => $cliente->primera_comercializacion,
                    'ultima_comercializacion' => $cliente->ultima_comercializacion,
                    'estado_actividad' => $estadoActividad,
                    'estadisticas_facturas' => $estadisticasFacturas
                ];
                
                // Filtrar por estado de actividad si se especifica
                if (!$request->get('estado_actividad') || $estadoActividad === $request->get('estado_actividad')) {
                    $clientesConEstadisticas[] = $clienteData;
                }
            }
            
            // Ordenar resultados
            $validSortFields = ['total_ventas', 'total_ingresos', 'porcentaje_facturas_pagadas', 'nombre_cliente'];
            if (in_array($sortBy, $validSortFields)) {
                usort($clientesConEstadisticas, function($a, $b) use ($sortBy, $order) {
                    $valueA = $a[$sortBy];
                    $valueB = $b[$sortBy];
                    
                    if ($order === 'asc') {
                        return $valueA <=> $valueB;
                    } else {
                        return $valueB <=> $valueA;
                    }
                });
            }
            
            // Paginación
            $totalClientes = count($clientesConEstadisticas);
            $clientesPaginados = array_slice($clientesConEstadisticas, $offset, $limit);
            
            return response()->json([
                'success' => true,
                'data' => $clientesPaginados,
                'pagination' => [
                    'total_clientes' => $totalClientes,
                    'limit' => $limit,
                    'offset' => $offset,
                    'has_more' => ($offset + $limit) < $totalClientes
                ],
                'filtros_aplicados' => [
                    'estado_actividad' => $request->get('estado_actividad'),
                    'monto_minimo' => $montoMinimo,
                    'ventas_minimas' => $ventasMinimas,
                    'sort_by' => $sortBy,
                    'order' => $order
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error en lista clientes dashboard avanzado: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error al obtener lista de clientes con filtros'
            ], 500);
        }
    }
    
    /**
     * Validar datos de entrada para métodos de dashboard
     */
    private function validarParametrosDashboard($nombreCliente)
    {
        if (empty($nombreCliente) || strlen($nombreCliente) < 2) {
            return [
                'valido' => false,
                'error' => 'El nombre del cliente debe tener al menos 2 caracteres'
            ];
        }
        
        if (strlen($nombreCliente) > 255) {
            return [
                'valido' => false,
                'error' => 'El nombre del cliente es demasiado largo'
            ];
        }
        
        return ['valido' => true];
    }
    
    /**
     * Calcular estadísticas de facturas (total, pagadas, porcentaje)
     */
    private function calcularEstadisticasFacturas($ventas)
    {
        $totalFacturas = 0;
        $facturasPagadas = 0;
        $facturasVencidas = 0;
        $facturasConHistorial = 0;
        $ingresosTotalFacturas = 0;
        $ingresosPagados = 0;
        
        // Intentar dos métodos para encontrar facturas:
        // 1. Por CodigoCotizacion (método tradicional)
        $codigosCotizacion = $ventas->pluck('CodigoCotizacion')->unique()->filter();
        $facturasPorCodigo = Factura::whereIn('numero', $codigosCotizacion)->get();
        
        // 2. Por idComercializacion (método alternativo)
        $idsComercializacion = $ventas->pluck('idComercializacion')->unique()->filter();
        $historialesPorId = HistorialEstadoFactura::whereIn('idComercializacion', $idsComercializacion)->get();
        $numeroFacturasPorId = $historialesPorId->pluck('factura_numero')->unique();
        $facturasPorId = Factura::whereIn('numero', $numeroFacturasPorId)->get();
        
        // Combinar ambos resultados y eliminar duplicados
        $todasLasFacturas = $facturasPorCodigo->merge($facturasPorId)->unique('numero');
        $totalFacturas = $todasLasFacturas->count();
        
        if ($totalFacturas == 0) {
            // No hay facturas, devolver estadísticas vacías
            return [
                'total_facturas' => 0,
                'facturas_pagadas' => 0,
                'facturas_pendientes' => 0,
                'facturas_vencidas' => 0,
                'facturas_con_historial' => 0,
                'porcentaje_pagadas' => 0,
                'ingresos_total_facturas' => 0,
                'ingresos_pagados' => 0,
                'porcentaje_ingresos_pagados' => 0,
                'valor_promedio_factura' => 0,
                'metodo_busqueda' => 'Sin facturas encontradas',
                'debug_info' => [
                    'codigos_cotizacion_count' => $codigosCotizacion->count(),
                    'ids_comercializacion_count' => $idsComercializacion->count(),
                    'facturas_por_codigo' => $facturasPorCodigo->count(),
                    'facturas_por_id' => $facturasPorId->count()
                ]
            ];
        }
        
        // Calcular suma total de facturas
        $ingresosTotalFacturas = $todasLasFacturas->sum('valor');
        
        // Obtener todos los historiales para las facturas encontradas
        $numeroFacturas = $todasLasFacturas->pluck('numero');
        $todosLosHistoriales = HistorialEstadoFactura::whereIn('factura_numero', $numeroFacturas)->get();
        
        // Análisis de estados
        $historialesPagados = $todosLosHistoriales->where('estado_id', 3)->pluck('factura_numero')->unique();
        $historialesVencidos = $todosLosHistoriales->where('estado_id', 4)->pluck('factura_numero')->unique();
        $facturasConHistorial = $todosLosHistoriales->pluck('factura_numero')->unique()->count();
        
        
        // Procesar cada factura para calcular estadísticas
        foreach ($todasLasFacturas as $factura) {
            // Verificar si la factura está pagada
            if ($historialesPagados->contains($factura->numero)) {
                $facturasPagadas++;
                $ingresosPagados += floatval($factura->valor);
            }
            
            // Verificar si la factura está vencida
            if ($historialesVencidos->contains($factura->numero)) {
                $facturasVencidas++;
            }
        }
        
        $porcentajePagadas = $totalFacturas > 0 ? round(($facturasPagadas / $totalFacturas) * 100, 2) : 0;
        $porcentajeIngresosPagados = $ingresosTotalFacturas > 0 ? round(($ingresosPagados / $ingresosTotalFacturas) * 100, 2) : 0;
        
        // Determinar qué método de búsqueda fue más efectivo
        $metodoBusqueda = '';
        if ($facturasPorCodigo->count() > 0 && $facturasPorId->count() > 0) {
            $metodoBusqueda = 'Ambos métodos (código + ID)';
        } elseif ($facturasPorCodigo->count() > 0) {
            $metodoBusqueda = 'Por código de cotización';
        } elseif ($facturasPorId->count() > 0) {
            $metodoBusqueda = 'Por ID de comercialización';
        } else {
            $metodoBusqueda = 'Ningún método efectivo';
        }
        
        return [
            'total_facturas' => $totalFacturas,
            'facturas_pagadas' => $facturasPagadas,
            'facturas_pendientes' => $totalFacturas - $facturasPagadas,
            'facturas_vencidas' => $facturasVencidas,
            'facturas_con_historial' => $facturasConHistorial,
            'porcentaje_pagadas' => $porcentajePagadas,
            'ingresos_total_facturas' => $ingresosTotalFacturas,
            'ingresos_pagados' => $ingresosPagados,
            'porcentaje_ingresos_pagados' => $porcentajeIngresosPagados,
            'valor_promedio_factura' => $totalFacturas > 0 ? round($ingresosTotalFacturas / $totalFacturas, 2) : 0,
            'metodo_busqueda' => $metodoBusqueda,
            'debug_info' => [
                'facturas_por_codigo' => $facturasPorCodigo->count(),
                'facturas_por_id' => $facturasPorId->count(),
                'total_combinado' => $totalFacturas,
                'historiales_encontrados' => $todosLosHistoriales->count()
            ]
        ];
    }
    
    /**
     * Calcular días desde inicio de comercialización hasta último cambio de estado en facturas
     */
    private function calcularDiasComercializacion($ventas)
    {
        $fechaInicioMasAntigua = null;
        $fechaUltimoCambioFactura = null;
        $fechaUltimaVenta = null;
        
        foreach ($ventas as $venta) {
            // Fecha de inicio más antigua
            if (!$fechaInicioMasAntigua || $venta->FechaInicio < $fechaInicioMasAntigua) {
                $fechaInicioMasAntigua = $venta->FechaInicio;
            }
            
            // Guardar fecha de la venta más reciente como respaldo
            if (!$fechaUltimaVenta || $venta->FechaInicio > $fechaUltimaVenta) {
                $fechaUltimaVenta = $venta->FechaInicio;
            }
            
            // Buscar la fecha más reciente de cambio de estado en las facturas de esta venta
            $facturas = Factura::where('numero', $venta->CodigoCotizacion)->get();
            
            foreach ($facturas as $factura) {
                $ultimoHistorial = HistorialEstadoFactura::where('factura_numero', $factura->numero)
                    ->orderBy('fecha', 'desc')
                    ->first();
                
                if ($ultimoHistorial && (!$fechaUltimoCambioFactura || $ultimoHistorial->fecha > $fechaUltimoCambioFactura)) {
                    $fechaUltimoCambioFactura = $ultimoHistorial->fecha;
                }
            }
        }
        
        // Si hay facturas con historial, usar la fecha más reciente de cambio de estado
        if ($fechaInicioMasAntigua && $fechaUltimoCambioFactura) {
            return Carbon::parse($fechaInicioMasAntigua)->diffInDays(Carbon::parse($fechaUltimoCambioFactura));
        }
        
        // Si no hay facturas con historial, usar la diferencia entre primera y última venta
        if ($fechaInicioMasAntigua && $fechaUltimaVenta && $fechaInicioMasAntigua !== $fechaUltimaVenta) {
            return Carbon::parse($fechaInicioMasAntigua)->diffInDays(Carbon::parse($fechaUltimaVenta));
        }
        
        // Si solo hay una venta o no hay datos, calcular días desde el inicio hasta hoy
        if ($fechaInicioMasAntigua) {
            return Carbon::parse($fechaInicioMasAntigua)->diffInDays(Carbon::now());
        }
        
        return 0;
    }
    
    /**
     * Calcular estadísticas adicionales útiles para el dashboard
     */
    private function calcularEstadisticasAdicionales($ventas)
    {
        $estadosCounts = $ventas->groupBy('estado_venta_id')->map->count();
        
        // Calcular tiempo promedio de las ventas completadas
        $ventasCompletadas = $ventas->whereIn('estado_venta_id', [1, 3]); // Terminada o Terminada SENCE
        $tiempoPromedioCompletar = 0;
        
        if ($ventasCompletadas->count() > 0) {
            $totalDias = 0;
            foreach ($ventasCompletadas as $venta) {
                // Buscar el último historial de estado para esta venta
                $ultimoEstado = \App\Models\HistorialEstadoVenta::where('venta_id', $venta->id)
                    ->orderBy('fecha', 'desc')
                    ->first();
                
                if ($ultimoEstado) {
                    $totalDias += Carbon::parse($venta->FechaInicio)->diffInDays(Carbon::parse($ultimoEstado->fecha));
                }
            }
            $tiempoPromedioCompletar = round($totalDias / $ventasCompletadas->count(), 1);
        }
        
        return [
            'ventas_en_proceso' => $estadosCounts->get(0, 0),
            'ventas_terminadas' => $estadosCounts->get(1, 0),
            'ventas_terminadas_sence' => $estadosCounts->get(3, 0),
            'ventas_reprogramadas' => $estadosCounts->get(6, 0),
            'ventas_perdidas' => $estadosCounts->get(7, 0),
            'tiempo_promedio_completar_dias' => $tiempoPromedioCompletar,
            'valor_promedio_comercializacion' => $ventas->avg('ValorFinalComercializacion'),
            'ticket_promedio' => round($ventas->avg('ValorFinalComercializacion'), 0)
        ];
    }
    
    /**
     * Determinar estado de actividad del cliente
     */
    private function determinarEstadoActividad($ultimaComercializacion)
    {
        $diasSinActividad = Carbon::parse($ultimaComercializacion)->diffInDays(Carbon::now());
        
        // Activo: menos de 30 días sin actividad
        if ($diasSinActividad <= 30) {
            return 'Activo';
        } 
        // Poco Activo: entre 31 y 90 días sin actividad
        elseif ($diasSinActividad <= 90) {
            return 'Poco Activo';
        } 
        // Inactivo: más de 90 días sin actividad
        else {
            return 'Inactivo';
        }
    }
    
    /**
     * Obtener línea de tiempo de comercialización por cliente
     */
    public function obtenerLineaTiempoComercializacion(Request $request)
    {
        try {
            set_time_limit(180);
            ini_set('memory_limit', '512M');
            
            $nombreCliente = $request->get('cliente');
            $fechaInicio = $request->get('fecha_inicio');
            $fechaFin = $request->get('fecha_fin');
            $agruparPor = $request->get('agrupar_por', 'mes'); // mes, trimestre, año
            
            // Validar parámetros requeridos
            if (!$nombreCliente) {
                return response()->json([
                    'success' => false,
                    'error' => 'El parámetro cliente es requerido'
                ], 400);
            }
            
            // Decodificar nombre del cliente
            $nombreCliente = urldecode($nombreCliente);
            
            // Obtener ventas del cliente que estén terminadas (estado 1 o 3)
            $ventasQuery = Venta::where('NombreCliente', $nombreCliente)
                ->whereIn('estado_venta_id', [1, 3]); // Solo ventas terminadas
            
            // Aplicar filtros de fecha si se proporcionan
            if ($fechaInicio) {
                $ventasQuery->where('FechaInicio', '>=', $fechaInicio);
            }
            if ($fechaFin) {
                $ventasQuery->where('FechaInicio', '<=', $fechaFin);
            }
            
            $ventas = $ventasQuery->get();
            
            if ($ventas->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'cliente_nombre' => $nombreCliente,
                        'periodos' => [],
                        'resumen' => [
                            'total_facturas_analizadas' => 0,
                            'mensaje' => 'No se encontraron ventas terminadas para este cliente en el período especificado'
                        ]
                    ]
                ]);
            }
            
            // Obtener idComercializacion de las ventas
            $idsComercializacion = $ventas->pluck('idComercializacion')->unique();
            
            // Obtener historiales de facturas relacionados con las ventas
            $historiales = HistorialEstadoFactura::whereIn('idComercializacion', $idsComercializacion)
                ->orderBy('fecha')
                ->get();
            
            if ($historiales->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'cliente_nombre' => $nombreCliente,
                        'periodos' => [],
                        'resumen' => [
                            'total_facturas_analizadas' => 0,
                            'mensaje' => 'No se encontraron historiales de facturas para las ventas terminadas de este cliente'
                        ]
                    ]
                ]);
            }
            
            // Agrupar historiales por número de factura
            $historialesAgrupados = $historiales->groupBy('factura_numero');
            
            // Procesar línea de tiempo por factura
            $datosLineaTiempo = [];
            
            foreach ($historialesAgrupados as $numeroFactura => $historialFactura) {
                $lineaTiempoFactura = $this->procesarLineaTiempoFactura($numeroFactura, $historialFactura, $agruparPor);
                
                if ($lineaTiempoFactura) {
                    $datosLineaTiempo[] = $lineaTiempoFactura;
                }
            }
            
            // Agrupar y consolidar datos por período
            $periodos = $this->consolidarLineaTiempoPorPeriodo($datosLineaTiempo, $agruparPor);
            
            // Calcular resumen estadístico
            $resumen = $this->calcularResumenLineaTiempo($datosLineaTiempo);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'cliente_nombre' => $nombreCliente,
                    'agrupar_por' => $agruparPor,
                    'periodos' => $periodos,
                    'resumen' => $resumen
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error en línea de tiempo comercialización: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error al generar línea de tiempo de comercialización'
            ], 500);
        }
    }
    
    /**
     * Procesar línea de tiempo para una factura específica
     */
    private function procesarLineaTiempoFactura($numeroFactura, $historiales, $agruparPor)
    {
        $estados = [
            1 => ['nombre' => 'Emitida', 'color' => '#3B82F6', 'descripcion' => 'Factura emitida'],
            2 => ['nombre' => 'En Proceso', 'color' => '#F59E0B', 'descripcion' => 'En proceso de pago'],
            3 => ['nombre' => 'Pagada', 'color' => '#10B981', 'descripcion' => 'Factura pagada'],
            4 => ['nombre' => 'Vencida', 'color' => '#EF4444', 'descripcion' => 'Factura vencida'],
            5 => ['nombre' => 'Anulada', 'color' => '#6B7280', 'descripcion' => 'Factura anulada']
        ];
        
        $lineaTiempo = [];
        $fechaInicio = null;
        $fechaFin = null;
        $estadoFinal = null;
        $valorFactura = 0;
        
        foreach ($historiales as $historial) {
            $fecha = $this->parsearFecha($historial->fecha);
            if (!$fecha) continue;
            
            // Establecer fecha de inicio (primera emisión)
            if (!$fechaInicio || ($historial->estado_id == 1 && $fecha < $fechaInicio)) {
                $fechaInicio = $fecha;
            }
            
            // Actualizar fecha fin y estado final
            if (!$fechaFin || $fecha > $fechaFin) {
                $fechaFin = $fecha;
                $estadoFinal = $historial->estado_id;
            }
            
            // Obtener valor de la factura del último historial con valor pagado
            if ($historial->pagado > 0) {
                $valorFactura = floatval($historial->pagado);
            }
            
            // Agregar evento a la línea de tiempo
            $periodo = $this->obtenerPeriodoParaFecha($fecha, $agruparPor);
            $estadoInfo = $estados[$historial->estado_id] ?? ['nombre' => 'Desconocido', 'color' => '#6B7280', 'descripcion' => 'Estado desconocido'];
            
            $lineaTiempo[] = [
                'fecha' => $fecha->format('Y-m-d'),
                'periodo' => $periodo,
                'estado_id' => $historial->estado_id,
                'estado_nombre' => $estadoInfo['nombre'],
                'estado_color' => $estadoInfo['color'],
                'estado_descripcion' => $estadoInfo['descripcion'],
                'factura_numero' => $numeroFactura,
                'factura_valor' => floatval($historial->pagado),
                'id_comercializacion' => $historial->idComercializacion
            ];
        }
        
        if (empty($lineaTiempo) || !$fechaInicio || !$fechaFin) {
            return null;
        }
        
        // Si no tenemos valor de la factura, intentar obtenerlo de cualquier historial
        if ($valorFactura == 0) {
            $historialesConValor = $historiales->where('pagado', '>', 0)->first();
            if ($historialesConValor) {
                $valorFactura = floatval($historialesConValor->pagado);
            }
        }
        
        // Calcular métricas de tiempo
        $diasTranscurridos = $fechaInicio->diffInDays($fechaFin);
        $estadoFinalInfo = $estados[$estadoFinal] ?? ['nombre' => 'Desconocido', 'color' => '#6B7280'];
        
        return [
            'factura_numero' => $numeroFactura,
            'factura_valor' => $valorFactura,
            'fecha_inicio' => $fechaInicio->format('Y-m-d'),
            'fecha_fin' => $fechaFin->format('Y-m-d'),
            'dias_transcurridos' => $diasTranscurridos,
            'estado_final' => $estadoFinal,
            'estado_final_nombre' => $estadoFinalInfo['nombre'],
            'estado_final_color' => $estadoFinalInfo['color'],
            'eventos' => $lineaTiempo
        ];
    }
    
    /**
     * Obtener período para una fecha según el tipo de agrupación
     */
    private function obtenerPeriodoParaFecha($fecha, $agruparPor)
    {
        switch ($agruparPor) {
            case 'trimestre':
                $trimestre = ceil($fecha->month / 3);
                return $fecha->year . '-Q' . $trimestre;
            case 'año':
                return (string) $fecha->year;
            case 'mes':
            default:
                return $fecha->format('Y-m');
        }
    }
    
    /**
     * Consolidar datos de línea de tiempo por período
     */
    private function consolidarLineaTiempoPorPeriodo($datosLineaTiempo, $agruparPor)
    {
        $periodos = [];
        $facturasEmitidas = []; // Rastrear facturas emitidas por período
        $facturasPagadas = []; // Rastrear facturas pagadas por período
        
        // Primera pasada: recopilar información de todas las facturas
        foreach ($datosLineaTiempo as $facturaData) {
            $numeroFactura = $facturaData['factura_numero'];
            $fechaEmision = null;
            $fechaPago = null;
            $valorFactura = $facturaData['factura_valor'];
            
            // Buscar fecha de emisión y pago en los eventos
            foreach ($facturaData['eventos'] as $evento) {
                if ($evento['estado_id'] == 1 && !$fechaEmision) { // Primera emisión
                    $fechaEmision = $evento['fecha'];
                }
                if ($evento['estado_id'] == 3) { // Último pago
                    $fechaPago = $evento['fecha'];
                }
            }
            
            // Asignar factura al período de emisión
            if ($fechaEmision) {
                $periodoEmision = $this->obtenerPeriodoParaFecha(Carbon::parse($fechaEmision), $agruparPor);
                if (!isset($facturasEmitidas[$periodoEmision])) {
                    $facturasEmitidas[$periodoEmision] = [];
                }
                $facturasEmitidas[$periodoEmision][] = [
                    'numero' => $numeroFactura,
                    'valor' => $valorFactura,
                    'fecha_emision' => $fechaEmision
                ];
            }
            
            // Asignar factura al período de pago si está pagada
            if ($fechaPago) {
                $periodoPago = $this->obtenerPeriodoParaFecha(Carbon::parse($fechaPago), $agruparPor);
                if (!isset($facturasPagadas[$periodoPago])) {
                    $facturasPagadas[$periodoPago] = [];
                }
                $facturasPagadas[$periodoPago][] = [
                    'numero' => $numeroFactura,
                    'valor' => $valorFactura,
                    'fecha_pago' => $fechaPago,
                    'dias_transcurridos' => $facturaData['dias_transcurridos']
                ];
            }
        }
        
        // Obtener todos los períodos únicos
        $todosLosPeriodos = array_unique(array_merge(
            array_keys($facturasEmitidas),
            array_keys($facturasPagadas)
        ));
        sort($todosLosPeriodos);
        
        // Segunda pasada: calcular eventos por período (para mostrar actividad)
        $eventosPorPeriodo = [];
        foreach ($datosLineaTiempo as $facturaData) {
            foreach ($facturaData['eventos'] as $evento) {
                $periodo = $evento['periodo'];
                
                if (!isset($eventosPorPeriodo[$periodo])) {
                    $eventosPorPeriodo[$periodo] = [
                        'eventos_emision' => 0,
                        'eventos_proceso' => 0,
                        'eventos_pago' => 0,
                        'eventos_vencida' => 0,
                        'eventos_anulada' => 0
                    ];
                }
                
                switch ($evento['estado_id']) {
                    case 1: $eventosPorPeriodo[$periodo]['eventos_emision']++; break;
                    case 2: $eventosPorPeriodo[$periodo]['eventos_proceso']++; break;
                    case 3: $eventosPorPeriodo[$periodo]['eventos_pago']++; break;
                    case 4: $eventosPorPeriodo[$periodo]['eventos_vencida']++; break;
                    case 5: $eventosPorPeriodo[$periodo]['eventos_anulada']++; break;
                }
            }
        }
        
        // Crear estructura final por período
        $periodosOrdenados = [];
        foreach ($todosLosPeriodos as $periodo) {
            $facturasEmitidasEnPeriodo = $facturasEmitidas[$periodo] ?? [];
            $facturasPagadasEnPeriodo = $facturasPagadas[$periodo] ?? [];
            $eventosEnPeriodo = $eventosPorPeriodo[$periodo] ?? [];
            
            $totalFacturasEmitidas = count($facturasEmitidasEnPeriodo);
            $totalFacturasPagadas = count($facturasPagadasEnPeriodo);
            
            $valorTotalEmitidas = array_sum(array_column($facturasEmitidasEnPeriodo, 'valor'));
            $valorTotalPagadas = array_sum(array_column($facturasPagadasEnPeriodo, 'valor'));
            
            // Calcular tiempo promedio de pago para facturas pagadas en este período
            $tiemposPago = array_column($facturasPagadasEnPeriodo, 'dias_transcurridos');
            $diasPromedioParaPago = count($tiemposPago) > 0 
                ? round(array_sum($tiemposPago) / count($tiemposPago), 1) 
                : 0;
            
            // Calcular porcentaje de facturas pagadas del total emitidas en este período
            $porcentajePagadas = $totalFacturasEmitidas > 0 
                ? round(($totalFacturasPagadas / $totalFacturasEmitidas) * 100, 1) 
                : ($totalFacturasPagadas > 0 ? 'N/A (solo pagos)' : 0);
            
            $periodosOrdenados[] = [
                'periodo' => $periodo,
                'facturas_emitidas' => $totalFacturasEmitidas,
                'facturas_pagadas' => $totalFacturasPagadas,
                'eventos_emision' => $eventosEnPeriodo['eventos_emision'] ?? 0,
                'eventos_en_proceso' => $eventosEnPeriodo['eventos_proceso'] ?? 0,
                'eventos_pago' => $eventosEnPeriodo['eventos_pago'] ?? 0,
                'eventos_vencida' => $eventosEnPeriodo['eventos_vencida'] ?? 0,
                'eventos_anulada' => $eventosEnPeriodo['eventos_anulada'] ?? 0,
                'valor_total_emitidas' => $valorTotalEmitidas,
                'valor_total_pagadas' => $valorTotalPagadas,
                'dias_promedio_para_pago' => $diasPromedioParaPago,
                'porcentaje_pagadas' => $porcentajePagadas,
                'nota' => $totalFacturasEmitidas == 0 && $totalFacturasPagadas > 0 
                    ? 'Período con solo pagos de facturas emitidas en períodos anteriores' 
                    : null
            ];
        }
        
        return $periodosOrdenados;
    }
    
    /**
     * Calcular resumen estadístico de la línea de tiempo
     */
    private function calcularResumenLineaTiempo($datosLineaTiempo)
    {
        $totalFacturas = count($datosLineaTiempo);
        $facturasPagadas = 0;
        $facturasVencidas = 0;
        $valorTotalFacturas = 0;
        $valorTotalPagado = 0;
        $tiemposParaPago = [];
        
        foreach ($datosLineaTiempo as $facturaData) {
            $valorTotalFacturas += $facturaData['factura_valor'];
            
            if ($facturaData['estado_final'] == 3) { // Pagada
                $facturasPagadas++;
                $valorTotalPagado += $facturaData['factura_valor'];
                $tiemposParaPago[] = $facturaData['dias_transcurridos'];
            } elseif ($facturaData['estado_final'] == 4) { // Vencida
                $facturasVencidas++;
            }
        }
        
        $tiempoPromedioParaPago = count($tiemposParaPago) > 0 
            ? round(array_sum($tiemposParaPago) / count($tiemposParaPago), 1) 
            : 0;
        
        $tiempoMinimoParaPago = count($tiemposParaPago) > 0 ? min($tiemposParaPago) : 0;
        $tiempoMaximoParaPago = count($tiemposParaPago) > 0 ? max($tiemposParaPago) : 0;
        
        return [
            'total_facturas_analizadas' => $totalFacturas,
            'facturas_pagadas' => $facturasPagadas,
            'facturas_vencidas' => $facturasVencidas,
            'facturas_pendientes' => $totalFacturas - $facturasPagadas - $facturasVencidas,
            'porcentaje_pagadas' => $totalFacturas > 0 ? round(($facturasPagadas / $totalFacturas) * 100, 1) : 0,
            'valor_total_facturas' => $valorTotalFacturas,
            'valor_total_pagado' => $valorTotalPagado,
            'porcentaje_valor_pagado' => $valorTotalFacturas > 0 ? round(($valorTotalPagado / $valorTotalFacturas) * 100, 1) : 0,
            'tiempo_promedio_para_pago_dias' => $tiempoPromedioParaPago,
            'tiempo_minimo_para_pago_dias' => $tiempoMinimoParaPago,
            'tiempo_maximo_para_pago_dias' => $tiempoMaximoParaPago
        ];
    }
}
