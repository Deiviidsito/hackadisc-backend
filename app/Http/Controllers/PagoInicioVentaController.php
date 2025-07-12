<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PagoInicioVentaController extends Controller
{
    /**
     * ANÁLISIS DE TIEMPO PROMEDIO DESDE INICIO DE VENTA HASTA PAGO COMPLETO
     * 
     * Calcula cuánto tiempo transcurre en promedio desde que se emite una comercialización
     * hasta que Insecap recibe todo el dinero de las facturas.
     * 
     * Proceso:
     * 1. Toma FechaInicio de la comercialización (campo clave para el análisis)
     * 2. Busca la fecha donde el pago total alcanza exactamente el ValorFinalComercializacion
     * 3. Calcula la diferencia en días promedio
     * 
     * Parámetros opcionales:
     * - año: filtrar por año específico
     * - fecha_inicio: filtrar desde fecha específica (formato YYYY-MM-DD)
     * - fecha_fin: filtrar hasta fecha específica (formato YYYY-MM-DD)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function analizarTiempoPagoCompleto(Request $request)
    {
        try {
            set_time_limit(300);
            ini_set('memory_limit', '1G');
            
            Log::info("🕐 INICIANDO ANÁLISIS TIEMPO PAGO COMPLETO");
            
            // Parámetros de filtrado opcionales (sin estado_venta - se usan filtros internos)
            $año = $request->input('año');
            $fechaInicio = $request->input('fecha_inicio');
            $fechaFin = $request->input('fecha_fin');
            
            // Estados válidos y prefijos a ignorar (filtros internos automáticos)
            $validStates = [0, 1, 3];
            $ignorePrefixes = ['ADI', 'OTR', 'SPD'];
            
            // Construir filtros de fecha WHERE adicionales
            $filtrosFecha = "";
            $parametros = [];
            
            if ($año) {
                $filtrosFecha .= " AND YEAR(v.FechaInicio) = ?";
                $parametros[] = $año;
            }
            
            if ($fechaInicio) {
                $filtrosFecha .= " AND v.FechaInicio >= ?";
                $parametros[] = $fechaInicio;
            }
            
            if ($fechaFin) {
                $filtrosFecha .= " AND v.FechaInicio <= ?";
                $parametros[] = $fechaFin;
            }
            
            Log::info("🔍 Filtros aplicados", [
                'año' => $año,
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
                'estados_validos' => $validStates
            ]);
            
            // OPTIMIZACIÓN DATACENTER: Una sola consulta para todas las facturas
            $todasLasFacturas = DB::select("
                SELECT 
                    f.idComercializacion,
                    f.numero,
                    f.FechaFacturacion,
                    hef.estado_id,
                    hef.fecha as fecha_estado,
                    hef.pagado,
                    v.FechaInicio,
                    v.ValorFinalComercializacion,
                    v.CodigoCotizacion
                FROM facturas f
                INNER JOIN historial_estados_factura hef ON f.numero = hef.factura_numero
                INNER JOIN ventas v ON f.idComercializacion = v.idComercializacion
                WHERE 
                    v.CodigoCotizacion NOT LIKE 'ADI%' 
                    AND v.CodigoCotizacion NOT LIKE 'OTR%' 
                    AND v.CodigoCotizacion NOT LIKE 'SPD%'
                    AND v.ValorFinalComercializacion > 0
                    AND hef.estado_id = 3
                    AND hef.pagado > 0
                ORDER BY f.idComercializacion, hef.fecha ASC
            ");
            
            // Agrupar por comercialización para procesamiento en memoria
            $facturasPorComercializacion = [];
            foreach ($todasLasFacturas as $factura) {
                $id = $factura->idComercializacion;
                if (!isset($facturasPorComercializacion[$id])) {
                    $facturasPorComercializacion[$id] = [
                        'facturas' => [],
                        'comercializacion' => (object)[
                            'idComercializacion' => $factura->idComercializacion,
                            'CodigoCotizacion' => $factura->CodigoCotizacion,
                            'FechaInicio' => $factura->FechaInicio,
                            'ValorFinalComercializacion' => $factura->ValorFinalComercializacion
                        ]
                    ];
                }
                $facturasPorComercializacion[$id]['facturas'][] = $factura;
            }
            
            $tiemposPago = [];
            $comercializacionesProcesadas = 0;
            $comercializacionesValidas = 0;
            $facturasAnticipadasCount = 0;
            
            // Procesar cada comercialización en memoria (mucho más rápido)
            foreach ($facturasPorComercializacion as $idComercializacion => $data) {
                $comercializacion = $data['comercializacion'];
                $facturas = $data['facturas'];
                $comercializacionesProcesadas++;
                
                if (empty($facturas)) {
                    continue;
                }
                
                // Analizar progresión de pagos para encontrar cuándo se completó
                $fechaPagoCompleto = $this->calcularFechaPagoCompletoOptimizado(
                    $facturas, 
                    $comercializacion->ValorFinalComercializacion
                );
                
                if ($fechaPagoCompleto) {
                    try {
                        $fechaInicio = Carbon::parse($comercializacion->FechaInicio);
                        $fechaCompleto = Carbon::parse($fechaPagoCompleto);
                        
                        $diasTranscurridos = $fechaInicio->diffInDays($fechaCompleto);
                        
                        if ($diasTranscurridos >= 0) {
                            // Casos normales: incluir pagos desde el día 0 (mismo día)
                            $tiemposPago[] = $diasTranscurridos;
                            $comercializacionesValidas++;
                        } else {
                            // Casos especiales: facturas emitidas antes del inicio
                            $facturasAnticipadasCount++;
                        }
                        
                    } catch (\Exception $e) {
                        // Continuar con siguiente comercialización si hay error de fecha
                        continue;
                    }
                }
            }
            
            // Calcular estadísticas avanzadas
            if (count($tiemposPago) > 0) {
                $promedioDias = round(array_sum($tiemposPago) / count($tiemposPago), 1);
                $medianaDias = $this->calcularMediana($tiemposPago);
                $minDias = min($tiemposPago);
                $maxDias = max($tiemposPago);
                
                // Desviación estándar
                $desviacionEstandar = $this->calcularDesviacionEstandar($tiemposPago, $promedioDias);
                
                // Percentiles para análisis de riesgo
                $percentil25 = $this->calcularPercentil($tiemposPago, 25);
                $percentil75 = $this->calcularPercentil($tiemposPago, 75);
                $percentil90 = $this->calcularPercentil($tiemposPago, 90);
                
                // Convertir a formato más amigable
                $promedioSemanas = round($promedioDias / 7, 1);
                $promedioMeses = round($promedioDias / 30, 1);
                
                // Rangos para planificación financiera
                $rangoMinimo = max(1, round($promedioDias - $desviacionEstandar, 0));
                $rangoMaximo = round($promedioDias + $desviacionEstandar, 0);
                
                return response()->json([
                    'success' => true,
                    'mensaje' => 'Análisis de cuanto se demoran en promedio los clientes desde que se inicia una venta hasta que se recibe el dinero',
                    'estadisticas_tiempo_pago' => [
                        'promedio_dias' => $promedioDias,
                        'promedio_semanas' => $promedioSemanas,
                        'promedio_meses' => $promedioMeses,
                        'mediana_dias' => $medianaDias,
                        'desviacion_estandar_dias' => round($desviacionEstandar, 1),
                        'tiempo_minimo_dias' => $minDias,
                        'tiempo_maximo_dias' => $maxDias,
                        'percentil_25_dias' => $percentil25,
                        'percentil_75_dias' => $percentil75,
                        'percentil_90_dias' => $percentil90
                    ],
                    'analisis_financiero' => [
                        'rango_probable_dias' => "{$rangoMinimo} - {$rangoMaximo}",
                        'interpretacion_variabilidad' => "Los datos pueden variar en un rango ±" . round($desviacionEstandar, 1) . " días iniciando desde el promedio de {$promedioDias} días",
                        'planificacion_flujo_caja' => "Para planificar flujo de caja, considere que el 75% de los pagos llegan antes de {$percentil75} días",
                        'escenario_conservador' => "En un escenario conservador, espere pagos hasta {$percentil90} días para el 90% de casos",
                        'recomendacion_credito' => $percentil90 > 90 ? "Considere políticas de crédito más estrictas" : "Los tiempos de pago están dentro de rangos aceptables"
                    ],
                    'resumen' => [
                        'comercializaciones_analizadas' => $comercializacionesValidas,
                        'facturas_emitidas_antes_de_inicio' => $facturasAnticipadasCount,
                        'nota' => 'Se excluyen únicamente pagos realizados antes del inicio de la comercialización',
                        'interpretacion' => "En promedio, transcurren {$promedioDias} días (≈{$promedioSemanas} semanas) desde el inicio de una comercialización hasta recibir el pago completo"
                    ]
                ]);
                
            } else {
                return response()->json([
                    'success' => false,
                    'mensaje' => 'No se encontraron comercializaciones con pago completo para analizar',
                    'comercializaciones_procesadas' => $comercializacionesProcesadas
                ]);
            }
            
        } catch (\Exception $e) {
            Log::error('💥 ERROR EN ANÁLISIS TIEMPO PAGO', [
                'mensaje' => $e->getMessage(),
                'linea' => $e->getLine(),
                'archivo' => $e->getFile()
            ]);
            
            return response()->json([
                'success' => false,
                'mensaje' => 'Error al analizar tiempo de pago',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Calcula la fecha exacta en que se completó el pago de una comercialización
     * 
     * Busca el momento preciso cuando el total pagado alcanza exactamente
     * el ValorFinalComercializacion de la comercialización.
     * 
     * @param array $facturas Todas las facturas con sus estados
     * @param float $valorFinalComercializacion Valor total esperado
     * @return string|null Fecha cuando se completó el pago o null si no se completó
     */
    private function calcularFechaPagoCompleto($facturas, $valorFinalComercializacion)
    {
        $eventosPago = [];
        
        // Extraer todos los eventos de pago cronológicamente
        foreach ($facturas as $factura) {
            // Solo considerar facturas con estado 3 (pagada) y monto > 0
            if ($factura->estado_id == 3 && $factura->pagado > 0) {
                $eventosPago[] = [
                    'fecha' => $factura->fecha_estado,
                    'monto_pagado' => floatval($factura->pagado),
                    'factura' => $factura->numero
                ];
            }
        }
        
        if (empty($eventosPago)) {
            return null; // No hay pagos registrados
        }
        
        // Ordenar eventos por fecha cronológica
        usort($eventosPago, function($a, $b) {
            return strtotime($a['fecha']) - strtotime($b['fecha']);
        });
        
        // Calcular pago acumulado hasta encontrar cuándo se completó exactamente
        $pagoAcumulado = 0;
        $valorObjetivo = floatval($valorFinalComercializacion);
        
        foreach ($eventosPago as $evento) {
            $pagoAcumulado += $evento['monto_pagado'];
            
            // Verificar si se alcanzó el valor final (tolerancia de $100 por redondeos)
            if (abs($pagoAcumulado - $valorObjetivo) <= 100) {
                Log::debug("💰 Pago completado", [
                    'fecha_completo' => $evento['fecha'],
                    'pago_acumulado' => $pagoAcumulado,
                    'valor_objetivo' => $valorObjetivo,
                    'diferencia' => abs($pagoAcumulado - $valorObjetivo)
                ]);
                
                return $evento['fecha'];
            }
            
            // Si se pasó del monto, tomar la fecha del evento anterior
            if ($pagoAcumulado > $valorObjetivo) {
                Log::debug("📊 Pago excedido", [
                    'fecha_exceso' => $evento['fecha'],
                    'pago_acumulado' => $pagoAcumulado,
                    'valor_objetivo' => $valorObjetivo
                ]);
                
                return $evento['fecha'];
            }
        }
        
        // No se completó el pago total
        Log::debug("⚠️ Pago incompleto", [
            'pago_acumulado' => $pagoAcumulado,
            'valor_objetivo' => $valorObjetivo,
            'faltante' => $valorObjetivo - $pagoAcumulado
        ]);
        
        return null;
    }
    
    /**
     * Versión optimizada para calcular fecha de pago completo
     * Procesa en memoria sin logs para máxima velocidad
     */
    private function calcularFechaPagoCompletoOptimizado($facturas, $valorFinalComercializacion)
    {
        $pagoAcumulado = 0;
        $valorObjetivo = floatval($valorFinalComercializacion);
        
        // Las facturas ya vienen ordenadas por fecha
        foreach ($facturas as $factura) {
            $pagoAcumulado += floatval($factura->pagado);
            
            // Verificar si se alcanzó el valor final (tolerancia de $100)
            if (abs($pagoAcumulado - $valorObjetivo) <= 100 || $pagoAcumulado >= $valorObjetivo) {
                return $factura->fecha_estado;
            }
        }
        
        return null; // No se completó el pago
    }
    
    /**
     * Calcula la mediana de un array de números
     * 
     * @param array $numbers
     * @return float
     */
    private function calcularMediana($numbers)
    {
        sort($numbers);
        $count = count($numbers);
        
        if ($count % 2 == 0) {
            return ($numbers[$count/2 - 1] + $numbers[$count/2]) / 2;
        } else {
            return $numbers[floor($count/2)];
        }
    }
    
    /**
     * Calcula la desviación estándar de un array de números
     */
    private function calcularDesviacionEstandar($numbers, $promedio)
    {
        $sumaCuadrados = 0;
        foreach ($numbers as $number) {
            $sumaCuadrados += pow($number - $promedio, 2);
        }
        return sqrt($sumaCuadrados / count($numbers));
    }

    /**
     * Calcula un percentil específico de un array de números
     */
    private function calcularPercentil($numbers, $percentil)
    {
        sort($numbers);
        $count = count($numbers);
        $index = ($percentil / 100) * ($count - 1);
        
        if (floor($index) == $index) {
            return $numbers[$index];
        } else {
            $lower = $numbers[floor($index)];
            $upper = $numbers[ceil($index)];
            return $lower + (($upper - $lower) * ($index - floor($index)));
        }
    }
    
    /**
     * Endpoint simple para probar que el controlador funciona
     */
    public function test(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'PagoInicioVentaController está funcionando',
            'timestamp' => now(),
            'parametros_recibidos' => $request->all()
        ]);
    }
}
