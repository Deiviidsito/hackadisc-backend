<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * CONTROLADOR ANÁLISIS TIEMPO FACTURACIÓN → PAGO
 * 
 * Especializado en calcular tiempos desde emisión de factura hasta recepción de pago
 * 
 * FUNCIONALIDADES PRINCIPALES:
 * - Cálculo tiempo promedio desde facturación hasta pago efectivo
 * - Análisis por tipo de factura (SENCE vs Cliente)
 * - Análisis por cliente individual de comportamiento de pago
 * - Identificación de facturas pendientes de pago
 * - Distribución de tiempos de pago en rangos
 * - Análisis de flujo de efectivo y morosidad
 * 
 * LÓGICA DE CÁLCULO:
 * - Toma FechaFacturacion como punto de inicio
 * - Busca último estado 3 (Pagado) con monto > 0 como fecha de pago
 * - Calcula diferencia en días entre facturación y pago
 * - Distingue entre facturas SENCE y facturas cliente
 * - Identifica facturas sin pago (pendientes)
 */
class TiempoPagoController extends Controller
{
    /**
     * CALCULAR TIEMPO PROMEDIO FACTURACIÓN → PAGO
     * 
     * Analiza tiempo desde emisión de factura hasta recepción de pago efectivo
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function calcularTiempoFacturacionPago(Request $request)
    {
        try {
            set_time_limit(300);
            ini_set('memory_limit', '1G');
            
            $startTime = microtime(true);
            Log::info("⏱️ INICIANDO CÁLCULO TIEMPO FACTURACIÓN → PAGO");
            
            // Parámetros de filtrado
            $año = $request->input('año', null);
            $mes = $request->input('mes', null);
            $tipoFactura = $request->input('tipo_factura', 'todas'); // 'sence', 'cliente', 'todas'
            $incluirPendientes = $request->input('incluir_pendientes', false);
            
            // Cargar datos desde la base de datos en lugar del JSON
            $comercializacionesData = $this->cargarDatosBaseDatos($año, $mes);
            if (!$comercializacionesData) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudieron cargar los datos de la base de datos'
                ], 500);
            }
            
            $comercializacionesAnalizadas = 0;
            $facturasAnalizadas = 0;
            $facturasPagadas = 0;
            $facturasPendientes = 0;
            $tiemposPago = [];
            $detallesFacturas = [];
            $estadisticas = [
                'facturas_sence_pagadas' => 0,
                'facturas_cliente_pagadas' => 0,
                'facturas_sence_pendientes' => 0,
                'facturas_cliente_pendientes' => 0,
                'monto_total_pagado' => 0,
                'monto_total_pendiente' => 0
            ];
            
            foreach ($comercializacionesData as $comercializacion) {
                $comercializacionesAnalizadas++;
                
                // Los filtros de fecha ya se aplicaron en la consulta DB
                // if (!$this->cumpleFiltrosFecha($comercializacion, $año, $mes)) {
                //     continue;
                // }
                
                // Verificar si tiene facturas
                if (!isset($comercializacion['Facturas']) || empty($comercializacion['Facturas'])) {
                    continue;
                }
                
                foreach ($comercializacion['Facturas'] as $factura) {
                    $facturasAnalizadas++;
                    
                    // Detectar tipo de factura
                    $tipoFacturaDetectado = $this->detectarTipoFactura($factura, $comercializacion);
                    
                    // Filtrar por tipo si se especifica
                    if ($tipoFactura !== 'todas') {
                        if (($tipoFactura === 'sence' && $tipoFacturaDetectado !== 'facturas_sence') ||
                            ($tipoFactura === 'cliente' && $tipoFacturaDetectado !== 'facturas_cliente')) {
                            continue;
                        }
                    }
                    
                    // Obtener fecha de facturación
                    if (!isset($factura['FechaFacturacion'])) continue;
                    
                    try {
                        $fechaFacturacion = Carbon::createFromFormat('Y-m-d', $factura['FechaFacturacion']);
                    } catch (\Exception $e) {
                        continue;
                    }
                    
                    // Buscar fecha de pago efectivo (último estado 3 con monto > 0)
                    $fechaPago = $this->obtenerFechaPagoEfectivo($factura);
                    $montoPagado = $this->obtenerMontoPagado($factura);
                    $montoTotal = $comercializacion['ValorFinalComercializacion'] ?? 0;
                    
                    if ($fechaPago) {
                        // Factura pagada
                        $diasDiferencia = $fechaFacturacion->diffInDays($fechaPago);
                        $tiemposPago[] = $diasDiferencia;
                        $facturasPagadas++;
                        
                        $estadisticas[$tipoFacturaDetectado . '_pagadas']++;
                        $estadisticas['monto_total_pagado'] += $montoPagado;
                        
                        $detallesFacturas[] = [
                            'codigo_cotizacion' => $comercializacion['CodigoCotizacion'],
                            'cliente' => $comercializacion['NombreCliente'],
                            'numero_factura' => $factura['NumeroFactura'],
                            'fecha_facturacion' => $factura['FechaFacturacion'],
                            'fecha_pago' => $fechaPago->format('d/m/Y'),
                            'dias_pago' => $diasDiferencia,
                            'monto_pagado' => $montoPagado,
                            'tipo_factura' => $tipoFacturaDetectado,
                            'estado' => 'pagada'
                        ];
                    } else {
                        // Factura pendiente
                        $facturasPendientes++;
                        $estadisticas[$tipoFacturaDetectado . '_pendientes']++;
                        
                        // Calcular días pendientes desde facturación hasta hoy
                        $diasPendientes = $fechaFacturacion->diffInDays(Carbon::now());
                        
                        if ($incluirPendientes) {
                            $detallesFacturas[] = [
                                'codigo_cotizacion' => $comercializacion['CodigoCotizacion'],
                                'cliente' => $comercializacion['NombreCliente'],
                                'numero_factura' => $factura['NumeroFactura'],
                                'fecha_facturacion' => $factura['FechaFacturacion'],
                                'fecha_pago' => null,
                                'dias_pendientes' => $diasPendientes,
                                'monto_pendiente' => $this->calcularMontoPendiente($factura, $montoTotal),
                                'tipo_factura' => $tipoFacturaDetectado,
                                'estado' => 'pendiente'
                            ];
                        }
                        
                        $estadisticas['monto_total_pendiente'] += $this->calcularMontoPendiente($factura, $montoTotal);
                    }
                }
            }
            
            // Calcular estadísticas finales
            $resultados = $this->calcularEstadisticasFinalesPago($tiemposPago, $detallesFacturas);
            
            $tiempoEjecucion = round((microtime(true) - $startTime) * 1000, 2);
            
            return response()->json([
                'success' => true,
                'message' => 'Análisis tiempo facturación → pago completado exitosamente',
                'datos' => [
                    'resumen' => [
                        'comercializaciones_analizadas' => $comercializacionesAnalizadas,
                        'facturas_analizadas' => $facturasAnalizadas,
                        'facturas_pagadas' => $facturasPagadas,
                        'facturas_pendientes' => $facturasPendientes,
                        'porcentaje_pagadas' => $facturasAnalizadas > 0 ? 
                            round(($facturasPagadas / $facturasAnalizadas) * 100, 2) : 0,
                        'filtros_aplicados' => [
                            'año' => $año,
                            'mes' => $mes,
                            'tipo_factura' => $tipoFactura,
                            'incluir_pendientes' => $incluirPendientes
                        ]
                    ],
                    'tiempo_promedio_pago' => $resultados['tiempo_promedio'],
                    'estadisticas' => array_merge($estadisticas, $resultados['estadisticas']),
                    'distribucion_tiempos' => $resultados['distribucion'],
                    'casos_extremos' => $resultados['casos_extremos'],
                    'top_clientes_mas_lentos' => $resultados['top_lentos_pago'],
                    'top_clientes_mas_rapidos' => $resultados['top_rapidos_pago'],
                    'facturas_pendientes_criticas' => $resultados['pendientes_criticas']
                ],
                'metadata' => [
                    'tiempo_ejecucion_ms' => $tiempoEjecucion,
                    'timestamp' => now()->format('Y-m-d H:i:s'),
                    'total_registros_json' => count($comercializacionesData)
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error("❌ ERROR CALCULANDO TIEMPO FACTURACIÓN → PAGO: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al calcular tiempo facturación → pago: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * ANÁLISIS MOROSIDAD POR CLIENTE
     * 
     * Analiza comportamiento de pago por cliente individual
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function analizarMorosidadPorCliente(Request $request)
    {
        try {
            $comercializacionesData = $this->cargarDatosBaseDatos();
            if (!$comercializacionesData) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo cargar los datos de la base de datos'
                ], 500);
            }
            
            $año = $request->input('año', null);
            $mes = $request->input('mes', null);
            $tipoFactura = $request->input('tipo_factura', 'todas');
            
            $clientesData = [];
            
            foreach ($comercializacionesData as $comercializacion) {
                if (!$this->cumpleFiltrosFecha($comercializacion, $año, $mes)) {
                    continue;
                }
                
                if (!isset($comercializacion['Facturas']) || empty($comercializacion['Facturas'])) {
                    continue;
                }
                
                $clienteNombre = $comercializacion['NombreCliente'];
                
                if (!isset($clientesData[$clienteNombre])) {
                    $clientesData[$clienteNombre] = [
                        'nombre_cliente' => $clienteNombre,
                        'facturas_totales' => 0,
                        'facturas_pagadas' => 0,
                        'facturas_pendientes' => 0,
                        'tiempos_pago' => [],
                        'monto_total_pagado' => 0,
                        'monto_total_pendiente' => 0,
                        'dias_pendientes_acumulados' => 0
                    ];
                }
                
                foreach ($comercializacion['Facturas'] as $factura) {
                    $tipoFacturaDetectado = $this->detectarTipoFactura($factura, $comercializacion);
                    
                    if ($tipoFactura !== 'todas') {
                        if (($tipoFactura === 'sence' && $tipoFacturaDetectado !== 'facturas_sence') ||
                            ($tipoFactura === 'cliente' && $tipoFacturaDetectado !== 'facturas_cliente')) {
                            continue;
                        }
                    }
                    
                    if (!isset($factura['FechaFacturacion'])) continue;
                    
                    $clientesData[$clienteNombre]['facturas_totales']++;
                    
                    $fechaPago = $this->obtenerFechaPagoEfectivo($factura);
                    
                    if ($fechaPago) {
                        $clientesData[$clienteNombre]['facturas_pagadas']++;
                        
                        try {
                            $fechaFacturacion = Carbon::createFromFormat('Y-m-d', $factura['FechaFacturacion']);
                            $diasPago = $fechaFacturacion->diffInDays($fechaPago);
                            $clientesData[$clienteNombre]['tiempos_pago'][] = $diasPago;
                            $clientesData[$clienteNombre]['monto_total_pagado'] += $this->obtenerMontoPagado($factura);
                        } catch (\Exception $e) {
                            continue;
                        }
                    } else {
                        $clientesData[$clienteNombre]['facturas_pendientes']++;
                        
                        try {
                            $fechaFacturacion = Carbon::createFromFormat('Y-m-d', $factura['FechaFacturacion']);
                            $diasPendientes = $fechaFacturacion->diffInDays(Carbon::now());
                            $clientesData[$clienteNombre]['dias_pendientes_acumulados'] += $diasPendientes;
                            $clientesData[$clienteNombre]['monto_total_pendiente'] += $this->calcularMontoPendiente($factura, $comercializacion['ValorFinalComercializacion'] ?? 0);
                        } catch (\Exception $e) {
                            continue;
                        }
                    }
                }
            }
            
            // Calcular estadísticas por cliente
            $resultadosClientes = [];
            foreach ($clientesData as $cliente => $data) {
                if ($data['facturas_totales'] === 0) continue;
                
                $tiemposPago = $data['tiempos_pago'];
                $tiempoPromedioPago = count($tiemposPago) > 0 ? round(array_sum($tiemposPago) / count($tiemposPago), 2) : 0;
                $porcentajePagadas = round(($data['facturas_pagadas'] / $data['facturas_totales']) * 100, 2);
                $diasPromediosPendientes = $data['facturas_pendientes'] > 0 ? 
                    round($data['dias_pendientes_acumulados'] / $data['facturas_pendientes'], 2) : 0;
                
                $resultadosClientes[] = [
                    'cliente' => $cliente,
                    'facturas_totales' => $data['facturas_totales'],
                    'facturas_pagadas' => $data['facturas_pagadas'],
                    'facturas_pendientes' => $data['facturas_pendientes'],
                    'porcentaje_pagadas' => $porcentajePagadas,
                    'tiempo_promedio_pago_dias' => $tiempoPromedioPago,
                    'dias_promedio_pendientes' => $diasPromediosPendientes,
                    'monto_total_pagado' => $data['monto_total_pagado'],
                    'monto_total_pendiente' => $data['monto_total_pendiente'],
                    'clasificacion_morosidad' => $this->clasificarMorosidad($porcentajePagadas, $diasPromediosPendientes)
                ];
            }
            
            // Ordenar por morosidad (más problemáticos primero)
            usort($resultadosClientes, function($a, $b) {
                // Priorizar por días pendientes, luego por porcentaje no pagado
                $scoreA = $a['dias_promedio_pendientes'] + ((100 - $a['porcentaje_pagadas']) * 2);
                $scoreB = $b['dias_promedio_pendientes'] + ((100 - $b['porcentaje_pagadas']) * 2);
                return $scoreB <=> $scoreA;
            });
            
            return response()->json([
                'success' => true,
                'message' => 'Análisis de morosidad por cliente completado exitosamente',
                'datos' => [
                    'total_clientes_analizados' => count($resultadosClientes),
                    'filtros_aplicados' => [
                        'año' => $año,
                        'mes' => $mes,
                        'tipo_factura' => $tipoFactura
                    ],
                    'clientes' => $resultadosClientes
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error("❌ ERROR ANÁLISIS MOROSIDAD: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error en análisis de morosidad: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * DISTRIBUCIÓN TIEMPOS DE PAGO
     * 
     * Analiza distribución de tiempos de pago en rangos predefinidos
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function obtenerDistribucionTiemposPago(Request $request)
    {
        try {
            $comercializacionesData = $this->cargarDatosBaseDatos();
            if (!$comercializacionesData) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo cargar los datos desde la base de datos'
                ], 500);
            }
            
            $año = $request->input('año', null);
            $mes = $request->input('mes', null);
            $tipoFactura = $request->input('tipo_factura', 'todas');
            
            $tiemposPago = [];
            $detallesPorRango = [];
            
            foreach ($comercializacionesData as $comercializacion) {
                if (!$this->cumpleFiltrosFecha($comercializacion, $año, $mes)) {
                    continue;
                }
                
                if (!isset($comercializacion['Facturas']) || empty($comercializacion['Facturas'])) {
                    continue;
                }
                
                foreach ($comercializacion['Facturas'] as $factura) {
                    $tipoFacturaDetectado = $this->detectarTipoFactura($factura, $comercializacion);
                    
                    if ($tipoFactura !== 'todas') {
                        if (($tipoFactura === 'sence' && $tipoFacturaDetectado !== 'facturas_sence') ||
                            ($tipoFactura === 'cliente' && $tipoFacturaDetectado !== 'facturas_cliente')) {
                            continue;
                        }
                    }
                    
                    if (!isset($factura['FechaFacturacion'])) continue;
                    
                    $fechaPago = $this->obtenerFechaPagoEfectivo($factura);
                    if (!$fechaPago) continue; // Solo facturas pagadas
                    
                    try {
                        $fechaFacturacion = Carbon::createFromFormat('Y-m-d', $factura['FechaFacturacion']);
                        $diasPago = $fechaFacturacion->diffInDays($fechaPago);
                        
                        $tiemposPago[] = $diasPago;
                        
                        // Clasificar por rango
                        $rango = $this->clasificarPorRangoPago($diasPago);
                        if (!isset($detallesPorRango[$rango])) {
                            $detallesPorRango[$rango] = [];
                        }
                        
                        $detallesPorRango[$rango][] = [
                            'codigo_cotizacion' => $comercializacion['CodigoCotizacion'],
                            'cliente' => $comercializacion['NombreCliente'],
                            'numero_factura' => $factura['NumeroFactura'],
                            'dias_pago' => $diasPago,
                            'monto_pagado' => $this->obtenerMontoPagado($factura),
                            'tipo_factura' => $tipoFacturaDetectado
                        ];
                    } catch (\Exception $e) {
                        continue;
                    }
                }
            }
            
            // Generar distribución
            $distribucion = $this->generarDistribucionTiemposPago($tiemposPago, $detallesPorRango);
            
            return response()->json([
                'success' => true,
                'message' => 'Distribución de tiempos de pago generada exitosamente',
                'datos' => [
                    'total_facturas_pagadas' => count($tiemposPago),
                    'filtros_aplicados' => [
                        'año' => $año,
                        'mes' => $mes,
                        'tipo_factura' => $tipoFactura
                    ],
                    'distribucion' => $distribucion,
                    'estadisticas_generales' => [
                        'promedio_dias_pago' => count($tiemposPago) > 0 ? round(array_sum($tiemposPago) / count($tiemposPago), 2) : 0,
                        'mediana_dias_pago' => count($tiemposPago) > 0 ? $this->calcularMediana($tiemposPago) : 0,
                        'minimo_dias_pago' => count($tiemposPago) > 0 ? min($tiemposPago) : 0,
                        'maximo_dias_pago' => count($tiemposPago) > 0 ? max($tiemposPago) : 0
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error("❌ ERROR DISTRIBUCIÓN TIEMPOS PAGO: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error en distribución de tiempos de pago: ' . $e->getMessage()
            ], 500);
        }
    }
    
    // ==================================================================================
    // MÉTODOS AUXILIARES
    // ==================================================================================
    
    /**
     * Cargar datos del archivo JSON
     */
    private function cargarDatosJSON()
    {
        try {
            $rutasPosibles = [
                'c:\Users\David\Downloads\datasets\8.data Oct a DIC 2024.json',
                storage_path('app/datasets/8.data Oct a DIC 2024.json'),
                base_path('storage/datasets/8.data Oct a DIC 2024.json')
            ];
            
            foreach ($rutasPosibles as $ruta) {
                if (file_exists($ruta)) {
                    $contenido = file_get_contents($ruta);
                    $datos = json_decode($contenido, true);
                    
                    if ($datos !== null && is_array($datos)) {
                        Log::info("✅ JSON cargado exitosamente desde: " . $ruta);
                        return $datos;
                    }
                }
            }
            
            Log::error("❌ No se pudo encontrar el archivo JSON en ninguna ubicación");
            return null;
            
        } catch (\Exception $e) {
            Log::error("❌ Error cargando JSON: " . $e->getMessage());
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
     * Detectar tipo de factura (SENCE vs Cliente)
     */
    private function detectarTipoFactura($factura, $comercializacion = null)
    {
        // CRITERIO PRINCIPAL: Verificar si la fecha coincide con estado 3 (Terminada SENCE)
        if ($comercializacion && isset($comercializacion['Estados'])) {
            foreach ($comercializacion['Estados'] as $estado) {
                if ($estado['EstadoComercializacion'] == 3) {
                    try {
                        $fechaEstado3 = Carbon::createFromFormat('Y-m-d', $estado['fecha']);
                        $fechaFactura = Carbon::createFromFormat('Y-m-d', $factura['FechaFacturacion']);
                        
                        // Si la factura se emite el mismo día o muy cerca del estado 3, es SENCE
                        if ($fechaFactura->diffInDays($fechaEstado3) <= 2) {
                            return 'facturas_sence';
                        }
                    } catch (\Exception $e) {
                        continue;
                    }
                }
            }
        }
        
        // CRITERIO SECUNDARIO: Analizar el valor pagado en estados de factura
        if (isset($factura['EstadosFactura'])) {
            $montoMaximo = 0;
            foreach ($factura['EstadosFactura'] as $estadoFactura) {
                if (isset($estadoFactura['pagado'])) {
                    $montoPagado = $this->convertirPagadoANumero($estadoFactura['pagado']);
                    if ($montoPagado > $montoMaximo) {
                        $montoMaximo = $montoPagado;
                    }
                }
            }
            
            // Si el monto es muy alto (>500,000), probablemente es SENCE
            if ($montoMaximo > 500000) {
                return 'facturas_sence';
            }
        }
        
        return 'facturas_cliente';
    }
    
    /**
     * Obtener fecha de pago efectivo (último estado 3 con monto > 0)
     */
    private function obtenerFechaPagoEfectivo($factura)
    {
        if (!isset($factura['EstadosFactura']) || empty($factura['EstadosFactura'])) {
            return null;
        }
        
        $fechasPago = [];
        
        foreach ($factura['EstadosFactura'] as $estadoFactura) {
            if ($estadoFactura['estado_id'] == 3 && 
                isset($estadoFactura['pagado']) && 
                $this->esFacturaPagada($estadoFactura['pagado'])) {
                
                try {
                    $fecha = Carbon::createFromFormat('Y-m-d', $estadoFactura['fecha']);
                    $fechasPago[] = $fecha;
                } catch (\Exception $e) {
                    continue;
                }
            }
        }
        
        if (empty($fechasPago)) {
            return null;
        }
        
        // Retornar la fecha más reciente de pago
        return max($fechasPago);
    }
    
    /**
     * Obtener monto total pagado
     */
    private function obtenerMontoPagado($factura)
    {
        if (!isset($factura['EstadosFactura'])) {
            return 0;
        }
        
        $montoPagado = 0;
        
        foreach ($factura['EstadosFactura'] as $estadoFactura) {
            if ($estadoFactura['estado_id'] == 3 && 
                isset($estadoFactura['pagado']) && 
                $this->esFacturaPagada($estadoFactura['pagado'])) {
                $montoPagado += $this->convertirPagadoANumero($estadoFactura['pagado']);
            }
        }
        
        return $montoPagado;
    }
    
    /**
     * Calcular monto pendiente estimado
     */
    private function calcularMontoPendiente($factura, $montoTotal)
    {
        $montoPagado = $this->obtenerMontoPagado($factura);
        
        // Si no hay monto pagado, asumir que toda la factura está pendiente
        if ($montoPagado == 0) {
            // Estimar basado en tipo de factura
            $tipoFactura = $this->detectarTipoFactura($factura);
            if ($tipoFactura === 'facturas_sence') {
                // SENCE suele ser ~60-80% del total
                return $montoTotal * 0.7;
            } else {
                // Cliente suele ser el restante ~20-40%
                return $montoTotal * 0.3;
            }
        }
        
        return max(0, $montoTotal - $montoPagado);
    }
    
    /**
     * Calcular estadísticas finales de pago
     */
    private function calcularEstadisticasFinalesPago($tiemposPago, $detallesFacturas)
    {
        if (empty($tiemposPago)) {
            return [
                'tiempo_promedio' => 0,
                'estadisticas' => [],
                'distribucion' => [],
                'casos_extremos' => [],
                'top_lentos_pago' => [],
                'top_rapidos_pago' => [],
                'pendientes_criticas' => []
            ];
        }
        
        $promedio = round(array_sum($tiemposPago) / count($tiemposPago), 2);
        $mediana = $this->calcularMediana($tiemposPago);
        
        // Casos extremos de pago
        $casosExtremos = [
            'pago_mas_rapido' => null,
            'pago_mas_lento' => null
        ];
        
        $facturasPagadas = array_filter($detallesFacturas, function($detalle) {
            return $detalle['estado'] === 'pagada';
        });
        
        foreach ($facturasPagadas as $detalle) {
            if ($casosExtremos['pago_mas_rapido'] === null || $detalle['dias_pago'] < $casosExtremos['pago_mas_rapido']['dias_pago']) {
                $casosExtremos['pago_mas_rapido'] = $detalle;
            }
            if ($casosExtremos['pago_mas_lento'] === null || $detalle['dias_pago'] > $casosExtremos['pago_mas_lento']['dias_pago']) {
                $casosExtremos['pago_mas_lento'] = $detalle;
            }
        }
        
        // Facturas pendientes críticas (>60 días)
        $pendientesCriticas = array_filter($detallesFacturas, function($detalle) {
            return $detalle['estado'] === 'pendiente' && $detalle['dias_pendientes'] > 60;
        });
        
        // Ordenar pendientes críticas por días pendientes
        usort($pendientesCriticas, function($a, $b) {
            return $b['dias_pendientes'] <=> $a['dias_pendientes'];
        });
        
        return [
            'tiempo_promedio' => $promedio,
            'estadisticas' => [
                'mediana_dias_pago' => $mediana,
                'minimo_dias_pago' => min($tiemposPago),
                'maximo_dias_pago' => max($tiemposPago),
                'desviacion_estandar_pago' => $this->calcularDesviacionEstandar($tiemposPago)
            ],
            'distribucion' => $this->generarDistribucionTiemposPago($tiemposPago, []),
            'casos_extremos' => $casosExtremos,
            'top_lentos_pago' => array_slice($facturasPagadas, -5), // Top 5 más lentos
            'top_rapidos_pago' => array_slice($facturasPagadas, 0, 5), // Top 5 más rápidos
            'pendientes_criticas' => array_slice($pendientesCriticas, 0, 10) // Top 10 más críticas
        ];
    }
    
    /**
     * Generar distribución de tiempos de pago en rangos
     */
    private function generarDistribucionTiemposPago($tiemposPago, $detallesPorRango = [])
    {
        $rangos = [
            'inmediato' => ['min' => 0, 'max' => 0, 'count' => 0, 'descripcion' => 'Mismo día'],
            'muy_rapido' => ['min' => 1, 'max' => 7, 'count' => 0, 'descripcion' => '1-7 días'],
            'rapido' => ['min' => 8, 'max' => 15, 'count' => 0, 'descripcion' => '8-15 días'],
            'normal' => ['min' => 16, 'max' => 30, 'count' => 0, 'descripcion' => '16-30 días'],
            'lento' => ['min' => 31, 'max' => 60, 'count' => 0, 'descripcion' => '31-60 días'],
            'muy_lento' => ['min' => 61, 'max' => 90, 'count' => 0, 'descripcion' => '61-90 días'],
            'critico' => ['min' => 91, 'max' => 999, 'count' => 0, 'descripcion' => '91+ días']
        ];
        
        foreach ($tiemposPago as $tiempo) {
            foreach ($rangos as $key => &$rango) {
                if ($tiempo >= $rango['min'] && $tiempo <= $rango['max']) {
                    $rango['count']++;
                    break;
                }
            }
        }
        
        $total = count($tiemposPago);
        foreach ($rangos as &$rango) {
            $rango['porcentaje'] = $total > 0 ? round(($rango['count'] / $total) * 100, 2) : 0;
            
            // Agregar ejemplos si existen
            if (isset($detallesPorRango)) {
                $keyRango = array_search($rango, $rangos);
                $rango['ejemplos'] = isset($detallesPorRango[$keyRango]) ? 
                    array_slice($detallesPorRango[$keyRango], 0, 3) : [];
            }
        }
        
        return $rangos;
    }
    
    /**
     * Clasificar tiempo por rango de pago
     */
    private function clasificarPorRangoPago($dias)
    {
        if ($dias === 0) return 'inmediato';
        if ($dias >= 1 && $dias <= 7) return 'muy_rapido';
        if ($dias >= 8 && $dias <= 15) return 'rapido';
        if ($dias >= 16 && $dias <= 30) return 'normal';
        if ($dias >= 31 && $dias <= 60) return 'lento';
        if ($dias >= 61 && $dias <= 90) return 'muy_lento';
        return 'critico';
    }
    
    /**
     * Clasificar morosidad de cliente
     */
    private function clasificarMorosidad($porcentajePagadas, $diasPromedioPendientes)
    {
        if ($porcentajePagadas >= 90 && $diasPromedioPendientes <= 30) {
            return 'excelente';
        } elseif ($porcentajePagadas >= 80 && $diasPromedioPendientes <= 45) {
            return 'bueno';
        } elseif ($porcentajePagadas >= 70 && $diasPromedioPendientes <= 60) {
            return 'regular';
        } elseif ($porcentajePagadas >= 50 && $diasPromedioPendientes <= 90) {
            return 'malo';
        } else {
            return 'critico';
        }
    }
    
    /**
     * Calcular mediana
     */
    private function calcularMediana($array)
    {
        sort($array);
        $count = count($array);
        
        if ($count % 2 === 0) {
            return ($array[$count / 2 - 1] + $array[$count / 2]) / 2;
        }
        
        return $array[($count - 1) / 2];
    }
    
    /**
     * Calcular desviación estándar
     */
    private function calcularDesviacionEstandar($array)
    {
        $mean = array_sum($array) / count($array);
        $variance = array_sum(array_map(function($x) use ($mean) {
            return pow($x - $mean, 2);
        }, $array)) / count($array);
        
        return round(sqrt($variance), 2);
    }
    
    /**
     * CARGAR DATOS DESDE BASE DE DATOS
     * 
     * Carga comercializaciones desde la base de datos con sus facturas y estados de facturas
     * especializando en información de pagos
     */
    private function cargarDatosBaseDatos($año = null, $mes = null)
    {
        try {
            Log::info("🔍 Cargando datos desde BD para análisis de pagos - Año: " . ($año ?? 'todos') . ", Mes: " . ($mes ?? 'todos'));
            
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
            
            // Cargar facturas con sus estados de pago
            $comercializacionesIds = array_column($ventas, 'idComercializacion');
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
                
                // Cargar estados de facturas (especialmente estados de pago)
                $facturasNumeros = array_column($resultadosFacturas, 'NumeroFactura');
                $estadosFacturas = [];
                
                if (!empty($facturasNumeros)) {
                    $facturasNumerosStr = "'" . implode("','", $facturasNumeros) . "'";
                    
                    $queryEstadosFacturas = "
                        SELECT 
                            hef.factura_numero,
                            hef.estado_id,
                            hef.fecha,
                            hef.pagado,
                            hef.observacion,
                            ef.nombre as nombre_estado
                        FROM historial_estados_factura hef
                        INNER JOIN estado_facturas ef ON hef.estado_id = ef.id
                        WHERE hef.factura_numero IN ({$facturasNumerosStr})
                        ORDER BY hef.factura_numero, hef.fecha ASC
                    ";
                    
                    $resultadosEstados = DB::select($queryEstadosFacturas);
                    
                    // Organizar por número de factura
                    foreach ($resultadosEstados as $estado) {
                        $estadosFacturas[$estado->factura_numero][] = [
                            'estado_id' => $estado->estado_id,
                            'fecha' => $estado->fecha,
                            'pagado' => $estado->pagado,
                            'observacion' => $estado->observacion,
                            'nombre_estado' => $estado->nombre_estado
                        ];
                    }
                    
                    Log::info("💰 Cargados estados de pago para " . count($estadosFacturas) . " facturas");
                }
                
                // Organizar facturas por comercialización incluyendo sus estados
                foreach ($resultadosFacturas as $factura) {
                    $facturaCompleta = [
                        'NumeroFactura' => $factura->NumeroFactura,
                        'FechaFacturacion' => $factura->FechaFacturacion,
                        'MontoFactura' => $factura->MontoFactura,
                        // Agregar estados de factura en formato compatible
                        'EstadosFactura' => $estadosFacturas[$factura->NumeroFactura] ?? []
                    ];
                    
                    $facturas[$factura->idComercializacion][] = $facturaCompleta;
                }
                
                Log::info("🧾 Organizadas facturas para " . count($facturas) . " comercializaciones");
            }
            
            // Construir estructura de datos compatible
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
                    // Agregar facturas con sus estados de pago
                    'Facturas' => $facturas[$venta->idComercializacion] ?? []
                ];
                
                $comercializacionesData[] = $comercializacion;
            }
            
            Log::info("✅ Estructura completa creada para " . count($comercializacionesData) . " comercializaciones con datos de pago");
            
            return $comercializacionesData;
            
        } catch (\Exception $e) {
            Log::error("❌ Error cargando datos de BD para análisis de pagos: " . $e->getMessage());
            return null;
        }
    }

    /**
     * MÉTODOS HELPER PARA MANEJO DE VALORES DE PAGO
     */
    
    /**
     * Determina si una factura está pagada basándose en el valor del campo 'pagado'
     * El campo puede contener:
     * - String "0.00" (no pagado)
     * - String con valor monetario "485000.00" (pagado)
     * - Número 0 (no pagado)
     * - Número > 0 (pagado)
     */
    private function esFacturaPagada($valorPagado)
    {
        if (is_null($valorPagado)) {
            return false;
        }
        
        // Si es string
        if (is_string($valorPagado)) {
            $numeroLimpio = str_replace([',', ' '], '', $valorPagado);
            return is_numeric($numeroLimpio) && (float)$numeroLimpio > 0;
        }
        
        // Si es número
        return is_numeric($valorPagado) && $valorPagado > 0;
    }
    
    /**
     * Convierte el valor del campo 'pagado' a número
     */
    private function convertirPagadoANumero($valorPagado)
    {
        if (is_null($valorPagado)) {
            return 0;
        }
        
        // Si es string, limpiar y convertir
        if (is_string($valorPagado)) {
            $numeroLimpio = str_replace([',', ' '], '', $valorPagado);
            return is_numeric($numeroLimpio) ? (float)$numeroLimpio : 0;
        }
        
        // Si ya es número, retornar tal como está
        return is_numeric($valorPagado) ? (float)$valorPagado : 0;
    }
}
