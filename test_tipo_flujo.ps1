# SCRIPT DE PRUEBAS - ANÁLISIS TIPOS DE FLUJO COMERCIALIZACIÓN
Write-Host "Iniciando pruebas Análisis Tipos de Flujo" -ForegroundColor Green

$baseUrl = "http://localhost:8000/api"
$headers = @{
    "Content-Type" = "application/json"
    "Accept" = "application/json"
}

# TEST 1: Análisis comparativo tipos de flujo (completo vs simple)
Write-Host "`nTEST 1: Análisis comparativo tipos de flujo" -ForegroundColor Yellow

$body1 = @{
    "año" = 2024
    "mes" = 10
} | ConvertTo-Json

try {
    $response1 = Invoke-RestMethod -Uri "$baseUrl/tipo-flujo/analizar" -Method POST -Body $body1 -Headers $headers
    
    if ($response1.success) {
        Write-Host "EXITO - Análisis tipos de flujo completado" -ForegroundColor Green
        
        $resumen = $response1.datos.resumen
        Write-Host "Comercializaciones analizadas: $($resumen.comercializaciones_analizadas)" -ForegroundColor White
        Write-Host "Flujo Completo (con SENCE): $($resumen.flujo_completo_count) ($($resumen.porcentaje_completo) %)" -ForegroundColor Cyan
        Write-Host "Flujo Simple (sin SENCE): $($resumen.flujo_simple_count) ($($resumen.porcentaje_simple) %)" -ForegroundColor Cyan
        
        # Mostrar estadísticas del flujo completo
        if ($response1.datos.flujo_completo) {
            $completo = $response1.datos.flujo_completo
            Write-Host "`nFLUJO COMPLETO (0→3→1 - CON SENCE):" -ForegroundColor Yellow
            Write-Host "  Tiempo promedio total: $($completo.tiempo_promedio_total) días" -ForegroundColor White
            Write-Host "  Tiempo promedio SENCE: $($completo.tiempo_promedio_sence) días" -ForegroundColor White
            Write-Host "  Tiempo promedio Cliente: $($completo.tiempo_promedio_cliente) días" -ForegroundColor White
            Write-Host "  Valor promedio: $($completo.valor_promedio)" -ForegroundColor Green
            Write-Host "  Facturas promedio: $($completo.facturas_promedio)" -ForegroundColor White
            Write-Host "  Clientes únicos: $($completo.clientes_unicos)" -ForegroundColor White
        }
        
        # Mostrar estadísticas del flujo simple
        if ($response1.datos.flujo_simple) {
            $simple = $response1.datos.flujo_simple
            Write-Host "`nFLUJO SIMPLE (0→1 - SIN SENCE):" -ForegroundColor Yellow
            Write-Host "  Tiempo promedio total: $($simple.tiempo_promedio_total) días" -ForegroundColor White
            Write-Host "  Valor promedio: $($simple.valor_promedio)" -ForegroundColor Green
            Write-Host "  Facturas promedio: $($simple.facturas_promedio)" -ForegroundColor White
            Write-Host "  Clientes únicos: $($simple.clientes_unicos)" -ForegroundColor White
        }
        
        # Mostrar comparativa
        if ($response1.datos.comparativa) {
            $comp = $response1.datos.comparativa
            Write-Host "`nCOMPARATIVA:" -ForegroundColor Yellow
            if ($comp.diferencia_tiempo_total) {
                if ($comp.diferencia_tiempo_total -gt 0) {
                    Write-Host "  El flujo completo se demora $($comp.diferencia_tiempo_total) días más que el simple" -ForegroundColor Red
                } else {
                    Write-Host "  El flujo simple se demora $([Math]::Abs($comp.diferencia_tiempo_total)) días más que el completo" -ForegroundColor Red
                }
            }
            if ($comp.diferencia_valor_promedio) {
                if ($comp.diferencia_valor_promedio -gt 0) {
                    Write-Host "  El flujo completo genera $($comp.diferencia_valor_promedio) más en valor promedio" -ForegroundColor Green
                } else {
                    Write-Host "  El flujo simple genera $([Math]::Abs($comp.diferencia_valor_promedio)) más en valor promedio" -ForegroundColor Green
                }
            }
        }
        
        # Mostrar análisis de eficiencia
        if ($response1.datos.analisis_eficiencia) {
            $eficiencia = $response1.datos.analisis_eficiencia
            Write-Host "`nANÁLISIS DE EFICIENCIA:" -ForegroundColor Yellow
            Write-Host "  Flujo más rápido: $($eficiencia.flujo_mas_rapido)" -ForegroundColor Cyan
            Write-Host "  Flujo mayor valor: $($eficiencia.flujo_mayor_valor)" -ForegroundColor Green
        }
        
    } else {
        Write-Host "ERROR: $($response1.message)" -ForegroundColor Red
    }
} catch {
    Write-Host "ERROR EN CONEXIÓN: $($_.Exception.Message)" -ForegroundColor Red
}

