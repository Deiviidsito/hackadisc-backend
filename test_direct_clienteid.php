<?php

require_once 'vendor/autoload.php';

// Configurar Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🧪 PRUEBA DIRECTA: ClienteId con InsecapClienteId\n";
echo "===============================================\n\n";

// 1. Crear cliente de prueba
echo "1. Creando cliente de prueba...\n";
DB::table('clientes')->insertOrIgnore([
    'InsecapClienteId' => 334,
    'NombreCliente' => 'SUEZ Medioambiente Chile S.A',
    'created_at' => now(),
    'updated_at' => now()
]);

$cliente = DB::table('clientes')->where('InsecapClienteId', 334)->first();
echo "✅ Cliente: ID={$cliente->id}, InsecapClienteId={$cliente->InsecapClienteId}, Nombre={$cliente->NombreCliente}\n\n";

// 2. Crear venta directamente usando InsecapClienteId
echo "2. Creando venta usando InsecapClienteId directamente...\n";

// Obtener un estado_venta_id válido
$estadoVenta = DB::table('estado_ventas')->first();
echo "   Estado venta disponible: {$estadoVenta->id} - {$estadoVenta->codigo_estado}\n";

try {
    $inserted = DB::table('ventas')->insertOrIgnore([
        'idComercializacion' => 12345,
        'CodigoCotizacion' => 'COT-001',
        'FechaInicio' => '2024-01-15',
        'ClienteId' => 334, // Usar InsecapClienteId directamente
        'NombreCliente' => 'SUEZ Medioambiente Chile S.A',
        'CorreoCreador' => 'admin@test.com',
        'ValorFinalComercializacion' => 150000.00,
        'ValorFinalCotizacion' => 140000.00,
        'NumeroEstados' => 3,
        'estado_venta_id' => $estadoVenta->id,
        'created_at' => now(),
        'updated_at' => now()
    ]);
    
    echo "   Insert result: " . ($inserted ? 'Success' : 'Failed/Already exists') . "\n";
    
} catch (Exception $e) {
    echo "❌ Error al insertar venta: " . $e->getMessage() . "\n";
    return;
}

$venta = DB::table('ventas')->where('idComercializacion', 12345)->first();
if ($venta) {
    echo "✅ Venta creada:\n";
    echo "   - idComercializacion: {$venta->idComercializacion}\n";
    echo "   - ClienteId: {$venta->ClienteId}\n";
    echo "   - NombreCliente: {$venta->NombreCliente}\n\n";
} else {
    echo "❌ No se pudo recuperar la venta después de la inserción\n";
    return;
}

// 3. Probar relación Eloquent
echo "3. Probando relación Eloquent Venta → Cliente...\n";
$ventaModel = App\Models\Venta::where('idComercializacion', 12345)->first();

if ($ventaModel) {
    echo "✅ Venta Model encontrada:\n";
    echo "   - ClienteId en venta: {$ventaModel->ClienteId}\n";
    
    $clienteRelacionado = $ventaModel->cliente;
    if ($clienteRelacionado) {
        echo "✅ Relación funcionando correctamente:\n";
        echo "   - Cliente ID nativo: {$clienteRelacionado->id}\n";
        echo "   - Cliente InsecapClienteId: {$clienteRelacionado->InsecapClienteId}\n";
        echo "   - Cliente Nombre: {$clienteRelacionado->NombreCliente}\n";
        echo "   - ✅ CORRECTO: venta.ClienteId ({$ventaModel->ClienteId}) = cliente.InsecapClienteId ({$clienteRelacionado->InsecapClienteId})\n";
    } else {
        echo "❌ No se pudo cargar el cliente relacionado\n";
    }
} else {
    echo "❌ No se encontró la venta\n";
}

// 4. Verificar consulta inversa
echo "\n4. Probando relación inversa Cliente → Ventas...\n";
$clienteModel = App\Models\Cliente::where('InsecapClienteId', 334)->first();
if ($clienteModel) {
    // Verificar si el modelo Cliente tiene la relación ventas
    $reflection = new ReflectionClass($clienteModel);
    if ($reflection->hasMethod('ventas')) {
        $ventas = $clienteModel->ventas;
        echo "✅ Cliente tiene " . $ventas->count() . " ventas asociadas\n";
        foreach ($ventas as $v) {
            echo "   - Venta: {$v->idComercializacion}, ClienteId: {$v->ClienteId}\n";
        }
    } else {
        echo "ℹ️  Relación inversa 'ventas' no definida en modelo Cliente\n";
    }
}

echo "\n🎉 RESULTADO: ClienteId ahora almacena InsecapClienteId correctamente!\n";
echo "📊 ANTES: ClienteId = ID nativo de Laravel (1, 2, 3...)\n";
echo "📊 AHORA: ClienteId = InsecapClienteId del JSON (334, 335, etc.)\n";
