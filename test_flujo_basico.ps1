# SCRIPT DE PRUEBAS - ANÁLISIS TIPOS DE FLUJO
Write-Host "Iniciando pruebas Análisis Tipos de Flujo" -ForegroundColor Green

$baseUrl = "http://localhost:8000/api"
$headers = @{
    "Content-Type" = "application/json"
    "Accept" = "application/json"
}

# TEST 1: Análisis comparativo tipos de flujo
Write-Host "`nTEST 1: Análisis comparativo tipos de flujo" -ForegroundColor Yellow

$body1 = @{
    año = 2024
    mes = 10
} | ConvertTo-Json

try {
    $response1 = Invoke-RestMethod -Uri "$baseUrl/tipo-flujo/analizar" -Method POST -Body $body1 -Headers $headers
    
    if ($response1.success) {
        Write-Host "EXITO - Análisis tipos de flujo completado" -ForegroundColor Green
        
        $resumen = $response1.datos.resumen
        Write-Host "Comercializaciones analizadas: $($resumen.comercializaciones_analizadas)" -ForegroundColor White
        Write-Host "Flujo Completo (con SENCE): $($resumen.flujo_completo_count) casos" -ForegroundColor Cyan
        Write-Host "Flujo Simple (sin SENCE): $($resumen.flujo_simple_count) casos" -ForegroundColor Cyan
        
        # Mostrar estadísticas del flujo completo
        if ($response1.datos.flujo_completo) {
            $completo = $response1.datos.flujo_completo
            Write-Host "`nFLUJO COMPLETO (0→3→1 - CON SENCE):" -ForegroundColor Yellow
            Write-Host "  Tiempo promedio total: $($completo.tiempo_promedio_total) días" -ForegroundColor White
            Write-Host "  Valor promedio: $($completo.valor_promedio)" -ForegroundColor Green
            Write-Host "  Clientes únicos: $($completo.clientes_unicos)" -ForegroundColor White
        }
        
        # Mostrar estadísticas del flujo simple
        if ($response1.datos.flujo_simple) {
            $simple = $response1.datos.flujo_simple
            Write-Host "`nFLUJO SIMPLE (0→1 - SIN SENCE):" -ForegroundColor Yellow
            Write-Host "  Tiempo promedio total: $($simple.tiempo_promedio_total) días" -ForegroundColor White
            Write-Host "  Valor promedio: $($simple.valor_promedio)" -ForegroundColor Green
            Write-Host "  Clientes únicos: $($simple.clientes_unicos)" -ForegroundColor White
        }
        
    } else {
        Write-Host "ERROR: $($response1.message)" -ForegroundColor Red
    }
} catch {
    Write-Host "ERROR EN CONEXIÓN: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host "`nPruebas completadas" -ForegroundColor Green