# TEST 2: Análisis preferencias clientes por tipo de flujo
Write-Host "`nTEST 2: Análisis preferencias clientes por tipo de flujo" -ForegroundColor Yellow

$body2 = @{
    "año" = 2024
} | ConvertTo-Json

try {
    $response2 = Invoke-RestMethod -Uri "$baseUrl/tipo-flujo/preferencias" -Method POST -Body $body2 -Headers $headers
    
    if ($response2.success) {
        Write-Host "EXITO - Análisis preferencias clientes completado" -ForegroundColor Green
        
        Write-Host "Total clientes analizados: $($response2.datos.total_clientes_analizados)" -ForegroundColor White
        
        if ($response2.datos.estadisticas_preferencias) {
            $stats = $response2.datos.estadisticas_preferencias
            Write-Host "`nESTADÍSTICAS DE PREFERENCIAS:" -ForegroundColor White
            Write-Host "  Prefieren SENCE (fuerte): $($stats.completo_fuerte) clientes" -ForegroundColor Green
            Write-Host "  Prefieren SENCE (leve): $($stats.completo_leve) clientes" -ForegroundColor Green
            Write-Host "  Prefieren sin SENCE (fuerte): $($stats.simple_fuerte) clientes" -ForegroundColor Yellow
            Write-Host "  Prefieren sin SENCE (leve): $($stats.simple_leve) clientes" -ForegroundColor Yellow
            Write-Host "  Comportamiento mixto: $($stats.mixto) clientes" -ForegroundColor Cyan
        }
        
        if ($response2.datos.resumen_preferencias) {
            $resumen_pref = $response2.datos.resumen_preferencias
            Write-Host "`nRESUMEN PREFERENCIAS:" -ForegroundColor White
            Write-Host "  Total prefieren SENCE: $($resumen_pref.prefieren_sence) clientes" -ForegroundColor Green
            Write-Host "  Total prefieren SIN SENCE: $($resumen_pref.prefieren_sin_sence) clientes" -ForegroundColor Yellow
            Write-Host "  Comportamiento mixto: $($resumen_pref.comportamiento_mixto) clientes" -ForegroundColor Cyan
        }
        
        # Mostrar top 5 clientes más activos
        if ($response2.datos.clientes -and $response2.datos.clientes.Count -gt 0) {
            Write-Host "`nTOP 5 CLIENTES MÁS ACTIVOS:" -ForegroundColor Yellow
            for ($i = 0; $i -lt [Math]::Min(5, $response2.datos.clientes.Count); $i++) {
                $cliente = $response2.datos.clientes[$i]
                Write-Host "  $($i+1). $($cliente.nombre_cliente)" -ForegroundColor White
                Write-Host "     Total comercializaciones: $($cliente.total_comercializaciones)" -ForegroundColor White
                Write-Host "     Flujo Completo: $($cliente.flujo_completo) ($($cliente.porcentaje_completo) %)" -ForegroundColor Green
                Write-Host "     Flujo Simple: $($cliente.flujo_simple) ($($cliente.porcentaje_simple) %)" -ForegroundColor Yellow
                Write-Host "     Preferencia: $($cliente.preferencia)" -ForegroundColor Cyan
                Write-Host "     Valor promedio Completo: $($cliente.valor_promedio_completo)" -ForegroundColor White
                Write-Host "     Valor promedio Simple: $($cliente.valor_promedio_simple)" -ForegroundColor White
                Write-Host ""
            }
        }
    } else {
        Write-Host "ERROR: $($response2.message)" -ForegroundColor Red
    }
} catch {
    Write-Host "ERROR EN CONEXIÓN: $($_.Exception.Message)" -ForegroundColor Red
}

