<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ConsultarFiabilidadCliente extends Controller
{
    /**
     * ANÁLISIS DE FIABILIDAD Y PREDICCIÓN DE PAGOS POR CLIENTE ESPECÍFICO
     * 
     * Analiza el comportamiento histórico de pago de un cliente específico y predice
     * cuándo se recibirán los pagos de ventas pendientes basándose en su historial.
     * 
     * Funcionalidades:
     * 1. Estadísticas personalizadas del cliente (similar al endpoint global)
     * 2. Identificación de pagos pendientes (estado factura 4)
     * 3. Predicción de fechas de pago basada en comportamiento histórico
     * 4. Recomendaciones de gestión comercial
     * 
     * @param Request $request - debe incluir 'nombre_cliente'
     * @return \Illuminate\Http\JsonResponse
     */
    public function analizarFiabilidadCliente(Request $request)
    {
        try {
            set_time_limit(300);
            ini_set('memory_limit', '1G');
            
            $nombreCliente = $request->input('nombre_cliente');
            $ano = $request->input('anio', date('Y')); // Por defecto año actual (usando 'anio' en lugar de 'año')
            
            if (empty($nombreCliente)) {
                return response()->json([
                    'success' => false,
                    'mensaje' => 'El nombre del cliente es requerido'
                ], 400);
            }
            
            Log::info("🔍 INICIANDO ANÁLISIS DE FIABILIDAD", [
                'cliente' => $nombreCliente,
                'año' => $ano
            ]);
            
            // OPTIMIZACIÓN: Una sola consulta para obtener historial completo del cliente
            $historicoCliente = DB::select("
                SELECT 
                    f.idComercializacion,
                    f.numero,
                    f.FechaFacturacion,
                    hef.estado_id,
                    hef.fecha as fecha_estado,
                    hef.pagado,
                    v.FechaInicio,
                    v.ValorFinalComercializacion,
                    v.CodigoCotizacion,
                    c.NombreCliente
                FROM facturas f
                INNER JOIN historial_estados_factura hef ON f.numero = hef.factura_numero
                INNER JOIN ventas v ON f.idComercializacion = v.idComercializacion
                INNER JOIN clientes c ON v.ClienteId = c.InsecapClienteId
                WHERE 
                    c.NombreCliente = ?
                    AND YEAR(v.FechaInicio) = ?
                    AND v.CodigoCotizacion NOT LIKE 'ADI%' 
                    AND v.CodigoCotizacion NOT LIKE 'OTR%' 
                    AND v.CodigoCotizacion NOT LIKE 'SPD%'
                    AND v.ValorFinalComercializacion > 0
                ORDER BY f.idComercializacion, hef.fecha ASC
            ", [$nombreCliente, $ano]);
            
            if (empty($historicoCliente)) {
                return response()->json([
                    'success' => false,
                    'mensaje' => "No se encontraron datos para el cliente: {$nombreCliente}"
                ], 404);
            }
            
            // Agrupar datos por comercialización
            $comercializacionesCliente = [];
            $pagosPendientes = [];
            
            foreach ($historicoCliente as $registro) {
                $idComercializacion = $registro->idComercializacion;
                
                if (!isset($comercializacionesCliente[$idComercializacion])) {
                    $comercializacionesCliente[$idComercializacion] = [
                        'comercializacion' => (object)[
                            'idComercializacion' => $registro->idComercializacion,
                            'CodigoCotizacion' => $registro->CodigoCotizacion,
                            'FechaInicio' => $registro->FechaInicio,
                            'ValorFinalComercializacion' => $registro->ValorFinalComercializacion
                        ],
                        'facturas_pagadas' => [],
                        'facturas_pendientes' => []
                    ];
                }
                
                if ($registro->estado_id == 3 && $registro->pagado > 0) {
                    // Facturas completamente pagadas
                    $comercializacionesCliente[$idComercializacion]['facturas_pagadas'][] = $registro;
                } elseif ($registro->estado_id == 4) {
                    // Facturas en proceso de pago (abonos)
                    $comercializacionesCliente[$idComercializacion]['facturas_pendientes'][] = $registro;
                }
            }
            
            // ANÁLISIS 1: Estadísticas históricas del cliente
            $estadisticasHistoricas = $this->calcularEstadisticasCliente($comercializacionesCliente);
            
            // ANÁLISIS 2: Identificar y analizar pagos pendientes
            $analisisPendientes = $this->analizarPagosPendientes($comercializacionesCliente);
            
            // ANÁLISIS 3: Predicciones basadas en comportamiento
            $predicciones = $this->generarPredicciones($estadisticasHistoricas, $analisisPendientes);
            
            return response()->json([
                'success' => true,
                'mensaje' => "Análisis de fiabilidad completado para {$nombreCliente}",
                'cliente' => $nombreCliente,
                'estadisticas_historicas' => $estadisticasHistoricas,
                'pagos_pendientes' => $analisisPendientes,
                'predicciones' => $predicciones,
                'recomendaciones' => $this->generarRecomendaciones($estadisticasHistoricas, $analisisPendientes)
            ]);
            
        } catch (\Exception $e) {
            Log::error('💥 ERROR EN ANÁLISIS DE FIABILIDAD CLIENTE', [
                'mensaje' => $e->getMessage(),
                'linea' => $e->getLine(),
                'archivo' => $e->getFile()
            ]);
            
            return response()->json([
                'success' => false,
                'mensaje' => 'Error al analizar fiabilidad del cliente',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Calcula estadísticas históricas similares al endpoint global pero para un cliente específico
     */
    private function calcularEstadisticasCliente($comercializacionesCliente)
    {
        $tiemposPago = [];
        $comercializacionesCompletadas = 0;
        $facturasAnticipadasCount = 0;
        
        foreach ($comercializacionesCliente as $data) {
            $comercializacion = $data['comercializacion'];
            $facturasPagadas = $data['facturas_pagadas'];
            
            if (empty($facturasPagadas)) {
                continue;
            }
            
            // Calcular fecha de pago completo para esta comercialización
            $fechaPagoCompleto = $this->calcularFechaPagoCompletoCliente(
                $facturasPagadas, 
                $comercializacion->ValorFinalComercializacion
            );
            
            if ($fechaPagoCompleto) {
                try {
                    $fechaInicio = Carbon::parse($comercializacion->FechaInicio);
                    $fechaCompleto = Carbon::parse($fechaPagoCompleto);
                    
                    $diasTranscurridos = $fechaInicio->diffInDays($fechaCompleto);
                    
                    if ($diasTranscurridos >= 0) {
                        $tiemposPago[] = $diasTranscurridos;
                        $comercializacionesCompletadas++;
                    } else {
                        $facturasAnticipadasCount++;
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
        }
        
        if (empty($tiemposPago)) {
            return [
                'comercializaciones_completadas' => 0,
                'mensaje' => 'No se encontraron comercializaciones completadas para este cliente'
            ];
        }
        
        // Calcular estadísticas (misma lógica que endpoint global)
        $promedioDias = round(array_sum($tiemposPago) / count($tiemposPago), 1);
        $medianaDias = $this->calcularMediana($tiemposPago);
        $minDias = min($tiemposPago);
        $maxDias = max($tiemposPago);
        $desviacionEstandar = $this->calcularDesviacionEstandar($tiemposPago, $promedioDias);
        
        $percentil25 = $this->calcularPercentil($tiemposPago, 25);
        $percentil75 = $this->calcularPercentil($tiemposPago, 75);
        $percentil90 = $this->calcularPercentil($tiemposPago, 90);
        
        return [
            'comercializaciones_completadas' => $comercializacionesCompletadas,
            'facturas_anticipadas' => $facturasAnticipadasCount,
            'promedio_dias' => $promedioDias,
            'mediana_dias' => $medianaDias,
            'desviacion_estandar_dias' => round($desviacionEstandar, 1),
            'tiempo_minimo_dias' => $minDias,
            'tiempo_maximo_dias' => $maxDias,
            'percentil_25_dias' => $percentil25,
            'percentil_75_dias' => $percentil75,
            'percentil_90_dias' => $percentil90,
            'interpretacion' => "Este cliente históricamente paga en promedio en {$promedioDias} días"
        ];
    }
    
    /**
     * Analiza comercializaciones con pagos pendientes (estado 4)
     */
    private function analizarPagosPendientes($comercializacionesCliente)
    {
        $pendientes = [];
        $totalPendiente = 0;
        
        foreach ($comercializacionesCliente as $data) {
            $comercializacion = $data['comercializacion'];
            $facturasPendientes = $data['facturas_pendientes'];
            
            if (empty($facturasPendientes)) {
                continue;
            }
            
            // Calcular cuánto ha pagado vs cuánto debe
            $totalPagado = 0;
            foreach ($facturasPendientes as $factura) {
                $totalPagado += floatval($factura->pagado);
            }
            
            $valorTotal = floatval($comercializacion->ValorFinalComercializacion);
            $pendientePorPagar = $valorTotal - $totalPagado;
            
            // Solo incluir si realmente está pendiente
            if ($pendientePorPagar > 100) { // Tolerancia de $100
                $diasTranscurridos = Carbon::parse($comercializacion->FechaInicio)->diffInDays(Carbon::now());
                
                $pendientes[] = [
                    'codigo_cotizacion' => $comercializacion->CodigoCotizacion,
                    'fecha_inicio' => $comercializacion->FechaInicio,
                    'valor_total' => $valorTotal,
                    'pagado' => $totalPagado,
                    'pendiente' => $pendientePorPagar,
                    'porcentaje_pagado' => round(($totalPagado / $valorTotal) * 100, 1),
                    'dias_transcurridos' => $diasTranscurridos
                ];
                
                $totalPendiente += $pendientePorPagar;
            }
        }
        
        return [
            'total_comercializaciones_pendientes' => count($pendientes),
            'valor_total_pendiente' => $totalPendiente,
            'detalle_pendientes' => $pendientes
        ];
    }
    
    /**
     * Genera predicciones basadas en el comportamiento histórico
     */
    private function generarPredicciones($estadisticas, $pendientes)
    {
        if (empty($estadisticas['promedio_dias']) || empty($pendientes['detalle_pendientes'])) {
            return [
                'predicciones_disponibles' => false,
                'mensaje' => 'El cliente no cuenta con facturas abonadas parcialmente, está al día en sus pagos',
                'explicacion' => 'Las predicciones se generan cuando hay facturas con pagos parciales (estadoFactura = 4). Este cliente mantiene sus facturas completamente pagadas o sin deuda pendiente',
                'estado_cliente' => 'Al día en pagos - Sin deudas pendientes'
            ];
        }
        
        $promedioDias = $estadisticas['promedio_dias'];
        $percentil75 = $estadisticas['percentil_75_dias'];
        $percentil90 = $estadisticas['percentil_90_dias'];
        
        $prediccionesPorVenta = [];
        
        foreach ($pendientes['detalle_pendientes'] as $pendiente) {
            $fechaInicio = Carbon::parse($pendiente['fecha_inicio']);
            $diasTranscurridos = $pendiente['dias_transcurridos'];
            
            // Predicción optimista (promedio)
            $fechaPredichaOptimista = $fechaInicio->copy()->addDays($promedioDias);
            
            // Predicción realista (percentil 75)
            $fechaPredichaRealista = $fechaInicio->copy()->addDays($percentil75);
            
            // Predicción conservadora (percentil 90)
            $fechaPredichaConservadora = $fechaInicio->copy()->addDays($percentil90);
            
            $estado = 'en_tiempo';
            if ($diasTranscurridos > $percentil90) {
                $estado = 'muy_retrasado';
            } elseif ($diasTranscurridos > $percentil75) {
                $estado = 'retrasado';
            } elseif ($diasTranscurridos > $promedioDias) {
                $estado = 'ligeramente_retrasado';
            }
            
            $prediccionesPorVenta[] = [
                'codigo_cotizacion' => $pendiente['codigo_cotizacion'],
                'valor_pendiente' => $pendiente['pendiente'],
                'estado_actual' => $estado,
                'prediccion_optimista' => $fechaPredichaOptimista->format('Y-m-d'),
                'prediccion_realista' => $fechaPredichaRealista->format('Y-m-d'),
                'prediccion_conservadora' => $fechaPredichaConservadora->format('Y-m-d'),
                'dias_hasta_prediccion_realista' => max(0, $percentil75 - $diasTranscurridos)
            ];
        }
        
        return [
            'predicciones_disponibles' => true,
            'base_historica' => "Predicciones basadas en {$estadisticas['comercializaciones_completadas']} comercializaciones completadas",
            'predicciones_por_venta' => $prediccionesPorVenta
        ];
    }
    
    /**
     * Genera recomendaciones de gestión comercial
     */
    private function generarRecomendaciones($estadisticas, $pendientes)
    {
        $recomendaciones = [];
        
        if (empty($estadisticas['promedio_dias'])) {
            return ['No hay suficientes datos históricos para generar recomendaciones'];
        }
        
        $promedio = $estadisticas['promedio_dias'];
        $percentil90 = $estadisticas['percentil_90_dias'];
        
        // Evaluación del comportamiento del cliente
        if ($promedio <= 30) {
            $recomendaciones[] = "✅ Cliente de EXCELENTE comportamiento de pago (promedio: {$promedio} días)";
            $recomendaciones[] = "💡 Se puede ofrecer condiciones preferenciales";
        } elseif ($promedio <= 60) {
            $recomendaciones[] = "✅ Cliente de BUEN comportamiento de pago (promedio: {$promedio} días)";
            $recomendaciones[] = "💡 Cliente confiable para operaciones estándar";
        } elseif ($promedio <= 90) {
            $recomendaciones[] = "⚠️ Cliente de comportamiento REGULAR (promedio: {$promedio} días)";
            $recomendaciones[] = "💡 Considerar seguimiento más frecuente";
        } else {
            $recomendaciones[] = "🚨 Cliente de comportamiento LENTO (promedio: {$promedio} días)";
            $recomendaciones[] = "💡 Requiere gestión especial y políticas más estrictas";
        }
        
        // Recomendaciones sobre pagos pendientes
        if (!empty($pendientes['detalle_pendientes'])) {
            $totalPendiente = $pendientes['valor_total_pendiente'];
            $cantidadPendientes = $pendientes['total_comercializaciones_pendientes'];
            
            $recomendaciones[] = "📊 Tiene {$cantidadPendientes} comercializaciones pendientes por $" . number_format($totalPendiente, 0);
            
            if ($percentil90 > 120) {
                $recomendaciones[] = "🚨 ALERTA: En el peor escenario, este cliente puede tardar hasta {$percentil90} días";
            }
        } else {
            // Cliente sin deudas pendientes
            if ($promedio > 90) {
                $recomendaciones[] = "✅ Aunque este cliente demora en pagar, no es deudor, por lo que es un cliente fiable";
                $recomendaciones[] = "💡 Se puede mantener relación comercial con plazos ajustados a su patrón de pago";
            } else {
                $recomendaciones[] = "✅ Cliente sin deudas pendientes y con buen comportamiento de pago";
                $recomendaciones[] = "💡 Cliente ideal para operaciones comerciales";
            }
        }
        
        return $recomendaciones;
    }
    
    /**
     * Métodos auxiliares (mismos que en PagoInicioVentaController)
     */
    private function calcularFechaPagoCompletoCliente($facturas, $valorFinalComercializacion)
    {
        $pagoAcumulado = 0;
        $valorObjetivo = floatval($valorFinalComercializacion);
        
        foreach ($facturas as $factura) {
            $pagoAcumulado += floatval($factura->pagado);
            
            if (abs($pagoAcumulado - $valorObjetivo) <= 100 || $pagoAcumulado >= $valorObjetivo) {
                return $factura->fecha_estado;
            }
        }
        
        return null;
    }
    
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
    
    private function calcularDesviacionEstandar($numbers, $promedio)
    {
        $sumaCuadrados = 0;
        foreach ($numbers as $number) {
            $sumaCuadrados += pow($number - $promedio, 2);
        }
        return sqrt($sumaCuadrados / count($numbers));
    }
    
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
}
