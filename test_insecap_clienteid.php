<?php

require_once 'vendor/autoload.php';

use Illuminate\Http\Request;

// Configurar Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Datos de prueba que simula el JSON
$datosVentas = [
    [
        "idComercializacion" => 12345,
        "CodigoCotizacion" => "COT-001",
        "FechaInicio" => "2024-01-15",
        "ClienteId" => 334, // Este es el InsecapClienteId que debería almacenarse
        "NombreCliente" => "SUEZ Medioambiente Chile S.A",
        "CorreoCreador" => "admin@test.com",
        "ValorFinalComercializacion" => 150000.00,
        "ValorFinalCotizacion" => 140000.00,
        "NumeroEstados" => 3,
        "Estados" => [
            ["CodigoEstado" => "INICIO", "FechaEstado" => "2024-01-15"],
            ["CodigoEstado" => "PROCESO", "FechaEstado" => "2024-01-20"],
            ["CodigoEstado" => "FINALIZADO", "FechaEstado" => "2024-01-25"]
        ]
    ]
];

echo "🧪 PRUEBA: Verificar ClienteId con InsecapClienteId\n";
echo "================================================\n\n";

// 1. Verificar cliente existente
echo "1. Verificando cliente con InsecapClienteId = 334...\n";
$cliente = DB::table('clientes')->where('InsecapClienteId', 334)->first();
if ($cliente) {
    echo "✅ Cliente encontrado:\n";
    echo "   - ID nativo Laravel: {$cliente->id}\n";
    echo "   - InsecapClienteId: {$cliente->InsecapClienteId}\n";
    echo "   - Nombre: {$cliente->NombreCliente}\n\n";
} else {
    echo "❌ Cliente no encontrado. Creando cliente de prueba...\n";
    DB::table('clientes')->insert([
        'InsecapClienteId' => 334,
        'NombreCliente' => 'SUEZ Medioambiente Chile S.A',
        'created_at' => now(),
        'updated_at' => now()
    ]);
    echo "✅ Cliente creado con InsecapClienteId = 334\n\n";
}

// 2. Simular importación usando el endpoint
echo "2. Simulando importación de venta...\n";
$request = new Request();
$request->merge(['ventas' => $datosVentas]);

try {
    $controller = new App\Http\Controllers\ImportController();
    $response = $controller->importarVentasJson($request);
    
    echo "✅ Importación completada. Respuesta:\n";
    echo json_encode($response->getData(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    
} catch (Exception $e) {
    echo "❌ Error en importación: " . $e->getMessage() . "\n\n";
}

// 3. Verificar los datos en la base de datos
echo "3. Verificando venta creada en base de datos...\n";
$venta = DB::table('ventas')->where('idComercializacion', 12345)->first();
if ($venta) {
    echo "✅ Venta encontrada:\n";
    echo "   - idComercializacion: {$venta->idComercializacion}\n";
    echo "   - ClienteId (InsecapClienteId): {$venta->ClienteId}\n";
    echo "   - NombreCliente: {$venta->NombreCliente}\n\n";
    
    // 4. Probar relación Eloquent
    echo "4. Probando relación Eloquent Venta → Cliente...\n";
    $ventaModel = App\Models\Venta::where('idComercializacion', 12345)->first();
    if ($ventaModel && $ventaModel->cliente) {
        echo "✅ Relación funcionando correctamente:\n";
        echo "   - Venta ClienteId: {$ventaModel->ClienteId}\n";
        echo "   - Cliente relacionado: {$ventaModel->cliente->NombreCliente}\n";
        echo "   - Cliente InsecapClienteId: {$ventaModel->cliente->InsecapClienteId}\n";
    } else {
        echo "❌ Error en relación Eloquent\n";
    }
} else {
    echo "❌ Venta no encontrada en base de datos\n";
}

echo "\n🎉 Prueba completada!\n";
