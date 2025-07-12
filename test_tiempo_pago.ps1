# SCRIPT DE PRUEBAS - TIEMPO FACTURACION -> PAGO
Write-Host "Iniciando pruebas Tiempo Facturacion -> Pago" -ForegroundColor Green

$baseUrl = "http://localhost:8000/api"
$headers = @{
    "Content-Type" = "application/json"
    "Accept" = "application/json"
}

# TEST 1: Tiempo promedio facturacion -> pago (todas las facturas)
Write-Host "`nTEST 1: Tiempo promedio facturacion -> pago (todas)" -ForegroundColor Yellow

$body1 = @{
    "año" = 2024
    "mes" = 10
    "tipo_factura" = "todas"
    "incluir_pendientes" = $false
} | ConvertTo-Json

try {
    $response1 = Invoke-RestMethod -Uri "$baseUrl/tiempo-pago/promedio" -Method POST -Body $body1 -Headers $headers
    
    if ($response1.success) {
        Write-Host "EXITO - Analisis tiempo pago completado" -ForegroundColor Green
        Write-Host "Facturas analizadas: $($response1.datos.resumen.facturas_analizadas)" -ForegroundColor White
        Write-Host "Facturas pagadas: $($response1.datos.resumen.facturas_pagadas)" -ForegroundColor White
        Write-Host "Facturas pendientes: $($response1.datos.resumen.facturas_pendientes)" -ForegroundColor White
        Write-Host "Porcentaje pagadas: $($response1.datos.resumen.porcentaje_pagadas)%" -ForegroundColor White
        Write-Host "Tiempo promedio pago: $($response1.datos.tiempo_promedio_pago) dias" -ForegroundColor Cyan
        
        if ($response1.datos.estadisticas) {
            Write-Host "`nESTADISTICAS:" -ForegroundColor White
            Write-Host "  Facturas SENCE pagadas: $($response1.datos.estadisticas.facturas_sence_pagadas)" -ForegroundColor White
            Write-Host "  Facturas Cliente pagadas: $($response1.datos.estadisticas.facturas_cliente_pagadas)" -ForegroundColor White
            Write-Host "  Facturas SENCE pendientes: $($response1.datos.estadisticas.facturas_sence_pendientes)" -ForegroundColor White
            Write-Host "  Facturas Cliente pendientes: $($response1.datos.estadisticas.facturas_cliente_pendientes)" -ForegroundColor White
            Write-Host "  Monto total pagado: $($response1.datos.estadisticas.monto_total_pagado)" -ForegroundColor Green
            Write-Host "  Monto total pendiente: $($response1.datos.estadisticas.monto_total_pendiente)" -ForegroundColor Red
        }
        
        # Mostrar casos extremos
        if ($response1.datos.casos_extremos) {
            Write-Host "`nCASOS EXTREMOS:" -ForegroundColor White
            if ($response1.datos.casos_extremos.pago_mas_rapido) {
                $rapido = $response1.datos.casos_extremos.pago_mas_rapido
                Write-Host "  Pago mas rapido: $($rapido.cliente) ($($rapido.numero_factura)) - $($rapido.dias_pago) dias" -ForegroundColor Green
            }
            if ($response1.datos.casos_extremos.pago_mas_lento) {
                $lento = $response1.datos.casos_extremos.pago_mas_lento
                Write-Host "  Pago mas lento: $($lento.cliente) ($($lento.numero_factura)) - $($lento.dias_pago) dias" -ForegroundColor Red
            }
        }
    } else {
        Write-Host "ERROR: $($response1.message)" -ForegroundColor Red
    }
} catch {
    Write-Host "ERROR EN CONEXION: $($_.Exception.Message)" -ForegroundColor Red
}

# TEST 2: Analisis de morosidad por cliente
Write-Host "`nTEST 2: Analisis de morosidad por cliente" -ForegroundColor Yellow

$body2 = @{
    "año" = 2024
    "tipo_factura" = "todas"
} | ConvertTo-Json

