<?php

// Probar el agente Capi
echo "=== Probando Capi - Agente de IA ===\n\n";

// Test 1: Información sobre Capi
echo "--- Obteniendo información de Capi ---\n";
$aboutResponse = file_get_contents('http://127.0.0.1:8000/api/capi/about');
echo $aboutResponse . "\n\n";

// Test 2: Pregunta sobre usuarios
echo "--- Preguntando sobre usuarios ---\n";
$question = "Hola Capi, necesito saber cuántos usuarios hay en la plataforma";

$data = json_encode(['question' => $question]);
$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\n",
        'content' => $data
    ]
]);

$response = file_get_contents('http://127.0.0.1:8000/api/capi/ask', false, $context);
echo "Pregunta: $question\n";
echo "Respuesta: " . $response . "\n\n";

// Test 3: Pregunta sobre estadísticas
echo "--- Preguntando sobre estadísticas ---\n";
$question2 = "¿Cuántos usuarios activos tenemos en total?";

$data2 = json_encode(['question' => $question2]);
$context2 = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\n",
        'content' => $data2
    ]
]);

$response2 = file_get_contents('http://127.0.0.1:8000/api/capi/ask', false, $context2);
echo "Pregunta: $question2\n";
echo "Respuesta: " . $response2 . "\n";

echo "\n=== Pruebas completadas ===\n";
