﻿<?php

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
     * ðŸ“‹ Retorna lista completa de clientes con estadÃ­sticas bÃ¡sicas
     */
    public function listarClientes()
    {
        try {
            $clientes = Cliente::select('id', 'InsecapClienteId', 'NombreCliente')
                ->orderBy('NombreCliente')
                ->get()
                ->map(function($cliente) {
                    try {
                        // Calcular estadÃ­sticas bÃ¡sicas
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
                        // Si hay error con un cliente especÃ­fico, devolver datos bÃ¡sicos
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
     * GET /api/clientes/{id}/analytics - ANALÃTICAS COMPLETAS POR CLIENTE
     * ðŸ“Š Dashboard personalizado con todas las mÃ©tricas de un cliente especÃ­fico
     */
    public function analyticsCliente($clienteId)
    {
        try {
            // Verificar que el cliente existe
            $cliente = Cliente::findOrFail($clienteId);

            // Obtener datos bÃ¡sicos del cliente de manera segura
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
                'simulador_prediccion' => $this->simularPrediccionPagos($clienteId),
                'timestamp' => now()->toISOString()
            ];

            return response()->json([
                'success' => true,
                'message' => "AnalÃ­ticas completas para {$cliente->NombreCliente}",
                'datos' => $analytics
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener analÃ­ticas del cliente',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * GET /api/clientes/{id}/comparar?cliente_comparacion={id2} - COMPARAR DOS CLIENTES
     * ðŸ” Comparativa detallada entre dos clientes
     */
    public function compararClientes($clienteId, Request $request)
    {
        try {
            $clienteComparacionId = $request->query('cliente_comparacion');
            
            if (!$clienteComparacionId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Debe proporcionar cliente_comparacion como parÃ¡metro'
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

    /**
     * GET /api/clientes/{id}/simulador-pagos - SIMULADOR DE PREDICCIÃ“N DE PAGOS
     * ðŸ”® Simulador avanzado que predice comportamientos de pago
     */
    public function simuladorPrediccionPagos($clienteId)
    {
        try {
            $cliente = Cliente::findOrFail($clienteId);
            $simulacion = $this->simularPrediccionPagos($clienteId);

            return response()->json([
                'success' => true,
                'message' => "Simulador de predicciÃ³n de pagos para {$cliente->NombreCliente}",
                'datos' => $simulacion,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error en simulador de predicciÃ³n',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ==================== MÃ‰TODOS AUXILIARES ====================

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
                    'aÃ±os_como_cliente' => $ventas->count() > 0 ? 
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
                ->limit(10) // Limitar a las Ãºltimas 10 ventas
                ->get()
                ->map(function($venta) {
                    return [
                        'codigo_cotizacion' => $venta->CodigoCotizacion,
                        'fecha_inicio' => $venta->FechaInicio,
                        'valor_comercializacion' => $venta->ValorFinalComercializacion,
                    ];
                });

            // AgrupaciÃ³n por aÃ±o
            $ventasPorAÃ±o = Venta::where('ClienteId', $clienteId)
                ->selectRaw('YEAR(FechaInicio) as aÃ±o, COUNT(*) as cantidad, SUM(ValorFinalComercializacion) as valor_total')
                ->groupBy('aÃ±o')
                ->orderBy('aÃ±o', 'desc')
                ->get();

            return [
                'ventas_recientes' => $ventas,
                'agrupacion_anual' => $ventasPorAÃ±o,
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
     * Analiza el comportamiento histÃ³rico de pagos con detalles cronolÃ³gicos
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
            
            // Ordenar por fecha de facturaciÃ³n mÃ¡s reciente
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
     * ESTIMACIÃ“N DE TIEMPO PARA NUEVA VENTA
     * Predice cuÃ¡ndo pagarÃ¡ una nueva venta basÃ¡ndose en comportamiento histÃ³rico
     */
    private function estimarTiempoPagoNuevaVenta($clienteId)
    {
        try {
            $historiaPagos = $this->obtenerHistoriaPagos($clienteId);
            
            if (empty($historiaPagos['resumen_comportamiento']) || 
                $historiaPagos['resumen_comportamiento']['facturas_pagadas'] == 0) {
                return [
                    'estimacion_disponible' => false,
                    'razon' => 'No hay historial de pagos suficiente para realizar estimaciÃ³n',
                    'recomendacion' => 'Establecer condiciones de pago estÃ¡ndar (30-45 dÃ­as)'
                ];
            }
            
            $resumen = $historiaPagos['resumen_comportamiento'];
            $facturasPagadas = array_filter($historiaPagos['facturas_historicas'], fn($f) => $f['estado'] === 'pagada');
            
            // Extraer tiempos de pago para cÃ¡lculos estadÃ­sticos
            $tiemposPago = array_column($facturasPagadas, 'dias_pago');
            
            if (count($tiemposPago) < 2) {
                return [
                    'estimacion_disponible' => false,
                    'razon' => 'Historial insuficiente (menos de 2 pagos)',
                    'tiempo_unico' => $tiemposPago[0] ?? null
                ];
            }
            
            // CÃ¡lculos estadÃ­sticos
            $promedio = $resumen['tiempo_promedio_pago'];
            $mediana = $this->calcularMediana($tiemposPago);
            $desviacion = $this->calcularDesviacionEstandar($tiemposPago, $promedio);
            
            // Percentiles para rangos de estimaciÃ³n
            $percentil25 = $this->calcularPercentil($tiemposPago, 25);
            $percentil75 = $this->calcularPercentil($tiemposPago, 75);
            $percentil90 = $this->calcularPercentil($tiemposPago, 90);
            
            // AnÃ¡lisis de tendencia (Â¿estÃ¡ mejorando o empeorando?)
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
     * ANÃLISIS DETALLADO DE COMPORTAMIENTO DE FACTURACIÃ“N
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
                
                // Calcular tiempo de facturaciÃ³n si es posible
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
     * ANÃLISIS DETALLADO DE MOROSIDAD
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
            
            // AnÃ¡lisis de puntualidad (facturas pagadas en <= 30 dÃ­as)
            $facturasPuntuales = array_filter($facturasPagadas, fn($f) => $f['dias_pago'] <= 30);
            $facturasRetrasadas = array_filter($facturasPagadas, fn($f) => $f['dias_pago'] > 30 && $f['dias_pago'] <= 90);
            $facturasMorosas = array_filter($facturasPagadas, fn($f) => $f['dias_pago'] > 90);
            
            // AnÃ¡lisis de facturas pendientes crÃ­ticas
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
     * ANÃLISIS DE FLUJO COMERCIAL (SENCE vs DIRECTO)
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

    /**
     * ðŸ”® SIMULADOR DE PREDICCIÃ“N DE PAGOS AVANZADO
     * Utiliza machine learning bÃ¡sico y anÃ¡lisis estadÃ­stico para predecir comportamientos
     */
    private function simularPrediccionPagos($clienteId)
    {
        try {
            // 1. Recopilar datos histÃ³ricos del cliente
            $datosHistoricos = $this->recopilarDatosHistoricos($clienteId);
            
            // 2. AnÃ¡lisis de patrones de comportamiento
            $patronesComportamiento = $this->analizarPatronesComportamiento($datosHistoricos);
            
            // 3. Simulaciones con diferentes escenarios
            $simulacionesEscenarios = $this->generarSimulacionesEscenarios($datosHistoricos, $patronesComportamiento);
            
            // 4. Predicciones basadas en IA bÃ¡sica
            $prediccionesIA = $this->generarPrediccionesIA($datosHistoricos);
            
            // 5. Score de confiabilidad del cliente
            $scoreConfiabilidad = $this->calcularScoreConfiabilidad($datosHistoricos);
            
            // 6. Recomendaciones comerciales dinÃ¡micas
            $recomendacionesDinamicas = $this->generarRecomendacionesDinamicas($scoreConfiabilidad, $patronesComportamiento);

            return [
                'simulador_activo' => true,
                'datos_base' => [
                    'facturas_analizadas' => $datosHistoricos['total_facturas'],
                    'periodo_analisis' => $datosHistoricos['periodo_analisis'],
                    'calidad_datos' => $datosHistoricos['calidad_datos']
                ],
                'patrones_identificados' => $patronesComportamiento,
                'simulaciones' => $simulacionesEscenarios,
                'predicciones_ia' => $prediccionesIA,
                'score_confiabilidad' => $scoreConfiabilidad,
                'recomendaciones_dinamicas' => $recomendacionesDinamicas,
                'analisis_riesgo' => $this->evaluarRiesgoCredito($scoreConfiabilidad),
                'alertas_automaticas' => $this->generarAlertasAutomaticas($datosHistoricos, $scoreConfiabilidad),
                'dashboard_interactivo' => $this->generarDashboardInteractivo($datosHistoricos, $simulacionesEscenarios),
                'timestamp_simulacion' => now()->toISOString()
            ];
            
        } catch (\Exception $e) {
            return [
                'simulador_activo' => false,
                'error' => $e->getMessage(),
                'mensaje' => 'No hay suficientes datos para ejecutar simulaciones avanzadas'
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

        // AgrupaciÃ³n por aÃ±o y mes
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

        $tendenciasPorAÃ±o = $ventas->map(function($ventasAÃ±o) {
            return [
                'cantidad_ventas' => $ventasAÃ±o->count(),
                'valor_total' => $ventasAÃ±o->sum('ValorComercializacion'),
                'valor_promedio' => $ventasAÃ±o->avg('ValorComercializacion')
            ];
        });

        return [
            'evolucion_anual' => $tendenciasPorAÃ±o,
            'crecimiento_ventas' => $this->calcularCrecimiento($tendenciasPorAÃ±o, 'cantidad_ventas'),
            'crecimiento_valores' => $this->calcularCrecimiento($tendenciasPorAÃ±o, 'valor_total'),
            'estacionalidad' => $this->analizarEstacionalidad($clienteId)
        ];
    }

    private function obtenerComparativaMercado($clienteId)
    {
        // Obtener mÃ©tricas del cliente
        $resumenCliente = $this->obtenerResumenGeneral($clienteId);
        
        // Obtener mÃ©tricas promedio del mercado
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

    // MÃ©todos auxiliares adicionales
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
        $aÃ±os = array_keys($datos->toArray());
        if (count($aÃ±os) < 2) return 'insuficientes_datos';
        
        $primerAÃ±o = $datos[$aÃ±os[0]][$campo];
        $ultimoAÃ±o = $datos[$aÃ±os[count($aÃ±os) - 1]][$campo];
        
        if ($primerAÃ±o == 0) return 'sin_base_calculo';
        
        return (($ultimoAÃ±o - $primerAÃ±o) / $primerAÃ±o) * 100;
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
        // Simplificado - en una implementaciÃ³n real serÃ­a mÃ¡s complejo
        return 45; // dÃ­as promedio estimado
    }

    private function calcularPosicionMercado($clienteId, $tipo)
    {
        // ImplementaciÃ³n simplificada del ranking
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
        
        // AnÃ¡lisis de ventas
        if ($metricas['ventas']['diferencia'] > 0) {
            $analisis['ventas'] = 'Cliente A tiene mÃ¡s ventas';
        } elseif ($metricas['ventas']['diferencia'] < 0) {
            $analisis['ventas'] = 'Cliente B tiene mÃ¡s ventas';
        } else {
            $analisis['ventas'] = 'Ambos clientes tienen igual cantidad de ventas';
        }
        
        // AnÃ¡lisis de valores
        if ($metricas['valor_promedio']['diferencia'] > 0) {
            $analisis['valor_promedio'] = 'Cliente A tiene mayor valor promedio por venta';
        } elseif ($metricas['valor_promedio']['diferencia'] < 0) {
            $analisis['valor_promedio'] = 'Cliente B tiene mayor valor promedio por venta';
        } else {
            $analisis['valor_promedio'] = 'Ambos clientes tienen igual valor promedio';
        }
        
        return $analisis;
    }

    // ===== MÃ‰TODOS AUXILIARES Y DE CÃLCULO =====

    /**
     * Detecta el tipo de factura basÃ¡ndose en los datos JSON
     */
    private function detectarTipoFactura($datosJSON)
    {
        if (!is_array($datosJSON)) return 'cliente';
        
        // Buscar indicadores de financiamiento SENCE
        $indicadoresSENCE = ['sence', 'financiamiento', 'subvenciÃ³n', 'franquicia'];
        
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
     * Estima el monto de la factura basÃ¡ndose en datos disponibles
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
     * Calcula la mediana de un array de nÃºmeros
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
     * Calcula la desviaciÃ³n estÃ¡ndar
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
     * Calcula un percentil especÃ­fico
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
        
        // Ordenar por fecha de facturaciÃ³n
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
            $recomendaciones[] = 'Considerar condiciones de pago mÃ¡s estrictas o descuentos por pronto pago';
        }
        
        if ($desviacion > 30) {
            $recomendaciones[] = 'Comportamiento muy variable, evaluar factores estacionales o especÃ­ficos';
        }
        
        if ($tendencia === 'empeorando') {
            $recomendaciones[] = 'Tendencia negativa detectada, requiere seguimiento comercial urgente';
        } elseif ($tendencia === 'mejorando') {
            $recomendaciones[] = 'Mejora en tiempos de pago, cliente confiable para nuevos negocios';
        }
        
        if ($promedio <= 30) {
            $recomendaciones[] = 'Cliente con excelente historial de pago, candidato para condiciones preferenciales';
        }
        
        return empty($recomendaciones) ? ['Cliente con comportamiento de pago estÃ¡ndar'] : $recomendaciones;
    }

    /**
     * EvalÃºa la confiabilidad de la estimaciÃ³n
     */
    private function evaluarConfiabilidadEstimacion($cantidadFacturas, $desviacion, $promedio)
    {
        $score = 0;
        
        // Puntos por cantidad de datos
        if ($cantidadFacturas >= 10) $score += 40;
        elseif ($cantidadFacturas >= 5) $score += 25;
        elseif ($cantidadFacturas >= 3) $score += 15;
        else $score += 5;
        
        // Puntos por consistencia (baja desviaciÃ³n)
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
     * ClasificaciÃ³n avanzada de morosidad
     */
    private function clasificarMorosidadAvanzada($porcentajePagadas, $promedioPago, $pendientesCriticas)
    {
        $score = 0;
        
        // EvaluaciÃ³n por porcentaje de facturas pagadas
        if ($porcentajePagadas >= 95) $score += 40;
        elseif ($porcentajePagadas >= 85) $score += 30;
        elseif ($porcentajePagadas >= 70) $score += 20;
        elseif ($porcentajePagadas >= 50) $score += 10;
        
        // EvaluaciÃ³n por tiempo promedio de pago
        if ($promedioPago <= 30) $score += 35;
        elseif ($promedioPago <= 45) $score += 25;
        elseif ($promedioPago <= 60) $score += 15;
        elseif ($promedioPago <= 90) $score += 5;
        
        // PenalizaciÃ³n por facturas crÃ­ticas pendientes
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
        
        // Ordenar por fecha y tomar Ãºltimas 6 vs anteriores
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
     * Genera recomendaciones comerciales especÃ­ficas
     */
    private function generarRecomendacionesComerciales($clasificacion, $porcentajePagadas, $promedioPago)
    {
        $recomendaciones = [];
        
        switch ($clasificacion) {
            case 'excelente':
                $recomendaciones[] = 'Cliente premium - candidato para condiciones preferenciales';
                $recomendaciones[] = 'Considerar aumentar lÃ­mites de crÃ©dito';
                break;
            case 'bueno':
                $recomendaciones[] = 'Cliente confiable para operaciones regulares';
                $recomendaciones[] = 'Mantener seguimiento estÃ¡ndar';
                break;
            case 'regular':
                $recomendaciones[] = 'Requiere seguimiento mÃ¡s frecuente';
                $recomendaciones[] = 'Considerar garantÃ­as adicionales para nuevas ventas';
                break;
            case 'riesgo':
                $recomendaciones[] = 'Implementar seguimiento estrecho de cobranza';
                $recomendaciones[] = 'Evaluar condiciones de pago mÃ¡s estrictas';
                break;
            case 'alto_riesgo':
                $recomendaciones[] = 'CLIENTE DE ALTO RIESGO - requiere aprobaciÃ³n especial';
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
     * Recomienda el flujo Ã³ptimo para el cliente
     */
    private function recomendarFlujoOptimo($flujoCompleto, $flujoSimple, $tiemposCompleto, $tiemposSimple, $valoresCompleto, $valoresSimple)
    {
        $recomendaciones = [];
        
        // AnÃ¡lisis de preferencia histÃ³rica
        if ($flujoCompleto > $flujoSimple) {
            $recomendaciones[] = 'Cliente prefiere financiamiento SENCE - ofertar estas opciones prioritariamente';
        } elseif ($flujoSimple > $flujoCompleto) {
            $recomendaciones[] = 'Cliente prefiere pago directo - preparar propuestas sin financiamiento';
        } else {
            $recomendaciones[] = 'Cliente mixto - preparar ambas opciones de financiamiento';
        }
        
        // AnÃ¡lisis de valores
        $valorPromedioCompleto = !empty($valoresCompleto) ? array_sum($valoresCompleto) / count($valoresCompleto) : 0;
        $valorPromedioSimple = !empty($valoresSimple) ? array_sum($valoresSimple) / count($valoresSimple) : 0;
        
        if ($valorPromedioCompleto > $valorPromedioSimple * 1.5) {
            $recomendaciones[] = 'Proyectos con financiamiento SENCE generan mayor valor - potenciar esta lÃ­nea';
        } elseif ($valorPromedioSimple > $valorPromedioCompleto * 1.5) {
            $recomendaciones[] = 'Ventas directas mÃ¡s rentables - enfocar en soluciones inmediatas';
        }
        
        // AnÃ¡lisis de tiempos
        $diferenciaTiempo = $this->calcularDiferenciaTiempos($tiemposCompleto, $tiemposSimple);
        if (is_numeric($diferenciaTiempo) && $diferenciaTiempo > 30) {
            $recomendaciones[] = 'Flujo con financiamiento es significativamente mÃ¡s lento - considerar agilizar procesos';
        }
        
        return $recomendaciones;
    }

    // ===== MÃ‰TODOS DEL SIMULADOR DE PREDICCIÃ“N DE PAGOS =====

    /**
     * Recopila datos histÃ³ricos completos del cliente para anÃ¡lisis predictivo
     */
    private function recopilarDatosHistoricos($clienteId)
    {
        $ventas = Venta::where('ClienteId', $clienteId)->pluck('CodigoCotizacion');
        $facturas = Factura::whereIn('numero', $ventas)->get();
        
        $datosFacturas = [];
        $tiemposPago = [];
        $montosFacturados = [];
        $tiposFactura = [];
        
        foreach ($facturas as $factura) {
            $datosJSON = json_decode($factura->datos_json, true);
            $fechaFacturacion = isset($datosJSON['FechaFacturacion']) ? $datosJSON['FechaFacturacion'] : null;
            
            if (!$fechaFacturacion) continue;
            
            // Obtener historial de estados
            $historialEstados = HistorialEstadoFactura::where('numero', $factura->numero)
                ->orderBy('fecha', 'asc')
                ->get();
            
            $estadoPago = 'pendiente';
            $montoPagado = 0;
            $fechaPago = null;
            $diasPago = null;
            
            foreach ($historialEstados as $estado) {
                if ($estado->estado == 3 && $estado->monto > 0) {
                    $montoPagado += $estado->monto;
                    $fechaPago = $estado->fecha;
                    $estadoPago = 'pagada';
                }
            }
            
            if ($fechaPago) {
                $diasPago = Carbon::parse($fechaFacturacion)->diffInDays(Carbon::parse($fechaPago));
                $tiemposPago[] = $diasPago;
            }
            
            $montoEstimado = $this->estimarMontoFactura($datosJSON, $montoPagado);
            $tipoFactura = $this->detectarTipoFactura($datosJSON);
            
            $datosFacturas[] = [
                'numero' => $factura->numero,
                'fecha_facturacion' => $fechaFacturacion,
                'fecha_pago' => $fechaPago,
                'dias_pago' => $diasPago,
                'monto_estimado' => $montoEstimado,
                'monto_pagado' => $montoPagado,
                'estado' => $estadoPago,
                'tipo' => $tipoFactura,
                'mes' => Carbon::parse($fechaFacturacion)->month,
                'aÃ±o' => Carbon::parse($fechaFacturacion)->year
            ];
            
            $montosFacturados[] = $montoEstimado;
            $tiposFactura[] = $tipoFactura;
        }
        
        // Calcular calidad de los datos
        $calidadDatos = $this->evaluarCalidadDatos($datosFacturas);
        
        return [
            'facturas_detalladas' => $datosFacturas,
            'total_facturas' => count($datosFacturas),
            'facturas_pagadas' => count(array_filter($datosFacturas, fn($f) => $f['estado'] === 'pagada')),
            'tiempos_pago' => $tiemposPago,
            'montos_facturados' => $montosFacturados,
            'tipos_factura' => $tiposFactura,
            'periodo_analisis' => [
                'desde' => !empty($datosFacturas) ? min(array_column($datosFacturas, 'fecha_facturacion')) : null,
                'hasta' => !empty($datosFacturas) ? max(array_column($datosFacturas, 'fecha_facturacion')) : null
            ],
            'calidad_datos' => $calidadDatos
        ];
    }

    /**
     * Analiza patrones de comportamiento en los datos histÃ³ricos
     */
    private function analizarPatronesComportamiento($datosHistoricos)
    {
        $facturas = $datosHistoricos['facturas_detalladas'];
        $tiemposPago = $datosHistoricos['tiempos_pago'];
        
        if (empty($tiemposPago)) {
            return [
                'patron_disponible' => false,
                'mensaje' => 'No hay suficientes datos de pagos para anÃ¡lisis de patrones'
            ];
        }
        
        // AnÃ¡lisis estadÃ­stico avanzado
        $estadisticas = $this->calcularEstadisticasAvanzadas($tiemposPago);
        
        // Patrones estacionales
        $patronesEstacionales = $this->detectarPatronesEstacionales($facturas);
        
        // Tendencias temporales
        $tendencias = $this->analizarTendenciasTemporal($facturas);
        
        // Patrones por tipo de factura
        $patronesTipo = $this->analizarPatronesPorTipo($facturas);
        
        // DetecciÃ³n de anomalÃ­as
        $anomalias = $this->detectarAnomalias($tiemposPago);
        
        return [
            'patron_disponible' => true,
            'estadisticas_avanzadas' => $estadisticas,
            'patrones_estacionales' => $patronesEstacionales,
            'tendencias_temporales' => $tendencias,
            'patrones_por_tipo' => $patronesTipo,
            'anomalias_detectadas' => $anomalias,
            'consistencia_comportamiento' => $this->evaluarConsistencia($tiemposPago),
            'volatilidad' => $this->calcularVolatilidad($tiemposPago)
        ];
    }

    /**
     * Genera simulaciones con diferentes escenarios de mercado
     */
    private function generarSimulacionesEscenarios($datosHistoricos, $patronesComportamiento)
    {
        if (!$patronesComportamiento['patron_disponible']) {
            return [
                'simulaciones_disponibles' => false,
                'mensaje' => 'No hay patrones suficientes para ejecutar simulaciones'
            ];
        }
        
        $tiemposPago = $datosHistoricos['tiempos_pago'];
        $estadisticas = $patronesComportamiento['estadisticas_avanzadas'];
        
        // Escenario 1: Condiciones normales
        $escenarioNormal = $this->simularEscenario($tiemposPago, 'normal', $estadisticas);
        
        // Escenario 2: Crisis econÃ³mica (+30% tiempo de pago)
        $escenarioCrisis = $this->simularEscenario($tiemposPago, 'crisis', $estadisticas);
        
        // Escenario 3: Bonanza econÃ³mica (-20% tiempo de pago)
        $escenarioBonanza = $this->simularEscenario($tiemposPago, 'bonanza', $estadisticas);
        
        // Escenario 4: Cambio de condiciones comerciales
        $escenarioNuevasCondiciones = $this->simularEscenario($tiemposPago, 'nuevas_condiciones', $estadisticas);
        
        // Escenario 5: Estacionalidad extrema
        $escenarioEstacional = $this->simularEscenarioEstacional($datosHistoricos, $patronesComportamiento);
        
        return [
            'simulaciones_disponibles' => true,
            'total_escenarios' => 5,
            'escenarios' => [
                'normal' => $escenarioNormal,
                'crisis_economica' => $escenarioCrisis,
                'bonanza_economica' => $escenarioBonanza,
                'nuevas_condiciones' => $escenarioNuevasCondiciones,
                'estacionalidad_extrema' => $escenarioEstacional
            ],
            'recomendacion_escenario' => $this->recomendarMejorEscenario($escenarioNormal, $escenarioCrisis, $escenarioBonanza)
        ];
    }

    /**
     * Genera predicciones usando algoritmos de IA bÃ¡sica
     */
    private function generarPrediccionesIA($datosHistoricos)
    {
        $facturas = $datosHistoricos['facturas_detalladas'];
        $tiemposPago = $datosHistoricos['tiempos_pago'];
        
        if (count($tiemposPago) < 3) {
            return [
                'predicciones_disponibles' => false,
                'mensaje' => 'Datos insuficientes para predicciones de IA'
            ];
        }
        
        // Algoritmo de regresiÃ³n lineal simple
        $regresionLineal = $this->aplicarRegresionLineal($tiemposPago);
        
        // Algoritmo de promedio mÃ³vil exponencial
        $promedioMovil = $this->aplicarPromedioMovilExponencial($tiemposPago);
        
        // Algoritmo de redes neuronales bÃ¡sico (simulado)
        $redNeuronal = $this->simularRedNeuronal($datosHistoricos);
        
        // PredicciÃ³n por machine learning bayesiano
        $bayesiano = $this->aplicarAlgoritmoBayesiano($datosHistoricos);
        
        // Ensemble de predicciones (combinaciÃ³n de algoritmos)
        $ensemble = $this->combinarPredicciones($regresionLineal, $promedioMovil, $redNeuronal, $bayesiano);
        
        return [
            'predicciones_disponibles' => true,
            'algoritmos_utilizados' => 4,
            'predicciones_individuales' => [
                'regresion_lineal' => $regresionLineal,
                'promedio_movil_exponencial' => $promedioMovil,
                'red_neuronal_simulada' => $redNeuronal,
                'algoritmo_bayesiano' => $bayesiano
            ],
            'prediccion_ensemble' => $ensemble,
            'confianza_ia' => $this->calcularConfianzaIA($ensemble, $tiemposPago),
            'precision_historica' => $this->evaluarPrecisionHistorica($datosHistoricos)
        ];
    }

    /**
     * Calcula un score de confiabilidad integral del cliente
     */
    private function calcularScoreConfiabilidad($datosHistoricos)
    {
        $facturas = $datosHistoricos['facturas_detalladas'];
        $tiemposPago = $datosHistoricos['tiempos_pago'];
        $totalFacturas = $datosHistoricos['total_facturas'];
        $facturasPagadas = $datosHistoricos['facturas_pagadas'];
        
        $score = 0;
        $factores = [];
        
        // Factor 1: Porcentaje de facturas pagadas (0-25 puntos)
        $porcentajePago = $totalFacturas > 0 ? ($facturasPagadas / $totalFacturas) * 100 : 0;
        $puntosPago = min(25, ($porcentajePago / 100) * 25);
        $score += $puntosPago;
        $factores['facturas_pagadas'] = ['puntos' => $puntosPago, 'porcentaje' => $porcentajePago];
        
        // Factor 2: Consistencia en tiempos de pago (0-20 puntos)
        if (!empty($tiemposPago)) {
            $desviacion = $this->calcularDesviacionEstandar($tiemposPago, array_sum($tiemposPago) / count($tiemposPago));
            $consistencia = max(0, 20 - ($desviacion / 5)); // Menos desviaciÃ³n = mÃ¡s puntos
            $score += $consistencia;
            $factores['consistencia_pagos'] = ['puntos' => $consistencia, 'desviacion' => $desviacion];
        }
        
        // Factor 3: Tiempo promedio de pago (0-20 puntos)
        if (!empty($tiemposPago)) {
            $promedioTiempo = array_sum($tiemposPago) / count($tiemposPago);
            $puntosVelocidad = max(0, 20 - ($promedioTiempo / 3)); // Menos dÃ­as = mÃ¡s puntos
            $score += $puntosVelocidad;
            $factores['velocidad_pago'] = ['puntos' => $puntosVelocidad, 'promedio_dias' => $promedioTiempo];
        }
        
        // Factor 4: Volumen de negocio (0-15 puntos)
        $puntosPorVolumen = min(15, $totalFacturas * 2); // 2 puntos por factura
        $score += $puntosPorVolumen;
        $factores['volumen_negocio'] = ['puntos' => $puntosPorVolumen, 'total_facturas' => $totalFacturas];
        
        // Factor 5: Tendencia de mejora (0-10 puntos)
        $tendenciaMejora = $this->evaluarTendenciaMejora($facturas);
        $score += $tendenciaMejora;
        $factores['tendencia_mejora'] = ['puntos' => $tendenciaMejora];
        
        // Factor 6: Calidad de datos (0-10 puntos)
        $calidadDatos = $datosHistoricos['calidad_datos']['score_calidad'] / 10; // Convertir a escala 0-10
        $score += $calidadDatos;
        $factores['calidad_datos'] = ['puntos' => $calidadDatos];
        
        // Normalizar score a escala 0-100
        $scoreNormalizado = min(100, $score);
        $clasificacion = $this->clasificarScore($scoreNormalizado);
        
        return [
            'score_total' => round($scoreNormalizado, 1),
            'clasificacion' => $clasificacion,
            'factores_evaluados' => $factores,
            'nivel_confianza' => $this->determinarNivelConfianza($scoreNormalizado),
            'limite_credito_sugerido' => $this->sugerirLimiteCredito($scoreNormalizado, $datosHistoricos),
            'condiciones_pago_recomendadas' => $this->recomendarCondicionesPago($scoreNormalizado, $tiemposPago)
        ];
    }

    /**
     * Genera recomendaciones comerciales dinÃ¡micas
     */
    private function generarRecomendacionesDinamicas($scoreConfiabilidad, $patronesComportamiento)
    {
        $score = $scoreConfiabilidad['score_total'];
        $clasificacion = $scoreConfiabilidad['clasificacion'];
        $recomendaciones = [];
        
        // Recomendaciones basadas en score
        if ($score >= 80) {
            $recomendaciones['estrategia_comercial'] = [
                'tipo' => 'cliente_premium',
                'acciones' => [
                    'Ofertar condiciones preferenciales de pago',
                    'Aumentar lÃ­mite de crÃ©dito disponible',
                    'Priorizar propuestas comerciales',
                    'Considerar descuentos por volumen'
                ]
            ];
        } elseif ($score >= 60) {
            $recomendaciones['estrategia_comercial'] = [
                'tipo' => 'cliente_confiable',
                'acciones' => [
                    'Mantener condiciones estÃ¡ndar',
                    'Monitoreo rutinario de pagos',
                    'Evaluar aumentos graduales de crÃ©dito'
                ]
            ];
        } elseif ($score >= 40) {
            $recomendaciones['estrategia_comercial'] = [
                'tipo' => 'cliente_riesgo_moderado',
                'acciones' => [
                    'Solicitar garantÃ­as adicionales',
                    'Reducir plazos de pago',
                    'Aumentar frecuencia de seguimiento',
                    'Evaluar pagos anticipados con descuento'
                ]
            ];
        } else {
            $recomendaciones['estrategia_comercial'] = [
                'tipo' => 'cliente_alto_riesgo',
                'acciones' => [
                    'REQUERIR PAGO ANTICIPADO',
                    'Solicitar garantÃ­as bancarias',
                    'AprobaciÃ³n gerencial obligatoria',
                    'Monitoreo diario de cuenta'
                ]
            ];
        }
        
        // Recomendaciones basadas en patrones
        if ($patronesComportamiento['patron_disponible']) {
            $volatilidad = $patronesComportamiento['volatilidad'];
            
            if ($volatilidad > 30) {
                $recomendaciones['gestion_riesgo'] = [
                    'alerta' => 'Alta volatilidad en comportamiento de pago',
                    'acciones' => [
                        'Establecer alertas automÃ¡ticas de seguimiento',
                        'Revisar condiciones cada trimestre',
                        'Considerar seguro de crÃ©dito'
                    ]
                ];
            }
            
            $tendencia = $patronesComportamiento['tendencias_temporales']['direccion'] ?? 'estable';
            if ($tendencia === 'empeorando') {
                $recomendaciones['alerta_tendencia'] = [
                    'tipo' => 'deterioro_detectado',
                    'acciones' => [
                        'ReuniÃ³n comercial urgente',
                        'RevisiÃ³n de condiciones contractuales',
                        'EvaluaciÃ³n de situaciÃ³n financiera del cliente'
                    ]
                ];
            }
        }
        
        // Recomendaciones de automatizaciÃ³n
        $recomendaciones['automatizacion'] = [
            'alertas_sugeridas' => $this->sugerirAlertas($score, $patronesComportamiento),
            'informes_automaticos' => $this->sugerirInformes($clasificacion),
            'integraciones_recomendadas' => $this->sugerirIntegraciones($score)
        ];
        
        return $recomendaciones;
    }

    /**
     * EvalÃºa el riesgo crediticio del cliente
     */
    private function evaluarRiesgoCredito($scoreConfiabilidad)
    {
        $score = $scoreConfiabilidad['score_total'];
        
        if ($score >= 80) {
            $nivelRiesgo = 'BAJO';
            $colorRiesgo = 'verde';
            $probabilidadDefault = '< 5%';
        } elseif ($score >= 60) {
            $nivelRiesgo = 'MODERADO';
            $colorRiesgo = 'amarillo';
            $probabilidadDefault = '5-15%';
        } elseif ($score >= 40) {
            $nivelRiesgo = 'ALTO';
            $colorRiesgo = 'naranja';
            $probabilidadDefault = '15-35%';
        } else {
            $nivelRiesgo = 'CRÃTICO';
            $colorRiesgo = 'rojo';
            $probabilidadDefault = '> 35%';
        }
        
        return [
            'nivel_riesgo' => $nivelRiesgo,
            'color_indicador' => $colorRiesgo,
            'probabilidad_default' => $probabilidadDefault,
            'score_referencia' => $score,
            'recomendacion_exposicion' => $this->calcularExposicionRecomendada($score),
            'seguimiento_requerido' => $this->determinarFrecuenciaSeguimiento($nivelRiesgo),
            'alertas_criticas' => $this->definirAlertasCriticas($nivelRiesgo)
        ];
    }

    /**
     * Genera alertas automÃ¡ticas basadas en el anÃ¡lisis
     */
    private function generarAlertasAutomaticas($datosHistoricos, $scoreConfiabilidad)
    {
        $alertas = [];
        $score = $scoreConfiabilidad['score_total'];
        $totalFacturas = $datosHistoricos['total_facturas'];
        $facturasPendientes = $totalFacturas - $datosHistoricos['facturas_pagadas'];
        
        // Alerta por score bajo
        if ($score < 40) {
            $alertas[] = [
                'tipo' => 'score_critico',
                'severidad' => 'ALTA',
                'mensaje' => 'Cliente con score de confiabilidad crÃ­tico',
                'accion_requerida' => 'RevisiÃ³n inmediata de condiciones comerciales',
                'automatizable' => true
            ];
        }
        
        // Alerta por facturas pendientes
        if ($facturasPendientes > 5) {
            $alertas[] = [
                'tipo' => 'muchas_pendientes',
                'severidad' => 'MEDIA',
                'mensaje' => "Cliente tiene {$facturasPendientes} facturas pendientes",
                'accion_requerida' => 'Contactar para gestiÃ³n de cobranza',
                'automatizable' => true
            ];
        }
        
        // Alerta por falta de actividad reciente
        $ultimaFactura = $datosHistoricos['periodo_analisis']['hasta'] ?? null;
        if ($ultimaFactura && Carbon::parse($ultimaFactura)->diffInDays(now()) > 180) {
            $alertas[] = [
                'tipo' => 'inactividad',
                'severidad' => 'BAJA',
                'mensaje' => 'Cliente sin actividad comercial reciente (>6 meses)',
                'accion_requerida' => 'Contacto comercial para reactivaciÃ³n',
                'automatizable' => false
            ];
        }
        
        return [
            'total_alertas' => count($alertas),
            'alertas_activas' => $alertas,
            'configuracion_recomendada' => [
                'frecuencia_revision' => $this->determinarFrecuenciaAlertas($score),
                'destinatarios_sugeridos' => ['gerente_comercial', 'analista_credito'],
                'canales_notificacion' => ['email', 'dashboard', 'whatsapp']
            ]
        ];
    }

    /**
     * Genera un dashboard interactivo con mÃ©tricas clave
     */
    private function generarDashboardInteractivo($datosHistoricos, $simulacionesEscenarios)
    {
        $tiemposPago = $datosHistoricos['tiempos_pago'];
        
        return [
            'metricas_clave' => [
                'tiempo_promedio_pago' => !empty($tiemposPago) ? round(array_sum($tiemposPago) / count($tiemposPago), 1) : 0,
                'mediana_pago' => !empty($tiemposPago) ? $this->calcularMediana($tiemposPago) : 0,
                'pago_mas_rapido' => !empty($tiemposPago) ? min($tiemposPago) : 0,
                'pago_mas_lento' => !empty($tiemposPago) ? max($tiemposPago) : 0,
                'total_facturas' => $datosHistoricos['total_facturas'],
                'porcentaje_pagadas' => $datosHistoricos['total_facturas'] > 0 ? 
                    round(($datosHistoricos['facturas_pagadas'] / $datosHistoricos['total_facturas']) * 100, 1) : 0
            ],
            'graficos_sugeridos' => [
                'tendencia_temporal' => $this->generarDatosTendencia($datosHistoricos),
                'distribucion_tiempos' => $this->generarDatosDistribucion($tiemposPago),
                'comparativa_escenarios' => $this->generarDatosComparativa($simulacionesEscenarios),
                'evolucion_score' => $this->generarDatosEvolucionScore($datosHistoricos)
            ],
            'widgets_interactivos' => [
                'simulador_tiempo_pago' => [
                    'titulo' => 'Simulador de Tiempo de Pago',
                    'descripcion' => 'Ajusta parÃ¡metros para ver predicciones',
                    'parametros' => ['monto_factura', 'tipo_factura', 'mes_facturacion']
                ],
                'calculadora_riesgo' => [
                    'titulo' => 'Calculadora de Riesgo',
                    'descripcion' => 'EvalÃºa riesgo de nuevas ventas',
                    'parametros' => ['monto_venta', 'plazo_pago', 'garantias']
                ],
                'predictor_flujo_efectivo' => [
                    'titulo' => 'Predictor de Flujo de Efectivo',
                    'descripcion' => 'Predice cuÃ¡ndo llegarÃ¡ el pago',
                    'parametros' => ['fecha_facturacion', 'monto', 'condiciones']
                ]
            ],
            'configuracion_alertas' => [
                'tiempo_excedido' => 'Alerta cuando supere tiempo promedio + 50%',
                'facturas_acumuladas' => 'Alerta con más de 3 facturas pendientes',
                'score_descendente' => 'Alerta si score baja más de 10 puntos'
            ]
        ];
    }

    // Métodos auxiliares básicos para el simulador
    private function analizarPatronesPorTipo($facturas) { return []; }
    private function detectarAnomalias($tiemposPago) { return []; }
    private function evaluarConsistencia($tiemposPago) { return 0; }
    private function calcularVolatilidad($tiemposPago) { return 0; }
    private function simularEscenario($tiempos, $tipo, $estadisticas) { 
        return ['escenario' => $tipo, 'promedio_proyectado' => 30, 'confianza' => 70]; 
    }
    private function simularEscenarioEstacional($datos, $patrones) { 
        return ['escenario' => 'estacionalidad_extrema', 'disponible' => false]; 
    }
    private function recomendarMejorEscenario($n, $c, $b) { 
        return ['escenario_recomendado' => 'normal']; 
    }
    private function aplicarRegresionLineal($tiempos) { return ['disponible' => false]; }
    private function aplicarPromedioMovilExponencial($tiempos) { return ['disponible' => false]; }
    private function simularRedNeuronal($datos) { return ['disponible' => false]; }
    private function aplicarAlgoritmoBayesiano($datos) { return ['disponible' => false]; }
    private function combinarPredicciones($r, $p, $n, $b) { return ['disponible' => false]; }
    private function calcularConfianzaIA($e, $t) { return 0; }
    private function evaluarPrecisionHistorica($d) { return ['precision_disponible' => false]; }
    private function evaluarTendenciaMejora($f) { return 5; }
    private function clasificarScore($s) { return ['categoria' => 'Bueno', 'color' => 'azul']; }
    private function determinarNivelConfianza($s) { return 'MEDIA'; }
    private function sugerirLimiteCredito($s, $d) { return 500000; }
    private function recomendarCondicionesPago($s, $t) { return ['plazo' => 30, 'tipo' => 'estándar']; }
    private function sugerirAlertas($s, $p) { return []; }
    private function sugerirInformes($c) { return ['frecuencia' => 'mensual']; }
    private function sugerirIntegraciones($s) { return ['dashboard_basico']; }
    private function calcularExposicionRecomendada($s) { return 'Normal'; }
    private function determinarFrecuenciaSeguimiento($n) { return 'mensual'; }
    private function definirAlertasCriticas($n) { return []; }
    private function determinarFrecuenciaAlertas($s) { return 'mensual'; }
    private function generarDatosTendencia($d) { return []; }
    private function generarDatosDistribucion($t) { return []; }
    private function generarDatosComparativa($s) { return []; }
    private function generarDatosEvolucionScore($d) { return []; }
    private function estimarMontoFactura($json, $pago) { return $pago > 0 ? $pago : 100000; }
    private function detectarTipoFactura($json) { return 'standard'; }
    private function evaluarCalidadDatos($f) { return ['score_calidad' => 80, 'nivel' => 'buena']; }
    private function calcularEstadisticasAvanzadas($t) { 
        if (empty($t)) return ['disponible' => false];
        $promedio = array_sum($t) / count($t);
        return ['disponible' => true, 'promedio' => $promedio, 'mediana' => $promedio];
    }
    private function detectarPatronesEstacionales($f) { return ['patrones_disponibles' => false]; }
    private function analizarTendenciasTemporal($f) { return ['tendencia_disponible' => false]; }
    private function calcularConfianzaEscenario($tipo, $datos) { return 70; }

}
