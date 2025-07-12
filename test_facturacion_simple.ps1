# SCRIPT DE PRUEBAS - TIEMPO TERMINACION FACTURACION
Write-Host "Iniciando pruebas Tiempo Terminacion -> Facturacion" -ForegroundColor Green

$baseUrl = "http://localhost:8000/api"
$headers = @{
    "Content-Type" = "application/json"
    "Accept" = "application/json"
}

# TEST 1: Tiempo promedio terminacion -> facturacion
Write-Host "`nTEST 1: Tiempo promedio terminacion -> facturacion" -ForegroundColor Yellow

$body1 = @{
    "año" = 2024
    "mes" = 10
    "tipo_factura" = "todas"
} | ConvertTo-Json

try {
    $response1 = Invoke-RestMethod -Uri "$baseUrl/tiempo-facturacion/promedio" -Method POST -Body $body1 -Headers $headers
    
    if ($response1.success) {
        Write-Host "EXITO - Analisis completado" -ForegroundColor Green
        Write-Host "Comercializaciones analizadas: $($response1.datos.resumen.comercializaciones_analizadas)" -ForegroundColor White
        Write-Host "Con factura: $($response1.datos.resumen.comercializaciones_con_factura)" -ForegroundColor White
        Write-Host "Tiempo promedio: $($response1.datos.tiempo_promedio) dias" -ForegroundColor Cyan
        
        if ($response1.datos.estadisticas) {
            Write-Host "Facturas SENCE: $($response1.datos.estadisticas.facturas_sence)" -ForegroundColor White
            Write-Host "Facturas Cliente: $($response1.datos.estadisticas.facturas_cliente)" -ForegroundColor White
        }
    } else {
        Write-Host "ERROR: $($response1.message)" -ForegroundColor Red
    }
} catch {
    Write-Host "ERROR EN CONEXION: $($_.Exception.Message)" -ForegroundColor Red
}

# TEST 2: Solo facturas cliente
Write-Host "`nTEST 2: Solo facturas cliente" -ForegroundColor Yellow

$body2 = @{
    "año" = 2024
    "tipo_factura" = "cliente"
} | ConvertTo-Json

try {
    $response2 = Invoke-RestMethod -Uri "$baseUrl/tiempo-facturacion/promedio" -Method POST -Body $body2 -Headers $headers
    
    if ($response2.success) {
        Write-Host "EXITO - Facturas cliente analizadas" -ForegroundColor Green
        Write-Host "Con factura cliente: $($response2.datos.resumen.comercializaciones_con_factura)" -ForegroundColor White
        Write-Host "Tiempo promedio cliente: $($response2.datos.tiempo_promedio) dias" -ForegroundColor Cyan
    } else {
        Write-Host "ERROR: $($response2.message)" -ForegroundColor Red
    }
} catch {
    Write-Host "ERROR EN CONEXION: $($_.Exception.Message)" -ForegroundColor Red
}

# TEST 3: Analisis por cliente
Write-Host "`nTEST 3: Analisis por cliente" -ForegroundColor Yellow

$body3 = @{
    "año" = 2024
    "tipo_factura" = "todas"
} | ConvertTo-Json

try {
    $response3 = Invoke-RestMethod -Uri "$baseUrl/tiempo-facturacion/por-cliente" -Method POST -Body $body3 -Headers $headers
    
    if ($response3.success) {
        Write-Host "EXITO - Analisis por cliente completado" -ForegroundColor Green
        Write-Host "Total clientes analizados: $($response3.datos.total_clientes_analizados)" -ForegroundColor White
        
        if ($response3.datos.clientes -and $response3.datos.clientes.Count -gt 0) {
            Write-Host "TOP 3 CLIENTES MAS LENTOS:" -ForegroundColor Red
            for ($i = 0; $i -lt [Math]::Min(3, $response3.datos.clientes.Count); $i++) {
                $cliente = $response3.datos.clientes[$i]
                Write-Host "  $($i+1). $($cliente.cliente): $($cliente.tiempo_promedio_dias) dias ($($cliente.comercializaciones) ventas)" -ForegroundColor White
            }
        }
    } else {
        Write-Host "ERROR: $($response3.message)" -ForegroundColor Red
    }
} catch {
    Write-Host "ERROR EN CONEXION: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host "`nPruebas completadas" -ForegroundColor Green
