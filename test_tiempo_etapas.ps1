# ======================================================================
# SCRIPT DE TESTING - ANALISIS TIEMPO ENTRE ETAPAS
# ======================================================================
# Prueba todos los endpoints del nuevo TiempoEtapasController
# 
# Funcionalidades testadas:
# 1. Tiempo promedio entre etapas (desde BD)
# 2. Analisis por cliente
# 3. Distribucion de tiempos
# 4. Procesamiento directo desde JSON
# ======================================================================

# Configuracion
$baseUrl = "http://localhost:8000/api"
$headers = @{
    "Content-Type" = "application/json"
    "Accept" = "application/json"
}

Write-Host "INICIANDO TESTING - ANALISIS TIEMPO ENTRE ETAPAS" -ForegroundColor Green
Write-Host "=================================================" -ForegroundColor Green

# ======================================================================
# TEST 1: TIEMPO PROMEDIO ENTRE ETAPAS (DESDE BD)
# ======================================================================
Write-Host ""
Write-Host "TEST 1: Calculando tiempo promedio entre etapas..." -ForegroundColor Yellow

$body1 = @{
    anio = 2024
    mes_inicio = 10
    mes_fin = 12
    incluir_detalles = $false
} | ConvertTo-Json

try {
    $response1 = Invoke-RestMethod -Uri "$baseUrl/tiempo-etapas/promedio" -Method POST -Headers $headers -Body $body1
    
    Write-Host "TEST 1 EXITOSO" -ForegroundColor Green
    Write-Host "Tiempo promedio: $($response1.estadisticas.tiempo_promedio_dias) dias" -ForegroundColor Cyan
    Write-Host "Tiempo mediano: $($response1.estadisticas.tiempo_mediano_dias) dias" -ForegroundColor Cyan
    Write-Host "Ventas procesadas: $($response1.estadisticas.total_ventas_procesadas)" -ForegroundColor Cyan
    Write-Host "Ventas con tiempo calculado: $($response1.estadisticas.ventas_con_tiempo_calculado)" -ForegroundColor Cyan
    
} catch {
    Write-Host "TEST 1 FALLO" -ForegroundColor Red
    Write-Host "Error: $($_.Exception.Message)" -ForegroundColor Red
}

# ======================================================================
# TEST 4: PROCESAMIENTO DIRECTO DESDE JSON (MAS SIMPLE)
# ======================================================================
Write-Host ""
Write-Host "TEST 4: Procesando archivo JSON directamente..." -ForegroundColor Yellow

# Buscar archivo JSON en Downloads
$jsonPath = "C:\Users\David\Downloads\datasets\8.data Oct a DIC 2024.json"

if (Test-Path $jsonPath) {
    Write-Host "Archivo encontrado, procesando..." -ForegroundColor Green
    
    # Metodo simple usando curl
    try {
        $curlCommand = "curl -X POST `"$baseUrl/tiempo-etapas/procesar-json`" -F `"archivo_json=@`"$jsonPath`"`" -F `"anio=2024`" -F `"mes_inicio=10`" -F `"mes_fin=12`" -F `"incluir_detalles=false`""
        
        Write-Host "Ejecutando: $curlCommand" -ForegroundColor Cyan
        
        $result = Invoke-Expression $curlCommand
        Write-Host "Resultado: $result" -ForegroundColor White
        
    } catch {
        Write-Host "TEST 4 FALLO" -ForegroundColor Red
        Write-Host "Error: $($_.Exception.Message)" -ForegroundColor Red
    }
} else {
    Write-Host "TEST 4 OMITIDO - Archivo JSON no encontrado en: $jsonPath" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "=================================================" -ForegroundColor Green
Write-Host "TESTING COMPLETADO" -ForegroundColor Green
