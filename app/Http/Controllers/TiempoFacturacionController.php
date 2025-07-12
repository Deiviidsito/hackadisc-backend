<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * CONTROLADOR ANÁLISIS TIEMPO ESTADO 1 (LISTA PARA FACTURAR) → PRIMERA FACTURA
 * 
 * Especializado en calcular tiempos desde estado 1 (Lista para Facturar) hasta primera factura emitida
 * 
 * FUNCIONALIDADES PRINCIPALES:
 * - Cálculo tiempo promedio desde estado 1 hasta emisión primera factura
 * - Análisis casos donde facturan antes de estar lista (estado 1)
 * - Distribución de tiempos facturación en rangos
 * - Identificación facturas SENCE vs facturas cliente
 * - Análisis comportamiento facturación por tipo
 * 
 * LÓGICA DE CÁLCULO:
 * - Encuentra fecha de estado 1 (Lista para Facturar) como punto de inicio
 * - Identifica primera factura emitida usando FechaFacturacion del JSON
 * - Calcula diferencia en días entre estado 1 y primera facturación
 * - Detecta casos especiales donde facturan antes del estado 1
 * - Maneja casos complejos con múltiples facturas por comercialización
 * 
 * CASOS ESPECIALES:
 * - Facturas emitidas ANTES del estado 1: contador separado
 * - Comercializaciones sin estado 1: excluidas del análisis
 * - Múltiples facturas: se toma la primera cronológicamente
 */
