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
            // Limpiar tabla de estadísticas para regenerar
            DatosEstadistica::truncate();
            
            $estadisticasGeneradas = 0;
            
            // Obtener todas las facturas con sus historiales
            $facturas = Factura::all();
            
            foreach ($facturas as $factura) {
                // Obtener el historial de estados de esta factura
                $historiales = HistorialEstadoFactura::where('factura_numero', $factura->numero)
                    ->orderBy('fecha')
                    ->get();
                
                if ($historiales->isEmpty()) {
                    continue; // No hay historial para esta factura
                }
                
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
                    continue; // No encontramos estado 1, saltamos esta factura
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
                
                // Crear registro de estadística
                DatosEstadistica::create([
                    'idComercializacion' => $idComercializacion,
                    'factura_numero' => $factura->numero,
                    'fecha_emision_factura' => $fechaEmision,
                    'fecha_pago_final' => $fechaPago,
                    'dias_para_pago' => $diasParaPago,
                    'meses_para_pago' => $mesesParaPago,
                    'factura_pagada' => $facturaPagada,
                    'monto_factura' => null, // Se puede calcular después si es necesario
                    'cliente_nombre' => $clienteNombre,
                ]);
                
                $estadisticasGeneradas++;
            }
            
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
     * Obtener resumen de estadísticas de pago
     */
    public function obtenerResumenEstadisticas()
    {
        try {
            $totalFacturas = DatosEstadistica::count();
            $facturasPagadas = DatosEstadistica::where('factura_pagada', true)->count();
            $facturasPendientes = $totalFacturas - $facturasPagadas;
            
            // Estadísticas de tiempo de pago (solo facturas pagadas)
            $tiemposPageados = DatosEstadistica::where('factura_pagada', true)
                ->whereNotNull('dias_para_pago')
                ->get();
            
            $promedioDias = $tiemposPageados->avg('dias_para_pago');
            $promedioMeses = $tiemposPageados->avg('meses_para_pago');
            $minimosDias = $tiemposPageados->min('dias_para_pago');
            $maximosDias = $tiemposPageados->max('dias_para_pago');
            
            return response()->json([
                'success' => true,
                'data' => [
                    'total_facturas' => $totalFacturas,
                    'facturas_pagadas' => $facturasPagadas,
                    'facturas_pendientes' => $facturasPendientes,
                    'porcentaje_pagadas' => $totalFacturas > 0 ? round(($facturasPagadas / $totalFacturas) * 100, 2) : 0,
                    'tiempo_promedio_pago' => [
                        'dias' => round($promedioDias, 2),
                        'meses' => round($promedioMeses, 2),
                    ],
                    'tiempo_minimo_pago_dias' => $minimosDias,
                    'tiempo_maximo_pago_dias' => $maximosDias,
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
}
