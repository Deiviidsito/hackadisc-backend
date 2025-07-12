<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DebugController extends Controller
{
    public function testBasico(Request $request)
    {
        try {
            $result = DB::select("SELECT 1 as test");
            return response()->json([
                'success' => true,
                'mensaje' => 'Conexión DB funcionando',
                'resultado' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    public function testTablas(Request $request)
    {
        try {
            $ventas = DB::select("SELECT COUNT(*) as total FROM ventas LIMIT 1");
            $historial = DB::select("SELECT COUNT(*) as total FROM historial_estados_venta LIMIT 1");
            $estados = DB::select("SELECT id, nombre FROM estado_ventas ORDER BY id");
            
            return response()->json([
                'success' => true,
                'conteos' => [
                    'ventas' => $ventas[0]->total,
                    'historial_estados' => $historial[0]->total
                ],
                'estados_disponibles' => $estados
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    public function testJoin(Request $request)
    {
        try {
            // Probar consulta específica con estados 0 y 1
            $consulta = "
                SELECT 
                    v.idVenta,
                    v.CodigoCotizacion,
                    MIN(CASE WHEN hev.estado_venta_id = 0 THEN hev.fecha END) as fecha_estado_0,
                    MAX(CASE WHEN hev.estado_venta_id = 1 THEN hev.fecha END) as fecha_estado_1
                FROM ventas v
                LEFT JOIN historial_estados_venta hev ON v.idVenta = hev.venta_id
                WHERE hev.estado_venta_id IN (0, 1)
                GROUP BY v.idVenta, v.CodigoCotizacion
                HAVING fecha_estado_0 IS NOT NULL AND fecha_estado_1 IS NOT NULL
                LIMIT 5
            ";
            
            $resultado = DB::select($consulta);
            
            return response()->json([
                'success' => true,
                'resultado' => $resultado,
                'total_encontrado' => count($resultado)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