class TiempoFacturacionController extends Controller
{
    /**
     * CALCULAR TIEMPO PROMEDIO ESTADO 1 → PRIMERA FACTURA
     * 
     * Analiza tiempo desde estado 1 (Lista para Facturar) hasta primera factura
     * usando datos del JSON proporcionado
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function calcularTiempoTerminacionFacturacion(Request $request)
    {
        try {
            set_time_limit(300);
            ini_set('memory_limit', '1G');
            
            $startTime = microtime(true);
            Log::info("⏱️ INICIANDO CÁLCULO TIEMPO ESTADO 1 → PRIMERA FACTURA");
            
            // Cargar datos del JSON
            $jsonData = $this->cargarDatosJSON();
            if (!$jsonData) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo cargar el archivo JSON de datos'
                ], 500);
            }
            
            // Parámetros de filtrado
            $año = $request->input('año', null);
            $mes = $request->input('mes', null);
            $tipoFactura = $request->input('tipo_factura', 'todas'); // 'sence', 'cliente', 'todas'
            
            $comercializacionesAnalizadas = 0;
            $comercializacionesConFactura = 0;
            $tiemposCalculados = [];
            $detallesComercializaciones = [];
            $estadisticas = [
                'facturas_sence' => 0,
                'facturas_cliente' => 0,
                'sin_estado_1' => 0,
                'sin_facturas' => 0,
                'multiples_facturas' => 0,
                'facturas_antes_estado_1' => 0,  // NUEVO: contador de facturas emitidas antes del estado 1
                'facturas_despues_estado_1' => 0, // NUEVO: contador de facturas emitidas después del estado 1
                'facturas_mismo_dia' => 0  // NUEVO: facturas emitidas el mismo día del estado 1
            ];
            
            foreach ($jsonData as $comercializacion) {
                $comercializacionesAnalizadas++;
                
                // Aplicar filtros de fecha
                if (!$this->cumpleFiltrosFecha($comercializacion, $año, $mes)) {
                    continue;
                }
                
                // Encontrar fecha del estado 1 (Lista para Facturar)
                $fechaEstado1 = $this->obtenerFechaEstado1($comercializacion);
                if (!$fechaEstado1) {
                    $estadisticas['sin_estado_1']++;
                    continue;
                }
                
                // Verificar si tiene facturas
                if (!isset($comercializacion['Facturas']) || empty($comercializacion['Facturas'])) {
                    $estadisticas['sin_facturas']++;
                    continue;
                }
                
                // Obtener primera factura cronológicamente
                $primeraFactura = $this->obtenerPrimeraFacturaCronologica($comercializacion['Facturas'], $tipoFactura, $comercializacion);
                if (!$primeraFactura) {
                    continue;
                }
                
                // Calcular tiempo entre estado 1 y primera factura
                $fechaFacturacion = Carbon::createFromFormat('d/m/Y', $primeraFactura['FechaFacturacion']);
                $diasDiferencia = $fechaEstado1->diffInDays($fechaFacturacion, false); // false para permitir valores negativos
                
                // Clasificar según timing de facturación
                if ($diasDiferencia < 0) {
                    $estadisticas['facturas_antes_estado_1']++;
                    // Para casos negativos, guardamos el valor absoluto pero los marcamos especialmente
                    $tiemposCalculados[] = abs($diasDiferencia);
                    $tipoTiming = 'antes_estado_1';
                } elseif ($diasDiferencia == 0) {
                    $estadisticas['facturas_mismo_dia']++;
                    $tiemposCalculados[] = $diasDiferencia;
                    $tipoTiming = 'mismo_dia';
                } else {
                    $estadisticas['facturas_despues_estado_1']++;
                    $tiemposCalculados[] = $diasDiferencia;
                    $tipoTiming = 'despues_estado_1';
                }
                
                $comercializacionesConFactura++;
                
                // Estadísticas por tipo de factura
                $tipoFacturaDetectado = $this->detectarTipoFactura($primeraFactura, $comercializacion);
                $estadisticas[$tipoFacturaDetectado]++;
                
                if (count($comercializacion['Facturas']) > 1) {
                    $estadisticas['multiples_facturas']++;
                }
                
                // Guardar detalles para análisis
                $detallesComercializaciones[] = [
                    'codigo_cotizacion' => $comercializacion['CodigoCotizacion'],
                    'cliente' => $comercializacion['NombreCliente'],
                    'fecha_estado_1' => $fechaEstado1->format('d/m/Y'),
                    'fecha_facturacion' => $primeraFactura['FechaFacturacion'],
                    'dias_diferencia' => $diasDiferencia,
                    'dias_diferencia_absoluta' => abs($diasDiferencia),
                    'numero_factura' => $primeraFactura['numero'],
                    'tipo_factura' => $tipoFacturaDetectado,
                    'tipo_timing' => $tipoTiming,
                    'valor_comercializacion' => $comercializacion['ValorFinalComercializacion'] ?? 0
                ];
            }
            
            // Calcular estadísticas finales
            $resultados = $this->calcularEstadisticasFinales($tiemposCalculados, $detallesComercializaciones);
            
            $tiempoEjecucion = round((microtime(true) - $startTime) * 1000, 2);
            
            return response()->json([
                'success' => true,
                'message' => 'Análisis tiempo estado 1 → primera factura completado exitosamente',
                'datos' => [
                    'resumen' => [
                        'comercializaciones_analizadas' => $comercializacionesAnalizadas,
                        'comercializaciones_con_factura' => $comercializacionesConFactura,
                        'porcentaje_facturadas' => $comercializacionesConFactura > 0 ? 
                            round(($comercializacionesConFactura / $comercializacionesAnalizadas) * 100, 2) : 0,
                        'filtros_aplicados' => [
                            'año' => $año,
                            'mes' => $mes,
                            'tipo_factura' => $tipoFactura
                        ]
                    ],
                    'tiempo_promedio' => $resultados['tiempo_promedio'],
                    'estadisticas' => array_merge($estadisticas, $resultados['estadisticas']),
                    'casos_especiales' => [
                        'facturas_antes_estado_1' => $estadisticas['facturas_antes_estado_1'],
                        'facturas_mismo_dia' => $estadisticas['facturas_mismo_dia'],
                        'facturas_despues_estado_1' => $estadisticas['facturas_despues_estado_1'],
                        'porcentaje_antes' => $comercializacionesConFactura > 0 ? 
                            round(($estadisticas['facturas_antes_estado_1'] / $comercializacionesConFactura) * 100, 2) : 0,
                        'mensaje_casos_antes' => 'Casos donde la factura se emitió antes de que la comercialización estuviera lista'
                    ],
                    'distribucion_tiempos' => $resultados['distribucion'],
                    'casos_extremos' => $resultados['casos_extremos'],
                    'top_clientes_mas_lentos' => $resultados['top_lentos'],
                    'top_clientes_mas_rapidos' => $resultados['top_rapidos']
                ],
                'metadata' => [
                    'tiempo_ejecucion_ms' => $tiempoEjecucion,
                    'timestamp' => now()->format('Y-m-d H:i:s'),
                    'total_registros_json' => count($jsonData)
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error("❌ ERROR CALCULANDO TIEMPO ESTADO 1 → PRIMERA FACTURA: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al calcular tiempo estado 1 → primera factura: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * ANÁLISIS POR CLIENTE - TIEMPO ESTADO 1 → PRIMERA FACTURA
     * 
     * Agrupa resultados por cliente mostrando comportamiento individual
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function analizarTiemposPorCliente(Request $request)
    {
        try {
            $jsonData = $this->cargarDatosJSON();
            if (!$jsonData) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo cargar el archivo JSON de datos'
                ], 500);
            }
            
            $año = $request->input('año', null);
            $mes = $request->input('mes', null);
            $tipoFactura = $request->input('tipo_factura', 'todas');
            
            $clientesData = [];
            
            foreach ($jsonData as $comercializacion) {
                if (!$this->cumpleFiltrosFecha($comercializacion, $año, $mes)) {
                    continue;
                }
                
                $fechaEstado1 = $this->obtenerFechaEstado1MasReciente($comercializacion);
                if (!$fechaEstado1) continue;
                
                if (!isset($comercializacion['Facturas']) || empty($comercializacion['Facturas'])) {
                    continue;
                }
                
                $primeraFactura = $this->obtenerPrimeraFactura($comercializacion['Facturas'], $tipoFactura, $comercializacion);
                if (!$primeraFactura) continue;
                
                $fechaFacturacion = Carbon::createFromFormat('d/m/Y', $primeraFactura['FechaFacturacion']);
                $diasDiferencia = $fechaEstado1->diffInDays($fechaFacturacion);
                
                $clienteNombre = $comercializacion['NombreCliente'];
                
                if (!isset($clientesData[$clienteNombre])) {
                    $clientesData[$clienteNombre] = [
                        'nombre_cliente' => $clienteNombre,
                        'comercializaciones' => 0,
                        'tiempos' => [],
                        'valor_total' => 0,
                        'facturas_sence' => 0,
                        'facturas_cliente' => 0
                    ];
                }
                
                $clientesData[$clienteNombre]['comercializaciones']++;
                $clientesData[$clienteNombre]['tiempos'][] = abs($diasDiferencia); // Usar valor absoluto para estadísticas
                $clientesData[$clienteNombre]['valor_total'] += $comercializacion['ValorFinalComercializacion'] ?? 0;
                
                $tipoFacturaDetectado = $this->detectarTipoFactura($primeraFactura, $comercializacion);
                if ($tipoFacturaDetectado === 'facturas_sence') {
                    $clientesData[$clienteNombre]['facturas_sence']++;
                } else {
                    $clientesData[$clienteNombre]['facturas_cliente']++;
                }
            }
            
            // Calcular estadísticas por cliente
            $resultadosClientes = [];
            foreach ($clientesData as $cliente => $data) {
                if (empty($data['tiempos'])) continue;
                
                $tiempos = $data['tiempos'];
                $resultadosClientes[] = [
                    'cliente' => $cliente,
                    'comercializaciones' => $data['comercializaciones'],
                    'tiempo_promedio_dias' => round(array_sum($tiempos) / count($tiempos), 2),
                    'tiempo_minimo_dias' => min($tiempos),
                    'tiempo_maximo_dias' => max($tiempos),
                    'valor_total_comercializaciones' => $data['valor_total'],
                    'facturas_sence' => $data['facturas_sence'],
                    'facturas_cliente' => $data['facturas_cliente'],
                    'distribucion_tiempos' => $this->generarDistribucionTiempos($tiempos)
                ];
            }
            
            // Ordenar por tiempo promedio (más lento primero)
            usort($resultadosClientes, function($a, $b) {
                return $b['tiempo_promedio_dias'] <=> $a['tiempo_promedio_dias'];
            });
            
            return response()->json([
                'success' => true,
                'message' => 'Análisis por cliente completado exitosamente',
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
            Log::error("❌ ERROR ANÁLISIS POR CLIENTE: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error en análisis por cliente: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * DISTRIBUCIÓN TIEMPOS FACTURACIÓN
     * 
     * Analiza distribución de tiempos en rangos predefinidos
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function obtenerDistribucionTiempos(Request $request)
    {
        try {
            $jsonData = $this->cargarDatosJSON();
            if (!$jsonData) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo cargar el archivo JSON de datos'
                ], 500);
            }
            
            $año = $request->input('año', null);
            $mes = $request->input('mes', null);
            $tipoFactura = $request->input('tipo_factura', 'todas');
            
            $tiempos = [];
            $detallesPorRango = [];
            
            foreach ($jsonData as $comercializacion) {
                if (!$this->cumpleFiltrosFecha($comercializacion, $año, $mes)) {
                    continue;
                }
                
                $fechaEstado1 = $this->obtenerFechaEstado1MasReciente($comercializacion);
                if (!$fechaEstado1) continue;
                
                if (!isset($comercializacion['Facturas']) || empty($comercializacion['Facturas'])) {
                    continue;
                }
                
                $primeraFactura = $this->obtenerPrimeraFactura($comercializacion['Facturas'], $tipoFactura, $comercializacion);
                if (!$primeraFactura) continue;
                
                $fechaFacturacion = Carbon::createFromFormat('d/m/Y', $primeraFactura['FechaFacturacion']);
                $diasDiferencia = $fechaEstado1->diffInDays($fechaFacturacion);
                
                $tiempos[] = $diasDiferencia;
                
                // Clasificar por rango
                $rango = $this->clasificarPorRango($diasDiferencia);
                if (!isset($detallesPorRango[$rango])) {
                    $detallesPorRango[$rango] = [];
                }
                
                $detallesPorRango[$rango][] = [
                    'codigo_cotizacion' => $comercializacion['CodigoCotizacion'],
                    'cliente' => $comercializacion['NombreCliente'],
                    'dias' => $diasDiferencia,
                    'valor' => $comercializacion['ValorFinalComercializacion'] ?? 0
                ];
            }
            
            // Generar distribución
            $distribucion = $this->generarDistribucionCompleta($tiempos, $detallesPorRango);
            
            return response()->json([
                'success' => true,
                'message' => 'Distribución de tiempos generada exitosamente',
                'datos' => [
                    'total_comercializaciones' => count($tiempos),
                    'filtros_aplicados' => [
                        'año' => $año,
                        'mes' => $mes,
                        'tipo_factura' => $tipoFactura
                    ],
                    'distribucion' => $distribucion,
                    'estadisticas_generales' => [
                        'promedio_dias' => count($tiempos) > 0 ? round(array_sum($tiempos) / count($tiempos), 2) : 0,
                        'mediana_dias' => count($tiempos) > 0 ? $this->calcularMediana($tiempos) : 0,
                        'minimo_dias' => count($tiempos) > 0 ? min($tiempos) : 0,
                        'maximo_dias' => count($tiempos) > 0 ? max($tiempos) : 0
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error("❌ ERROR DISTRIBUCIÓN TIEMPOS: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error en distribución de tiempos: ' . $e->getMessage()
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
            // Intentar cargar desde diferentes ubicaciones posibles
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
        
        // Usar FechaInicio para filtrar
        try {
            $fechaInicio = Carbon::createFromFormat('d/m/Y', $comercializacion['FechaInicio']);
            
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
     * Obtener fecha del estado 1 (Lista para Facturar)
     * A diferencia del método anterior, este busca específicamente el estado 1
     * sin importar si hay estados posteriores
     */
    private function obtenerFechaEstado1($comercializacion)
    {
        if (!isset($comercializacion['Estados']) || empty($comercializacion['Estados'])) {
            return null;
        }
        
        foreach ($comercializacion['Estados'] as $estado) {
            if ($estado['EstadoComercializacion'] == 1) {
                try {
                    $fecha = Carbon::createFromFormat('d/m/Y', $estado['Fecha']);
                    return $fecha;
                } catch (\Exception $e) {
                    continue;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Obtener primera factura cronológicamente (la más antigua)
     * Independiente del tipo, solo busca la primera factura emitida
     */
    private function obtenerPrimeraFacturaCronologica($facturas, $tipoFactura = 'todas', $comercializacion = null)
    {
        $facturasValidas = [];
        
        foreach ($facturas as $factura) {
            if (!isset($factura['FechaFacturacion'])) continue;
            
            try {
                $fechaFacturacion = Carbon::createFromFormat('d/m/Y', $factura['FechaFacturacion']);
                $facturasValidas[] = [
                    'factura' => $factura,
                    'fecha' => $fechaFacturacion
                ];
            } catch (\Exception $e) {
                continue;
            }
        }
        
        if (empty($facturasValidas)) {
            return null;
        }
        
        // Ordenar por fecha (más antigua primero)
        usort($facturasValidas, function($a, $b) {
            return $a['fecha'] <=> $b['fecha'];
        });
        
        // Filtrar por tipo si se especifica
        if ($tipoFactura !== 'todas') {
            foreach ($facturasValidas as $facturaData) {
                $tipo = $this->detectarTipoFactura($facturaData['factura'], $comercializacion);
                if (($tipoFactura === 'sence' && $tipo === 'facturas_sence') ||
                    ($tipoFactura === 'cliente' && $tipo === 'facturas_cliente')) {
                    return $facturaData['factura'];
                }
            }
            return null;
        }
        
        // Retornar la primera factura cronológicamente
        return $facturasValidas[0]['factura'];
    }
    
    /**
     * Obtener fecha más reciente del estado 1 (Terminada) - MÉTODO LEGACY
     * Mantenido para compatibilidad con otros métodos que aún lo usen
     */
    private function obtenerFechaEstado1MasReciente($comercializacion)
    {
        if (!isset($comercializacion['Estados']) || empty($comercializacion['Estados'])) {
            return null;
        }
        
        $fechasEstado1 = [];
        
        foreach ($comercializacion['Estados'] as $estado) {
            if ($estado['EstadoComercializacion'] == 1) {
                try {
                    $fecha = Carbon::createFromFormat('d/m/Y', $estado['Fecha']);
                    $fechasEstado1[] = $fecha;
                } catch (\Exception $e) {
                    continue;
                }
            }
        }
        
        if (empty($fechasEstado1)) {
            return null;
        }
        
        // Retornar la fecha más reciente
        return max($fechasEstado1);
    }
    
    /**
     * Obtener primera factura según tipo
     */
    private function obtenerPrimeraFactura($facturas, $tipoFactura = 'todas', $comercializacion = null)
    {
        $facturasValidas = [];
        
        foreach ($facturas as $factura) {
            if (!isset($factura['FechaFacturacion'])) continue;
            
            try {
                $fechaFacturacion = Carbon::createFromFormat('d/m/Y', $factura['FechaFacturacion']);
                $facturasValidas[] = [
                    'factura' => $factura,
                    'fecha' => $fechaFacturacion
                ];
            } catch (\Exception $e) {
                continue;
            }
        }
        
        if (empty($facturasValidas)) {
            return null;
        }
        
        // Ordenar por fecha (más antigua primero)
        usort($facturasValidas, function($a, $b) {
            return $a['fecha'] <=> $b['fecha'];
        });
        
        // Filtrar por tipo si se especifica
        if ($tipoFactura !== 'todas') {
            foreach ($facturasValidas as $facturaData) {
                $tipo = $this->detectarTipoFactura($facturaData['factura'], $comercializacion);
                if (($tipoFactura === 'sence' && $tipo === 'facturas_sence') ||
                    ($tipoFactura === 'cliente' && $tipo === 'facturas_cliente')) {
                    return $facturaData['factura'];
                }
            }
            return null;
        }
        
        return $facturasValidas[0]['factura'];
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
                        $fechaEstado3 = Carbon::createFromFormat('d/m/Y', $estado['Fecha']);
                        $fechaFactura = Carbon::createFromFormat('d/m/Y', $factura['FechaFacturacion']);
                        
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
        // Las facturas SENCE suelen tener montos más altos en el campo "Pagado"
        if (isset($factura['EstadosFactura'])) {
            $montoMaximo = 0;
            foreach ($factura['EstadosFactura'] as $estadoFactura) {
                if (isset($estadoFactura['Pagado']) && $estadoFactura['Pagado'] > $montoMaximo) {
                    $montoMaximo = $estadoFactura['Pagado'];
                }
            }
            
            // Si el monto es muy alto (>500,000), probablemente es SENCE
            if ($montoMaximo > 500000) {
                return 'facturas_sence';
            }
        }
        
        // Por defecto, asumir que es factura de cliente
        return 'facturas_cliente';
    }
    
    /**
     * Calcular estadísticas finales
     */
    private function calcularEstadisticasFinales($tiempos, $detalles)
    {
        if (empty($tiempos)) {
            return [
                'tiempo_promedio' => 0,
                'estadisticas' => [],
                'distribucion' => [],
                'casos_extremos' => [],
                'top_lentos' => [],
                'top_rapidos' => []
            ];
        }
        
        $promedio = round(array_sum($tiempos) / count($tiempos), 2);
        $mediana = $this->calcularMediana($tiempos);
        
        // Casos extremos
        $casosExtremos = [
            'mas_rapido' => null,
            'mas_lento' => null
        ];
        
        foreach ($detalles as $detalle) {
            if ($casosExtremos['mas_rapido'] === null || $detalle['dias_diferencia'] < $casosExtremos['mas_rapido']['dias_diferencia']) {
                $casosExtremos['mas_rapido'] = $detalle;
            }
            if ($casosExtremos['mas_lento'] === null || $detalle['dias_diferencia'] > $casosExtremos['mas_lento']['dias_diferencia']) {
                $casosExtremos['mas_lento'] = $detalle;
            }
        }
        
        // Top clientes más lentos y rápidos
        $clientesTiempos = [];
        foreach ($detalles as $detalle) {
            $cliente = $detalle['cliente'];
            if (!isset($clientesTiempos[$cliente])) {
                $clientesTiempos[$cliente] = [];
            }
            $clientesTiempos[$cliente][] = $detalle['dias_diferencia'];
        }
        
        $promediosClientes = [];
        foreach ($clientesTiempos as $cliente => $tiemposCliente) {
            $promediosClientes[] = [
                'cliente' => $cliente,
                'promedio_dias' => round(array_sum($tiemposCliente) / count($tiemposCliente), 2),
                'comercializaciones' => count($tiemposCliente)
            ];
        }
        
        usort($promediosClientes, function($a, $b) {
            return $b['promedio_dias'] <=> $a['promedio_dias'];
        });
        
        return [
            'tiempo_promedio' => $promedio,
            'estadisticas' => [
                'mediana_dias' => $mediana,
                'minimo_dias' => min($tiempos),
                'maximo_dias' => max($tiempos),
                'desviacion_estandar' => $this->calcularDesviacionEstandar($tiempos)
            ],
            'distribucion' => $this->generarDistribucionTiempos($tiempos),
            'casos_extremos' => $casosExtremos,
            'top_lentos' => array_slice($promediosClientes, 0, 5),
            'top_rapidos' => array_slice(array_reverse($promediosClientes), 0, 5)
        ];
    }
    
    /**
     * Generar distribución de tiempos en rangos
     */
    private function generarDistribucionTiempos($tiempos)
    {
        $rangos = [
            'mismo_dia' => ['min' => 0, 'max' => 0, 'count' => 0],
            'muy_rapido' => ['min' => 1, 'max' => 3, 'count' => 0],
            'rapido' => ['min' => 4, 'max' => 7, 'count' => 0],
            'normal' => ['min' => 8, 'max' => 15, 'count' => 0],
            'lento' => ['min' => 16, 'max' => 30, 'count' => 0],
            'muy_lento' => ['min' => 31, 'max' => 60, 'count' => 0],
            'extremo' => ['min' => 61, 'max' => 999, 'count' => 0]
        ];
        
        foreach ($tiempos as $tiempo) {
            foreach ($rangos as $key => &$rango) {
                if ($tiempo >= $rango['min'] && $tiempo <= $rango['max']) {
                    $rango['count']++;
                    break;
                }
            }
        }
        
        $total = count($tiempos);
        foreach ($rangos as &$rango) {
            $rango['porcentaje'] = $total > 0 ? round(($rango['count'] / $total) * 100, 2) : 0;
        }
        
        return $rangos;
    }
    
    /**
     * Generar distribución completa con detalles
     */
    private function generarDistribucionCompleta($tiempos, $detallesPorRango)
    {
        $distribucion = $this->generarDistribucionTiempos($tiempos);
        
        // Agregar ejemplos a cada rango
        foreach ($distribucion as $key => &$rango) {
            $rango['ejemplos'] = isset($detallesPorRango[$key]) ? 
                array_slice($detallesPorRango[$key], 0, 3) : [];
        }
        
        return $distribucion;
    }
    
    /**
     * Clasificar tiempo por rango
     */
    private function clasificarPorRango($dias)
    {
        if ($dias === 0) return 'mismo_dia';
        if ($dias >= 1 && $dias <= 3) return 'muy_rapido';
        if ($dias >= 4 && $dias <= 7) return 'rapido';
        if ($dias >= 8 && $dias <= 15) return 'normal';
        if ($dias >= 16 && $dias <= 30) return 'lento';
        if ($dias >= 31 && $dias <= 60) return 'muy_lento';
        return 'extremo';
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
}
