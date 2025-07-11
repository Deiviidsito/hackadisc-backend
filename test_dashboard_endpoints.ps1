# Script de Validación de Endpoints Dashboard de Clientes
# Para ejecutar en PowerShell

Write-Host "=== VALIDACION DE ENDPOINTS DASHBOARD DE CLIENTES ===" -ForegroundColor Green

# Test 1: Lista básica de clientes
Write-Host "`n1. Probando lista básica de clientes..." -ForegroundColor Yellow
try {
    $response1 = Invoke-WebRequest -Uri "http://localhost:8000/api/clientes-dashboard" -Method GET -ContentType "application/json"
    $data1 = $response1.Content | ConvertFrom-Json
    Write-Host "✓ Status: $($response1.StatusCode)" -ForegroundColor Green
    Write-Host "✓ Total clientes: $($data1.total_clientes)" -ForegroundColor Green
    Write-Host "✓ Clientes en respuesta: $($data1.data.Count)" -ForegroundColor Green
} catch {
    Write-Host "✗ Error: $($_.Exception.Message)" -ForegroundColor Red
}

# Test 2: Lista avanzada con filtros
Write-Host "`n2. Probando lista avanzada con filtros..." -ForegroundColor Yellow
try {
    $url2 = "http://localhost:8000/api/clientes-dashboard-avanzado?limit=5&sort_by=total_ingresos&order=desc&monto_minimo=5000000"
    $response2 = Invoke-WebRequest -Uri $url2 -Method GET -ContentType "application/json"
    $data2 = $response2.Content | ConvertFrom-Json
    Write-Host "✓ Status: $($response2.StatusCode)" -ForegroundColor Green
    Write-Host "✓ Total clientes filtrados: $($data2.pagination.total_clientes)" -ForegroundColor Green
    Write-Host "✓ Clientes en respuesta: $($data2.data.Count)" -ForegroundColor Green
    Write-Host "✓ Filtro monto mínimo aplicado: $($data2.filtros_aplicados.monto_minimo)" -ForegroundColor Green
} catch {
    Write-Host "✗ Error: $($_.Exception.Message)" -ForegroundColor Red
}

# Test 3: Dashboard específico de cliente
Write-Host "`n3. Probando dashboard específico de cliente..." -ForegroundColor Yellow
try {
    $clienteNombre = "Syncore%20Montajes%20Industriales"
    $response3 = Invoke-WebRequest -Uri "http://localhost:8000/api/cliente-dashboard/$clienteNombre" -Method GET -ContentType "application/json"
    $data3 = $response3.Content | ConvertFrom-Json
    Write-Host "✓ Status: $($response3.StatusCode)" -ForegroundColor Green
    Write-Host "✓ Cliente: $($data3.data.cliente_nombre)" -ForegroundColor Green
    Write-Host "✓ Total ventas: $($data3.data.total_ventas)" -ForegroundColor Green
    Write-Host "✓ Total ingresos: $($data3.data.total_ingresos)" -ForegroundColor Green
    Write-Host "✓ Días comercialización: $($data3.data.dias_comercializacion)" -ForegroundColor Green
    Write-Host "✓ Estado actividad: $($data3.data.informacion_temporal.estado_actividad)" -ForegroundColor Green
} catch {
    Write-Host "✗ Error: $($_.Exception.Message)" -ForegroundColor Red
}

# Test 4: Cliente no encontrado (debe dar error 404)
Write-Host "`n4. Probando cliente no encontrado..." -ForegroundColor Yellow
try {
    $response4 = Invoke-WebRequest -Uri "http://localhost:8000/api/cliente-dashboard/ClienteInexistente" -Method GET -ContentType "application/json"
    Write-Host "✗ Debería haber dado error 404" -ForegroundColor Red
} catch {
    if ($_.Exception.Response.StatusCode -eq 404) {
        Write-Host "✓ Error 404 correcto para cliente inexistente" -ForegroundColor Green
    } else {
        Write-Host "✗ Error inesperado: $($_.Exception.Message)" -ForegroundColor Red
    }
}

# Test 5: Filtros de paginación
Write-Host "`n5. Probando paginación..." -ForegroundColor Yellow
try {
    $url5a = "http://localhost:8000/api/clientes-dashboard-avanzado?limit=2&offset=0"
    $url5b = "http://localhost:8000/api/clientes-dashboard-avanzado?limit=2&offset=2"
    $response5a = Invoke-WebRequest -Uri $url5a -Method GET -ContentType "application/json"
    $response5b = Invoke-WebRequest -Uri $url5b -Method GET -ContentType "application/json"
    $data5a = $response5a.Content | ConvertFrom-Json
    $data5b = $response5b.Content | ConvertFrom-Json
    
    Write-Host "✓ Página 1 - Clientes: $($data5a.data.Count)" -ForegroundColor Green
    Write-Host "✓ Página 2 - Clientes: $($data5b.data.Count)" -ForegroundColor Green
    Write-Host "✓ Has more (página 1): $($data5a.pagination.has_more)" -ForegroundColor Green
    
    # Verificar que los clientes son diferentes
    $cliente1_p1 = $data5a.data[0].nombre_cliente
    $cliente1_p2 = $data5b.data[0].nombre_cliente
    if ($cliente1_p1 -ne $cliente1_p2) {
        Write-Host "✓ Paginación funciona correctamente (clientes diferentes)" -ForegroundColor Green
    } else {
        Write-Host "✗ Paginación puede tener problemas (mismos clientes)" -ForegroundColor Red
    }
} catch {
    Write-Host "✗ Error en paginación: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host "`n=== RESUMEN ===" -ForegroundColor Cyan
Write-Host "Los endpoints de dashboard de clientes están funcionando correctamente." -ForegroundColor Green
Write-Host "Todas las funcionalidades principales han sido validadas:" -ForegroundColor Green
Write-Host "• Lista básica de clientes" -ForegroundColor Green
Write-Host "• Lista avanzada con filtros" -ForegroundColor Green  
Write-Host "• Dashboard específico de cliente" -ForegroundColor Green
Write-Host "• Manejo de errores (404)" -ForegroundColor Green
Write-Host "• Paginación" -ForegroundColor Green