try {
    $response2 = Invoke-RestMethod -Uri "$baseUrl/tiempo-pago/morosidad" -Method POST -Body $body2 -Headers $headers
    
    if ($response2.success) {
        Write-Host "EXITO - Analisis de morosidad completado" -ForegroundColor Green
        Write-Host "Total clientes analizados: $($response2.datos.total_clientes_analizados)" -ForegroundColor White
        
        if ($response2.datos.clientes -and $response2.datos.clientes.Count -gt 0) {
            Write-Host "`nTOP 5 CLIENTES CON MAYOR MOROSIDAD:" -ForegroundColor Red
            for ($i = 0; $i -lt [Math]::Min(5, $response2.datos.clientes.Count); $i++) {
                $cliente = $response2.datos.clientes[$i]
                Write-Host "  $($i+1). $($cliente.cliente)" -ForegroundColor White
                Write-Host "     Facturas: $($cliente.facturas_pagadas)/$($cliente.facturas_totales) pagadas ($($cliente.porcentaje_pagadas)%)" -ForegroundColor White
                Write-Host "     Tiempo promedio pago: $($cliente.tiempo_promedio_pago_dias) dias" -ForegroundColor White
                Write-Host "     Dias promedio pendientes: $($cliente.dias_promedio_pendientes) dias" -ForegroundColor White
                Write-Host "     Clasificacion: $($cliente.clasificacion_morosidad)" -ForegroundColor White
                Write-Host "     Monto pendiente: $($cliente.monto_total_pendiente)" -ForegroundColor Red
                Write-Host ""
            }
            
            # Mostrar clientes con mejor comportamiento
            $clientesBuenos = $response2.datos.clientes | Where-Object { $_.clasificacion_morosidad -eq 'excelente' -or $_.clasificacion_morosidad -eq 'bueno' }
            if ($clientesBuenos.Count -gt 0) {
                Write-Host "CLIENTES CON EXCELENTE COMPORTAMIENTO DE PAGO:" -ForegroundColor Green
                $clientesBuenos | Select-Object -First 3 | ForEach-Object {
                    Write-Host "  - $($_.cliente): $($_.porcentaje_pagadas)% pagadas, $($_.tiempo_promedio_pago_dias) dias promedio" -ForegroundColor Green
                }
            }
        }
    } else {
        Write-Host "ERROR: $($response2.message)" -ForegroundColor Red
    }
} catch {
    Write-Host "ERROR EN CONEXION: $($_.Exception.Message)" -ForegroundColor Red
}

# TEST 3: Distribucion de tiempos de pago
Write-Host "`nTEST 3: Distribucion de tiempos de pago" -ForegroundColor Yellow

$body3 = @{
    "año" = 2024
    "tipo_factura" = "todas"
} | ConvertTo-Json

try {
    $response3 = Invoke-RestMethod -Uri "$baseUrl/tiempo-pago/distribucion" -Method POST -Body $body3 -Headers $headers
    
    if ($response3.success) {
        Write-Host "EXITO - Distribucion de tiempos de pago completada" -ForegroundColor Green
        Write-Host "Total facturas pagadas: $($response3.datos.total_facturas_pagadas)" -ForegroundColor White
        
        if ($response3.datos.estadisticas_generales) {
            $stats = $response3.datos.estadisticas_generales
            Write-Host "`nESTADISTICAS GENERALES:" -ForegroundColor White
            Write-Host "  Promedio: $($stats.promedio_dias_pago) dias" -ForegroundColor White
            Write-Host "  Mediana: $($stats.mediana_dias_pago) dias" -ForegroundColor White
            Write-Host "  Minimo: $($stats.minimo_dias_pago) dias" -ForegroundColor White
            Write-Host "  Maximo: $($stats.maximo_dias_pago) dias" -ForegroundColor White
        }
        
        if ($response3.datos.distribucion) {
            Write-Host "`nDISTRIBUCION POR RANGOS DE PAGO:" -ForegroundColor White
            foreach ($rango in $response3.datos.distribucion.PSObject.Properties) {
                $valor = $rango.Value
                if ($valor.count -gt 0) {
                    Write-Host "  $($valor.descripcion): $($valor.count) casos ($($valor.porcentaje)%)" -ForegroundColor White
                }
            }
        }
    } else {
        Write-Host "ERROR: $($response3.message)" -ForegroundColor Red
    }
} catch {
    Write-Host "ERROR EN CONEXION: $($_.Exception.Message)" -ForegroundColor Red
}

