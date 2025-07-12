<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * CONTROLADOR ANÁLISIS TIPOS DE FLUJO COMERCIALIZACIÓN
 * 
 * Especializado en analizar y comparar los dos tipos de flujo principales:
 * - Flujo Completo (con SENCE): 0 → 3 → 1 (2 facturas)
 * - Flujo Simple (sin SENCE): 0 → 1 (1 factura)
 * 
 * FUNCIONALIDADES PRINCIPALES:
 * - Detección automática del tipo de flujo por comercialización
 * - Análisis comparativo de tiempos entre flujos
 * - Preferencias de clientes por tipo de financiamiento
 * - Métricas de eficiencia y rentabilidad por flujo
 * - Análisis de adopción de financiamiento SENCE
 * - Impacto en tiempos y facturación por tipo de flujo
 * 
 * LÓGICA DE DETECCIÓN:
 * - Flujo Completo: Tiene estado 3 (Terminada SENCE) + estado 1 (Terminada)
 * - Flujo Simple: Solo tiene estado 1 (Terminada), sin estado 3
 * - Análisis de facturas asociadas para validar el tipo
 */
class TipoFlujoController extends Controller
{
    /**
     * ANÁLISIS COMPARATIVO TIPOS DE FLUJO
     * 
     * Compara flujos con SENCE vs sin SENCE en todos los aspectos
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function analizarTiposFlujo(Request $request)
    {
        try {
            set_time_limit(300);
            ini_set('memory_limit', '1G');
            
            $startTime = microtime(true);
            Log::info("⏱️ INICIANDO ANÁLISIS TIPOS DE FLUJO");
            
            // Cargar datos del JSON
            $comercializacionesData = $this->cargarDatosBaseDatos();
            if (!$comercializacionesData) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo cargar el archivo JSON de datos'
                ], 500);
            }
            
            // Parámetros de filtrado
            $año = $request->input('año', null);
            $mes = $request->input('mes', null);
            
            $comercializacionesAnalizadas = 0;
            $flujoCompleto = [
                'count' => 0,
                'tiempos_total' => [],
                'tiempos_sence' => [],
                'tiempos_cliente' => [],
                'valores' => [],
                'facturas_count' => [],
                'clientes' => []
            ];
            
            $flujoSimple = [
                'count' => 0,
                'tiempos_total' => [],
                'valores' => [],
                'facturas_count' => [],
                'clientes' => []
            ];
            
            $detallesComercializaciones = [];
            
            foreach ($comercializacionesData as $comercializacion) {
                $comercializacionesAnalizadas++;
                
                // Aplicar filtros de fecha
                if (!$this->cumpleFiltrosFecha($comercializacion, $año, $mes)) {
                    continue;
                }
                
                // Detectar tipo de flujo
                $tipoFlujo = $this->detectarTipoFlujo($comercializacion);
                $tiemposDetalle = $this->calcularTiemposDetallados($comercializacion);
                
                $detalleComercializacion = [
                    'codigo_cotizacion' => $comercializacion['CodigoCotizacion'],
                    'cliente' => $comercializacion['NombreCliente'],
                    'tipo_flujo' => $tipoFlujo,
                    'valor_comercializacion' => $comercializacion['ValorFinalComercializacion'] ?? 0,
                    'numero_facturas' => isset($comercializacion['Facturas']) ? count($comercializacion['Facturas']) : 0,
                    'tiempos' => $tiemposDetalle
                ];
                
                if ($tipoFlujo === 'completo') {
                    $flujoCompleto['count']++;
                    $flujoCompleto['clientes'][] = $comercializacion['NombreCliente'];
                    $flujoCompleto['valores'][] = $comercializacion['ValorFinalComercializacion'] ?? 0;
                    $flujoCompleto['facturas_count'][] = isset($comercializacion['Facturas']) ? count($comercializacion['Facturas']) : 0;
                    
                    if ($tiemposDetalle['tiempo_total'] !== null) {
                        $flujoCompleto['tiempos_total'][] = $tiemposDetalle['tiempo_total'];
                    }
                    if ($tiemposDetalle['tiempo_sence'] !== null) {
                        $flujoCompleto['tiempos_sence'][] = $tiemposDetalle['tiempo_sence'];
                    }
                    if ($tiemposDetalle['tiempo_cliente'] !== null) {
                        $flujoCompleto['tiempos_cliente'][] = $tiemposDetalle['tiempo_cliente'];
                    }
                    
                } elseif ($tipoFlujo === 'simple') {
                    $flujoSimple['count']++;
                    $flujoSimple['clientes'][] = $comercializacion['NombreCliente'];
                    $flujoSimple['valores'][] = $comercializacion['ValorFinalComercializacion'] ?? 0;
                    $flujoSimple['facturas_count'][] = isset($comercializacion['Facturas']) ? count($comercializacion['Facturas']) : 0;
                    
                    if ($tiemposDetalle['tiempo_total'] !== null) {
                        $flujoSimple['tiempos_total'][] = $tiemposDetalle['tiempo_total'];
                    }
                }
                
                $detallesComercializaciones[] = $detalleComercializacion;
            }
            
            // Calcular estadísticas comparativas
            $resultados = $this->calcularEstadisticasComparativas($flujoCompleto, $flujoSimple);
            
            $tiempoEjecucion = round((microtime(true) - $startTime) * 1000, 2);
            
            return response()->json([
                'success' => true,
                'message' => 'Análisis tipos de flujo completado exitosamente',
                'datos' => [
                    'resumen' => [
                        'comercializaciones_analizadas' => $comercializacionesAnalizadas,
                        'flujo_completo_count' => $flujoCompleto['count'],
                        'flujo_simple_count' => $flujoSimple['count'],
                        'porcentaje_completo' => $comercializacionesAnalizadas > 0 ? 
                            round(($flujoCompleto['count'] / $comercializacionesAnalizadas) * 100, 2) : 0,
                        'porcentaje_simple' => $comercializacionesAnalizadas > 0 ? 
                            round(($flujoSimple['count'] / $comercializacionesAnalizadas) * 100, 2) : 0,
                        'filtros_aplicados' => [
                            'año' => $año,
                            'mes' => $mes
                        ]
                    ],
                    'flujo_completo' => $resultados['flujo_completo'],
                    'flujo_simple' => $resultados['flujo_simple'],
                    'comparativa' => $resultados['comparativa'],
                    'preferencias_clientes' => $resultados['preferencias_clientes'],
                    'analisis_eficiencia' => $resultados['analisis_eficiencia']
                ],
                'metadata' => [
                    'tiempo_ejecucion_ms' => $tiempoEjecucion,
                    'timestamp' => now()->format('Y-m-d H:i:s'),
                    'total_registros_json' => count($comercializacionesData)
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error("❌ ERROR ANÁLISIS TIPOS DE FLUJO: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al analizar tipos de flujo: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * ANÁLISIS PREFERENCIAS CLIENTES POR FLUJO
     * 
     * Analiza qué clientes prefieren cada tipo de flujo
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function analizarPreferenciasClientes(Request $request)
    {
        try {
            $comercializacionesData = $this->cargarDatosBaseDatos();
            if (!$comercializacionesData) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo cargar el archivo JSON de datos'
                ], 500);
            }
            
            $año = $request->input('año', null);
            $mes = $request->input('mes', null);
            
            $clientesData = [];
            
            foreach ($comercializacionesData as $comercializacion) {
                if (!$this->cumpleFiltrosFecha($comercializacion, $año, $mes)) {
                    continue;
                }
                
                $clienteNombre = $comercializacion['NombreCliente'];
                $tipoFlujo = $this->detectarTipoFlujo($comercializacion);
                
                if (!isset($clientesData[$clienteNombre])) {
                    $clientesData[$clienteNombre] = [
                        'nombre_cliente' => $clienteNombre,
                        'total_comercializaciones' => 0,
                        'flujo_completo' => 0,
                        'flujo_simple' => 0,
                        'valor_total_completo' => 0,
                        'valor_total_simple' => 0,
                        'preferencia' => null,
                        'valor_promedio_completo' => 0,
                        'valor_promedio_simple' => 0
                    ];
                }
                
                $clientesData[$clienteNombre]['total_comercializaciones']++;
                $valor = $comercializacion['ValorFinalComercializacion'] ?? 0;
                
                if ($tipoFlujo === 'completo') {
                    $clientesData[$clienteNombre]['flujo_completo']++;
                    $clientesData[$clienteNombre]['valor_total_completo'] += $valor;
                } elseif ($tipoFlujo === 'simple') {
                    $clientesData[$clienteNombre]['flujo_simple']++;
                    $clientesData[$clienteNombre]['valor_total_simple'] += $valor;
                }
            }
            
            // Calcular preferencias y estadísticas por cliente
            $resultadosClientes = [];
            foreach ($clientesData as $cliente => $data) {
                if ($data['total_comercializaciones'] === 0) continue;
                
                // Calcular promedios
                $data['valor_promedio_completo'] = $data['flujo_completo'] > 0 ? 
                    round($data['valor_total_completo'] / $data['flujo_completo'], 2) : 0;
                    
                $data['valor_promedio_simple'] = $data['flujo_simple'] > 0 ? 
                    round($data['valor_total_simple'] / $data['flujo_simple'], 2) : 0;
                
                // Determinar preferencia
                $porcentajeCompleto = round(($data['flujo_completo'] / $data['total_comercializaciones']) * 100, 2);
                $porcentajeSimple = round(($data['flujo_simple'] / $data['total_comercializaciones']) * 100, 2);
                
                if ($porcentajeCompleto > 70) {
                    $data['preferencia'] = 'completo_fuerte';
                } elseif ($porcentajeCompleto > 50) {
                    $data['preferencia'] = 'completo_leve';
                } elseif ($porcentajeSimple > 70) {
                    $data['preferencia'] = 'simple_fuerte';
                } elseif ($porcentajeSimple > 50) {
                    $data['preferencia'] = 'simple_leve';
                } else {
                    $data['preferencia'] = 'mixto';
                }
                
                $data['porcentaje_completo'] = $porcentajeCompleto;
                $data['porcentaje_simple'] = $porcentajeSimple;
                
                $resultadosClientes[] = $data;
            }
            
            // Ordenar por total de comercializaciones (más activos primero)
            usort($resultadosClientes, function($a, $b) {
                return $b['total_comercializaciones'] <=> $a['total_comercializaciones'];
            });
            
            // Generar estadísticas de preferencias
            $estadisticasPreferencias = [
                'completo_fuerte' => 0,
                'completo_leve' => 0,
                'simple_fuerte' => 0,
                'simple_leve' => 0,
                'mixto' => 0
            ];
            
            foreach ($resultadosClientes as $cliente) {
                $estadisticasPreferencias[$cliente['preferencia']]++;
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Análisis preferencias clientes completado exitosamente',
                'datos' => [
                    'total_clientes_analizados' => count($resultadosClientes),
                    'filtros_aplicados' => [
                        'año' => $año,
                        'mes' => $mes
                    ],
                    'estadisticas_preferencias' => $estadisticasPreferencias,
                    'clientes' => $resultadosClientes,
                    'resumen_preferencias' => [
                        'prefieren_sence' => $estadisticasPreferencias['completo_fuerte'] + $estadisticasPreferencias['completo_leve'],
                        'prefieren_sin_sence' => $estadisticasPreferencias['simple_fuerte'] + $estadisticasPreferencias['simple_leve'],
                        'comportamiento_mixto' => $estadisticasPreferencias['mixto']
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error("❌ ERROR ANÁLISIS PREFERENCIAS: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error en análisis de preferencias: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * ANÁLISIS EFICIENCIA POR TIPO DE FLUJO
     * 
     * Compara la eficiencia operacional entre tipos de flujo
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function analizarEficienciaPorFlujo(Request $request)
    {
        try {
            $comercializacionesData = $this->cargarDatosBaseDatos();
            if (!$comercializacionesData) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo cargar el archivo JSON de datos'
                ], 500);
            }
            
            $año = $request->input('año', null);
            $mes = $request->input('mes', null);
            
            $eficienciaCompleto = [
                'tiempos_desarrollo' => [],
                'tiempos_facturacion' => [],
                'tiempos_pago' => [],
                'valores_promedio' => [],
                'numero_facturas' => [],
                'tasa_pago' => 0,
                'facturas_pagadas' => 0,
                'facturas_totales' => 0
            ];
            
            $eficienciaSimple = [
                'tiempos_desarrollo' => [],
                'tiempos_facturacion' => [],
                'tiempos_pago' => [],
                'valores_promedio' => [],
                'numero_facturas' => [],
                'tasa_pago' => 0,
                'facturas_pagadas' => 0,
                'facturas_totales' => 0
            ];
            
            foreach ($comercializacionesData as $comercializacion) {
                if (!$this->cumpleFiltrosFecha($comercializacion, $año, $mes)) {
                    continue;
                }
                
                $tipoFlujo = $this->detectarTipoFlujo($comercializacion);
                $tiemposDetalle = $this->calcularTiemposDetallados($comercializacion);
                $estadosPago = $this->analizarEstadosPago($comercializacion);
                
                $datosEficiencia = [
                    'tiempo_desarrollo' => $tiemposDetalle['tiempo_total'],
                    'valor' => $comercializacion['ValorFinalComercializacion'] ?? 0,
                    'numero_facturas' => isset($comercializacion['Facturas']) ? count($comercializacion['Facturas']) : 0,
                    'facturas_pagadas' => $estadosPago['facturas_pagadas'],
                    'facturas_totales' => $estadosPago['facturas_totales']
                ];
                
                if ($tipoFlujo === 'completo') {
                    $this->agregarDatosEficiencia($eficienciaCompleto, $datosEficiencia, $tiemposDetalle);
                } elseif ($tipoFlujo === 'simple') {
                    $this->agregarDatosEficiencia($eficienciaSimple, $datosEficiencia, $tiemposDetalle);
                }
            }
            
            // Calcular métricas de eficiencia
            $resultadosEficiencia = [
                'flujo_completo' => $this->calcularMetricasEficiencia($eficienciaCompleto),
                'flujo_simple' => $this->calcularMetricasEficiencia($eficienciaSimple)
            ];
            
            // Calcular comparativas de eficiencia
            $comparativaEficiencia = $this->compararEficiencia($resultadosEficiencia['flujo_completo'], $resultadosEficiencia['flujo_simple']);
            
            return response()->json([
                'success' => true,
                'message' => 'Análisis eficiencia por flujo completado exitosamente',
                'datos' => [
                    'filtros_aplicados' => [
                        'año' => $año,
                        'mes' => $mes
                    ],
                    'eficiencia_flujo_completo' => $resultadosEficiencia['flujo_completo'],
                    'eficiencia_flujo_simple' => $resultadosEficiencia['flujo_simple'],
                    'comparativa_eficiencia' => $comparativaEficiencia,
                    'recomendaciones' => $this->generarRecomendaciones($comparativaEficiencia)
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error("❌ ERROR ANÁLISIS EFICIENCIA: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error en análisis de eficiencia: ' . $e->getMessage()
            ], 500);
        }
    }
    
    // ==================================================================================
    // MÉTODOS AUXILIARES
    // ==================================================================================
    
    /**
     * CARGAR DATOS DESDE BASE DE DATOS
     * 
     * Carga comercializaciones desde la base de datos con historial de estados
     * para detectar tipos de flujo (con/sin SENCE)
     */
    private function cargarDatosBaseDatos($año = null, $mes = null)
    {
        try {
            Log::info("🔍 Cargando datos desde BD para análisis de flujos - Año: " . ($año ?? 'todos') . ", Mes: " . ($mes ?? 'todos'));
            
            // Query para obtener ventas con filtros de fecha
            $queryBase = "
                SELECT 
                    v.idVenta,
                    v.idComercializacion,
                    v.CodigoCotizacion,
                    v.FechaInicio,
                    v.ClienteId,
                    v.NombreCliente,
                    v.ValorFinalComercializacion,
                    v.CorreoCreador,
                    v.estado_venta_id as estado_actual
                FROM ventas v
                WHERE 1=1
                    -- Excluir prefijos específicos
                    AND v.CodigoCotizacion NOT LIKE 'ADI%'
                    AND v.CodigoCotizacion NOT LIKE 'OTR%' 
                    AND v.CodigoCotizacion NOT LIKE 'SPD%'
            ";
            
            // Aplicar filtros de fecha
            if ($año) {
                $queryBase .= " AND YEAR(v.FechaInicio) = {$año}";
            }
            
            if ($mes) {
                $queryBase .= " AND MONTH(v.FechaInicio) = {$mes}";
            }
            
            $queryBase .= " ORDER BY v.FechaInicio DESC";
            
            $ventas = DB::select($queryBase);
            
            Log::info("📊 Encontradas " . count($ventas) . " ventas");
            
            // Cargar historial de estados para todas las ventas
            $ventasIds = array_column($ventas, 'idVenta');
            $comercializacionesIds = array_column($ventas, 'idComercializacion');
            
            $historialEstados = [];
            if (!empty($ventasIds)) {
                $queryEstados = "
                    SELECT 
                        hev.venta_id,
                        hev.idComercializacion,
                        hev.estado_venta_id,
                        hev.fecha,
                        ev.nombre as nombre_estado
                    FROM historial_estados_venta hev
                    INNER JOIN estado_ventas ev ON hev.estado_venta_id = ev.id
                    WHERE hev.venta_id IN (" . implode(',', $ventasIds) . ")
                    ORDER BY hev.venta_id, hev.fecha ASC
                ";
                
                $resultadosEstados = DB::select($queryEstados);
                
                // Organizar por venta_id
                foreach ($resultadosEstados as $estado) {
                    $historialEstados[$estado->venta_id][] = [
                        'EstadoComercializacion' => $estado->estado_venta_id, // Mantener nombre compatible
                        'Fecha' => $estado->fecha,
                        'nombre_estado' => $estado->nombre_estado
                    ];
                }
                
                Log::info("📈 Cargados estados para " . count($historialEstados) . " ventas");
            }
            
            // Cargar facturas relacionadas por idComercializacion
            $facturas = [];
            if (!empty($comercializacionesIds)) {
                $queryFacturas = "
                    SELECT 
                        f.numero as NumeroFactura,
                        f.FechaFacturacion,
                        f.valor as MontoFactura,
                        f.idComercializacion
                    FROM facturas f
                    WHERE f.idComercializacion IN (" . implode(',', $comercializacionesIds) . ")
                    ORDER BY f.idComercializacion, f.FechaFacturacion ASC
                ";
                
                $resultadosFacturas = DB::select($queryFacturas);
                
                // Organizar por idComercializacion
                foreach ($resultadosFacturas as $factura) {
                    $facturas[$factura->idComercializacion][] = [
                        'NumeroFactura' => $factura->NumeroFactura,
                        'FechaFacturacion' => $factura->FechaFacturacion,
                        'MontoFactura' => $factura->MontoFactura
                    ];
                }
                
                Log::info("🧾 Cargadas facturas para " . count($facturas) . " comercializaciones");
            }
            
            // Construir estructura de datos compatible con código existente
            $comercializacionesData = [];
            foreach ($ventas as $venta) {
                $comercializacion = [
                    'idVenta' => $venta->idVenta,
                    'idComercializacion' => $venta->idComercializacion,
                    'CodigoCotizacion' => $venta->CodigoCotizacion,
                    'FechaInicio' => $venta->FechaInicio,
                    'ClienteId' => $venta->ClienteId,
                    'NombreCliente' => $venta->NombreCliente,
                    'ValorFinalComercializacion' => $venta->ValorFinalComercializacion,
                    'CorreoCreador' => $venta->CorreoCreador,
                    'estado_actual' => $venta->estado_actual,
                    // Agregar historial de estados (mantener nombres compatibles)
                    'Estados' => $historialEstados[$venta->idVenta] ?? [],
                    // Agregar facturas
                    'Facturas' => $facturas[$venta->idComercializacion] ?? []
                ];
                
                $comercializacionesData[] = $comercializacion;
            }
            
            Log::info("✅ Estructura completa creada para " . count($comercializacionesData) . " comercializaciones para análisis de flujos");
            
            return $comercializacionesData;
            
        } catch (\Exception $e) {
            Log::error("❌ Error cargando datos de BD para análisis de flujos: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Verificar si cumple filtros de fecha
     */
    private function cumpleFiltrosFecha($comercializacion, $año = null, $mes = null)
    {
        if (!$año && !$mes) return true;
        
        try {
            $fechaInicio = Carbon::createFromFormat('Y-m-d', $comercializacion['FechaInicio']);
            
            if ($año && $fechaInicio->year != $año) {
                return false;
            }
            
            if ($mes && $fechaInicio->month != $mes) {
                return false;
            }
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Detectar tipo de flujo de la comercialización
     */
    private function detectarTipoFlujo($comercializacion)
    {
        if (!isset($comercializacion['Estados']) || empty($comercializacion['Estados'])) {
            return 'indeterminado';
        }
        
        $tieneEstado1 = false;
        $tieneEstado3 = false;
        
        foreach ($comercializacion['Estados'] as $estado) {
            if ($estado['EstadoComercializacion'] == 1) {
                $tieneEstado1 = true;
            }
            if ($estado['EstadoComercializacion'] == 3) {
                $tieneEstado3 = true;
            }
        }
        
        if ($tieneEstado3 && $tieneEstado1) {
            return 'completo'; // Flujo 0 → 3 → 1 (con SENCE)
        } elseif ($tieneEstado1 && !$tieneEstado3) {
            return 'simple';   // Flujo 0 → 1 (sin SENCE)
        } else {
            return 'incompleto'; // No ha llegado a estado final
        }
    }
    
    /**
     * Calcular tiempos detallados por comercialización
     */
    private function calcularTiemposDetallados($comercializacion)
    {
        $tiempos = [
            'tiempo_total' => null,
            'tiempo_sence' => null,
            'tiempo_cliente' => null
        ];
        
        if (!isset($comercializacion['Estados']) || empty($comercializacion['Estados'])) {
            return $tiempos;
        }
        
        $fechaEstado0 = null;
        $fechaEstado1 = null;
        $fechaEstado3 = null;
        
        foreach ($comercializacion['Estados'] as $estado) {
            try {
                $fecha = Carbon::createFromFormat('Y-m-d', $estado['Fecha']);
                
                if ($estado['EstadoComercializacion'] == 0) {
                    $fechaEstado0 = $fecha;
                } elseif ($estado['EstadoComercializacion'] == 1) {
                    $fechaEstado1 = $fecha;
                } elseif ($estado['EstadoComercializacion'] == 3) {
                    $fechaEstado3 = $fecha;
                }
            } catch (\Exception $e) {
                continue;
            }
        }
        
        // Calcular tiempos según el tipo de flujo
        if ($fechaEstado0 && $fechaEstado1) {
            $tiempos['tiempo_total'] = $fechaEstado0->diffInDays($fechaEstado1);
        }
        
        if ($fechaEstado0 && $fechaEstado3) {
            $tiempos['tiempo_sence'] = $fechaEstado0->diffInDays($fechaEstado3);
        }
        
        if ($fechaEstado3 && $fechaEstado1) {
            $tiempos['tiempo_cliente'] = $fechaEstado3->diffInDays($fechaEstado1);
        }
        
        return $tiempos;
    }
    
    /**
     * Analizar estados de pago de las facturas
     */
    private function analizarEstadosPago($comercializacion)
    {
        $resultado = [
            'facturas_totales' => 0,
            'facturas_pagadas' => 0
        ];
        
        if (!isset($comercializacion['Facturas']) || empty($comercializacion['Facturas'])) {
            return $resultado;
        }
        
        foreach ($comercializacion['Facturas'] as $factura) {
            $resultado['facturas_totales']++;
            
            if (isset($factura['EstadosFactura']) && !empty($factura['EstadosFactura'])) {
                foreach ($factura['EstadosFactura'] as $estadoFactura) {
                    if ($estadoFactura['estado'] == 3 && 
                        isset($estadoFactura['Pagado']) && 
                        $estadoFactura['Pagado'] > 0) {
                        $resultado['facturas_pagadas']++;
                        break; // Solo contar una vez por factura
                    }
                }
            }
        }
        
        return $resultado;
    }
    
    /**
     * Calcular estadísticas comparativas entre flujos
     */
    private function calcularEstadisticasComparativas($flujoCompleto, $flujoSimple)
    {
        return [
            'flujo_completo' => [
                'count' => $flujoCompleto['count'],
                'tiempo_promedio_total' => count($flujoCompleto['tiempos_total']) > 0 ? 
                    round(array_sum($flujoCompleto['tiempos_total']) / count($flujoCompleto['tiempos_total']), 2) : 0,
                'tiempo_promedio_sence' => count($flujoCompleto['tiempos_sence']) > 0 ? 
                    round(array_sum($flujoCompleto['tiempos_sence']) / count($flujoCompleto['tiempos_sence']), 2) : 0,
                'tiempo_promedio_cliente' => count($flujoCompleto['tiempos_cliente']) > 0 ? 
                    round(array_sum($flujoCompleto['tiempos_cliente']) / count($flujoCompleto['tiempos_cliente']), 2) : 0,
                'valor_promedio' => count($flujoCompleto['valores']) > 0 ? 
                    round(array_sum($flujoCompleto['valores']) / count($flujoCompleto['valores']), 2) : 0,
                'facturas_promedio' => count($flujoCompleto['facturas_count']) > 0 ? 
                    round(array_sum($flujoCompleto['facturas_count']) / count($flujoCompleto['facturas_count']), 2) : 0,
                'clientes_unicos' => count(array_unique($flujoCompleto['clientes']))
            ],
            'flujo_simple' => [
                'count' => $flujoSimple['count'],
                'tiempo_promedio_total' => count($flujoSimple['tiempos_total']) > 0 ? 
                    round(array_sum($flujoSimple['tiempos_total']) / count($flujoSimple['tiempos_total']), 2) : 0,
                'valor_promedio' => count($flujoSimple['valores']) > 0 ? 
                    round(array_sum($flujoSimple['valores']) / count($flujoSimple['valores']), 2) : 0,
                'facturas_promedio' => count($flujoSimple['facturas_count']) > 0 ? 
                    round(array_sum($flujoSimple['facturas_count']) / count($flujoSimple['facturas_count']), 2) : 0,
                'clientes_unicos' => count(array_unique($flujoSimple['clientes']))
            ],
            'comparativa' => [
                'diferencia_tiempo_total' => count($flujoCompleto['tiempos_total']) > 0 && count($flujoSimple['tiempos_total']) > 0 ? 
                    round((array_sum($flujoCompleto['tiempos_total']) / count($flujoCompleto['tiempos_total'])) - 
                          (array_sum($flujoSimple['tiempos_total']) / count($flujoSimple['tiempos_total'])), 2) : null,
                'diferencia_valor_promedio' => count($flujoCompleto['valores']) > 0 && count($flujoSimple['valores']) > 0 ? 
                    round((array_sum($flujoCompleto['valores']) / count($flujoCompleto['valores'])) - 
                          (array_sum($flujoSimple['valores']) / count($flujoSimple['valores'])), 2) : null,
                'diferencia_facturas' => count($flujoCompleto['facturas_count']) > 0 && count($flujoSimple['facturas_count']) > 0 ? 
                    round((array_sum($flujoCompleto['facturas_count']) / count($flujoCompleto['facturas_count'])) - 
                          (array_sum($flujoSimple['facturas_count']) / count($flujoSimple['facturas_count'])), 2) : null
            ],
            'preferencias_clientes' => $this->analizarPreferenciasClientesBasico($flujoCompleto, $flujoSimple),
            'analisis_eficiencia' => [
                'flujo_mas_rapido' => $this->determinarFlujoMasRapido($flujoCompleto, $flujoSimple),
                'flujo_mayor_valor' => $this->determinarFlujoMayorValor($flujoCompleto, $flujoSimple)
            ]
        ];
    }
    
    /**
     * Agregar datos de eficiencia
     */
    private function agregarDatosEficiencia(&$eficiencia, $datos, $tiemposDetalle)
    {
        if ($datos['tiempo_desarrollo'] !== null) {
            $eficiencia['tiempos_desarrollo'][] = $datos['tiempo_desarrollo'];
        }
        
        $eficiencia['valores_promedio'][] = $datos['valor'];
        $eficiencia['numero_facturas'][] = $datos['numero_facturas'];
        $eficiencia['facturas_totales'] += $datos['facturas_totales'];
        $eficiencia['facturas_pagadas'] += $datos['facturas_pagadas'];
    }
    
    /**
     * Calcular métricas de eficiencia
     */
    private function calcularMetricasEficiencia($eficiencia)
    {
        return [
            'tiempo_promedio_desarrollo' => count($eficiencia['tiempos_desarrollo']) > 0 ? 
                round(array_sum($eficiencia['tiempos_desarrollo']) / count($eficiencia['tiempos_desarrollo']), 2) : 0,
            'valor_promedio' => count($eficiencia['valores_promedio']) > 0 ? 
                round(array_sum($eficiencia['valores_promedio']) / count($eficiencia['valores_promedio']), 2) : 0,
            'facturas_promedio' => count($eficiencia['numero_facturas']) > 0 ? 
                round(array_sum($eficiencia['numero_facturas']) / count($eficiencia['numero_facturas']), 2) : 0,
            'tasa_pago' => $eficiencia['facturas_totales'] > 0 ? 
                round(($eficiencia['facturas_pagadas'] / $eficiencia['facturas_totales']) * 100, 2) : 0
        ];
    }
    
    /**
     * Comparar eficiencia entre flujos
     */
    private function compararEficiencia($eficienciaCompleto, $eficienciaSimple)
    {
        return [
            'tiempo_desarrollo' => [
                'ganador' => $eficienciaSimple['tiempo_promedio_desarrollo'] < $eficienciaCompleto['tiempo_promedio_desarrollo'] ? 'simple' : 'completo',
                'diferencia' => abs($eficienciaCompleto['tiempo_promedio_desarrollo'] - $eficienciaSimple['tiempo_promedio_desarrollo'])
            ],
            'valor_promedio' => [
                'ganador' => $eficienciaCompleto['valor_promedio'] > $eficienciaSimple['valor_promedio'] ? 'completo' : 'simple',
                'diferencia' => abs($eficienciaCompleto['valor_promedio'] - $eficienciaSimple['valor_promedio'])
            ],
            'tasa_pago' => [
                'ganador' => $eficienciaCompleto['tasa_pago'] > $eficienciaSimple['tasa_pago'] ? 'completo' : 'simple',
                'diferencia' => abs($eficienciaCompleto['tasa_pago'] - $eficienciaSimple['tasa_pago'])
            ]
        ];
    }
    
    /**
     * Generar recomendaciones basadas en el análisis
     */
    private function generarRecomendaciones($comparativa)
    {
        $recomendaciones = [];
        
        if ($comparativa['tiempo_desarrollo']['ganador'] === 'simple') {
            $recomendaciones[] = "El flujo simple (sin SENCE) es más rápido en desarrollo. Considerar promover esta opción para proyectos urgentes.";
        }
        
        if ($comparativa['valor_promedio']['ganador'] === 'completo') {
            $recomendaciones[] = "El flujo completo (con SENCE) genera mayor valor promedio. Considerar incentivar el uso de financiamiento SENCE.";
        }
        
        if ($comparativa['tasa_pago']['ganador'] === 'completo') {
            $recomendaciones[] = "El flujo completo tiene mejor tasa de pago. El financiamiento SENCE mejora la liquidez.";
        }
        
        return $recomendaciones;
    }
    
    /**
     * Análisis básico de preferencias de clientes
     */
    private function analizarPreferenciasClientesBasico($flujoCompleto, $flujoSimple)
    {
        $clientesCompleto = array_count_values($flujoCompleto['clientes']);
        $clientesSimple = array_count_values($flujoSimple['clientes']);
        
        return [
            'clientes_solo_completo' => count(array_diff_key($clientesCompleto, $clientesSimple)),
            'clientes_solo_simple' => count(array_diff_key($clientesSimple, $clientesCompleto)),
            'clientes_mixtos' => count(array_intersect_key($clientesCompleto, $clientesSimple))
        ];
    }
    
    /**
     * Determinar flujo más rápido
     */
    private function determinarFlujoMasRapido($flujoCompleto, $flujoSimple)
    {
        $tiempoCompleto = count($flujoCompleto['tiempos_total']) > 0 ? 
            array_sum($flujoCompleto['tiempos_total']) / count($flujoCompleto['tiempos_total']) : PHP_INT_MAX;
            
        $tiempoSimple = count($flujoSimple['tiempos_total']) > 0 ? 
            array_sum($flujoSimple['tiempos_total']) / count($flujoSimple['tiempos_total']) : PHP_INT_MAX;
            
        return $tiempoSimple < $tiempoCompleto ? 'simple' : 'completo';
    }
    
    /**
     * Determinar flujo de mayor valor
     */
    private function determinarFlujoMayorValor($flujoCompleto, $flujoSimple)
    {
        $valorCompleto = count($flujoCompleto['valores']) > 0 ? 
            array_sum($flujoCompleto['valores']) / count($flujoCompleto['valores']) : 0;
            
        $valorSimple = count($flujoSimple['valores']) > 0 ? 
            array_sum($flujoSimple['valores']) / count($flujoSimple['valores']) : 0;
            
        return $valorCompleto > $valorSimple ? 'completo' : 'simple';
    }
}
