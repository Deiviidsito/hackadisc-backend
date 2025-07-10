<?php

// Script de debug para Capi
echo "=== Debug Capi ===\n";

$question = "Hola Capi, necesito saber cuántos usuarios hay en la plataforma";
$data = json_encode(['question' => $question]);

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\n",
        'content' => $data,
        'ignore_errors' => true
    ]
]);

$response = file_get_contents('http://127.0.0.1:8000/api/capi/ask', false, $context);

// Ver headers de respuesta
if (isset($http_response_header)) {
    echo "Headers de respuesta:\n";
    foreach ($http_response_header as $header) {
        echo "$header\n";
    }
    echo "\n";
}

if ($response !== false) {
    echo "Respuesta del servidor:\n";
    echo $response . "\n";
} else {
    echo "Error: No se pudo obtener respuesta del servidor.\n";
}