# TEST 3: Análisis eficiencia por tipo de flujo
Write-Host "`nTEST 3: Análisis eficiencia por tipo de flujo" -ForegroundColor Yellow

$body3 = @{
    "año" = 2024
} | ConvertTo-Json

try {
    $response3 = Invoke-RestMethod -Uri "$baseUrl/tipo-flujo/eficiencia" -Method POST -Body $body3 -Headers $headers
    
    if ($response3.success) {
        Write-Host "EXITO - Análisis eficiencia por flujo completado" -ForegroundColor Green
        
        # Mostrar eficiencia flujo completo
        if ($response3.datos.eficiencia_flujo_completo) {
            $ef_comp = $response3.datos.eficiencia_flujo_completo
            Write-Host "`nEFICIENCIA FLUJO COMPLETO (CON SENCE):" -ForegroundColor Yellow
            Write-Host "  Tiempo promedio desarrollo: $($ef_comp.tiempo_promedio_desarrollo) días" -ForegroundColor White
            Write-Host "  Valor promedio: $($ef_comp.valor_promedio)" -ForegroundColor Green
            Write-Host "  Facturas promedio: $($ef_comp.facturas_promedio)" -ForegroundColor White
            Write-Host "  Tasa de pago: $($ef_comp.tasa_pago) %" -ForegroundColor Green
        }
        
        # Mostrar eficiencia flujo simple
        if ($response3.datos.eficiencia_flujo_simple) {
            $ef_simple = $response3.datos.eficiencia_flujo_simple
            Write-Host "`nEFICIENCIA FLUJO SIMPLE (SIN SENCE):" -ForegroundColor Yellow
            Write-Host "  Tiempo promedio desarrollo: $($ef_simple.tiempo_promedio_desarrollo) días" -ForegroundColor White
            Write-Host "  Valor promedio: $($ef_simple.valor_promedio)" -ForegroundColor Green
            Write-Host "  Facturas promedio: $($ef_simple.facturas_promedio)" -ForegroundColor White
            Write-Host "  Tasa de pago: $($ef_simple.tasa_pago) %" -ForegroundColor Green
        }
        
        # Mostrar comparativa de eficiencia
        if ($response3.datos.comparativa_eficiencia) {
            $comp_ef = $response3.datos.comparativa_eficiencia
            Write-Host "`nCOMPARATIVA DE EFICIENCIA:" -ForegroundColor Yellow
            
            Write-Host "  Tiempo desarrollo:" -ForegroundColor White
            Write-Host "    Ganador: $($comp_ef.tiempo_desarrollo.ganador)" -ForegroundColor Cyan
            Write-Host "    Diferencia: $($comp_ef.tiempo_desarrollo.diferencia) días" -ForegroundColor White
            
            Write-Host "  Valor promedio:" -ForegroundColor White
            Write-Host "    Ganador: $($comp_ef.valor_promedio.ganador)" -ForegroundColor Cyan
            Write-Host "    Diferencia: $($comp_ef.valor_promedio.diferencia)" -ForegroundColor White
            
            Write-Host "  Tasa de pago:" -ForegroundColor White
            Write-Host "    Ganador: $($comp_ef.tasa_pago.ganador)" -ForegroundColor Cyan
            Write-Host "    Diferencia: $($comp_ef.tasa_pago.diferencia) %" -ForegroundColor White
        }
        
        # Mostrar recomendaciones
        if ($response3.datos.recomendaciones -and $response3.datos.recomendaciones.Count -gt 0) {
            Write-Host "`nRECOMENDACIONES:" -ForegroundColor Yellow
            foreach ($recomendacion in $response3.datos.recomendaciones) {
                Write-Host "  • $recomendacion" -ForegroundColor Green
            }
        }
        
    } else {
        Write-Host "ERROR: $($response3.message)" -ForegroundColor Red
    }
} catch {
    Write-Host "ERROR EN CONEXIÓN: $($_.Exception.Message)" -ForegroundColor Red
}

