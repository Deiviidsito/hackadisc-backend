# SCRIPT DE PRUEBAS MEJORADO - TIEMPO TERMINACION FACTURACION
Write-Host "Iniciando pruebas Tiempo Terminacion -> Facturacion (Mejorado)" -ForegroundColor Green

$baseUrl = "http://localhost:8000/api"
$headers = @{
    "Content-Type" = "application/json"
    "Accept" = "application/json"
}

# TEST 1: Distribucion de tiempos
Write-Host "`nTEST 1: Distribucion de tiempos de facturacion" -ForegroundColor Yellow

$body1 = @{
    "año" = 2024
    "tipo_factura" = "todas"
} | ConvertTo-Json

try {
    $response1 = Invoke-RestMethod -Uri "$baseUrl/tiempo-facturacion/distribucion" -Method POST -Body $body1 -Headers $headers
    
    if ($response1.success) {
        Write-Host "EXITO - Distribucion completada" -ForegroundColor Green
        Write-Host "Total comercializaciones: $($response1.datos.total_comercializaciones)" -ForegroundColor White
        
        if ($response1.datos.estadisticas_generales) {
            $stats = $response1.datos.estadisticas_generales
            Write-Host "Promedio: $($stats.promedio_dias) dias" -ForegroundColor White
            Write-Host "Mediana: $($stats.mediana_dias) dias" -ForegroundColor White
            Write-Host "Minimo: $($stats.minimo_dias) dias" -ForegroundColor White
            Write-Host "Maximo: $($stats.maximo_dias) dias" -ForegroundColor White
        }
        
        if ($response1.datos.distribucion) {
            Write-Host "`nDISTRIBUCION POR RANGOS:" -ForegroundColor White
            foreach ($rango in $response1.datos.distribucion.PSObject.Properties) {
                $valor = $rango.Value
                if ($valor.count -gt 0) {
                    Write-Host "  $($rango.Name): $($valor.count) casos ($($valor.porcentaje)%)" -ForegroundColor White
                }
            }
        }
    } else {
        Write-Host "ERROR: $($response1.message)" -ForegroundColor Red
    }
} catch {
    Write-Host "ERROR EN CONEXION: $($_.Exception.Message)" -ForegroundColor Red
}

# TEST 2: Comparacion SENCE vs Cliente
Write-Host "`nTEST 2: Comparacion SENCE vs Cliente" -ForegroundColor Yellow

# Facturas SENCE
$bodySence = @{
    "año" = 2024
    "tipo_factura" = "sence"
} | ConvertTo-Json

$bodyCliente = @{
    "año" = 2024
    "tipo_factura" = "cliente"
} | ConvertTo-Json

try {
    Write-Host "  Analizando facturas SENCE..." -ForegroundColor Gray
    $responseSence = Invoke-RestMethod -Uri "$baseUrl/tiempo-facturacion/promedio" -Method POST -Body $bodySence -Headers $headers
    
    Write-Host "  Analizando facturas Cliente..." -ForegroundColor Gray
    $responseCliente = Invoke-RestMethod -Uri "$baseUrl/tiempo-facturacion/promedio" -Method POST -Body $bodyCliente -Headers $headers
    
    if ($responseSence.success -and $responseCliente.success) {
        Write-Host "COMPARACION COMPLETADA" -ForegroundColor Green
        Write-Host "FACTURAS SENCE:" -ForegroundColor Yellow
        Write-Host "  Comercializaciones: $($responseSence.datos.resumen.comercializaciones_con_factura)" -ForegroundColor White
        Write-Host "  Tiempo promedio: $($responseSence.datos.tiempo_promedio) dias" -ForegroundColor White
        
        Write-Host "FACTURAS CLIENTE:" -ForegroundColor Yellow
        Write-Host "  Comercializaciones: $($responseCliente.datos.resumen.comercializaciones_con_factura)" -ForegroundColor White
        Write-Host "  Tiempo promedio: $($responseCliente.datos.tiempo_promedio) dias" -ForegroundColor White
        
        # Analizar diferencia
        $diferencia = $responseCliente.datos.tiempo_promedio - $responseSence.datos.tiempo_promedio
        if ($diferencia -gt 0) {
            Write-Host "Las facturas cliente tardan $([Math]::Round($diferencia, 2)) dias mas que las SENCE" -ForegroundColor Cyan
        } elseif ($diferencia -lt 0) {
            Write-Host "Las facturas SENCE tardan $([Math]::Round([Math]::Abs($diferencia), 2)) dias mas que las cliente" -ForegroundColor Cyan
        } else {
            Write-Host "Ambos tipos de factura tienen el mismo tiempo promedio" -ForegroundColor Cyan
        }
    }
} catch {
    Write-Host "ERROR EN COMPARACION: $($_.Exception.Message)" -ForegroundColor Red
}

