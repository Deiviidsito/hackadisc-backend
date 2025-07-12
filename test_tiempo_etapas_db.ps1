# Script de prueba para endpoints de tiempo entre etapas - Solo Base de Datos
# Solo realiza pruebas con los datos que ya están en la base de datos

Write-Host "PROBANDO ENDPOINTS DE TIEMPO ENTRE ETAPAS (SOLO BASE DE DATOS)" -ForegroundColor Green
Write-Host "=================================================================" -ForegroundColor Green

# URL base del API
$baseUrl = "http://localhost:8000/api"

# Headers para las peticiones
$headers = @{
    "Content-Type" = "application/json"
    "Accept" = "application/json"
}

Write-Host ""
Write-Host "PRUEBA 1: Tiempo promedio entre etapas (2024)" -ForegroundColor Yellow
Write-Host "Endpoint: POST /tiempo-etapas/promedio" -ForegroundColor Cyan

$body1 = @{
    "año" = 2024
    "mes_inicio" = 1
    "mes_fin" = 12
    "incluir_detalles" = $false
} | ConvertTo-Json

try {
    $response1 = Invoke-RestMethod -Uri "$baseUrl/tiempo-etapas/promedio" -Method POST -Headers $headers -Body $body1
    Write-Host "Respuesta exitosa:" -ForegroundColor Green
    $response1 | ConvertTo-Json -Depth 3 | Write-Host
} catch {
    Write-Host "Error: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host ""
Write-Host "PRUEBA 2: Analisis por cliente (2024)" -ForegroundColor Yellow
Write-Host "Endpoint: POST /tiempo-etapas/por-cliente" -ForegroundColor Cyan

$body2 = @{
    "año" = 2024
    "mes_inicio" = 1
    "mes_fin" = 12
} | ConvertTo-Json

try {
    $response2 = Invoke-RestMethod -Uri "$baseUrl/tiempo-etapas/por-cliente" -Method POST -Headers $headers -Body $body2
    Write-Host "Respuesta exitosa:" -ForegroundColor Green
    $response2 | ConvertTo-Json -Depth 3 | Write-Host
} catch {
    Write-Host "Error: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host ""
Write-Host "PRUEBA 3: Distribucion de tiempos (2024)" -ForegroundColor Yellow
Write-Host "Endpoint: POST /tiempo-etapas/distribucion" -ForegroundColor Cyan

$body3 = @{
    "año" = 2024
    "mes_inicio" = 1
    "mes_fin" = 12
} | ConvertTo-Json

try {
    $response3 = Invoke-RestMethod -Uri "$baseUrl/tiempo-etapas/distribucion" -Method POST -Headers $headers -Body $body3
    Write-Host "Respuesta exitosa:" -ForegroundColor Green
    $response3 | ConvertTo-Json -Depth 3 | Write-Host
} catch {
    Write-Host "Error: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host ""
Write-Host "PRUEBA 4: Analisis sin filtro de año (todos los datos)" -ForegroundColor Yellow
Write-Host "Endpoint: POST /tiempo-etapas/promedio" -ForegroundColor Cyan

$body4 = @{
    "incluir_detalles" = $true
} | ConvertTo-Json

try {
    $response4 = Invoke-RestMethod -Uri "$baseUrl/tiempo-etapas/promedio" -Method POST -Headers $headers -Body $body4
    Write-Host "Respuesta exitosa:" -ForegroundColor Green
    Write-Host "Estadisticas generales:" -ForegroundColor Cyan
    $response4.estadisticas | ConvertTo-Json | Write-Host
    Write-Host "Total detalles: $($response4.detalles_ventas.Count)" -ForegroundColor Cyan
} catch {
    Write-Host "Error: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host ""
Write-Host "FINALIZADO - Pruebas de endpoints completadas" -ForegroundColor Green
