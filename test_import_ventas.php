<?php

// Script de prueba para el endpoint de importación de ventas
$url = 'http://localhost:8000/api/importarVentasJson';
$filePath = __DIR__ . '/test_ventas_ejemplo.json';

if (!file_exists($filePath)) {
    echo "❌ Error: El archivo de prueba no existe: $filePath\n";
    exit(1);
}

echo "🧪 Probando endpoint de importación de ventas...\n";
echo "📁 Archivo: $filePath\n";
echo "📊 Tamaño: " . round(filesize($filePath) / 1024, 2) . " KB\n\n";

// Preparar datos para cURL
$postData = [
    'archivos[]' => new CURLFile($filePath, 'application/json', 'test_ventas_ejemplo.json')
];

// Inicializar cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);

$startTime = microtime(true);

// Ejecutar request
echo "🚀 Enviando request al endpoint...\n";
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

$endTime = microtime(true);
$duration = round($endTime - $startTime, 2);

curl_close($ch);

echo "⏱️ Tiempo de respuesta: {$duration} segundos\n";
echo "📡 Código HTTP: $httpCode\n\n";

if ($error) {
    echo "❌ Error cURL: $error\n";
    exit(1);
}

if ($httpCode >= 200 && $httpCode < 300) {
    echo "✅ ÉXITO - Respuesta del servidor:\n";
    echo "==========================================\n";
    
    // Formatear JSON para mejor legibilidad
    $responseData = json_decode($response, true);
    if ($responseData) {
        echo json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } else {
        echo $response;
    }
    echo "\n==========================================\n";
    
    // Mostrar métricas clave
    if ($responseData && isset($responseData['data'])) {
        $data = $responseData['data'];
        echo "\n📊 MÉTRICAS CLAVE:\n";
        echo "   📦 Archivos procesados: " . ($data['archivos_procesados'] ?? 0) . "\n";
        echo "   🆕 Ventas creadas: " . ($data['ventas_creadas'] ?? 0) . "\n";
        echo "   🔄 Ventas actualizadas: " . ($data['ventas_actualizadas'] ?? 0) . "\n";
        echo "   🚫 Ventas filtradas: " . ($data['ventas_filtradas'] ?? 0) . "\n";
        echo "   👥 Clientes creados: " . ($data['clientes_creados'] ?? 0) . "\n";
        echo "   📄 Facturas creadas: " . ($data['facturas_creadas'] ?? 0) . "\n";
        echo "   ⚡ Tiempo total: " . ($data['rendimiento']['tiempo_total_segundos'] ?? 0) . "s\n";
        echo "   🚀 Ventas/segundo: " . ($data['rendimiento']['ventas_por_segundo'] ?? 0) . "\n";
    }
    
} else {
    echo "❌ ERROR - Código HTTP: $httpCode\n";
    echo "Respuesta del servidor:\n";
    echo "==========================================\n";
    echo $response;
    echo "\n==========================================\n";
    
    // Intentar parsear el error como JSON
    $errorData = json_decode($response, true);
    if ($errorData && isset($errorData['error'])) {
        echo "\n🚨 DETALLES DEL ERROR:\n";
        echo "   Mensaje: " . $errorData['error'] . "\n";
        echo "   Código: " . ($errorData['codigo_error'] ?? 'N/A') . "\n";
        echo "   Timestamp: " . ($errorData['timestamp'] ?? 'N/A') . "\n";
    }
}

echo "\n✨ Prueba completada.\n";