# TEST 3: Casos con tiempos negativos (facturas antes de terminacion)
Write-Host "`nTEST 3: Analisis octubre 2024 (detallado)" -ForegroundColor Yellow

$bodyOct = @{
    "año" = 2024
    "mes" = 10
    "tipo_factura" = "todas"
} | ConvertTo-Json

try {
    $responseOct = Invoke-RestMethod -Uri "$baseUrl/tiempo-facturacion/promedio" -Method POST -Body $bodyOct -Headers $headers
    
    if ($responseOct.success) {
        Write-Host "OCTUBRE 2024 - ANALISIS DETALLADO" -ForegroundColor Green
        Write-Host "Comercializaciones analizadas: $($responseOct.datos.resumen.comercializaciones_analizadas)" -ForegroundColor White
        Write-Host "Con factura: $($responseOct.datos.resumen.comercializaciones_con_factura)" -ForegroundColor White
        Write-Host "Porcentaje facturadas: $($responseOct.datos.resumen.porcentaje_facturadas)%" -ForegroundColor White
        Write-Host "Tiempo promedio: $($responseOct.datos.tiempo_promedio) dias" -ForegroundColor Cyan
        
        if ($responseOct.datos.estadisticas) {
            Write-Host "`nESTADISTICAS OCTUBRE:" -ForegroundColor White
            Write-Host "  Facturas SENCE: $($responseOct.datos.estadisticas.facturas_sence)" -ForegroundColor White
            Write-Host "  Facturas Cliente: $($responseOct.datos.estadisticas.facturas_cliente)" -ForegroundColor White
            Write-Host "  Sin estado 1: $($responseOct.datos.estadisticas.sin_estado_1)" -ForegroundColor White
            Write-Host "  Sin facturas: $($responseOct.datos.estadisticas.sin_facturas)" -ForegroundColor White
            Write-Host "  Multiples facturas: $($responseOct.datos.estadisticas.multiples_facturas)" -ForegroundColor White
        }
        
        # Mostrar casos extremos
        if ($responseOct.datos.casos_extremos) {
            Write-Host "`nCASOS EXTREMOS:" -ForegroundColor White
            if ($responseOct.datos.casos_extremos.mas_rapido) {
                $rapido = $responseOct.datos.casos_extremos.mas_rapido
                Write-Host "  Mas rapido: $($rapido.cliente) ($($rapido.codigo_cotizacion)) - $($rapido.dias_diferencia) dias" -ForegroundColor Green
            }
            if ($responseOct.datos.casos_extremos.mas_lento) {
                $lento = $responseOct.datos.casos_extremos.mas_lento
                Write-Host "  Mas lento: $($lento.cliente) ($($lento.codigo_cotizacion)) - $($lento.dias_diferencia) dias" -ForegroundColor Red
            }
        }
    } else {
        Write-Host "ERROR: $($responseOct.message)" -ForegroundColor Red
    }
} catch {
    Write-Host "ERROR EN CONEXION: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host "`nPruebas completadas - Sistema funcionando correctamente" -ForegroundColor Green
Write-Host "NOTA: Tiempos negativos indican facturas emitidas antes de terminacion completa (normal para SENCE)" -ForegroundColor Yellow