# TEST 4: Análisis completo año 2024 (sin filtro de mes)
Write-Host "`nTEST 4: Análisis completo año 2024 (todos los meses)" -ForegroundColor Yellow

$body4 = @{
    "año" = 2024
} | ConvertTo-Json

try {
    $response4 = Invoke-RestMethod -Uri "$baseUrl/tipo-flujo/analizar" -Method POST -Body $body4 -Headers $headers
    
    if ($response4.success) {
        Write-Host "ANÁLISIS ANUAL 2024 COMPLETADO" -ForegroundColor Green
        
        $resumen = $response4.datos.resumen
        Write-Host "Total comercializaciones 2024: $($resumen.comercializaciones_analizadas)" -ForegroundColor White
        
        # Calcular insights adicionales
        $totalFlujoCompleto = $resumen.flujo_completo_count
        $totalFlujoSimple = $resumen.flujo_simple_count
        $totalGeneral = $totalFlujoCompleto + $totalFlujoSimple
        
        if ($totalGeneral -gt 0) {
            $adopcionSence = [Math]::Round(($totalFlujoCompleto / $totalGeneral) * 100, 2)
            Write-Host "`nINSIGHTS ANUALES 2024:" -ForegroundColor Yellow
            Write-Host "  Adopción financiamiento SENCE: $adopcionSence %" -ForegroundColor Green
            Write-Host "  Preferencia pago directo: $([Math]::Round(100 - $adopcionSence, 2)) %" -ForegroundColor Yellow
            
            if ($adopcionSence -gt 50) {
                Write-Host "  ✅ Los clientes prefieren el financiamiento SENCE" -ForegroundColor Green
            } else {
                Write-Host "  ⚠️ Los clientes prefieren pago directo sin SENCE" -ForegroundColor Yellow
            }
        }
        
        # Mostrar preferencias de clientes
        if ($response4.datos.preferencias_clientes) {
            $pref_clientes = $response4.datos.preferencias_clientes
            Write-Host "`nCOMPORTAMIENTO CLIENTES:" -ForegroundColor White
            Write-Host "  Solo usan Flujo Completo: $($pref_clientes.clientes_solo_completo) clientes" -ForegroundColor Green
            Write-Host "  Solo usan Flujo Simple: $($pref_clientes.clientes_solo_simple) clientes" -ForegroundColor Yellow
            Write-Host "  Usan ambos flujos: $($pref_clientes.clientes_mixtos) clientes" -ForegroundColor Cyan
        }
        
    } else {
        Write-Host "ERROR: $($response4.message)" -ForegroundColor Red
    }
} catch {
    Write-Host "ERROR EN CONEXIÓN: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host "`nPruebas completadas - Análisis tipos de flujo comercialización" -ForegroundColor Green
Write-Host "INSIGHT CLAVE: Este análisis revela las preferencias de financiamiento de los clientes" -ForegroundColor Yellow
Write-Host "             y la eficiencia de cada proceso (con SENCE vs sin SENCE)" -ForegroundColor Yellow