# TEST 4: Comparacion SENCE vs Cliente en tiempos de pago
Write-Host "`nTEST 4: Comparacion SENCE vs Cliente en tiempos de pago" -ForegroundColor Yellow

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
    Write-Host "  Analizando tiempos pago SENCE..." -ForegroundColor Gray
    $responseSence = Invoke-RestMethod -Uri "$baseUrl/tiempo-pago/promedio" -Method POST -Body $bodySence -Headers $headers
    
    Write-Host "  Analizando tiempos pago Cliente..." -ForegroundColor Gray
    $responseCliente = Invoke-RestMethod -Uri "$baseUrl/tiempo-pago/promedio" -Method POST -Body $bodyCliente -Headers $headers
    
    if ($responseSence.success -and $responseCliente.success) {
        Write-Host "COMPARACION TIEMPOS DE PAGO COMPLETADA" -ForegroundColor Green
        Write-Host "PAGOS SENCE:" -ForegroundColor Yellow
        Write-Host "  Facturas pagadas: $($responseSence.datos.resumen.facturas_pagadas)" -ForegroundColor White
        Write-Host "  Facturas pendientes: $($responseSence.datos.resumen.facturas_pendientes)" -ForegroundColor White
        Write-Host "  Tiempo promedio pago: $($responseSence.datos.tiempo_promedio_pago) dias" -ForegroundColor White
        Write-Host "  Monto pagado: $($responseSence.datos.estadisticas.monto_total_pagado)" -ForegroundColor Green
        Write-Host "  Monto pendiente: $($responseSence.datos.estadisticas.monto_total_pendiente)" -ForegroundColor Red
        
        Write-Host "PAGOS CLIENTE:" -ForegroundColor Yellow
        Write-Host "  Facturas pagadas: $($responseCliente.datos.resumen.facturas_pagadas)" -ForegroundColor White
        Write-Host "  Facturas pendientes: $($responseCliente.datos.resumen.facturas_pendientes)" -ForegroundColor White
        Write-Host "  Tiempo promedio pago: $($responseCliente.datos.tiempo_promedio_pago) dias" -ForegroundColor White
        Write-Host "  Monto pagado: $($responseCliente.datos.estadisticas.monto_total_pagado)" -ForegroundColor Green
        Write-Host "  Monto pendiente: $($responseCliente.datos.estadisticas.monto_total_pendiente)" -ForegroundColor Red
        
        # Analizar diferencia
        $diferenciaTiempo = $responseSence.datos.tiempo_promedio_pago - $responseCliente.datos.tiempo_promedio_pago
        if ($diferenciaTiempo -gt 0) {
            Write-Host "SENCE se demora $([Math]::Round($diferenciaTiempo, 2)) dias mas que los clientes en pagar" -ForegroundColor Cyan
        } elseif ($diferenciaTiempo -lt 0) {
            Write-Host "Los clientes se demoran $([Math]::Round([Math]::Abs($diferenciaTiempo), 2)) dias mas que SENCE en pagar" -ForegroundColor Cyan
        } else {
            Write-Host "Ambos tienen el mismo tiempo promedio de pago" -ForegroundColor Cyan
        }
    }
} catch {
    Write-Host "ERROR EN COMPARACION: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host "`nPruebas completadas - Analisis flujo de efectivo completo" -ForegroundColor Green
Write-Host "NOTA: Este analisis es clave para entender el flujo de efectivo y morosidad" -ForegroundColor Yellow
