<?php

require_once 'bootstrap/app.php';

use App\Models\Venta;
use App\Models\Factura;
use App\Models\HistorialEstadoFactura;

echo "=== ANÁLISIS DE INCONGRUENCIAS SYNCORE ===\n\n";

// Obtener ventas de Syncore
$ventas = Venta::where('NombreCliente', 'Syncore Montajes Industriales')->get();
echo "Total ventas encontradas: " . $ventas->count() . "\n";
echo "Valor total ventas: " . $ventas->sum('ValorFinalComercializacion') . "\n\n";

// Mostrar sample de valores
echo "=== SAMPLE VALORES VENTAS ===\n";
$ventasSample = $ventas->take(10);
foreach ($ventasSample as $venta) {
    echo sprintf("Código: %s, Valor: %s, Estado: %d\n", 
        $venta->CodigoCotizacion, 
        number_format($venta->ValorFinalComercializacion), 
        $venta->estado_venta_id
    );
}

echo "\n=== ANÁLISIS DE FACTURAS ===\n";

// Obtener códigos de cotización
$codigosCotizacion = $ventas->pluck('CodigoCotizacion')->unique();
echo "Códigos de cotización únicos: " . $codigosCotizacion->count() . "\n";

// Buscar facturas correspondientes
$facturas = Factura::whereIn('numero', $codigosCotizacion)->get();
echo "Facturas encontradas: " . $facturas->count() . "\n";

if ($facturas->count() > 0) {
    echo "Valor total facturas: " . $facturas->sum('valor') . "\n";
    
    echo "\n=== SAMPLE FACTURAS ===\n";
    foreach ($facturas->take(5) as $factura) {
        echo sprintf("Factura: %s, Valor: %s\n", 
            $factura->numero, 
            number_format($factura->valor)
        );
    }
    
    // Verificar historiales
    $numeroFacturas = $facturas->pluck('numero');
    $historiales = HistorialEstadoFactura::whereIn('factura_numero', $numeroFacturas)->get();
    echo "\nHistoriales de facturas encontrados: " . $historiales->count() . "\n";
    
    $pagados = $historiales->where('estado_id', 3)->count();
    echo "Eventos de pago encontrados: " . $pagados . "\n";
    
} else {
    echo "¡NO SE ENCONTRARON FACTURAS!\n";
    echo "Revisando si hay facturas con otros criterios...\n";
    
    // Verificar si las facturas usan idComercializacion
    $idsComercializacion = $ventas->pluck('idComercializacion')->unique();
    echo "IDs de comercialización únicos: " . $idsComercializacion->count() . "\n";
    
    $historialesPorId = HistorialEstadoFactura::whereIn('idComercializacion', $idsComercializacion)->get();
    echo "Historiales por idComercializacion: " . $historialesPorId->count() . "\n";
    
    if ($historialesPorId->count() > 0) {
        $facturasDeHistoriales = $historialesPorId->pluck('factura_numero')->unique();
        echo "Números de facturas únicos en historiales: " . $facturasDeHistoriales->count() . "\n";
        
        $facturasReales = Factura::whereIn('numero', $facturasDeHistoriales)->get();
        echo "Facturas reales encontradas por historiales: " . $facturasReales->count() . "\n";
    }
}

echo "\n=== ANÁLISIS DE FECHAS ===\n";
$primeraVenta = $ventas->min('FechaInicio');
$ultimaVenta = $ventas->max('FechaInicio');
echo "Primera venta: " . $primeraVenta . "\n";
echo "Última venta: " . $ultimaVenta . "\n";

$diasSinActividad = \Carbon\Carbon::parse($ultimaVenta)->diffInDays(\Carbon\Carbon::now());
echo "Días sin actividad: " . $diasSinActividad . "\n";
echo "Estado actividad calculado: " . ($diasSinActividad <= 30 ? 'Activo' : ($diasSinActividad <= 90 ? 'Poco Activo' : 'Inactivo')) . "\n";

?>
