# ==================================================================================
# SCRIPT DE PRUEBAS - TIEMPO TERMINACIÓN → FACTURACIÓN
# ==================================================================================
# Prueba las funcionalidades del TiempoFacturacionController
# que analiza el tiempo desde que una venta está terminada (estado 1)
# hasta que se emite la primera factura usando datos del JSON

Write-Host "🧪 INICIANDO PRUEBAS TIEMPO TERMINACIÓN → FACTURACIÓN" -ForegroundColor Green
Write-Host "============================================================" -ForegroundColor Cyan

$baseUrl = "http://localhost:8000/api"
$headers = @{
    "Content-Type" = "application/json"
    "Accept" = "application/json"
}

# ==================================================================================
# TEST 1: TIEMPO PROMEDIO TERMINACIÓN → FACTURACIÓN (TODAS LAS FACTURAS)
# ==================================================================================
Write-Host "`n📊 TEST 1: Calculando tiempo promedio terminación → facturación (todas)" -ForegroundColor Yellow

$body1 = @{
    "año" = 2024
    "mes" = 10
    "tipo_factura" = "todas"
} | ConvertTo-Json

try {
    $response1 = Invoke-RestMethod -Uri "$baseUrl/tiempo-facturacion/promedio" -Method POST -Body $body1 -Headers $headers
    
    if ($response1.success) {
        Write-Host "✅ ÉXITO - Análisis completado" -ForegroundColor Green
        Write-Host "📈 Comercializaciones analizadas: $($response1.datos.resumen.comercializaciones_analizadas)" -ForegroundColor White
        Write-Host "💰 Con factura: $($response1.datos.resumen.comercializaciones_con_factura)" -ForegroundColor White
        Write-Host "📊 Porcentaje facturadas: $($response1.datos.resumen.porcentaje_facturadas)%" -ForegroundColor White
        Write-Host "⏱️ Tiempo promedio: $($response1.datos.tiempo_promedio) días" -ForegroundColor Cyan
        Write-Host "📅 Filtros: Año $($response1.datos.resumen.filtros_aplicados.año), Mes $($response1.datos.resumen.filtros_aplicados.mes)" -ForegroundColor Gray
        
        # Mostrar estadísticas adicionales
        if ($response1.datos.estadisticas) {
            Write-Host "📊 Estadísticas adicionales:" -ForegroundColor White
            Write-Host "   • Facturas SENCE: $($response1.datos.estadisticas.facturas_sence)" -ForegroundColor White
            Write-Host "   • Facturas Cliente: $($response1.datos.estadisticas.facturas_cliente)" -ForegroundColor White
            Write-Host "   • Sin estado 1: $($response1.datos.estadisticas.sin_estado_1)" -ForegroundColor White
            Write-Host "   • Sin facturas: $($response1.datos.estadisticas.sin_facturas)" -ForegroundColor White
        }
        
        # Mostrar casos extremos
        if ($response1.datos.casos_extremos) {
            Write-Host "🔍 Casos extremos:" -ForegroundColor White
            if ($response1.datos.casos_extremos.mas_rapido) {
                $rapido = $response1.datos.casos_extremos.mas_rapido
                Write-Host "   • Más rápido: $($rapido.cliente) ($($rapido.codigo_cotizacion)) - $($rapido.dias_diferencia) días" -ForegroundColor Green
            }
            if ($response1.datos.casos_extremos.mas_lento) {
                $lento = $response1.datos.casos_extremos.mas_lento
                Write-Host "   • Más lento: $($lento.cliente) ($($lento.codigo_cotizacion)) - $($lento.dias_diferencia) días" -ForegroundColor Red
            }
        }
    } else {
        Write-Host "❌ ERROR: $($response1.message)" -ForegroundColor Red
    }
} catch {
    Write-Host "❌ ERROR EN CONEXIÓN: $($_.Exception.Message)" -ForegroundColor Red
}

# ==================================================================================
# TEST 2: TIEMPO PROMEDIO SOLO FACTURAS CLIENTE
# ==================================================================================
Write-Host "`n💰 TEST 2: Tiempo promedio terminación → facturación (solo facturas cliente)" -ForegroundColor Yellow

$body2 = @{
    "año" = 2024
    "tipo_factura" = "cliente"
} | ConvertTo-Json

