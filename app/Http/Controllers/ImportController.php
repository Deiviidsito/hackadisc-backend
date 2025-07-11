<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Cliente;
use App\Models\User;
use App\Models\Venta;
use App\Models\Factura;
use App\Models\EstadoVenta;
use App\Models\EstadoFactura;
use App\Models\HistorialEstadoVenta;
use App\Models\HistorialEstadoFactura;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ImportController extends Controller
{
    public function importarVentasJson(Request $request)
    {
        // Configuración optimizada para datasets grandes
        set_time_limit(600); // 10 minutos
        ini_set('memory_limit', '2G'); // 2GB de memoria
        ini_set('upload_max_filesize', '100M');
        ini_set('post_max_size', '1000M');
        ini_set('max_file_uploads', '20');
        
        // Validación para múltiples archivos (ajustada para límites de servidor)
        $request->validate([
            'archivos' => 'required|array|min:1|max:5', // Reducido a 5 archivos
            'archivos.*' => 'required|file|mimes:json|max:20480', // Reducido a 20MB por archivo
        ], [
            'archivos.required' => 'Debes seleccionar al menos un archivo .json para subir.',
            'archivos.array' => 'El formato de archivos no es válido.',
            'archivos.max' => 'Máximo 5 archivos permitidos por carga.',
            'archivos.*.file' => 'Uno de los archivos no es válido.',
            'archivos.*.mimes' => 'Todos los archivos deben ser JSON (.json).',
            'archivos.*.max' => 'Cada archivo no puede superar los 20MB.',
        ]);

        try {
            $totalContador = 0;
            $archivosGuardados = [];
            $todasLasVentas = [];
            
            // Leer todos los archivos primero
            foreach ($request->file('archivos') as $archivo) {
                if (!$archivo->isValid()) {
                    continue;
                }
                
                $rutaGuardada = $archivo->store('imports');
                $archivosGuardados[] = $rutaGuardada;
                
                $json = file_get_contents($archivo->getRealPath());
                if (empty($json)) {
                    continue;
                }
                
                $ventas = json_decode($json, true);
                if (!is_array($ventas)) {
                    continue;
                }
                
                $todasLasVentas = array_merge($todasLasVentas, $ventas);
            }

            if (empty($todasLasVentas)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontraron datos válidos en los archivos subidos.',
                ], 400);
            }

            // Procesamiento optimizado en lotes
            $chunkSize = 100; // Procesar en lotes de 100
            $chunks = array_chunk($todasLasVentas, $chunkSize);
            
            foreach ($chunks as $chunk) {
                $totalContador += $this->procesarLoteVentas($chunk);
            }

            return response()->json([
                'success' => true,
                'message' => 'Importación masiva exitosa.',
                'archivos_procesados' => count($archivosGuardados),
                'archivos_guardados' => $archivosGuardados,
                'registros_importados' => $totalContador
            ]);

        } catch (\Exception $e) {
            Log::error('Error al procesar importación masiva', [
                'error' => $e->getMessage(), 
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error interno al procesar los archivos: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Procesar un lote de ventas de forma optimizada
     */
    private function procesarLoteVentas($ventas)
    {
        // Precargar datos para evitar consultas N+1
        $clientesIds = collect($ventas)->pluck('ClienteId')->unique()->filter();
        $correosCreadores = collect($ventas)->pluck('CorreoCreador')->unique()->filter();
        
        // Cargar clientes existentes en memoria
        $clientesExistentes = Cliente::whereIn('InsecapClienteId', $clientesIds)->get()->keyBy('InsecapClienteId');
        
        // Cargar usuarios existentes en memoria
        $usuariosExistentes = User::whereIn('email', $correosCreadores)->get()->keyBy('email');
        
        // Arrays para bulk inserts
        $clientesNuevos = [];
        $ventasUpsert = [];
        $historialesVenta = [];
        $facturasUpsert = [];
        $historialesFactura = [];
        
        $contador = 0;
        $now = now();

        foreach ($ventas as $ventaData) {
            // Validación básica
            if (empty($ventaData['idComercializacion']) || 
                empty($ventaData['ClienteId']) || 
                empty($ventaData['NombreCliente']) || 
                empty($ventaData['CorreoCreador'])) {
                continue;
            }

            // Verificar que el usuario existe
            if (!$usuariosExistentes->has($ventaData['CorreoCreador'])) {
                continue;
            }

            // Preparar cliente si no existe
            $clienteId = null;
            if ($clientesExistentes->has($ventaData['ClienteId'])) {
                $clienteId = $clientesExistentes->get($ventaData['ClienteId'])->id;
            } else {
                // Marcar para creación masiva
                $clientesNuevos[$ventaData['ClienteId']] = [
                    'InsecapClienteId' => $ventaData['ClienteId'],
                    'NombreCliente' => $ventaData['NombreCliente'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // Determinar último estado
            $ultimoEstadoVenta = null;
            if (!empty($ventaData['Estados']) && is_array($ventaData['Estados'])) {
                $ultimo = end($ventaData['Estados']);
                if (isset($ultimo['EstadoComercializacion'])) {
                    $ultimoEstadoVenta = $ultimo['EstadoComercializacion'];
                }
            }

            // Preparar venta para upsert
            $ventasUpsert[] = [
                'idComercializacion' => $ventaData['idComercializacion'],
                'CodigoCotizacion' => $ventaData['CodigoCotizacion'] ?? null,
                'FechaInicio' => !empty($ventaData['FechaInicio']) ? 
                    Carbon::createFromFormat('d/m/Y', $ventaData['FechaInicio'])->format('Y-m-d') : null,
                'ClienteId' => $clienteId, // Se actualizará después si es nuevo
                'NombreCliente' => $ventaData['NombreCliente'],
                'CorreoCreador' => $ventaData['CorreoCreador'],
                'ValorFinalComercializacion' => $ventaData['ValorFinalComercializacion'] ?? null,
                'ValorFinalCotizacion' => $ventaData['ValorFinalCotizacion'] ?? null,
                'NumeroEstados' => $ventaData['NumeroEstados'] ?? null,
                'estado_venta_id' => $ultimoEstadoVenta,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            // Preparar historiales de venta
            if (!empty($ventaData['Estados']) && is_array($ventaData['Estados'])) {
                foreach ($ventaData['Estados'] as $estado) {
                    if (empty($estado['EstadoComercializacion']) || empty($estado['Fecha'])) continue;
                    
                    $historialesVenta[] = [
                        'venta_id' => null, // Se actualizará después
                        'idComercializacion' => $ventaData['idComercializacion'],
                        'estado_venta_id' => $estado['EstadoComercializacion'],
                        'fecha' => Carbon::createFromFormat('d/m/Y', $estado['Fecha'])->format('Y-m-d'),
                        'numero_estado' => $ventaData['NumeroEstados'] ?? null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            // Preparar facturas y sus historiales
            if (!empty($ventaData['Facturas']) && is_array($ventaData['Facturas'])) {
                foreach ($ventaData['Facturas'] as $facturaData) {
                    if (empty($facturaData['numero']) || empty($facturaData['FechaFacturacion'])) continue;
                    
                    $facturasUpsert[] = [
                        'numero' => $facturaData['numero'],
                        'FechaFacturacion' => Carbon::createFromFormat('d/m/Y', $facturaData['FechaFacturacion'])->format('Y-m-d'),
                        'NumeroEstadosFactura' => $facturaData['NumeroEstadosFactura'] ?? null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    // Preparar historiales de factura
                    if (!empty($facturaData['EstadosFactura']) && is_array($facturaData['EstadosFactura'])) {
                        foreach ($facturaData['EstadosFactura'] as $estadoFactura) {
                            if (empty($estadoFactura['estado']) || empty($estadoFactura['Fecha'])) continue;
                            
                            $historialesFactura[] = [
                                'factura_numero' => $facturaData['numero'],
                                'idComercializacion' => $ventaData['idComercializacion'],
                                'estado_id' => $estadoFactura['estado'],
                                'fecha' => Carbon::createFromFormat('d/m/Y', $estadoFactura['Fecha'])->format('Y-m-d'),
                                'pagado' => $estadoFactura['Pagado'] ?? null,
                                'observacion' => $estadoFactura['Observacion'] ?? null,
                                'usuario_email' => $estadoFactura['Usuario'] ?? null,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ];
                        }
                    }
                }
            }

            $contador++;
        }

        // Ejecutar bulk operations
        $this->ejecutarBulkOperations($clientesNuevos, $ventasUpsert, $historialesVenta, $facturasUpsert, $historialesFactura);
        
        return $contador;
    }

    /**
     * Ejecutar operaciones masivas en base de datos
     */
    private function ejecutarBulkOperations($clientesNuevos, $ventasUpsert, $historialesVenta, $facturasUpsert, $historialesFactura)
    {
        DB::transaction(function () use ($clientesNuevos, $ventasUpsert, $historialesVenta, $facturasUpsert, $historialesFactura) {
            // 1. Insertar clientes nuevos
            if (!empty($clientesNuevos)) {
                Cliente::insert(array_values($clientesNuevos));
                
                // Recargar clientes para obtener IDs
                $clientesActualizados = Cliente::whereIn('InsecapClienteId', array_keys($clientesNuevos))
                    ->get()->keyBy('InsecapClienteId');
                
                // Actualizar ClienteId en ventas donde es null
                foreach ($ventasUpsert as &$venta) {
                    if ($venta['ClienteId'] === null) {
                        // Buscar el cliente por InsecapClienteId usando los datos originales
                        foreach ($clientesNuevos as $insecapId => $clienteData) {
                            if ($clienteData['NombreCliente'] === $venta['NombreCliente']) {
                                if ($clientesActualizados->has($insecapId)) {
                                    $venta['ClienteId'] = $clientesActualizados->get($insecapId)->id;
                                    break;
                                }
                            }
                        }
                    }
                }
            }

            // 2. Upsert ventas (filtrar registros con ClienteId null)
            if (!empty($ventasUpsert)) {
                // Filtrar solo ventas que tienen ClienteId válido
                $ventasFiltradas = array_filter($ventasUpsert, function($venta) {
                    return $venta['ClienteId'] !== null;
                });
                
                if (!empty($ventasFiltradas)) {
                    Venta::upsert($ventasFiltradas, ['idComercializacion'], [
                        'CodigoCotizacion', 'FechaInicio', 'ClienteId', 'NombreCliente', 
                        'CorreoCreador', 'ValorFinalComercializacion', 'ValorFinalCotizacion', 
                        'NumeroEstados', 'estado_venta_id', 'updated_at'
                    ]);
                }
                
                // Log de registros descartados por ClienteId null
                $descartados = count($ventasUpsert) - count($ventasFiltradas);
                if ($descartados > 0) {
                    Log::warning("Se descartaron {$descartados} registros por ClienteId null");
                }
            }

            // 3. Upsert facturas
            if (!empty($facturasUpsert)) {
                Factura::upsert($facturasUpsert, ['numero'], [
                    'FechaFacturacion', 'NumeroEstadosFactura', 'updated_at'
                ]);
            }

            // 4. Limpiar e insertar historiales de ventas (solo para ventas que existen)
            if (!empty($historialesVenta)) {
                $idsComercializacion = collect($historialesVenta)->pluck('idComercializacion')->unique();
                HistorialEstadoVenta::whereIn('idComercializacion', $idsComercializacion)->delete();
                
                // Obtener IDs de ventas para historiales (solo ventas que realmente existen)
                $ventasConIds = Venta::whereIn('idComercializacion', $idsComercializacion)
                    ->get()->keyBy('idComercializacion');
                
                // Filtrar historiales solo para ventas que existen y actualizar venta_id
                $historialesFiltrados = [];
                foreach ($historialesVenta as $historial) {
                    if ($ventasConIds->has($historial['idComercializacion'])) {
                        $historial['venta_id'] = $ventasConIds->get($historial['idComercializacion'])->idVenta;
                        $historialesFiltrados[] = $historial;
                    }
                }
                
                // Solo insertar si hay historiales válidos
                if (!empty($historialesFiltrados)) {
                    HistorialEstadoVenta::insert($historialesFiltrados);
                }
                
                // Log de historiales descartados
                $descartados = count($historialesVenta) - count($historialesFiltrados);
                if ($descartados > 0) {
                    Log::warning("Se descartaron {$descartados} historiales de venta por venta_id null");
                }
            }

            // 5. Limpiar e insertar historiales de facturas (solo para facturas que existen)
            if (!empty($historialesFactura)) {
                $idsComercializacion = collect($historialesFactura)->pluck('idComercializacion')->unique();
                $numerosFactura = collect($historialesFactura)->pluck('factura_numero')->unique();
                
                HistorialEstadoFactura::whereIn('idComercializacion', $idsComercializacion)->delete();
                
                // Verificar que las facturas existen
                $facturasExistentes = Factura::whereIn('numero', $numerosFactura)
                    ->pluck('numero')
                    ->toArray();
                
                // Filtrar solo historiales de facturas que existen
                $historialesFacturaFiltrados = array_filter($historialesFactura, function($historial) use ($facturasExistentes) {
                    return in_array($historial['factura_numero'], $facturasExistentes);
                });
                
                // Solo insertar si hay historiales válidos
                if (!empty($historialesFacturaFiltrados)) {
                    HistorialEstadoFactura::insert($historialesFacturaFiltrados);
                }
                
                // Log de historiales de facturas descartados
                $descartados = count($historialesFactura) - count($historialesFacturaFiltrados);
                if ($descartados > 0) {
                    Log::warning("Se descartaron {$descartados} historiales de factura por factura inexistente");
                }
            }
        });
    }

    public function importarUsuariosJson(Request $request)
    {
        // Configuración optimizada
        set_time_limit(300); // 5 minutos
        ini_set('memory_limit', '1G');
        ini_set('upload_max_filesize', '100M');
        ini_set('post_max_size', '1000M');
        ini_set('max_file_uploads', '20');
        
        // Validación para múltiples archivos (ajustada para límites de servidor)
        $request->validate([
            'archivos' => 'required|array|min:1|max:5', // Reducido a 5 archivos
            'archivos.*' => 'required|file|mimes:json|max:20480', // Reducido a 20MB por archivo
        ], [
            'archivos.required' => 'Debes seleccionar al menos un archivo .json para subir.',
            'archivos.array' => 'El formato de archivos no es válido.',
            'archivos.max' => 'Máximo 5 archivos permitidos por carga.',
            'archivos.*.file' => 'Uno de los archivos no es válido.',
            'archivos.*.mimes' => 'Todos los archivos deben ser JSON (.json).',
            'archivos.*.max' => 'Cada archivo no puede superar los 20MB.',
        ]);

        try {
            $todosLosCorreos = collect();
            $archivosGuardados = [];

            // Leer todos los archivos y extraer correos
            foreach ($request->file('archivos') as $archivo) {
                if (!$archivo->isValid()) {
                    continue;
                }

                $rutaGuardada = $archivo->store('imports');
                $archivosGuardados[] = $rutaGuardada;

                $json = file_get_contents($archivo->getRealPath());
                if (empty($json)) {
                    continue;
                }

                $ventas = json_decode($json, true);
                if (!is_array($ventas)) {
                    continue;
                }

                // Extraer correos de forma optimizada
                $correosPorArchivo = $this->extraerCorreosOptimizado($ventas);
                $todosLosCorreos = $todosLosCorreos->merge($correosPorArchivo);
            }

            if ($todosLosCorreos->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontraron correos válidos en los archivos.',
                ], 400);
            }

            // Eliminar duplicados y filtrar correos válidos
            $correosUnicos = $todosLosCorreos->unique()->filter(function ($correo) {
                return filter_var($correo, FILTER_VALIDATE_EMAIL);
            });

            if ($correosUnicos->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontraron correos válidos en los archivos.',
                ], 400);
            }

            // Obtener usuarios existentes de una sola vez
            $usuariosExistentes = User::whereIn('email', $correosUnicos->toArray())
                ->pluck('email')
                ->toArray();

            // Filtrar correos que no existen
            $correosNuevos = $correosUnicos->diff($usuariosExistentes);

            if ($correosNuevos->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Todos los usuarios ya existían en el sistema.',
                    'archivos_procesados' => count($archivosGuardados),
                    'usuarios_creados' => 0,
                    'usuarios_totales' => $correosUnicos->count(),
                ]);
            }

            // Preparar datos para bulk insert
            $now = now();
            $usuariosParaInsertar = $correosNuevos->map(function ($correo) use ($now) {
                $nombre = explode('@', $correo)[0];
                return [
                    'email' => $correo,
                    'name' => $nombre,
                    'password' => bcrypt('password123'), // Password temporal
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })->toArray();

            // Insertar usuarios en lotes para evitar problemas de memoria
            $chunkSize = 500;
            $totalCreados = 0;
            
            collect($usuariosParaInsertar)->chunk($chunkSize)->each(function ($chunk) use (&$totalCreados) {
                User::insert($chunk->toArray());
                $totalCreados += $chunk->count();
                
                // Log de progreso para lotes grandes
                if ($totalCreados % 1000 === 0) {
                    Log::info("Usuarios creados: {$totalCreados}");
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Usuarios procesados correctamente.',
                'archivos_procesados' => count($archivosGuardados),
                'archivos_guardados' => $archivosGuardados,
                'usuarios_creados' => $totalCreados,
                'usuarios_existentes' => count($usuariosExistentes),
                'usuarios_totales' => $correosUnicos->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Error al procesar importación de usuarios', [
                'error' => $e->getMessage(), 
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error interno al procesar los archivos: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Extraer correos de forma optimizada desde array de ventas
     */
    private function extraerCorreosOptimizado($ventas)
    {
        $correos = collect();
        
        foreach ($ventas as $ventaData) {
            // Correo del creador
            if (!empty($ventaData['CorreoCreador'])) {
                $correos->push($ventaData['CorreoCreador']);
            }
            
            // Correos de estados de facturas
            if (!empty($ventaData['Facturas']) && is_array($ventaData['Facturas'])) {
                foreach ($ventaData['Facturas'] as $facturaData) {
                    if (!empty($facturaData['EstadosFactura']) && is_array($facturaData['EstadosFactura'])) {
                        foreach ($facturaData['EstadosFactura'] as $estadoFactura) {
                            if (!empty($estadoFactura['Usuario'])) {
                                $correos->push($estadoFactura['Usuario']);
                            }
                        }
                    }
                }
            }
        }
        
        return $correos;
    }

    public function obtenerVentaPorIdComercializacion(Request $request)
    {
        $request->validate([
            'idComercializacion' => 'required|integer',
        ]);

        $idComercializacion = $request->input('idComercializacion');

        // Buscar la venta por idComercializacion
        $venta = Venta::where('idComercializacion', $idComercializacion)->first();
        
        if (!$venta) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró ninguna venta con el ID de comercialización: ' . $idComercializacion,
            ], 404);
        }

        // Obtener el cliente
        $cliente = Cliente::find($venta->ClienteId);

        // Obtener los estados del historial de ventas
        $estadosVenta = HistorialEstadoVenta::where('idComercializacion', $idComercializacion)
            ->orderBy('fecha')
            ->get();

        // Obtener las facturas relacionadas con esta venta
        $facturas = Factura::whereIn('numero', function($query) use ($idComercializacion) {
            $query->select('factura_numero')
                  ->from('historial_estados_factura')
                  ->where('idComercializacion', $idComercializacion)
                  ->distinct();
        })->get();

        // Construir el array de respuesta en el formato original del JSON
        $ventaResponse = [
            'idComercializacion' => $venta->idComercializacion,
            'CodigoCotizacion' => $venta->CodigoCotizacion,
            'FechaInicio' => $venta->FechaInicio ? (is_string($venta->FechaInicio) ? $venta->FechaInicio : $venta->FechaInicio->format('d/m/Y')) : null,
            'ClienteId' => $cliente ? $cliente->InsecapClienteId : $venta->ClienteId,
            'NombreCliente' => $venta->NombreCliente,
            'CorreoCreador' => $venta->CorreoCreador,
            'ValorFinalComercializacion' => $venta->ValorFinalComercializacion,
            'ValorFinalCotizacion' => $venta->ValorFinalCotizacion,
            'NumeroEstados' => $venta->NumeroEstados,
            'Estados' => [],
            'Facturas' => []
        ];

        // Agregar los estados de la venta
        foreach ($estadosVenta as $estado) {
            $ventaResponse['Estados'][] = [
                'EstadoComercializacion' => $estado->estado_venta_id,
                'Fecha' => is_string($estado->fecha) ? $estado->fecha : $estado->fecha->format('d/m/Y')
            ];
        }

        // Agregar las facturas y sus estados
        foreach ($facturas as $factura) {
            $estadosFactura = HistorialEstadoFactura::where('factura_numero', $factura->numero)
                ->where('idComercializacion', $idComercializacion)
                ->orderBy('fecha')
                ->get();

            $facturaData = [
                'numero' => $factura->numero,
                'FechaFacturacion' => is_string($factura->FechaFacturacion) ? $factura->FechaFacturacion : $factura->FechaFacturacion->format('d/m/Y'),
                'NumeroEstadosFactura' => $factura->NumeroEstadosFactura,
                'EstadosFactura' => []
            ];

            foreach ($estadosFactura as $estadoFactura) {
                $facturaData['EstadosFactura'][] = [
                    'estado' => $estadoFactura->estado_id,
                    'Fecha' => is_string($estadoFactura->fecha) ? $estadoFactura->fecha : $estadoFactura->fecha->format('d/m/Y'),
                    'Pagado' => $estadoFactura->pagado,
                    'Observacion' => $estadoFactura->observacion,
                    'Usuario' => $estadoFactura->usuario_email
                ];
            }

            $ventaResponse['Facturas'][] = $facturaData;
        }

        return response()->json([
            'success' => true,
            'data' => [$ventaResponse] // Array con un elemento para mantener el formato original
        ]);
    }
}
