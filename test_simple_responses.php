<?php

// Script de prueba simple para usuarios
$url = 'http://localhost:8000/api/importarUsuariosJson';
$filePath = __DIR__ . '/test_usuarios_simple.json';

echo "🧪 Probando endpoint de usuarios simplificado...\n";

$postData = [
    'archivos[]' => new CURLFile($filePath, 'application/json', 'test_usuarios_simple.json')
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "📡 Código HTTP: $httpCode\n";
echo "📤 Respuesta:\n";
echo $response . "\n\n";

// Probar endpoint de ventas
echo "🧪 Probando endpoint de ventas simplificado...\n";

$url2 = 'http://localhost:8000/api/importarVentasJson';
$filePath2 = __DIR__ . '/test_ventas_ejemplo.json';

$postData2 = [
    'archivos[]' => new CURLFile($filePath2, 'application/json', 'test_ventas_ejemplo.json')
];

$ch2 = curl_init();
curl_setopt($ch2, CURLOPT_URL, $url2);
curl_setopt($ch2, CURLOPT_POST, true);
curl_setopt($ch2, CURLOPT_POSTFIELDS, $postData2);
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_HTTPHEADER, ['Accept: application/json']);

$response2 = curl_exec($ch2);
$httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);

echo "📡 Código HTTP: $httpCode2\n";
echo "📤 Respuesta:\n";
echo $response2 . "\n";

echo "✨ Pruebas completadas.\n";
