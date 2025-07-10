<?php

/**
 * Script de prueba para los endpoints de voz de Capi
 * Prueba Text-to-Speech principalmente (STT requiere archivos de audio reales)
 */

require_once __DIR__ . '/vendor/autoload.php';

class CapiVoiceTest
{
    private $baseUrl;
    
    public function __construct()
    {
        $this->baseUrl = 'http://localhost:8000/api/capi';
    }
    
    public function run()
    {
        echo "🎤 === PRUEBAS DE VOZ PARA CAPI === 🎤\n\n";
        
        // Prueba 1: Text-to-Speech
        $this->testTextToSpeech();
        
        // Prueba 2: Información sobre capacidades de voz
        $this->testVoiceInfo();
        
        echo "\n🎉 === PRUEBAS DE VOZ COMPLETADAS === 🎉\n";
    }
    
    private function testTextToSpeech()
    {
        echo "🔊 === PRUEBA: TEXT-TO-SPEECH === 🔊\n";
        
        $textsToTest = [
            "¡Hola! Soy Capi, la mascota de INSECAP. ¿En qué puedo ayudarte hoy?",
            "Tenemos varios usuarios registrados en nuestra plataforma.",
            "¿Te gustaría conocer más estadísticas o información específica?"
        ];
        
        foreach ($textsToTest as $index => $text) {
            echo "\n📝 Texto " . ($index + 1) . ": " . substr($text, 0, 50) . "...\n";
            
            $response = $this->makeRequest('/voice/text-to-speech', [
                'text' => $text
            ]);
            
            if ($response && isset($response['success']) && $response['success']) {
                echo "✅ Audio generado correctamente\n";
                echo "🔗 URL del audio: " . $response['audio_url'] . "\n";
                
                // Verificar que el archivo existe
                $fileName = basename(parse_url($response['audio_url'], PHP_URL_PATH));
                $filePath = __DIR__ . '/storage/app/voice_responses/' . $fileName;
                
                if (file_exists($filePath)) {
                    echo "📁 Archivo guardado: " . $fileName . "\n";
                    echo "📊 Tamaño: " . filesize($filePath) . " bytes\n";
                } else {
                    echo "⚠️  Archivo no encontrado en el sistema\n";
                }
            } else {
                echo "❌ Error al generar audio\n";
                if (isset($response['message'])) {
                    echo "💬 Mensaje: " . $response['message'] . "\n";
                }
            }
            
            echo str_repeat("─", 50) . "\n";
        }
    }
    
    private function testVoiceInfo()
    {
        echo "\n📋 === INFORMACIÓN DE CAPACIDADES DE VOZ === 📋\n";
        
        echo "🎯 Endpoints disponibles:\n";
        echo "  • POST /api/capi/voice/chat - Chat completo por voz (audio → texto → respuesta → audio)\n";
        echo "  • POST /api/capi/voice/text-to-speech - Solo convertir texto a voz\n";
        echo "  • GET /api/capi/voice/audio/{file} - Servir archivos de audio generados\n\n";
        
        echo "🔧 Funcionalidades implementadas:\n";
        echo "  • Speech-to-Text (Whisper) - Convertir voz del usuario a texto\n";
        echo "  • Procesamiento de IA - Capi responde la pregunta\n";
        echo "  • Text-to-Speech (TTS) - Convertir respuesta a voz\n";
        echo "  • Modo demo y modo OpenAI real\n\n";
        
        echo "📝 Formatos de audio soportados:\n";
        echo "  • Entrada: MP3, WAV, M4A, WebM (max 25MB)\n";
        echo "  • Salida: MP3\n\n";
        
        echo "🌍 Configuración:\n";
        echo "  • Idioma: Español (es)\n";
        echo "  • Voz: Nova (femenina, amigable)\n";
        echo "  • Modelo STT: whisper-1\n";
        echo "  • Modelo TTS: tts-1\n\n";
    }
    
    private function makeRequest($endpoint, $data = null)
    {
        $url = $this->baseUrl . $endpoint;
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30,
        ]);
        
        if ($data) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json'
            ]);
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            echo "❌ Error HTTP: $httpCode\n";
            echo "📄 Respuesta: $response\n";
            return null;
        }
        
        return json_decode($response, true);
    }
}

// Ejecutar las pruebas
$test = new CapiVoiceTest();
$test->run();