try {
    $response2 = Invoke-RestMethod -Uri "$baseUrl/tiempo-facturacion/promedio" -Method POST -Body $body2 -Headers $headers
    
    if ($response2.success) {
        Write-Host "✅ ÉXITO - Análisis facturas cliente completado" -ForegroundColor Green
        Write-Host "📈 Comercializaciones con factura cliente: $($response2.datos.resumen.comercializaciones_con_factura)" -ForegroundColor White
        Write-Host "⏱️ Tiempo promedio cliente: $($response2.datos.tiempo_promedio) días" -ForegroundColor Cyan
        
        # Mostrar distribución
        if ($response2.datos.distribucion_tiempos) {
            Write-Host "📊 Distribución facturas cliente:" -ForegroundColor White
            foreach ($rango in $response2.datos.distribucion_tiempos.PSObject.Properties) {
                $valor = $rango.Value
                if ($valor.count -gt 0) {
                    Write-Host "   • $($rango.Name): $($valor.count) casos ($($valor.porcentaje)%)" -ForegroundColor White
                }
            }
        }
    } else {
        Write-Host "❌ ERROR: $($response2.message)" -ForegroundColor Red
    }
} catch {
    Write-Host "❌ ERROR EN CONEXIÓN: $($_.Exception.Message)" -ForegroundColor Red
}

# ==================================================================================
# TEST 3: ANÁLISIS POR CLIENTE
# ==================================================================================
Write-Host "`n👥 TEST 3: Análisis de tiempos por cliente" -ForegroundColor Yellow

$body3 = @{
    "año" = 2024
    "tipo_factura" = "todas"
} | ConvertTo-Json

try {
    $response3 = Invoke-RestMethod -Uri "$baseUrl/tiempo-facturacion/por-cliente" -Method POST -Body $body3 -Headers $headers
    
    if ($response3.success) {
        Write-Host "✅ ÉXITO - Análisis por cliente completado" -ForegroundColor Green
        Write-Host "👥 Total clientes analizados: $($response3.datos.total_clientes_analizados)" -ForegroundColor White
        
        # Mostrar top 5 clientes más lentos
        if ($response3.datos.clientes -and $response3.datos.clientes.Count -gt 0) {
            Write-Host "🐌 TOP 5 CLIENTES MÁS LENTOS:" -ForegroundColor Red
            for ($i = 0; $i -lt [Math]::Min(5, $response3.datos.clientes.Count); $i++) {
                $cliente = $response3.datos.clientes[$i]
                Write-Host "   $($i+1). $($cliente.cliente): $($cliente.tiempo_promedio_dias) días promedio ($($cliente.comercializaciones) ventas)" -ForegroundColor White
                Write-Host "      Facturas SENCE: $($cliente.facturas_sence), Cliente: $($cliente.facturas_cliente)" -ForegroundColor Gray
            }
            
            # Mostrar top 5 clientes más rápidos
            Write-Host "🚀 TOP 5 CLIENTES MÁS RÁPIDOS:" -ForegroundColor Green
            $clientesRapidos = $response3.datos.clientes | Sort-Object tiempo_promedio_dias | Select-Object -First 5
            for ($i = 0; $i -lt $clientesRapidos.Count; $i++) {
                $cliente = $clientesRapidos[$i]
                Write-Host "   $($i+1). $($cliente.cliente): $($cliente.tiempo_promedio_dias) días promedio ($($cliente.comercializaciones) ventas)" -ForegroundColor White
                Write-Host "      Facturas SENCE: $($cliente.facturas_sence), Cliente: $($cliente.facturas_cliente)" -ForegroundColor Gray
            }
        }
    } else {
        Write-Host "❌ ERROR: $($response3.message)" -ForegroundColor Red
    }
} catch {
    Write-Host "❌ ERROR EN CONEXIÓN: $($_.Exception.Message)" -ForegroundColor Red
}

# ==================================================================================
# TEST 4: DISTRIBUCIÓN DE TIEMPOS
# ==================================================================================
Write-Host "`n📊 TEST 4: Distribución de tiempos de facturación" -ForegroundColor Yellow

$body4 = @{
    "año" = 2024
    "tipo_factura" = "todas"
} | ConvertTo-Json

