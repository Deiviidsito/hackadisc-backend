<?php

// Script de prueba legible para Capi
echo "🤖 === PROBANDO CAPI - AGENTE DE IA === 🤖\n\n";

function testCapiEndpoint($url, $data = null, $title = "") {
    echo "🔍 $title\n";
    echo str_repeat("─", 50) . "\n";
    
    if ($data) {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => json_encode($data),
                'ignore_errors' => true
            ]
        ]);
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'ignore_errors' => true
            ]
        ]);
    }
    
    $response = file_get_contents($url, false, $context);
    
    if ($response !== false) {
        $decoded = json_decode($response, true);
        
        if ($decoded) {
            if (isset($decoded['response'])) {
                echo "📝 Pregunta: " . ($decoded['question'] ?? 'N/A') . "\n";
                echo "🤖 Respuesta de Capi:\n";
                echo $decoded['response'] . "\n";
                echo "\n✅ Estado: " . ($decoded['success'] ? 'Éxito' : 'Error') . "\n";
                echo "🔧 Modo: " . ($decoded['mode'] ?? 'N/A') . "\n";
            } else {
                echo "ℹ️ Información de Capi:\n";
                echo "📛 Nombre: " . ($decoded['name'] ?? 'N/A') . "\n";
                echo "📖 Descripción: " . ($decoded['description'] ?? 'N/A') . "\n";
                echo "🔧 Modo: " . ($decoded['mode'] ?? 'N/A') . "\n";
                echo "🟢 Estado: " . ($decoded['status'] ?? 'N/A') . "\n";
                
                if (isset($decoded['capabilities'])) {
                    echo "⚡ Capacidades:\n";
                    foreach ($decoded['capabilities'] as $capability) {
                        echo "  • $capability\n";
                    }
                }
            }
        } else {
            echo "❌ Error: No se pudo decodificar la respuesta JSON\n";
            echo "Respuesta cruda: $response\n";
        }
    } else {
        echo "❌ Error: No se pudo conectar al servidor\n";
    }
    
    echo "\n" . str_repeat("═", 50) . "\n\n";
}

// Prueba 1: Información sobre Capi
testCapiEndpoint('http://127.0.0.1:8000/api/capi/about', null, "INFORMACIÓN DE CAPI");

// Prueba 2: Pregunta sobre usuarios
testCapiEndpoint(
    'http://127.0.0.1:8000/api/capi/ask',
    ['question' => 'Hola Capi, necesito saber cuales son los correos de mis usuarios'],
    "CONSULTA DE USUARIOS"
);

echo "🎉 === PRUEBAS COMPLETADAS === 🎉\n";
