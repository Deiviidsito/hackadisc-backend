<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Venta;
use App\Models\Factura;
use App\Models\HistorialEstadoVenta;
use App\Models\HistorialEstadoFactura;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class FrontEndController extends Controller
{
    /**
     * Calcular tiempo promedio desde proceso hasta pago completo para un cliente
     * 
     * Calcula el tiempo promedio desde que una venta está "en proceso" (estado 0)
     * hasta que todas las facturas están pagadas (estado 3) y el valor total
     * de las facturas pagadas es igual al ValorTotalComercialización
     * 
     * @param string $nombreCliente
     * @return \Illuminate\Http\JsonResponse
     */
    public function tiempoPromedioProcesoAPago($nombreCliente)
    {
        try {
            // Decodificar nombre del cliente si viene por URL
            $nombreCliente = urldecode($nombreCliente);
            
            // Validar que el cliente existe
            $ventas = Venta::where('NombreCliente', $nombreCliente)->get();
            
            if ($ventas->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Cliente no encontrado'
                ], 404);
            }
            
            $tiemposCalculados = [];
            
            foreach ($ventas as $venta) {
                $tiempoVenta = $this->calcularTiempoVentaIndividual($venta);
                
                if ($tiempoVenta !== null) {
                    $tiemposCalculados[] = $tiempoVenta;
                }
            }
            
            // Calcular promedio
            $promedioTiempo = 0;
            if (count($tiemposCalculados) > 0) {
                $promedioTiempo = round(array_sum($tiemposCalculados) / count($tiemposCalculados), 2);
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'cliente_nombre' => $nombreCliente,
                    'promedio_dias_proceso_a_pago' => $promedioTiempo
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error en tiempo promedio proceso a pago: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error al calcular tiempo promedio'
            ], 500);
        }
    }
    
    /**
     * Calcular tiempo para una venta individual desde proceso hasta pago completo
     * 
     * @param Venta $venta
     * @return float|null Días transcurridos o null si no se puede calcular
     */
    private function calcularTiempoVentaIndividual($venta)
    {
        try {
            // 1. Buscar fecha cuando la venta entró en estado 0 (en proceso)
            $fechaInicioProceso = $this->obtenerFechaEstadoVenta($venta, 0);
            
            if (!$fechaInicioProceso) {
                // Si no hay historial de estado 0, usar la fecha de inicio de la venta
                $fechaInicioProceso = Carbon::parse($venta->FechaInicio);
            }
            
            // 2. Buscar todas las facturas relacionadas con esta venta
            $facturas = $this->obtenerFacturasVenta($venta);
            
            if ($facturas->isEmpty()) {
                // No hay facturas, no se puede calcular el tiempo completo
                return null;
            }
            
            // 3. Verificar si todas las facturas están pagadas y suman el valor total
            $estadoPagoCompleto = $this->verificarPagoCompleto($facturas, $venta->ValorTotalComercializacion);
            
            if (!$estadoPagoCompleto['completo']) {
                // El pago no está completo, no se puede calcular
                return null;
            }
            
            // 4. Calcular días transcurridos
            $fechaPagoCompleto = $estadoPagoCompleto['fecha_pago_final'];
            $diasTranscurridos = $fechaInicioProceso->diffInDays($fechaPagoCompleto);
            
            return $diasTranscurridos;
            
        } catch (\Exception $e) {
            Log::warning('Error al calcular tiempo venta individual: ' . $e->getMessage(), [
                'venta_id' => $venta->idComercializacion
            ]);
            return null;
        }
    }
    
    /**
     * Calcular tiempo para una venta individual con información de debug
     * 
     * @param Venta $venta
     * @return array
     */
    private function calcularTiempoVentaIndividualConDebug($venta)
    {
        try {
            // 1. Buscar fecha cuando la venta entró en estado 0 (en proceso)
            $fechaInicioProceso = $this->obtenerFechaEstadoVenta($venta, 0);
            
            if (!$fechaInicioProceso) {
                // Si no hay historial de estado 0, usar la fecha de inicio de la venta
                $fechaInicioProceso = Carbon::parse($venta->FechaInicio);
                $usoFechaAlternativa = true;
            } else {
                $usoFechaAlternativa = false;
            }
            
            // 2. Buscar todas las facturas relacionadas con esta venta
            $facturas = $this->obtenerFacturasVenta($venta);
            
            if ($facturas->isEmpty()) {
                return [
                    'tiempo' => null,
                    'motivo' => 'Sin facturas asociadas'
                ];
            }
            
            // 3. Verificar si todas las facturas están pagadas y suman el valor total
            $estadoPagoCompleto = $this->verificarPagoCompleto($facturas, $venta->ValorTotalComercializacion);
            
            if (!$estadoPagoCompleto['completo']) {
                $motivo = '';
                if (!$estadoPagoCompleto['todas_pagadas']) {
                    $motivo .= 'No todas las facturas están pagadas. ';
                }
                if (!$estadoPagoCompleto['valor_correcto']) {
                    $motivo .= sprintf('Valor pagado (%.2f) != Valor esperado (%.2f). ', 
                        $estadoPagoCompleto['valor_pagado'], 
                        $estadoPagoCompleto['valor_esperado']);
                }
                
                return [
                    'tiempo' => null,
                    'motivo' => trim($motivo)
                ];
            }
            
            // 4. Calcular días transcurridos
            $fechaPagoCompleto = $estadoPagoCompleto['fecha_pago_final'];
            $diasTranscurridos = $fechaInicioProceso->diffInDays($fechaPagoCompleto);
            
            return [
                'tiempo' => $diasTranscurridos,
                'motivo' => 'Calculado exitosamente',
                'detalles' => [
                    'fecha_inicio' => $fechaInicioProceso->format('Y-m-d'),
                    'fecha_pago_final' => $fechaPagoCompleto->format('Y-m-d'),
                    'uso_fecha_alternativa' => $usoFechaAlternativa,
                    'num_facturas' => $facturas->count(),
                    'valor_pagado' => $estadoPagoCompleto['valor_pagado'],
                    'valor_esperado' => $estadoPagoCompleto['valor_esperado']
                ]
            ];
            
        } catch (\Exception $e) {
            return [
                'tiempo' => null,
                'motivo' => 'Error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtener fecha cuando la venta tuvo un estado específico
     * 
     * @param Venta $venta
     * @param int $estadoId
     * @return Carbon|null
     */
    private function obtenerFechaEstadoVenta($venta, $estadoId)
    {
        $historial = HistorialEstadoVenta::where('venta_id', $venta->idComercializacion)
            ->where('estado_venta_id', $estadoId)
            ->orderBy('fecha', 'asc')
            ->first();
        
        return $historial ? Carbon::parse($historial->fecha) : null;
    }
    
    /**
     * Obtener facturas relacionadas con una venta
     * 
     * @param Venta $venta
     * @return \Illuminate\Database\Eloquent\Collection
     */
    private function obtenerFacturasVenta($venta)
    {
        // Método 1: Buscar por CodigoCotizacion
        $facturasPorCodigo = Factura::where('numero', $venta->CodigoCotizacion)->get();
        
        // Método 2: Buscar por idComercializacion en historiales
        $historialesPorId = HistorialEstadoFactura::where('idComercializacion', $venta->idComercializacion)->get();
        $numeroFacturasPorId = $historialesPorId->pluck('factura_numero')->unique();
        $facturasPorId = Factura::whereIn('numero', $numeroFacturasPorId)->get();
        
        // Combinar y eliminar duplicados
        return $facturasPorCodigo->merge($facturasPorId)->unique('numero');
    }
    
    /**
     * Verificar si el pago está completo (todas facturas pagadas y suma correcta)
     * 
     * @param \Illuminate\Database\Eloquent\Collection $facturas
     * @param float $valorTotalEsperado
     * @return array
     */
    private function verificarPagoCompleto($facturas, $valorTotalEsperado)
    {
        $valorTotalPagado = 0;
        $fechaPagoFinal = null;
        $todasPagadas = true;
        
        foreach ($facturas as $factura) {
            // Verificar si esta factura está pagada (estado 3)
            $historialPago = HistorialEstadoFactura::where('factura_numero', $factura->numero)
                ->where('estado_id', 3)
                ->orderBy('fecha', 'desc')
                ->first();
            
            if ($historialPago) {
                // Factura está pagada
                $valorTotalPagado += floatval($factura->valor);
                
                // Actualizar fecha de pago final (la más reciente)
                $fechaPago = Carbon::parse($historialPago->fecha);
                if (!$fechaPagoFinal || $fechaPago > $fechaPagoFinal) {
                    $fechaPagoFinal = $fechaPago;
                }
            } else {
                // Factura no está pagada
                $todasPagadas = false;
            }
        }
        
        // Verificar si el valor pagado es igual al valor total esperado (con tolerancia de 1 peso)
        $valorIgual = abs($valorTotalPagado - $valorTotalEsperado) <= 1;
        
        return [
            'completo' => $todasPagadas && $valorIgual && $fechaPagoFinal,
            'fecha_pago_final' => $fechaPagoFinal,
            'valor_pagado' => $valorTotalPagado,
            'valor_esperado' => $valorTotalEsperado,
            'todas_pagadas' => $todasPagadas,
            'valor_correcto' => $valorIgual
        ];
    }
    
    /**
     * Obtener lista simple de todos los clientes disponibles
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function obtenerListaClientes()
    {
        try {
            $clientes = Venta::select('NombreCliente')
                ->distinct()
                ->orderBy('NombreCliente', 'asc')
                ->pluck('NombreCliente')
                ->values()
                ->toArray();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'clientes' => $clientes,
                    'total' => count($clientes)
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error al obtener lista de clientes: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error al obtener lista de clientes'
            ], 500);
        }
    }
}