try {
    $response4 = Invoke-RestMethod -Uri "$baseUrl/tiempo-facturacion/distribucion" -Method POST -Body $body4 -Headers $headers
    
    if ($response4.success) {
        Write-Host "✅ ÉXITO - Distribución completada" -ForegroundColor Green
        Write-Host "📈 Total comercializaciones: $($response4.datos.total_comercializaciones)" -ForegroundColor White
        
        # Mostrar estadísticas generales
        if ($response4.datos.estadisticas_generales) {
            $stats = $response4.datos.estadisticas_generales
            Write-Host "📊 Estadísticas generales:" -ForegroundColor White
            Write-Host "   • Promedio: $($stats.promedio_dias) días" -ForegroundColor White
            Write-Host "   • Mediana: $($stats.mediana_dias) días" -ForegroundColor White
            Write-Host "   • Mínimo: $($stats.minimo_dias) días" -ForegroundColor White
            Write-Host "   • Máximo: $($stats.maximo_dias) días" -ForegroundColor White
        }
        
        # Mostrar distribución por rangos
        if ($response4.datos.distribucion) {
            Write-Host "📊 DISTRIBUCIÓN POR RANGOS:" -ForegroundColor White
            foreach ($rango in $response4.datos.distribucion.PSObject.Properties) {
                $valor = $rango.Value
                if ($valor.count -gt 0) {
                    $rangoNombre = switch ($rango.Name) {
                        "mismo_dia" { "Mismo día (0)" }
                        "muy_rapido" { "Muy rápido (1-3 días)" }
                        "rapido" { "Rápido (4-7 días)" }
                        "normal" { "Normal (8-15 días)" }
                        "lento" { "Lento (16-30 días)" }
                        "muy_lento" { "Muy lento (31-60 días)" }
                        "extremo" { "Extremo (61+ días)" }
                        default { $rango.Name }
                    }
                    Write-Host "   • ${rangoNombre}: $($valor.count) casos ($($valor.porcentaje)%)" -ForegroundColor White
                    
                    # Mostrar ejemplos si existen
                    if ($valor.ejemplos -and $valor.ejemplos.Count -gt 0) {
                        Write-Host "     Ejemplos:" -ForegroundColor Gray
                        foreach ($ejemplo in $valor.ejemplos) {
                            Write-Host "       - $($ejemplo.cliente) ($($ejemplo.codigo_cotizacion)): $($ejemplo.dias) días" -ForegroundColor Gray
                        }
                    }
                }
            }
        }
    } else {
        Write-Host "❌ ERROR: $($response4.message)" -ForegroundColor Red
    }
} catch {
    Write-Host "❌ ERROR EN CONEXIÓN: $($_.Exception.Message)" -ForegroundColor Red
}

# ==================================================================================
# TEST 5: COMPARACIÓN FACTURAS SENCE vs CLIENTE
# ==================================================================================
Write-Host "`n🆚 TEST 5: Comparación SENCE vs Cliente" -ForegroundColor Yellow

# Test facturas SENCE
$bodySence = @{
    "año" = 2024
    "tipo_factura" = "sence"
} | ConvertTo-Json

try {
    Write-Host "   Analizando facturas SENCE..." -ForegroundColor Gray
    $responseSence = Invoke-RestMethod -Uri "$baseUrl/tiempo-facturacion/promedio" -Method POST -Body $bodySence -Headers $headers
    
    # Test facturas Cliente
    $bodyCliente = @{
        "año" = 2024
        "tipo_factura" = "cliente"
    } | ConvertTo-Json
    
    Write-Host "   Analizando facturas Cliente..." -ForegroundColor Gray
    $responseCliente = Invoke-RestMethod -Uri "$baseUrl/tiempo-facturacion/promedio" -Method POST -Body $bodyCliente -Headers $headers
    
    if ($responseSence.success -and $responseCliente.success) {
        Write-Host "✅ COMPARACIÓN COMPLETADA" -ForegroundColor Green
        Write-Host "💰 FACTURAS SENCE:" -ForegroundColor Yellow
        Write-Host "   • Comercializaciones: $($responseSence.datos.resumen.comercializaciones_con_factura)" -ForegroundColor White
        Write-Host "   • Tiempo promedio: $($responseSence.datos.tiempo_promedio) días" -ForegroundColor White
        
        Write-Host "👤 FACTURAS CLIENTE:" -ForegroundColor Yellow
        Write-Host "   • Comercializaciones: $($responseCliente.datos.resumen.comercializaciones_con_factura)" -ForegroundColor White
        Write-Host "   • Tiempo promedio: $($responseCliente.datos.tiempo_promedio) días" -ForegroundColor White
        
        # Calcular diferencia
        $diferencia = $responseCliente.datos.tiempo_promedio - $responseSence.datos.tiempo_promedio
        if ($diferencia -gt 0) {
            Write-Host "📊 Las facturas cliente tardan $diferencia días más que las SENCE" -ForegroundColor Cyan
        } else {
            Write-Host "📊 Las facturas SENCE tardan $([Math]::Abs($diferencia)) días más que las cliente" -ForegroundColor Cyan
        }
    }
} catch {
    Write-Host "❌ ERROR EN COMPARACIÓN: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host "`n🏁 PRUEBAS COMPLETADAS - TIEMPO TERMINACIÓN → FACTURACIÓN" -ForegroundColor Green
Write-Host "============================================================" -ForegroundColor Cyan
