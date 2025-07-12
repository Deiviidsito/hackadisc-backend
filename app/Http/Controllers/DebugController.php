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
    
    /**
     * ANÁLISIS COMPLETO DE ESTRUCTURA DE TABLAS Y RELACIONES
     */
    public function analizarEstructuraCompleta(Request $request)
    {
        try {
            $analisis = [];
            
            // 1. ESTRUCTURA DE CADA TABLA
            $tablas = ['ventas', 'facturas', 'historial_estados_venta', 'estado_ventas', 'clientes', 'datos_estadisticas'];
            
            foreach ($tablas as $tabla) {
                try {
                    // Obtener estructura de columnas
                    $columnas = DB::select("DESCRIBE {$tabla}");
                    
                    // Contar registros
                    $conteo = DB::select("SELECT COUNT(*) as total FROM {$tabla}");
                    
                    // Obtener algunos registros de ejemplo
                    $ejemplos = DB::select("SELECT * FROM {$tabla} LIMIT 3");
                    
                    $analisis['tablas'][$tabla] = [
                        'total_registros' => $conteo[0]->total,
                        'estructura' => $columnas,
                        'ejemplos' => $ejemplos
                    ];
                } catch (\Exception $e) {
                    $analisis['tablas'][$tabla] = [
                        'error' => "Tabla no existe o error: " . $e->getMessage()
                    ];
                }
            }
            
            // 2. ANÁLISIS DE RELACIONES
            $analisis['relaciones'] = [];
            
            // Relación ventas -> historial_estados_venta
            try {
                $ventasConEstados = DB::select("
                    SELECT 
                        v.idVenta,
                        v.CodigoCotizacion,
                        v.FechaInicio,
                        COUNT(hev.id) as total_cambios_estado,
                        GROUP_CONCAT(DISTINCT hev.estado_venta_id ORDER BY hev.fecha) as secuencia_estados,
                        MIN(hev.fecha) as primer_cambio,
                        MAX(hev.fecha) as ultimo_cambio
                    FROM ventas v
                    LEFT JOIN historial_estados_venta hev ON v.idVenta = hev.venta_id
                    GROUP BY v.idVenta, v.CodigoCotizacion, v.FechaInicio
                    ORDER BY v.idVenta
                    LIMIT 5
                ");
                
                $analisis['relaciones']['ventas_estados'] = $ventasConEstados;
            } catch (\Exception $e) {
                $analisis['relaciones']['ventas_estados'] = ['error' => $e->getMessage()];
            }
            
            // Relación ventas -> facturas
            try {
                $ventasConFacturas = DB::select("
                    SELECT 
                        v.idVenta,
                        v.CodigoCotizacion,
                        COUNT(f.id) as total_facturas,
                        GROUP_CONCAT(f.NumeroFactura) as numeros_facturas,
                        GROUP_CONCAT(f.TipoFactura) as tipos_facturas,
                        MIN(f.FechaFacturacion) as primera_factura,
                        MAX(f.FechaFacturacion) as ultima_factura,
                        SUM(f.MontoFactura) as monto_total_facturado
                    FROM ventas v
                    LEFT JOIN facturas f ON v.idVenta = f.venta_id
                    GROUP BY v.idVenta, v.CodigoCotizacion
                    ORDER BY v.idVenta
                    LIMIT 5
                ");
                
                $analisis['relaciones']['ventas_facturas'] = $ventasConFacturas;
            } catch (\Exception $e) {
                $analisis['relaciones']['ventas_facturas'] = ['error' => $e->getMessage()];
            }
            
            // 3. ANÁLISIS DE DISTRIBUCIÓN DE FECHAS
            try {
                $distribucionFechas = DB::select("
                    SELECT 
                        YEAR(STR_TO_DATE(FechaInicio, '%d/%m/%Y')) as año,
                        MONTH(STR_TO_DATE(FechaInicio, '%d/%m/%Y')) as mes,
                        COUNT(*) as total_ventas,
                        MIN(FechaInicio) as fecha_mas_antigua,
                        MAX(FechaInicio) as fecha_mas_reciente
                    FROM ventas 
                    WHERE FechaInicio IS NOT NULL
                    GROUP BY YEAR(STR_TO_DATE(FechaInicio, '%d/%m/%Y')), MONTH(STR_TO_DATE(FechaInicio, '%d/%m/%Y'))
                    ORDER BY año DESC, mes DESC
                    LIMIT 20
                ");
                
                $analisis['distribucion_fechas'] = $distribucionFechas;
            } catch (\Exception $e) {
                $analisis['distribucion_fechas'] = ['error' => $e->getMessage()];
            }
            
            // 4. ANÁLISIS DE ESTADOS DISPONIBLES Y SU USO
            try {
                $estadosUsage = DB::select("
                    SELECT 
                        ev.id,
                        ev.nombre,
                        COUNT(hev.id) as veces_usado,
                        COUNT(DISTINCT hev.venta_id) as ventas_diferentes
                    FROM estado_ventas ev
                    LEFT JOIN historial_estados_venta hev ON ev.id = hev.estado_venta_id
                    GROUP BY ev.id, ev.nombre
                    ORDER BY ev.id
                ");
                
                $analisis['uso_estados'] = $estadosUsage;
            } catch (\Exception $e) {
                $analisis['uso_estados'] = ['error' => $e->getMessage()];
            }
            
            // 5. ANÁLISIS DE TIPOS DE FACTURAS
            try {
                $tiposFacturas = DB::select("
                    SELECT 
                        TipoFactura,
                        COUNT(*) as cantidad,
                        SUM(MontoFactura) as monto_total,
                        AVG(MontoFactura) as monto_promedio,
                        MIN(FechaFacturacion) as fecha_mas_antigua,
                        MAX(FechaFacturacion) as fecha_mas_reciente
                    FROM facturas
                    GROUP BY TipoFactura
                    ORDER BY cantidad DESC
                ");
                
                $analisis['tipos_facturas'] = $tiposFacturas;
            } catch (\Exception $e) {
                $analisis['tipos_facturas'] = ['error' => $e->getMessage()];
            }
            
            // 6. VERIFICAR SI HAY DATOS JSON EN LAS TABLAS
            try {
                // Buscar campos que puedan contener JSON
                $camposJson = [];
                foreach (['ventas', 'facturas'] as $tabla) {
                    $estructura = DB::select("DESCRIBE {$tabla}");
                    foreach ($estructura as $campo) {
                        if (strpos(strtolower($campo->Type), 'json') !== false || 
                            strpos(strtolower($campo->Field), 'json') !== false ||
                            strpos(strtolower($campo->Field), 'datos') !== false) {
                            $camposJson[$tabla][] = $campo->Field;
                        }
                    }
                }
                
                $analisis['campos_json_detectados'] = $camposJson;
            } catch (\Exception $e) {
                $analisis['campos_json_detectados'] = ['error' => $e->getMessage()];
            }
            
            return response()->json([
                'success' => true,
                'mensaje' => 'Análisis completo de estructura de base de datos',
                'analisis' => $analisis
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error en análisis completo: ' . $e->getMessage()
            ], 500);
        }
    }
}
