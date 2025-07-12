<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cliente;
use App\Models\Venta;
use App\Models\Factura;
use App\Models\HistorialEstadoVenta;
use App\Models\HistorialEstadoFactura;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ClienteAnalyticsController extends Controller
{
    /**
     * GET /api/clientes/listar - LISTAR TODOS LOS CLIENTES
     * 📋 Retorna lista completa de clientes con estadísticas básicas
     */
    public function listarClientes()
    {
        try {
            $clientes = Cliente::select('id', 'InsecapClienteId', 'NombreCliente')
                ->orderBy('NombreCliente')
                ->get()
                ->map(function($cliente) {
                    try {
                        // Calcular estadísticas básicas
                        $totalVentas = Venta::where('ClienteId', $cliente->id)->count();
                        $valorTotal = Venta::where('ClienteId', $cliente->id)->sum('ValorFinalComercializacion') ?? 0;
                        
                        $ultimaVenta = Venta::where('ClienteId', $cliente->id)
                            ->orderBy('FechaInicio', 'desc')
                            ->first();
                        
                        // Contar facturas de manera segura
                        $totalFacturas = 0;
                        if ($totalVentas > 0) {
                            $codigosCotizacion = Venta::where('ClienteId', $cliente->id)
                                ->pluck('CodigoCotizacion')
                                ->filter(); // Filtrar valores nulos
                            
                            if ($codigosCotizacion->isNotEmpty()) {
                                $totalFacturas = Factura::whereIn('numero', $codigosCotizacion)->count();
                            }
                        }
                        
                        return [
                            'id' => $cliente->id,
                            'insecap_id' => $cliente->InsecapClienteId,
                            'nombre' => $cliente->NombreCliente,
                            'estadisticas' => [
                                'total_ventas' => $totalVentas,
                                'total_facturas' => $totalFacturas,
                                'valor_total_comercializaciones' => floatval($valorTotal),
                                'ultima_actividad' => $ultimaVenta ? $ultimaVenta->FechaInicio : null,
                                'estado_actividad' => $ultimaVenta ? 
                                    $this->determinarEstadoActividad($ultimaVenta->FechaInicio) : 'sin_actividad'
                            ]
                        ];
                    } catch (\Exception $e) {
                        // Si hay error con un cliente específico, devolver datos básicos
                        return [
                            'id' => $cliente->id,
                            'insecap_id' => $cliente->InsecapClienteId,
                            'nombre' => $cliente->NombreCliente,
                            'estadisticas' => [
                                'total_ventas' => 0,
                                'total_facturas' => 0,
                                'valor_total_comercializaciones' => 0,
                                'ultima_actividad' => null,
                                'estado_actividad' => 'error_calculo'
                            ],
                            'error' => $e->getMessage()
                        ];
                    }
                });

            $resumenSistema = [
                'total_ventas_sistema' => $clientes->sum('estadisticas.total_ventas'),
                'total_facturas_sistema' => $clientes->sum('estadisticas.total_facturas'),
                'valor_total_sistema' => $clientes->sum('estadisticas.valor_total_comercializaciones')
            ];

            return response()->json([
                'success' => true,
                'message' => 'Lista de clientes obtenida exitosamente',
                'datos' => [
                    'clientes' => $clientes,
                    'total_clientes' => $clientes->count(),
                    'resumen' => $resumenSistema
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener lista de clientes',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * GET /api/clientes/{id}/analytics - ANALÍTICAS COMPLETAS POR CLIENTE
     * 📊 Dashboard personalizado con todas las métricas de un cliente específico
     */
    public function analyticsCliente($clienteId)
    {
        try {
            // Verificar que el cliente existe
            $cliente = Cliente::findOrFail($clienteId);

            // Obtener datos básicos del cliente de manera segura
            $analytics = [
                'cliente_info' => [
                    'id' => $cliente->id,
                    'insecap_id' => $cliente->InsecapClienteId,
                    'nombre' => $cliente->NombreCliente
                ],
                'resumen_general' => $this->obtenerResumenGeneral($clienteId),
                'ventas_historicas' => $this->obtenerVentasBasicas($clienteId),
                'analisis_pagos' => $this->obtenerAnalisisPagosBasico($clienteId),
                'historia_pagos' => $this->obtenerHistoriaPagos($clienteId),
                'estimacion_pagos' => $this->estimarTiempoPagoNuevaVenta($clienteId),
                'comportamiento_facturacion' => $this->analizarComportamientoFacturacion($clienteId),
                'analisis_morosidad' => $this->analizarMorosidadDetallada($clienteId),
                'flujo_comercial' => $this->analizarFlujoComercial($clienteId),
                'timestamp' => now()->toISOString()
            ];

            return response()->json([
                'success' => true,
                'message' => "Analíticas completas para {$cliente->NombreCliente}",
                'datos' => $analytics
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener analíticas del cliente',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * GET /api/clientes/{id}/comparar?cliente_comparacion={id2} - COMPARAR DOS CLIENTES
     * 🔍 Comparativa detallada entre dos clientes
     */
    public function compararClientes($clienteId, Request $request)
    {
        try {
            $clienteComparacionId = $request->query('cliente_comparacion');
            
            if (!$clienteComparacionId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Debe proporcionar cliente_comparacion como parámetro'
                ], 400);
            }

            $cliente1 = Cliente::findOrFail($clienteId);
            $cliente2 = Cliente::findOrFail($clienteComparacionId);

            $comparativa = [
                'clientes' => [
                    'cliente_a' => [
                        'id' => $cliente1->id,
                        'nombre' => $cliente1->NombreCliente
                    ],
                    'cliente_b' => [
                        'id' => $cliente2->id,
                        'nombre' => $cliente2->NombreCliente
                    ]
                ],
                'metricas_comparadas' => $this->compararMetricas($clienteId, $clienteComparacionId),
                'analisis_diferencias' => $this->analizarDiferencias($clienteId, $clienteComparacionId)
            ];

            return response()->json([
                'success' => true,
                'message' => "Comparativa entre {$cliente1->NombreCliente} y {$cliente2->NombreCliente}",
                'datos' => $comparativa
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al comparar clientes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ==================== MÉTODOS AUXILIARES ====================

    private function determinarEstadoActividad($fechaUltimaVenta)
    {
        if (!$fechaUltimaVenta) return 'sin_actividad';
        
        $diasInactividad = Carbon::parse($fechaUltimaVenta)->diffInDays(now());
        
        if ($diasInactividad <= 30) return 'activo';
        if ($diasInactividad <= 90) return 'poco_activo';
        if ($diasInactividad <= 180) return 'inactivo';
        return 'muy_inactivo';
    }

    private function obtenerResumenGeneral($clienteId)
    {
        try {
            $ventas = Venta::where('ClienteId', $clienteId)->get();
            
            return [
                'total_ventas' => $ventas->count(),
                'valor_total_comercializaciones' => $ventas->sum('ValorFinalComercializacion'),
                'valor_promedio_venta' => $ventas->avg('ValorFinalComercializacion') ?? 0,
                'periodo_actividad' => [
                    'primera_venta' => $ventas->min('FechaInicio'),
                    'ultima_venta' => $ventas->max('FechaInicio'),
                    'años_como_cliente' => $ventas->count() > 0 ? 
                        Carbon::parse($ventas->min('FechaInicio'))->diffInYears(now()) : 0
                ]
            ];
        } catch (\Exception $e) {
            return [
                'total_ventas' => 0,
                'valor_total_comercializaciones' => 0,
                'valor_promedio_venta' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    private function obtenerVentasBasicas($clienteId)
    {
        try {
            $ventas = Venta::where('ClienteId', $clienteId)
                ->orderBy('FechaInicio', 'desc')
                ->limit(10) // Limitar a las últimas 10 ventas
                ->get()
                ->map(function($venta) {
                    return [
                        'codigo_cotizacion' => $venta->CodigoCotizacion,
                        'fecha_inicio' => $venta->FechaInicio,
                        'valor_comercializacion' => $venta->ValorFinalComercializacion,
                    ];
                });

            // Agrupación por año
            $ventasPorAño = Venta::where('ClienteId', $clienteId)
                ->selectRaw('YEAR(FechaInicio) as año, COUNT(*) as cantidad, SUM(ValorFinalComercializacion) as valor_total')
                ->groupBy('año')
                ->orderBy('año', 'desc')
                ->get();

            return [
                'ventas_recientes' => $ventas,
                'agrupacion_anual' => $ventasPorAño,
                'total_historico' => Venta::where('ClienteId', $clienteId)->count()
            ];
        } catch (\Exception $e) {
            return [
                'ventas_recientes' => [],
                'agrupacion_anual' => [],
                'total_historico' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    private function obtenerAnalisisPagosBasico($clienteId)
    {
        try {
            $ventas = Venta::where('ClienteId', $clienteId)->pluck('CodigoCotizacion');
            $facturas = Factura::whereIn('numero', $ventas)->get();
            
            $facturasPagadas = 0;
            $facturasPendientes = 0;

            foreach ($facturas as $factura) {
                $historial = HistorialEstadoFactura::where('numero', $factura->numero)
                    ->orderBy('fecha', 'desc')
                    ->first();
                
                if ($historial && $historial->estado == 3 && $historial->monto > 0) {
                    $facturasPagadas++;
                } else {
                    $facturasPendientes++;
                }
            }

            return [
                'total_facturas' => $facturas->count(),
                'facturas_pagadas' => $facturasPagadas,
                'facturas_pendientes' => $facturasPendientes,
                'porcentaje_pago' => $facturas->count() > 0 ? 
                    ($facturasPagadas / $facturas->count()) * 100 : 0,
                'clasificacion_pago' => $this->clasificarPagoSimple($facturasPagadas, $facturasPendientes)
            ];
        } catch (\Exception $e) {
            return [
                'total_facturas' => 0,
                'facturas_pagadas' => 0,
                'facturas_pendientes' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    private function clasificarPagoSimple($pagadas, $pendientes)
    {
        $total = $pagadas + $pendientes;
        if ($total == 0) return 'sin_datos';
        
        $porcentajePago = ($pagadas / $total) * 100;
        
        if ($porcentajePago >= 90) return 'excelente';
        if ($porcentajePago >= 70) return 'bueno';
        if ($porcentajePago >= 50) return 'regular';
        return 'necesita_atencion';
    }

    /**
     * HISTORIA DETALLADA DE PAGOS DEL CLIENTE
     * Analiza el comportamiento histórico de pagos con detalles cronológicos
     */
    private function obtenerHistoriaPagos($clienteId)
    {
        try {
            $ventas = Venta::where('ClienteId', $clienteId)->pluck('CodigoCotizacion');
            $facturas = Factura::whereIn('numero', $ventas)->get();
            
            $historiaPagos = [];
            $tiemposPago = [];
            $montosTotales = [];
            
            foreach ($facturas as $factura) {
                $datosJSON = json_decode($factura->datos_json, true);
                $fechaFacturacion = isset($datosJSON['FechaFacturacion']) ? $datosJSON['FechaFacturacion'] : null;
                
                if (!$fechaFacturacion) continue;
                
                // Obtener historial de estados de la factura
                $historialEstados = HistorialEstadoFactura::where('numero', $factura->numero)
                    ->orderBy('fecha', 'asc')
                    ->get();
                
                $pagoCompleto = null;
                $montoPagado = 0;
                $fechaPago = null;
                
                foreach ($historialEstados as $estado) {
                    if ($estado->estado == 3 && $estado->monto > 0) {
                        $montoPagado += $estado->monto;
                        $fechaPago = $estado->fecha;
                    }
                }
                
                $estado = 'pendiente';
                $diasPago = null;
                
                if ($montoPagado > 0 && $fechaPago) {
                    $estado = 'pagada';
                    $diasPago = Carbon::parse($fechaFacturacion)->diffInDays(Carbon::parse($fechaPago));
                    $tiemposPago[] = $diasPago;
                }
                
                $tipoFactura = $this->detectarTipoFactura($datosJSON);
                $montoEstimado = $this->estimarMontoFactura($datosJSON, $montoPagado);
                $montosTotales[] = $montoEstimado;
                
                $historiaPagos[] = [
                    'numero_factura' => $factura->numero,
                    'fecha_facturacion' => $fechaFacturacion,
                    'fecha_pago' => $fechaPago,
                    'dias_pago' => $diasPago,
                    'monto_pagado' => $montoPagado,
                    'monto_estimado' => $montoEstimado,
                    'estado' => $estado,
                    'tipo_factura' => $tipoFactura
                ];
            }
            
            // Ordenar por fecha de facturación más reciente
            usort($historiaPagos, function($a, $b) {
                return strtotime($b['fecha_facturacion']) - strtotime($a['fecha_facturacion']);
            });
            
            return [
                'facturas_historicas' => $historiaPagos,
                'resumen_comportamiento' => [
                    'total_facturas' => count($historiaPagos),
                    'facturas_pagadas' => count(array_filter($historiaPagos, fn($f) => $f['estado'] === 'pagada')),
                    'tiempo_promedio_pago' => !empty($tiemposPago) ? round(array_sum($tiemposPago) / count($tiemposPago), 1) : 0,
                    'tiempo_minimo_pago' => !empty($tiemposPago) ? min($tiemposPago) : 0,
                    'tiempo_maximo_pago' => !empty($tiemposPago) ? max($tiemposPago) : 0,
                    'monto_total_facturado' => array_sum($montosTotales),
                    'monto_total_pagado' => array_sum(array_column($historiaPagos, 'monto_pagado'))
                ],
                'patron_pago' => $this->identificarPatronPago($tiemposPago),
                'ultimas_facturas' => array_slice($historiaPagos, 0, 5)
            ];
            
        } catch (\Exception $e) {
            return [
                'facturas_historicas' => [],
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * ESTIMACIÓN DE TIEMPO PARA NUEVA VENTA
     * Predice cuándo pagará una nueva venta basándose en comportamiento histórico
     */
    private function estimarTiempoPagoNuevaVenta($clienteId)
    {
        try {
            $historiaPagos = $this->obtenerHistoriaPagos($clienteId);
            
            if (empty($historiaPagos['resumen_comportamiento']) || 
                $historiaPagos['resumen_comportamiento']['facturas_pagadas'] == 0) {
                return [
                    'estimacion_disponible' => false,
                    'razon' => 'No hay historial de pagos suficiente para realizar estimación',
                    'recomendacion' => 'Establecer condiciones de pago estándar (30-45 días)'
                ];
            }
            
            $resumen = $historiaPagos['resumen_comportamiento'];
            $facturasPagadas = array_filter($historiaPagos['facturas_historicas'], fn($f) => $f['estado'] === 'pagada');
            
            // Extraer tiempos de pago para cálculos estadísticos
            $tiemposPago = array_column($facturasPagadas, 'dias_pago');
            
            if (count($tiemposPago) < 2) {
                return [
                    'estimacion_disponible' => false,
                    'razon' => 'Historial insuficiente (menos de 2 pagos)',
                    'tiempo_unico' => $tiemposPago[0] ?? null
                ];
            }
            
            // Cálculos estadísticos
            $promedio = $resumen['tiempo_promedio_pago'];
            $mediana = $this->calcularMediana($tiemposPago);
            $desviacion = $this->calcularDesviacionEstandar($tiemposPago, $promedio);
            
            // Percentiles para rangos de estimación
            $percentil25 = $this->calcularPercentil($tiemposPago, 25);
            $percentil75 = $this->calcularPercentil($tiemposPago, 75);
            $percentil90 = $this->calcularPercentil($tiemposPago, 90);
            
            // Análisis de tendencia (¿está mejorando o empeorando?)
            $tendencia = $this->analizarTendenciaPagos($facturasPagadas);
            
            // Generar estimaciones
            $fechaHoy = now();
            
            return [
                'estimacion_disponible' => true,
                'datos_base' => [
                    'facturas_analizadas' => count($tiemposPago),
                    'periodo_analisis' => [
                        'desde' => min(array_column($facturasPagadas, 'fecha_facturacion')),
                        'hasta' => max(array_column($facturasPagadas, 'fecha_facturacion'))
                    ]
                ],
                'estadisticas_pago' => [
                    'promedio_dias' => $promedio,
                    'mediana_dias' => $mediana,
                    'desviacion_estandar' => round($desviacion, 1),
                    'rango_habitual' => [$percentil25, $percentil75],
                    'tiempo_minimo' => $resumen['tiempo_minimo_pago'],
                    'tiempo_maximo' => $resumen['tiempo_maximo_pago']
                ],
                'estimaciones_nueva_venta' => [
                    'escenario_optimista' => [
                        'dias_estimados' => $percentil25,
                        'fecha_estimada' => $fechaHoy->copy()->addDays($percentil25)->format('Y-m-d'),
                        'probabilidad' => '25%'
                    ],
                    'escenario_probable' => [
                        'dias_estimados' => round($mediana),
                        'fecha_estimada' => $fechaHoy->copy()->addDays($mediana)->format('Y-m-d'),
                        'probabilidad' => '50%'
                    ],
                    'escenario_conservador' => [
                        'dias_estimados' => $percentil75,
                        'fecha_estimada' => $fechaHoy->copy()->addDays($percentil75)->format('Y-m-d'),
                        'probabilidad' => '75%'
                    ],
                    'escenario_pesimista' => [
                        'dias_estimados' => $percentil90,
                        'fecha_estimada' => $fechaHoy->copy()->addDays($percentil90)->format('Y-m-d'),
                        'probabilidad' => '90%'
                    ]
                ],
                'tendencia_comportamiento' => $tendencia,
                'recomendaciones' => $this->generarRecomendacionesPago($promedio, $desviacion, $tendencia),
                'confiabilidad_estimacion' => $this->evaluarConfiabilidadEstimacion(count($tiemposPago), $desviacion, $promedio)
            ];
            
        } catch (\Exception $e) {
            return [
                'estimacion_disponible' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * ANÁLISIS DETALLADO DE COMPORTAMIENTO DE FACTURACIÓN
     */
    private function analizarComportamientoFacturacion($clienteId)
    {
        try {
            $ventas = Venta::where('ClienteId', $clienteId)->get();
            $codigosCotizacion = $ventas->pluck('CodigoCotizacion');
            $facturas = Factura::whereIn('numero', $codigosCotizacion)->get();
            
            $facturasSENCE = 0;
            $facturasCliente = 0;
            $tiemposFacturacion = [];
            $valoresPorTipo = ['sence' => [], 'cliente' => []];
            
            foreach ($facturas as $factura) {
                $datosJSON = json_decode($factura->datos_json, true);
                $tipoFactura = $this->detectarTipoFactura($datosJSON);
                
                if ($tipoFactura === 'sence') {
                    $facturasSENCE++;
                    $valoresPorTipo['sence'][] = $this->estimarMontoFactura($datosJSON, 0);
                } else {
                    $facturasCliente++;
                    $valoresPorTipo['cliente'][] = $this->estimarMontoFactura($datosJSON, 0);
                }
                
                // Calcular tiempo de facturación si es posible
                $venta = $ventas->where('CodigoCotizacion', $factura->numero)->first();
                if ($venta && isset($datosJSON['FechaFacturacion'])) {
                    $fechaInicio = Carbon::parse($venta->FechaInicio);
                    $fechaFacturacion = Carbon::parse($datosJSON['FechaFacturacion']);
                    $tiemposFacturacion[] = $fechaInicio->diffInDays($fechaFacturacion);
                }
            }
            
            $totalFacturas = $facturasSENCE + $facturasCliente;
            
            return [
                'resumen_tipos' => [
                    'total_facturas' => $totalFacturas,
                    'facturas_sence' => $facturasSENCE,
                    'facturas_cliente' => $facturasCliente,
                    'porcentaje_sence' => $totalFacturas > 0 ? round(($facturasSENCE / $totalFacturas) * 100, 1) : 0,
                    'porcentaje_cliente' => $totalFacturas > 0 ? round(($facturasCliente / $totalFacturas) * 100, 1) : 0
                ],
                'preferencia_financiamiento' => $this->determinarPreferenciaFinanciamiento($facturasSENCE, $facturasCliente),
                'valores_promedio' => [
                    'factura_sence_promedio' => !empty($valoresPorTipo['sence']) ? round(array_sum($valoresPorTipo['sence']) / count($valoresPorTipo['sence']), 2) : 0,
                    'factura_cliente_promedio' => !empty($valoresPorTipo['cliente']) ? round(array_sum($valoresPorTipo['cliente']) / count($valoresPorTipo['cliente']), 2) : 0
                ],
                'tiempos_facturacion' => [
                    'tiempo_promedio_dias' => !empty($tiemposFacturacion) ? round(array_sum($tiemposFacturacion) / count($tiemposFacturacion), 1) : 0,
                    'tiempo_minimo' => !empty($tiemposFacturacion) ? min($tiemposFacturacion) : 0,
                    'tiempo_maximo' => !empty($tiemposFacturacion) ? max($tiemposFacturacion) : 0
                ]
            ];
            
        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
                'resumen_tipos' => [
                    'total_facturas' => 0,
                    'facturas_sence' => 0,
                    'facturas_cliente' => 0
                ]
            ];
        }
    }

    /**
     * ANÁLISIS DETALLADO DE MOROSIDAD
     */
    private function analizarMorosidadDetallada($clienteId)
    {
        try {
            $historiaPagos = $this->obtenerHistoriaPagos($clienteId);
            $facturas = $historiaPagos['facturas_historicas'] ?? [];
            
            if (empty($facturas)) {
                return [
                    'clasificacion' => 'sin_datos',
                    'facturas_analizadas' => 0
                ];
            }
            
            $facturasPagadas = array_filter($facturas, fn($f) => $f['estado'] === 'pagada');
            $facturasPendientes = array_filter($facturas, fn($f) => $f['estado'] === 'pendiente');
            
            $tiemposPago = array_column($facturasPagadas, 'dias_pago');
            $porcentajePagadas = (count($facturasPagadas) / count($facturas)) * 100;
            
            // Análisis de puntualidad (facturas pagadas en <= 30 días)
            $facturasPuntuales = array_filter($facturasPagadas, fn($f) => $f['dias_pago'] <= 30);
            $facturasRetrasadas = array_filter($facturasPagadas, fn($f) => $f['dias_pago'] > 30 && $f['dias_pago'] <= 90);
            $facturasMorosas = array_filter($facturasPagadas, fn($f) => $f['dias_pago'] > 90);
            
            // Análisis de facturas pendientes críticas
            $pendientesCriticas = [];
            foreach ($facturasPendientes as $pendiente) {
                $diasPendientes = Carbon::parse($pendiente['fecha_facturacion'])->diffInDays(now());
                if ($diasPendientes > 60) {
                    $pendientesCriticas[] = array_merge($pendiente, ['dias_pendientes' => $diasPendientes]);
                }
            }
            
            $promedioPago = !empty($tiemposPago) ? array_sum($tiemposPago) / count($tiemposPago) : 0;
            $clasificacion = $this->clasificarMorosidadAvanzada($porcentajePagadas, $promedioPago, count($pendientesCriticas));
            
            return [
                'clasificacion' => $clasificacion,
                'facturas_analizadas' => count($facturas),
                'resumen_pagos' => [
                    'facturas_pagadas' => count($facturasPagadas),
                    'facturas_pendientes' => count($facturasPendientes),
                    'porcentaje_pagadas' => round($porcentajePagadas, 1),
                    'tiempo_promedio_pago' => round($promedioPago, 1)
                ],
                'analisis_puntualidad' => [
                    'puntuales_30_dias' => count($facturasPuntuales),
                    'retrasadas_30_90_dias' => count($facturasRetrasadas),
                    'morosas_mas_90_dias' => count($facturasMorosas),
                    'porcentaje_puntualidad' => count($facturasPagadas) > 0 ? round((count($facturasPuntuales) / count($facturasPagadas)) * 100, 1) : 0
                ],
                'facturas_criticas' => [
                    'pendientes_criticas' => count($pendientesCriticas),
                    'detalle_criticas' => $pendientesCriticas
                ],
                'tendencia_mejora' => $this->analizarTendenciaMejora($facturasPagadas),
                'recomendaciones_comerciales' => $this->generarRecomendacionesComerciales($clasificacion, $porcentajePagadas, $promedioPago)
            ];
            
        } catch (\Exception $e) {
            return [
                'clasificacion' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * ANÁLISIS DE FLUJO COMERCIAL (SENCE vs DIRECTO)
     */
    private function analizarFlujoComercial($clienteId)
    {
        try {
            $ventas = Venta::where('ClienteId', $clienteId)->pluck('CodigoCotizacion');
            
            $flujoCompleto = 0; // Ventas con financiamiento SENCE (estado 3)
            $flujoSimple = 0;   // Ventas directas (solo estado 1)
            $tiemposCompleto = [];
            $tiemposSimple = [];
            $valoresCompleto = [];
            $valoresSimple = [];
            
            foreach ($ventas as $codigoCotizacion) {
                $venta = Venta::where('CodigoCotizacion', $codigoCotizacion)->first();
                if (!$venta) continue;
                
                // Verificar si tiene estado 3 (financiamiento SENCE)
                $tieneEstado3 = HistorialEstadoVenta::where('numero', $codigoCotizacion)
                    ->where('estado', 3)
                    ->exists();
                
                $tieneEstado1 = HistorialEstadoVenta::where('numero', $codigoCotizacion)
                    ->where('estado', 1)
                    ->exists();
                
                if ($tieneEstado3 && $tieneEstado1) {
                    $flujoCompleto++;
                    $valoresCompleto[] = $venta->ValorFinalComercializacion ?? 0;
                    
                    // Calcular tiempo del flujo completo
                    $tiempoFlujo = $this->calcularTiempoFlujoCompleto($codigoCotizacion);
                    if ($tiempoFlujo) $tiemposCompleto[] = $tiempoFlujo;
                    
                } elseif ($tieneEstado1 && !$tieneEstado3) {
                    $flujoSimple++;
                    $valoresSimple[] = $venta->ValorFinalComercializacion ?? 0;
                    
                    // Calcular tiempo del flujo simple
                    $tiempoFlujo = $this->calcularTiempoFlujoSimple($codigoCotizacion);
                    if ($tiempoFlujo) $tiemposSimple[] = $tiempoFlujo;
                }
            }
            
            $totalVentas = $flujoCompleto + $flujoSimple;
            
            return [
                'resumen_flujos' => [
                    'total_ventas' => $totalVentas,
                    'flujo_completo_sence' => $flujoCompleto,
                    'flujo_simple_directo' => $flujoSimple,
                    'porcentaje_sence' => $totalVentas > 0 ? round(($flujoCompleto / $totalVentas) * 100, 1) : 0,
                    'porcentaje_directo' => $totalVentas > 0 ? round(($flujoSimple / $totalVentas) * 100, 1) : 0
                ],
                'preferencia_cliente' => $this->determinarPreferenciaFlujo($flujoCompleto, $flujoSimple),
                'comparativa_tiempos' => [
                    'flujo_completo_promedio' => !empty($tiemposCompleto) ? round(array_sum($tiemposCompleto) / count($tiemposCompleto), 1) : 0,
                    'flujo_simple_promedio' => !empty($tiemposSimple) ? round(array_sum($tiemposSimple) / count($tiemposSimple), 1) : 0,
                    'diferencia_tiempo' => $this->calcularDiferenciaTiempos($tiemposCompleto, $tiemposSimple)
                ],
                'comparativa_valores' => [
                    'valor_promedio_completo' => !empty($valoresCompleto) ? round(array_sum($valoresCompleto) / count($valoresCompleto), 2) : 0,
                    'valor_promedio_simple' => !empty($valoresSimple) ? round(array_sum($valoresSimple) / count($valoresSimple), 2) : 0,
                    'total_facturado_sence' => array_sum($valoresCompleto),
                    'total_facturado_directo' => array_sum($valoresSimple)
                ],
                'recomendacion_flujo' => $this->recomendarFlujoOptimo($flujoCompleto, $flujoSimple, $tiemposCompleto, $tiemposSimple, $valoresCompleto, $valoresSimple)
            ];
            
        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
                'resumen_flujos' => [
                    'total_ventas' => 0,
                    'flujo_completo_sence' => 0,
                    'flujo_simple_directo' => 0
                ]
            ];
        }
    }

    private function obtenerVentasHistoricas($clienteId)
    {
        $ventas = Venta::where('ClienteId', $clienteId)
            ->orderBy('FechaInicio', 'desc')
            ->get()
            ->map(function($venta) {
                return [
                    'codigo_cotizacion' => $venta->CodigoCotizacion,
                    'fecha_inicio' => $venta->FechaInicio,
                    'valor_comercializacion' => $venta->ValorComercializacion,
                    'estado_actual' => $this->obtenerEstadoActualVenta($venta->CodigoCotizacion)
                ];
            });

        // Agrupación por año y mes
        $ventasPorPeriodo = $ventas->groupBy(function($venta) {
            return Carbon::parse($venta['fecha_inicio'])->format('Y-m');
        })->map(function($ventasMes) {
            return [
                'cantidad' => $ventasMes->count(),
                'valor_total' => $ventasMes->sum('valor_comercializacion'),
                'valor_promedio' => $ventasMes->avg('valor_comercializacion')
            ];
        });

        return [
            'ventas_detalladas' => $ventas,
            'agrupacion_temporal' => $ventasPorPeriodo,
            'tendencia_valores' => $this->calcularTendenciaValores($ventas)
        ];
    }

    private function obtenerAnalisisTiempos($clienteId)
    {
        $ventas = Venta::where('ClienteId', $clienteId)->pluck('CodigoCotizacion');
        
        $tiemposEtapas = [];
        foreach ($ventas as $codigoCotizacion) {
            $historial = HistorialEstadoVenta::where('numero', $codigoCotizacion)
                ->orderBy('fecha', 'asc')
                ->get();
            
            $tiempoEtapa = $this->calcularTiempoEtapa0a1($historial);
            if ($tiempoEtapa !== null) {
                $tiemposEtapas[] = $tiempoEtapa;
            }
        }

        return [
            'tiempo_promedio_desarrollo' => !empty($tiemposEtapas) ? array_sum($tiemposEtapas) / count($tiemposEtapas) : 0,
            'tiempo_minimo' => !empty($tiemposEtapas) ? min($tiemposEtapas) : 0,
            'tiempo_maximo' => !empty($tiemposEtapas) ? max($tiemposEtapas) : 0,
            'distribucion_tiempos' => $this->distribuirTiempos($tiemposEtapas),
            'total_proyectos_analizados' => count($tiemposEtapas)
        ];
    }

    private function obtenerAnalisisFacturacion($clienteId)
    {
        $ventas = Venta::where('ClienteId', $clienteId)->get();
        $facturas = Factura::whereIn('numero', $ventas->pluck('CodigoCotizacion'))->get();
        
        $facturasSENCE = $facturas->filter(function($factura) {
            $data = json_decode($factura->datos_json, true);
            return isset($data['TipoFactura']) && $data['TipoFactura'] === 'SENCE';
        });

        $facturasCliente = $facturas->filter(function($factura) {
            $data = json_decode($factura->datos_json, true);
            return isset($data['TipoFactura']) && $data['TipoFactura'] === 'Cliente';
        });

        return [
            'total_facturas' => $facturas->count(),
            'facturas_sence' => $facturasSENCE->count(),
            'facturas_cliente' => $facturasCliente->count(),
            'porcentaje_financiamiento' => $facturas->count() > 0 ? 
                ($facturasSENCE->count() / $facturas->count()) * 100 : 0,
            'preferencia_facturacion' => $this->determinarPreferenciaFacturacion($facturasSENCE->count(), $facturasCliente->count())
        ];
    }

    private function obtenerAnalisisPagos($clienteId)
    {
        $ventas = Venta::where('ClienteId', $clienteId)->pluck('CodigoCotizacion');
        $facturas = Factura::whereIn('numero', $ventas)->get();
        
        $tiemposPago = [];
        $facturasPagadas = 0;
        $facturasPendientes = 0;
        $montoTotalPagado = 0;
        $montoTotalPendiente = 0;

        foreach ($facturas as $factura) {
            $historial = HistorialEstadoFactura::where('numero', $factura->numero)
                ->orderBy('fecha', 'desc')
                ->first();
            
            if ($historial && $historial->estado == 3 && $historial->monto > 0) {
                $facturasPagadas++;
                $montoTotalPagado += $historial->monto;
                
                $data = json_decode($factura->datos_json, true);
                if (isset($data['FechaFacturacion'])) {
                    $tiempoPago = Carbon::parse($data['FechaFacturacion'])
                        ->diffInDays(Carbon::parse($historial->fecha));
                    $tiemposPago[] = $tiempoPago;
                }
            } else {
                $facturasPendientes++;
                // Estimar monto pendiente basado en valor promedio
                $montoTotalPendiente += $facturas->where('numero', $factura->numero)->first() ? 
                    ($montoTotalPagado / max($facturasPagadas, 1)) : 0;
            }
        }

        return [
            'facturas_pagadas' => $facturasPagadas,
            'facturas_pendientes' => $facturasPendientes,
            'porcentaje_pago' => $facturas->count() > 0 ? 
                ($facturasPagadas / $facturas->count()) * 100 : 0,
            'tiempo_promedio_pago' => !empty($tiemposPago) ? 
                array_sum($tiemposPago) / count($tiemposPago) : 0,
            'monto_total_pagado' => $montoTotalPagado,
            'monto_estimado_pendiente' => $montoTotalPendiente,
            'clasificacion_morosidad' => $this->clasificarMorosidad($facturasPagadas, $facturasPendientes, $tiemposPago)
        ];
    }

    private function obtenerComportamientoFlujo($clienteId)
    {
        $ventas = Venta::where('ClienteId', $clienteId)->pluck('CodigoCotizacion');
        $flujosCompletos = 0;
        $flujosSimples = 0;

        foreach ($ventas as $codigoCotizacion) {
            $tieneEstado3 = HistorialEstadoVenta::where('numero', $codigoCotizacion)
                ->where('estado', 3)
                ->exists();
            
            if ($tieneEstado3) {
                $flujosCompletos++;
            } else {
                $flujosSimples++;
            }
        }

        $total = $flujosCompletos + $flujosSimples;

        return [
            'flujos_completos' => $flujosCompletos,
            'flujos_simples' => $flujosSimples,
            'porcentaje_financiamiento' => $total > 0 ? ($flujosCompletos / $total) * 100 : 0,
            'preferencia_flujo' => $this->determinarPreferenciaFlujo($flujosCompletos, $flujosSimples),
            'adopcion_sence' => $this->evaluarAdopcionSENCE($flujosCompletos, $total)
        ];
    }

    private function obtenerTendenciasTemporales($clienteId)
    {
        $ventas = Venta::where('ClienteId', $clienteId)
            ->orderBy('FechaInicio')
            ->get()
            ->groupBy(function($venta) {
                return Carbon::parse($venta->FechaInicio)->format('Y');
            });

        $tendenciasPorAño = $ventas->map(function($ventasAño) {
            return [
                'cantidad_ventas' => $ventasAño->count(),
                'valor_total' => $ventasAño->sum('ValorComercializacion'),
                'valor_promedio' => $ventasAño->avg('ValorComercializacion')
            ];
        });

        return [
            'evolucion_anual' => $tendenciasPorAño,
            'crecimiento_ventas' => $this->calcularCrecimiento($tendenciasPorAño, 'cantidad_ventas'),
            'crecimiento_valores' => $this->calcularCrecimiento($tendenciasPorAño, 'valor_total'),
            'estacionalidad' => $this->analizarEstacionalidad($clienteId)
        ];
    }

    private function obtenerComparativaMercado($clienteId)
    {
        // Obtener métricas del cliente
        $resumenCliente = $this->obtenerResumenGeneral($clienteId);
        
        // Obtener métricas promedio del mercado
        $promedioMercado = [
            'valor_promedio_venta' => Venta::avg('ValorComercializacion'),
            'ventas_promedio_por_cliente' => Venta::count() / Cliente::count(),
            'tiempo_promedio_desarrollo' => $this->obtenerTiempoPromedioMercado()
        ];

        return [
            'posicion_valor_ventas' => $this->calcularPosicionMercado($clienteId, 'valor_ventas'),
            'posicion_cantidad_ventas' => $this->calcularPosicionMercado($clienteId, 'cantidad_ventas'),
            'comparativa_valores' => [
                'cliente' => $resumenCliente['valor_promedio_venta'],
                'mercado' => $promedioMercado['valor_promedio_venta'],
                'diferencia_porcentual' => $this->calcularDiferenciaPorcentual(
                    $resumenCliente['valor_promedio_venta'],
                    $promedioMercado['valor_promedio_venta']
                )
            ],
            'ranking_general' => $this->obtenerRankingCliente($clienteId)
        ];
    }

    // Métodos auxiliares adicionales
    private function calcularTiempoEtapa0a1($historial)
    {
        $fechaEstado0 = $historial->where('estado', 0)->first()?->fecha;
        $fechaEstado1 = $historial->where('estado', 1)->last()?->fecha;
        
        if ($fechaEstado0 && $fechaEstado1) {
            return Carbon::parse($fechaEstado0)->diffInDays(Carbon::parse($fechaEstado1));
        }
        
        return null;
    }

    private function distribuirTiempos($tiempos)
    {
        $rangos = [
            '0-7_dias' => 0,
            '8-15_dias' => 0,
            '16-30_dias' => 0,
            '31-60_dias' => 0,
            'mas_60_dias' => 0
        ];

        foreach ($tiempos as $tiempo) {
            if ($tiempo <= 7) $rangos['0-7_dias']++;
            elseif ($tiempo <= 15) $rangos['8-15_dias']++;
            elseif ($tiempo <= 30) $rangos['16-30_dias']++;
            elseif ($tiempo <= 60) $rangos['31-60_dias']++;
            else $rangos['mas_60_dias']++;
        }

        return $rangos;
    }

    private function obtenerEstadoActualVenta($codigoCotizacion)
    {
        return HistorialEstadoVenta::where('numero', $codigoCotizacion)
            ->orderBy('fecha', 'desc')
            ->first()?->estado ?? 'desconocido';
    }

    private function calcularTendenciaValores($ventas)
    {
        if ($ventas->count() < 2) return 'insuficientes_datos';
        
        $valores = $ventas->pluck('valor_comercializacion')->toArray();
        $primera_mitad = array_slice($valores, 0, ceil(count($valores) / 2));
        $segunda_mitad = array_slice($valores, ceil(count($valores) / 2));
        
        $promedio_primera = array_sum($primera_mitad) / count($primera_mitad);
        $promedio_segunda = array_sum($segunda_mitad) / count($segunda_mitad);
        
        if ($promedio_segunda > $promedio_primera * 1.1) return 'creciente';
        if ($promedio_segunda < $promedio_primera * 0.9) return 'decreciente';
        return 'estable';
    }

    private function determinarPreferenciaFacturacion($sence, $cliente)
    {
        if ($sence == 0 && $cliente == 0) return 'sin_datos';
        if ($sence == 0) return 'solo_cliente';
        if ($cliente == 0) return 'solo_sence';
        
        $ratio = $sence / ($sence + $cliente);
        if ($ratio > 0.7) return 'preferencia_sence';
        if ($ratio < 0.3) return 'preferencia_cliente';
        return 'mixto';
    }

    private function clasificarMorosidad($pagadas, $pendientes, $tiemposPago)
    {
        $total = $pagadas + $pendientes;
        if ($total == 0) return 'sin_datos';
        
        $porcentajePago = ($pagadas / $total) * 100;
        $tiempoPromedioPago = !empty($tiemposPago) ? array_sum($tiemposPago) / count($tiemposPago) : 0;
        
        if ($porcentajePago >= 95 && $tiempoPromedioPago <= 30) return 'excelente';
        if ($porcentajePago >= 80 && $tiempoPromedioPago <= 45) return 'bueno';
        if ($porcentajePago >= 60 && $tiempoPromedioPago <= 60) return 'regular';
        if ($porcentajePago >= 40) return 'malo';
        return 'critico';
    }

    private function determinarPreferenciaFlujo($completos, $simples)
    {
        $total = $completos + $simples;
        if ($total == 0) return 'sin_datos';
        
        $ratio = $completos / $total;
        if ($ratio > 0.7) return 'preferencia_financiamiento';
        if ($ratio < 0.3) return 'preferencia_directo';
        return 'mixto';
    }

    private function evaluarAdopcionSENCE($completos, $total)
    {
        if ($total == 0) return 'sin_datos';
        
        $porcentaje = ($completos / $total) * 100;
        if ($porcentaje >= 80) return 'alto_uso';
        if ($porcentaje >= 50) return 'uso_moderado';
        if ($porcentaje >= 20) return 'uso_ocasional';
        return 'poco_uso';
    }

    private function calcularCrecimiento($datos, $campo)
    {
        $años = array_keys($datos->toArray());
        if (count($años) < 2) return 'insuficientes_datos';
        
        $primerAño = $datos[$años[0]][$campo];
        $ultimoAño = $datos[$años[count($años) - 1]][$campo];
        
        if ($primerAño == 0) return 'sin_base_calculo';
        
        return (($ultimoAño - $primerAño) / $primerAño) * 100;
    }

    private function analizarEstacionalidad($clienteId)
    {
        $ventasPorMes = Venta::where('ClienteId', $clienteId)
            ->selectRaw('MONTH(FechaInicio) as mes, COUNT(*) as cantidad, AVG(ValorComercializacion) as valor_promedio')
            ->groupBy('mes')
            ->orderBy('mes')
            ->get()
            ->keyBy('mes');

        $meses = [];
        for ($i = 1; $i <= 12; $i++) {
            $meses[$i] = $ventasPorMes->get($i, (object)['cantidad' => 0, 'valor_promedio' => 0]);
        }

        return $meses;
    }

    private function obtenerTiempoPromedioMercado()
    {
        // Simplificado - en una implementación real sería más complejo
        return 45; // días promedio estimado
    }

    private function calcularPosicionMercado($clienteId, $tipo)
    {
        // Implementación simplificada del ranking
        $totalClientes = Cliente::count();
        $posicion = Cliente::where('id', '<=', $clienteId)->count();
        
        return [
            'posicion' => $posicion,
            'total_clientes' => $totalClientes,
            'percentil' => ($posicion / $totalClientes) * 100
        ];
    }

    private function calcularDiferenciaPorcentual($valor1, $valor2)
    {
        if ($valor2 == 0) return 0;
        return (($valor1 - $valor2) / $valor2) * 100;
    }

    private function obtenerRankingCliente($clienteId)
    {
        // Ranking basado en valor total de comercializaciones
        $ranking = Cliente::withSum('ventas', 'ValorComercializacion')
            ->orderBy('ventas_sum_valor_comercializacion', 'desc')
            ->pluck('id')
            ->search($clienteId);
        
        return $ranking !== false ? $ranking + 1 : 'no_determinado';
    }

    private function compararMetricas($clienteId1, $clienteId2)
    {
        $resumen1 = $this->obtenerResumenGeneral($clienteId1);
        $resumen2 = $this->obtenerResumenGeneral($clienteId2);
        
        return [
            'ventas' => [
                'cliente_a' => $resumen1['total_ventas'],
                'cliente_b' => $resumen2['total_ventas'],
                'diferencia' => $resumen1['total_ventas'] - $resumen2['total_ventas']
            ],
            'valor_total' => [
                'cliente_a' => $resumen1['valor_total_comercializaciones'],
                'cliente_b' => $resumen2['valor_total_comercializaciones'],
                'diferencia' => $resumen1['valor_total_comercializaciones'] - $resumen2['valor_total_comercializaciones']
            ],
            'valor_promedio' => [
                'cliente_a' => $resumen1['valor_promedio_venta'],
                'cliente_b' => $resumen2['valor_promedio_venta'],
                'diferencia' => $resumen1['valor_promedio_venta'] - $resumen2['valor_promedio_venta']
            ]
        ];
    }

    private function analizarDiferencias($clienteId1, $clienteId2)
    {
        $metricas = $this->compararMetricas($clienteId1, $clienteId2);
        
        $analisis = [];
        
        // Análisis de ventas
        if ($metricas['ventas']['diferencia'] > 0) {
            $analisis['ventas'] = 'Cliente A tiene más ventas';
        } elseif ($metricas['ventas']['diferencia'] < 0) {
            $analisis['ventas'] = 'Cliente B tiene más ventas';
        } else {
            $analisis['ventas'] = 'Ambos clientes tienen igual cantidad de ventas';
        }
        
        // Análisis de valores
        if ($metricas['valor_promedio']['diferencia'] > 0) {
            $analisis['valor_promedio'] = 'Cliente A tiene mayor valor promedio por venta';
        } elseif ($metricas['valor_promedio']['diferencia'] < 0) {
            $analisis['valor_promedio'] = 'Cliente B tiene mayor valor promedio por venta';
        } else {
            $analisis['valor_promedio'] = 'Ambos clientes tienen igual valor promedio';
        }
        
        return $analisis;
    }

    // ===== MÉTODOS AUXILIARES Y DE CÁLCULO =====

    /**
     * Detecta el tipo de factura basándose en los datos JSON
     */
    private function detectarTipoFactura($datosJSON)
    {
        if (!is_array($datosJSON)) return 'cliente';
        
        // Buscar indicadores de financiamiento SENCE
        $indicadoresSENCE = ['sence', 'financiamiento', 'subvención', 'franquicia'];
        
        foreach ($datosJSON as $key => $value) {
            $keyLower = strtolower((string)$key);
            $valueLower = strtolower((string)$value);
            
            foreach ($indicadoresSENCE as $indicador) {
                if (strpos($keyLower, $indicador) !== false || strpos($valueLower, $indicador) !== false) {
                    return 'sence';
                }
            }
        }
        
        return 'cliente';
    }

    /**
     * Estima el monto de la factura basándose en datos disponibles
     */
    private function estimarMontoFactura($datosJSON, $montoPagado = 0)
    {
        if (!is_array($datosJSON)) return $montoPagado;
        
        // Buscar campos de monto en orden de prioridad
        $camposValor = ['Total', 'Monto', 'Valor', 'Subtotal', 'ValorTotal', 'MontoFactura'];
        
        foreach ($camposValor as $campo) {
            if (isset($datosJSON[$campo]) && is_numeric($datosJSON[$campo])) {
                return (float)$datosJSON[$campo];
            }
        }
        
        // Si no se encuentra monto en JSON, usar el monto pagado como referencia
        return $montoPagado > 0 ? $montoPagado : 0;
    }

    /**
     * Identifica patrones en los tiempos de pago
     */
    private function identificarPatronPago($tiemposPago)
    {
        if (empty($tiemposPago)) return 'sin_datos';
        
        $promedio = array_sum($tiemposPago) / count($tiemposPago);
        $desviacion = $this->calcularDesviacionEstandar($tiemposPago, $promedio);
        
        if ($desviacion < 5) return 'muy_consistente';
        if ($desviacion < 15) return 'consistente';
        if ($desviacion < 30) return 'variable';
        return 'muy_variable';
    }

    /**
     * Calcula la mediana de un array de números
     */
    private function calcularMediana($numeros)
    {
        if (empty($numeros)) return 0;
        
        sort($numeros);
        $count = count($numeros);
        $middle = floor($count / 2);
        
        if ($count % 2 == 0) {
            return ($numeros[$middle - 1] + $numeros[$middle]) / 2;
        } else {
            return $numeros[$middle];
        }
    }

    /**
     * Calcula la desviación estándar
     */
    private function calcularDesviacionEstandar($numeros, $promedio)
    {
        if (empty($numeros)) return 0;
        
        $sumaCuadrados = 0;
        foreach ($numeros as $numero) {
            $sumaCuadrados += pow($numero - $promedio, 2);
        }
        
        return sqrt($sumaCuadrados / count($numeros));
    }

    /**
     * Calcula un percentil específico
     */
    private function calcularPercentil($numeros, $percentil)
    {
        if (empty($numeros)) return 0;
        
        sort($numeros);
        $index = ($percentil / 100) * (count($numeros) - 1);
        
        if (floor($index) == $index) {
            return $numeros[$index];
        } else {
            $lower = $numeros[floor($index)];
            $upper = $numeros[ceil($index)];
            return $lower + ($upper - $lower) * ($index - floor($index));
        }
    }

    /**
     * Analiza la tendencia de los pagos (mejorando/empeorando)
     */
    private function analizarTendenciaPagos($facturasPagadas)
    {
        if (count($facturasPagadas) < 3) return 'datos_insuficientes';
        
        // Ordenar por fecha de facturación
        usort($facturasPagadas, function($a, $b) {
            return strtotime($a['fecha_facturacion']) - strtotime($b['fecha_facturacion']);
        });
        
        $mitad = ceil(count($facturasPagadas) / 2);
        $primerasMitad = array_slice($facturasPagadas, 0, $mitad);
        $segundaMitad = array_slice($facturasPagadas, $mitad);
        
        $promedioPrimera = array_sum(array_column($primerasMitad, 'dias_pago')) / count($primerasMitad);
        $promedioSegunda = array_sum(array_column($segundaMitad, 'dias_pago')) / count($segundaMitad);
        
        $diferencia = $promedioSegunda - $promedioPrimera;
        
        if (abs($diferencia) < 3) return 'estable';
        if ($diferencia > 0) return 'empeorando';
        return 'mejorando';
    }

    /**
     * Genera recomendaciones basadas en el comportamiento de pago
     */
    private function generarRecomendacionesPago($promedio, $desviacion, $tendencia)
    {
        $recomendaciones = [];
        
        if ($promedio > 60) {
            $recomendaciones[] = 'Considerar condiciones de pago más estrictas o descuentos por pronto pago';
        }
        
        if ($desviacion > 30) {
            $recomendaciones[] = 'Comportamiento muy variable, evaluar factores estacionales o específicos';
        }
        
        if ($tendencia === 'empeorando') {
            $recomendaciones[] = 'Tendencia negativa detectada, requiere seguimiento comercial urgente';
        } elseif ($tendencia === 'mejorando') {
            $recomendaciones[] = 'Mejora en tiempos de pago, cliente confiable para nuevos negocios';
        }
        
        if ($promedio <= 30) {
            $recomendaciones[] = 'Cliente con excelente historial de pago, candidato para condiciones preferenciales';
        }
        
        return empty($recomendaciones) ? ['Cliente con comportamiento de pago estándar'] : $recomendaciones;
    }

    /**
     * Evalúa la confiabilidad de la estimación
     */
    private function evaluarConfiabilidadEstimacion($cantidadFacturas, $desviacion, $promedio)
    {
        $score = 0;
        
        // Puntos por cantidad de datos
        if ($cantidadFacturas >= 10) $score += 40;
        elseif ($cantidadFacturas >= 5) $score += 25;
        elseif ($cantidadFacturas >= 3) $score += 15;
        else $score += 5;
        
        // Puntos por consistencia (baja desviación)
        $coeficienteVariacion = $promedio > 0 ? ($desviacion / $promedio) : 1;
        if ($coeficienteVariacion < 0.2) $score += 30;
        elseif ($coeficienteVariacion < 0.4) $score += 20;
        elseif ($coeficienteVariacion < 0.6) $score += 10;
        
        // Puntos por tiempo promedio razonable
        if ($promedio >= 15 && $promedio <= 90) $score += 30;
        elseif ($promedio >= 5 && $promedio <= 120) $score += 20;
        else $score += 10;
        
        if ($score >= 80) return ['nivel' => 'alta', 'porcentaje' => $score];
        if ($score >= 60) return ['nivel' => 'media', 'porcentaje' => $score];
        if ($score >= 40) return ['nivel' => 'baja', 'porcentaje' => $score];
        return ['nivel' => 'muy_baja', 'porcentaje' => $score];
    }

    /**
     * Determina la preferencia de financiamiento del cliente
     */
    private function determinarPreferenciaFinanciamiento($facturasSENCE, $facturasCliente)
    {
        $total = $facturasSENCE + $facturasCliente;
        if ($total == 0) return 'sin_datos';
        
        $porcentajeSENCE = ($facturasSENCE / $total) * 100;
        
        if ($porcentajeSENCE >= 80) return 'prefiere_sence';
        if ($porcentajeSENCE >= 60) return 'mayormente_sence';
        if ($porcentajeSENCE >= 40) return 'mixto';
        if ($porcentajeSENCE >= 20) return 'mayormente_directo';
        return 'prefiere_directo';
    }

    /**
     * Clasificación avanzada de morosidad
     */
    private function clasificarMorosidadAvanzada($porcentajePagadas, $promedioPago, $pendientesCriticas)
    {
        $score = 0;
        
        // Evaluación por porcentaje de facturas pagadas
        if ($porcentajePagadas >= 95) $score += 40;
        elseif ($porcentajePagadas >= 85) $score += 30;
        elseif ($porcentajePagadas >= 70) $score += 20;
        elseif ($porcentajePagadas >= 50) $score += 10;
        
        // Evaluación por tiempo promedio de pago
        if ($promedioPago <= 30) $score += 35;
        elseif ($promedioPago <= 45) $score += 25;
        elseif ($promedioPago <= 60) $score += 15;
        elseif ($promedioPago <= 90) $score += 5;
        
        // Penalización por facturas críticas pendientes
        if ($pendientesCriticas == 0) $score += 25;
        elseif ($pendientesCriticas <= 2) $score += 10;
        else $score -= 10;
        
        if ($score >= 85) return 'excelente';
        if ($score >= 70) return 'bueno';
        if ($score >= 50) return 'regular';
        if ($score >= 30) return 'riesgo';
        return 'alto_riesgo';
    }

    /**
     * Analiza si hay tendencia de mejora en los pagos
     */
    private function analizarTendenciaMejora($facturasPagadas)
    {
        if (count($facturasPagadas) < 4) return 'datos_insuficientes';
        
        // Ordenar por fecha y tomar últimas 6 vs anteriores
        usort($facturasPagadas, function($a, $b) {
            return strtotime($a['fecha_facturacion']) - strtotime($b['fecha_facturacion']);
        });
        
        $recientes = array_slice($facturasPagadas, -6);
        $anteriores = array_slice($facturasPagadas, 0, -6);
        
        if (empty($anteriores)) return 'datos_insuficientes';
        
        $promedioReciente = array_sum(array_column($recientes, 'dias_pago')) / count($recientes);
        $promedioAnterior = array_sum(array_column($anteriores, 'dias_pago')) / count($anteriores);
        
        $mejora = $promedioAnterior - $promedioReciente;
        
        if ($mejora > 10) return 'mejorando_significativamente';
        if ($mejora > 5) return 'mejorando';
        if (abs($mejora) <= 5) return 'estable';
        if ($mejora < -10) return 'empeorando_significativamente';
        return 'empeorando';
    }

    /**
     * Genera recomendaciones comerciales específicas
     */
    private function generarRecomendacionesComerciales($clasificacion, $porcentajePagadas, $promedioPago)
    {
        $recomendaciones = [];
        
        switch ($clasificacion) {
            case 'excelente':
                $recomendaciones[] = 'Cliente premium - candidato para condiciones preferenciales';
                $recomendaciones[] = 'Considerar aumentar límites de crédito';
                break;
            case 'bueno':
                $recomendaciones[] = 'Cliente confiable para operaciones regulares';
                $recomendaciones[] = 'Mantener seguimiento estándar';
                break;
            case 'regular':
                $recomendaciones[] = 'Requiere seguimiento más frecuente';
                $recomendaciones[] = 'Considerar garantías adicionales para nuevas ventas';
                break;
            case 'riesgo':
                $recomendaciones[] = 'Implementar seguimiento estrecho de cobranza';
                $recomendaciones[] = 'Evaluar condiciones de pago más estrictas';
                break;
            case 'alto_riesgo':
                $recomendaciones[] = 'CLIENTE DE ALTO RIESGO - requiere aprobación especial';
                $recomendaciones[] = 'Considerar solo ventas con pago anticipado';
                break;
        }
        
        if ($promedioPago > 90) {
            $recomendaciones[] = 'Tiempo de pago excesivo - negociar mejores condiciones';
        }
        
        if ($porcentajePagadas < 70) {
            $recomendaciones[] = 'Alto porcentaje de facturas pendientes - revisar proceso comercial';
        }
        
        return $recomendaciones;
    }

    /**
     * Calcula diferencia de tiempos entre flujos
     */
    private function calcularDiferenciaTiempos($tiemposCompleto, $tiemposSimple)
    {
        if (empty($tiemposCompleto) || empty($tiemposSimple)) {
            return 'no_comparable';
        }
        
        $promedioCompleto = array_sum($tiemposCompleto) / count($tiemposCompleto);
        $promedioSimple = array_sum($tiemposSimple) / count($tiemposSimple);
        
        $diferencia = $promedioCompleto - $promedioSimple;
        return round($diferencia, 1);
    }

    /**
     * Calcula tiempo de flujo completo (con financiamiento)
     */
    private function calcularTiempoFlujoCompleto($codigoCotizacion)
    {
        $estados = HistorialEstadoVenta::where('numero', $codigoCotizacion)
            ->whereIn('estado', [1, 3])
            ->orderBy('fecha', 'asc')
            ->get();
        
        if ($estados->count() < 2) return null;
        
        $fechaInicio = $estados->where('estado', 1)->first()->fecha ?? null;
        $fechaFin = $estados->where('estado', 3)->last()->fecha ?? null;
        
        if (!$fechaInicio || !$fechaFin) return null;
        
        return Carbon::parse($fechaInicio)->diffInDays(Carbon::parse($fechaFin));
    }

    /**
     * Calcula tiempo de flujo simple (directo)
     */
    private function calcularTiempoFlujoSimple($codigoCotizacion)
    {
        $venta = Venta::where('CodigoCotizacion', $codigoCotizacion)->first();
        if (!$venta) return null;
        
        $estadoUno = HistorialEstadoVenta::where('numero', $codigoCotizacion)
            ->where('estado', 1)
            ->first();
        
        if (!$estadoUno) return null;
        
        return Carbon::parse($venta->FechaInicio)->diffInDays(Carbon::parse($estadoUno->fecha));
    }

    /**
     * Recomienda el flujo óptimo para el cliente
     */
    private function recomendarFlujoOptimo($flujoCompleto, $flujoSimple, $tiemposCompleto, $tiemposSimple, $valoresCompleto, $valoresSimple)
    {
        $recomendaciones = [];
        
        // Análisis de preferencia histórica
        if ($flujoCompleto > $flujoSimple) {
            $recomendaciones[] = 'Cliente prefiere financiamiento SENCE - ofertar estas opciones prioritariamente';
        } elseif ($flujoSimple > $flujoCompleto) {
            $recomendaciones[] = 'Cliente prefiere pago directo - preparar propuestas sin financiamiento';
        } else {
            $recomendaciones[] = 'Cliente mixto - preparar ambas opciones de financiamiento';
        }
        
        // Análisis de valores
        $valorPromedioCompleto = !empty($valoresCompleto) ? array_sum($valoresCompleto) / count($valoresCompleto) : 0;
        $valorPromedioSimple = !empty($valoresSimple) ? array_sum($valoresSimple) / count($valoresSimple) : 0;
        
        if ($valorPromedioCompleto > $valorPromedioSimple * 1.5) {
            $recomendaciones[] = 'Proyectos con financiamiento SENCE generan mayor valor - potenciar esta línea';
        } elseif ($valorPromedioSimple > $valorPromedioCompleto * 1.5) {
            $recomendaciones[] = 'Ventas directas más rentables - enfocar en soluciones inmediatas';
        }
        
        // Análisis de tiempos
        $diferenciaTiempo = $this->calcularDiferenciaTiempos($tiemposCompleto, $tiemposSimple);
        if (is_numeric($diferenciaTiempo) && $diferenciaTiempo > 30) {
            $recomendaciones[] = 'Flujo con financiamiento es significativamente más lento - considerar agilizar procesos';
        }
        
        return $recomendaciones;
    }
}
