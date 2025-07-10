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
        // Aumentar tiempo de ejecución para datasets grandes
        set_time_limit(300); // 5 minutos

        // Elimino la validación de expectsJson para permitir subida de archivos desde form-data
        $request->validate([
            'archivo' => 'required|file|mimes:json',
        ], [
            'archivo.required' => 'Debes seleccionar un archivo .json para subir.',
            'archivo.file' => 'El archivo no es válido.',
            'archivo.mimes' => 'El archivo debe ser un JSON (.json).',
        ]);

        if (!$request->hasFile('archivo') || !$request->file('archivo')->isValid()) {
            return response()->json([
                'success' => false,
                'message' => 'No se subió ningún archivo válido o el archivo está corrupto.',
            ], 400);
        }

        // Guarda una copia del archivo en storage/app/imports
        $rutaGuardada = $request->file('archivo')->store('imports');

        $json = file_get_contents($request->file('archivo')->getRealPath());
        if (empty($json)) {
            return response()->json([
                'success' => false,
                'message' => 'El archivo está vacío. Por favor, sube un archivo JSON con datos.',
            ], 400);
        }
        $ventas = json_decode($json, true);
        if (!is_array($ventas)) {
            return response()->json([
                'success' => false,
                'message' => 'El archivo no contiene un JSON válido. Asegúrate de que el archivo tenga el formato correcto.',
            ], 400);
        }

        // Procesar sin transacción global para mejorar performance
        try {
            $contador = 0;
            foreach ($ventas as $ventaData) {
                // Validación básica de campos requeridos
                if (empty($ventaData['idComercializacion']) || empty($ventaData['ClienteId']) || empty($ventaData['NombreCliente']) || empty($ventaData['CorreoCreador'])) {
                    continue; // Salta registros incompletos sin log costoso
                }
                // 1. Cliente
                $cliente = Cliente::firstOrCreate(
                    ['InsecapClienteId' => $ventaData['ClienteId']],
                    ['NombreCliente' => $ventaData['NombreCliente']]
                );

                // 2. Usuario
                $usuario = User::where('email', $ventaData['CorreoCreador'])->first();
                if (!$usuario) {
                    continue; // Salta ventas cuyo usuario no existe, sin log costoso
                }

                // Determinar el último estado de la venta (si existe)
                $ultimoEstadoVenta = null;
                if (!empty($ventaData['Estados']) && is_array($ventaData['Estados'])) {
                    $ultimo = end($ventaData['Estados']);
                    if (isset($ultimo['EstadoComercializacion'])) {
                        $ultimoEstadoVenta = $ultimo['EstadoComercializacion'];
                    }
                }

                // 3. Venta
                $venta = Venta::updateOrCreate(
                    ['idComercializacion' => $ventaData['idComercializacion']],
                    [
                        'CodigoCotizacion' => $ventaData['CodigoCotizacion'] ?? null,
                        'FechaInicio' => !empty($ventaData['FechaInicio']) ? Carbon::createFromFormat('d/m/Y', $ventaData['FechaInicio']) : null,
                        'ClienteId' => $cliente->id,
                        'NombreCliente' => $ventaData['NombreCliente'],
                        'CorreoCreador' => $usuario->email,
                        'ValorFinalComercializacion' => $ventaData['ValorFinalComercializacion'] ?? null,
                        'ValorFinalCotizacion' => $ventaData['ValorFinalCotizacion'] ?? null,
                        'NumeroEstados' => $ventaData['NumeroEstados'] ?? null,
                        'estado_venta_id' => $ultimoEstadoVenta, // Asigna el último estado
                    ]
                );

                // 4. Historial de estados de la venta
                if (!empty($ventaData['Estados']) && is_array($ventaData['Estados'])) {
                    foreach ($ventaData['Estados'] as $estado) {
                        if (empty($estado['EstadoComercializacion']) || empty($estado['Fecha'])) continue;
                        HistorialEstadoVenta::create([
                            'venta_id' => $venta->idComercializacion,
                            'estado_venta_id' => $estado['EstadoComercializacion'],
                            'fecha' => Carbon::createFromFormat('d/m/Y', $estado['Fecha']),
                            'numero_estado' => $ventaData['NumeroEstados'] ?? null,
                        ]);
                    }
                }

                // 5. Facturas y su historial
                if (!empty($ventaData['Facturas']) && is_array($ventaData['Facturas'])) {
                    foreach ($ventaData['Facturas'] as $facturaData) {
                        if (empty($facturaData['numero']) || empty($facturaData['FechaFacturacion'])) continue;
                        $factura = Factura::updateOrCreate(
                            ['numero' => $facturaData['numero']],
                            [
                                'FechaFacturacion' => Carbon::createFromFormat('d/m/Y', $facturaData['FechaFacturacion']),
                                'NumeroEstadosFactura' => $facturaData['NumeroEstadosFactura'] ?? null,
                            ]
                        );

                        // Historial de estados de la factura
                        if (!empty($facturaData['EstadosFactura']) && is_array($facturaData['EstadosFactura'])) {
                            foreach ($facturaData['EstadosFactura'] as $estadoFactura) {
                                if (empty($estadoFactura['estado']) || empty($estadoFactura['Fecha'])) continue;
                                HistorialEstadoFactura::create([
                                    'factura_numero' => $factura->numero,
                                    'estado_id' => $estadoFactura['estado'],
                                    'fecha' => Carbon::createFromFormat('d/m/Y', $estadoFactura['Fecha']),
                                    'pagado' => $estadoFactura['Pagado'] ?? null,
                                    'observacion' => $estadoFactura['Observacion'] ?? null,
                                    'usuario_email' => $estadoFactura['Usuario'] ?? null,
                                ]);
                            }
                        }
                    }
                }
                $contador++;
            }
            return response()->json([
                'success' => true,
                'message' => 'Subida de archivo exitosa, datos actualizados.',
                'archivo_guardado' => $rutaGuardada,
                'registros_importados' => $contador
            ]);
        } catch (\Exception $e) {
            Log::error('Error al procesar importación', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Error interno al procesar el archivo: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function importarUsuariosJson(Request $request)
    {
        $request->validate([
            'archivo' => 'required|file|mimes:json',
        ], [
            'archivo.required' => 'Debes seleccionar un archivo .json para subir.',
            'archivo.file' => 'El archivo no es válido.',
            'archivo.mimes' => 'El archivo debe ser un JSON (.json).',
        ]);

        if (!$request->hasFile('archivo') || !$request->file('archivo')->isValid()) {
            return response()->json([
                'success' => false,
                'message' => 'No se subió ningún archivo válido o el archivo está corrupto.',
            ], 400);
        }

        $json = file_get_contents($request->file('archivo')->getRealPath());
        if (empty($json)) {
            return response()->json([
                'success' => false,
                'message' => 'El archivo está vacío. Por favor, sube un archivo JSON con datos.',
            ], 400);
        }
        $ventas = json_decode($json, true);
        if (!is_array($ventas)) {
            return response()->json([
                'success' => false,
                'message' => 'El archivo no contiene un JSON válido. Asegúrate de que el archivo tenga el formato correcto.',
            ], 400);
        }

        $correos = collect();
        foreach ($ventas as $ventaData) {
            if (!empty($ventaData['CorreoCreador'])) {
                $correos->push($ventaData['CorreoCreador']);
            }
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
        $correos = $correos->unique();
        $creados = 0;
        foreach ($correos as $correo) {
            $nombre = explode('@', $correo)[0];
            $usuario = \App\Models\User::firstOrCreate(
                ['email' => $correo],
                ['name' => $nombre]
            );
            if ($usuario->wasRecentlyCreated) {
                $creados++;
                Log::info('Usuario creado', ['email' => $correo]);
            }
        }
        return response()->json([
            'success' => true,
            'message' => 'Usuarios procesados correctamente.',
            'usuarios_creados' => $creados,
            'usuarios_totales' => $correos->count(),
        ]);
    }
}
