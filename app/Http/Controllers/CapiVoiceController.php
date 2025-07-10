<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use OpenAI;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class CapiVoiceController extends Controller
{
    /**
     * Procesar audio del usuario y responder con voz
     */
    public function voiceChat(Request $request)
    {
        $request->validate([
            'audio' => 'required|file|mimes:mp3,wav,m4a,webm|max:25600', // 25MB max
        ]);

        try {
            // Guardar el archivo de audio temporalmente
            $audioFile = $request->file('audio');
            $tempPath = $audioFile->store('temp/audio', 'local');
            $fullPath = storage_path('app/' . $tempPath);

            // 1. Convertir audio a texto usando OpenAI Whisper
            $transcription = $this->speechToText($fullPath);
            
            if (!$transcription) {
                return response()->json([
                    'success' => false,
                    'message' => 'No pude entender el audio. ¿Podrías intentar de nuevo?'
                ], 400);
            }

            // 2. Procesar la pregunta con Capi (reutilizar lógica existente)
            $contextData = $this->getContextData($transcription);
            
            if (env('CAPI_DEMO_MODE', true)) {
                $textResponse = $this->generateDemoResponse($transcription, $contextData);
            } else {
                $textResponse = $this->getOpenAIResponse($transcription, $contextData);
            }

            // 3. Convertir respuesta de texto a audio
            $audioResponsePath = $this->textToSpeech($textResponse);

            // 4. Limpiar archivo temporal de entrada
            Storage::disk('local')->delete($tempPath);

            return response()->json([
                'success' => true,
                'transcription' => $transcription,
                'text_response' => $textResponse,
                'audio_response_url' => route('capi.voice.audio', ['file' => basename($audioResponsePath)]),
                'agent' => 'Capi',
                'mode' => env('CAPI_DEMO_MODE', true) ? 'demo' : 'openai'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lo siento, tuve un problema procesando tu audio.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Solo convertir texto a voz (para usar con chat de texto existente)
     */
    public function textToVoice(Request $request)
    {
        $request->validate([
            'text' => 'required|string|max:4000'
        ]);

        try {
            $text = $request->input('text');
            $audioPath = $this->textToSpeech($text);

            return response()->json([
                'success' => true,
                'audio_url' => route('capi.voice.audio', ['file' => basename($audioPath)]),
                'text' => $text
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'No pude generar el audio.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Servir archivos de audio generados
     */
    public function serveAudio($file)
    {
        $path = storage_path('app/voice_responses/' . $file);
        
        if (!file_exists($path)) {
            abort(404);
        }

        return response()->file($path, [
            'Content-Type' => 'audio/mpeg',
            'Content-Disposition' => 'inline; filename="' . $file . '"'
        ]);
    }

    /**
     * Convertir audio a texto usando OpenAI Whisper
     */
    private function speechToText(string $audioFilePath): ?string
    {
        try {
            if (env('CAPI_DEMO_MODE', true)) {
                // En modo demo, simular transcripción
                return "Hola Capi, ¿cuántos usuarios hay en la plataforma?";
            }

            $httpClient = new \GuzzleHttp\Client([
                'verify' => false,
                'timeout' => 60
            ]);
            
            $client = OpenAI::factory()
                ->withApiKey(env('OPENAI_API_KEY'))
                ->withHttpClient($httpClient)
                ->make();

            $response = $client->audio()->transcribe([
                'model' => 'whisper-1',
                'file' => fopen($audioFilePath, 'r'),
                'language' => 'es', // Español
            ]);

            return $response->text ?? null;

        } catch (\Exception $e) {
            Log::error('Error en speech-to-text: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Convertir texto a voz usando OpenAI TTS
     */
    private function textToSpeech(string $text): string
    {
        try {
            if (env('CAPI_DEMO_MODE', true)) {
                // En modo demo, crear un archivo de audio simulado
                $fileName = 'capi_response_' . time() . '.mp3';
                $outputPath = storage_path('app/voice_responses/' . $fileName);
                
                // Crear directorio si no existe
                if (!file_exists(dirname($outputPath))) {
                    mkdir(dirname($outputPath), 0755, true);
                }
                
                // Crear un archivo MP3 vacío/simulado (en producción real sería el audio de OpenAI)
                file_put_contents($outputPath, ''); // Archivo vacío para demo
                
                return $outputPath;
            }

            $httpClient = new \GuzzleHttp\Client([
                'verify' => false,
                'timeout' => 60
            ]);
            
            $client = OpenAI::factory()
                ->withApiKey(env('OPENAI_API_KEY'))
                ->withHttpClient($httpClient)
                ->make();

            $response = $client->audio()->speech([
                'model' => 'tts-1',
                'input' => $text,
                'voice' => 'nova', // Voz femenina amigable
            ]);

            // Guardar el audio
            $fileName = 'capi_response_' . time() . '.mp3';
            $outputPath = storage_path('app/voice_responses/' . $fileName);
            
            // Crear directorio si no existe
            if (!file_exists(dirname($outputPath))) {
                mkdir(dirname($outputPath), 0755, true);
            }
            
            file_put_contents($outputPath, $response);
            
            return $outputPath;

        } catch (\Exception $e) {
            Log::error('Error en text-to-speech: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtener respuesta de OpenAI (reutilizar del controlador principal)
     */
    private function getOpenAIResponse(string $question, array $contextData): string
    {
        $prompt = $this->buildPrompt($question, $contextData);
        
        $httpClient = new \GuzzleHttp\Client([
            'verify' => false,
            'timeout' => 30
        ]);
        
        $client = OpenAI::factory()
            ->withApiKey(env('OPENAI_API_KEY'))
            ->withHttpClient($httpClient)
            ->make();
        
        $response = $client->chat()->create([
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Eres Capi, la mascota oficial de INSECAP. Responde de manera conversacional y natural, como si estuvieras hablando en voz alta. Mantén las respuestas concisas pero amigables, ideal para ser escuchadas. Usa emojis pero con moderación.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'max_tokens' => 300, // Más corto para audio
            'temperature' => 0.8,
        ]);

        return $response->choices[0]->message->content;
    }

    // Reutilizar métodos del controlador principal
    private function getContextData(string $question): array
    {
        // Implementar la misma lógica que en CapiController
        $contextData = [];
        $questionLower = strtolower($question);

        if (str_contains($questionLower, 'usuario') || str_contains($questionLower, 'user')) {
            $contextData['users'] = [
                'total' => User::count(),
                'active' => User::whereNotNull('email_verified_at')->count(),
                'recent' => User::where('created_at', '>=', now()->subDays(7))->count(),
            ];

            if (str_contains($questionLower, 'correo') || str_contains($questionLower, 'email') || str_contains($questionLower, 'mail')) {
                $contextData['user_emails'] = User::select('name', 'email', 'email_verified_at')
                    ->get()
                    ->map(function($user) {
                        return [
                            'name' => $user->name,
                            'email' => $user->email,
                            'verified' => $user->email_verified_at ? 'Sí' : 'No'
                        ];
                    });
            }
        }

        return $contextData;
    }

    private function generateDemoResponse(string $question, array $contextData): string
    {
        return "¡Hola! Soy Capi, la mascota de INSECAP. He escuchado tu pregunta sobre $question. " .
               "En este momento tenemos " . User::count() . " usuarios registrados en nuestra plataforma. " .
               "¿Hay algo más en lo que pueda ayudarte?";
    }

    private function buildPrompt(string $question, array $contextData): string
    {
        $prompt = "Pregunta del usuario (por voz): {$question}\n\n";
        
        if (!empty($contextData)) {
            $prompt .= "Información disponible:\n";
            
            if (isset($contextData['users'])) {
                $users = $contextData['users'];
                $prompt .= "- Total usuarios: {$users['total']}\n";
                $prompt .= "- Usuarios activos: {$users['active']}\n";
            }

            if (isset($contextData['user_emails'])) {
                $prompt .= "\nCorreos de usuarios:\n";
                foreach ($contextData['user_emails'] as $user) {
                    $prompt .= "- {$user['name']}: {$user['email']}\n";
                }
            }
            
            $prompt .= "\nResponde de forma conversacional, como si estuvieras hablando.";
        }

        return $prompt;
    }
}
