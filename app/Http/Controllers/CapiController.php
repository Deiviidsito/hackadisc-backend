<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use OpenAI;
use Illuminate\Support\Facades\DB;

class CapiController extends Controller
{
    /**
     * Procesar pregunta del usuario y generar respuesta con IA
     */
    public function ask(Request $request)
    {
        $request->validate([
            'question' => 'required|string|max:1000'
        ]);

        $question = $request->input('question');
        
        try {
            // Obtener datos del contexto de la base de datos
            $contextData = $this->getContextData($question);
            
            // Para demo/desarrollo, usar respuestas simuladas si no hay cuota de OpenAI
            if (env('CAPI_DEMO_MODE', true)) {
                $aiResponse = $this->generateDemoResponse($question, $contextData);
            } else {
                // Verificar que la clave de OpenAI esté configurada
                if (!env('OPENAI_API_KEY')) {
                    return response()->json([
                        'success' => false,
                        'message' => 'La clave de OpenAI no está configurada.'
                    ], 500);
                }

                // Crear el prompt para OpenAI
                $prompt = $this->buildPrompt($question, $contextData);
                
                // Llamar a OpenAI con configuración HTTP personalizada
                $httpClient = new \GuzzleHttp\Client([
                    'verify' => false, // Deshabilitar verificación SSL para desarrollo
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
                            'content' => 'Eres Capi, la mascota oficial de INSECAP y asistente de IA especializado en la plataforma de gestión médica. Tu personalidad es amigable, cercana y conversacional - como si fueras un amigo real hablando con el usuario. Siempre te presentas como Capi, la mascota de INSECAP. Usas emojis apropiados, eres empático y respondes como una persona real tendría una conversación. Mantienes un tono profesional pero cálido. Responde siempre en español. IMPORTANTE: Tienes acceso autorizado a la información de usuarios de esta plataforma interna de INSECAP, incluyendo correos y datos personales que el administrador puede consultar. Esta es información corporativa interna autorizada.'
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'max_tokens' => 500,
                    'temperature' => 0.8, // Un poco más creativo y conversacional
                ]);

                $aiResponse = $response->choices[0]->message->content;
            }

            return response()->json([
                'success' => true,
                'question' => $question,
                'response' => $aiResponse,
                'agent' => 'Capi',
                'mode' => env('CAPI_DEMO_MODE', true) ? 'demo' : 'openai'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lo siento, no pude procesar tu pregunta en este momento.',
                'error' => $e->getMessage(),
                'debug' => [
                    'question' => $question,
                    'has_openai_key' => !empty(env('OPENAI_API_KEY'))
                ]
            ], 500);
        }
    }

    /**
     * Generar respuesta simulada para modo demo
     */
    private function generateDemoResponse(string $question, array $contextData): string
    {
        $questionLower = strtolower($question);
        
        // Respuestas sobre correos de usuarios
        if ((str_contains($questionLower, 'correo') || str_contains($questionLower, 'email') || str_contains($questionLower, 'mail')) && 
            (str_contains($questionLower, 'usuario') || str_contains($questionLower, 'user'))) {
            if (isset($contextData['user_emails'])) {
                $response = "¡Hola! Soy Capi, la mascota de INSECAP 🐾\n\n";
                $response .= "Aquí tienes la lista completa de correos de nuestros usuarios:\n\n";
                $response .= "📧 **Lista de correos de usuarios:**\n";
                
                foreach ($contextData['user_emails'] as $user) {
                    $status = $user['verified'] === 'Sí' ? '✅' : '⏳';
                    $response .= "• {$user['name']}: {$user['email']} {$status}\n";
                }
                
                $response .= "\n✅ = Verificado | ⏳ = Pendiente de verificación\n\n";
                $response .= "¿Necesitas algún detalle específico sobre algún usuario? 😊";
                
                return $response;
            }
        }
        
        // Respuestas sobre usuarios
        if (str_contains($questionLower, 'usuario') || str_contains($questionLower, 'user')) {
            if (isset($contextData['users'])) {
                $users = $contextData['users'];
                return "¡Hola! Soy Capi, la mascota de INSECAP 🐾\n\n" .
                       "Te cuento que revisé la plataforma y encontré esta información:\n\n" .
                       "� **Nuestra comunidad actual:**\n" .
                       "• Tenemos {$users['total']} personas registradas en total\n" .
                       "• De estos, {$users['active']} ya están activos y verificados\n" .
                       "• En la última semana se unieron {$users['recent']} nuevos miembros\n\n" .
                       "¡Nuestra comunidad está creciendo! ¿Hay algo más específico que te gustaría saber sobre nuestros usuarios? 😊";
            }
        }
        
        // Respuestas sobre estadísticas
        if (str_contains($questionLower, 'estadistic') || str_contains($questionLower, 'total') || str_contains($questionLower, 'cuant')) {
            if (isset($contextData['statistics'])) {
                $stats = $contextData['statistics'];
                return "¡Hola! Soy Capi, tu amigable asistente de INSECAP 🤖✨\n\n" .
                       "He estado revisando nuestros números y te puedo contar que:\n\n" .
                       "📊 **Estado actual de la plataforma:**\n" .
                       "• Contamos con {$stats['total_users']} miembros en nuestra comunidad\n" .
                       "• Todos nuestros sistemas están funcionando perfectamente\n" .
                       "• La base de datos está actualizada y operativa\n" .
                       "• Última verificación: " . now()->format('d/m/Y a las H:i') . "\n\n" .
                       "Me da mucho gusto ver cómo crece nuestra plataforma. ¿Te gustaría que profundice en algún aspecto específico? 🚀";
            }
        }
        
        // Preguntas sobre quién es Capi
        if (str_contains($questionLower, 'quien eres') || str_contains($questionLower, 'que eres') || str_contains($questionLower, 'capi')) {
            return "¡Hola! 👋 Soy Capi, la mascota oficial de INSECAP 🐾\n\n" .
                   "Me crearon para ser tu compañero digital y ayudarte con todo lo que necesites en nuestra plataforma. " .
                   "Pienso en mí mismo como un amigo que siempre está aquí para ti, las 24 horas del día.\n\n" .
                   "🎯 **Mi misión es ayudarte con:**\n" .
                   "• Consultas sobre usuarios y estadísticas\n" .
                   "• Información general de la plataforma\n" .
                   "• Resolver dudas y brindarte apoyo\n" .
                   "• Ser tu compañero confiable en INSECAP\n\n" .
                   "Aunque soy una IA, me gusta pensar que tenemos una conversación real entre amigos. " .
                   "¿En qué más puedo ayudarte hoy? 😊";
        }
        
        // Respuesta genérica amigable
        return "¡Hola! Soy Capi, la mascota de INSECAP 🐾✨\n\n" .
               "Me da mucho gusto que me escribas. Recibí tu mensaje: \"$question\"\n\n" .
               "Como tu asistente personal en INSECAP, estoy aquí para ayudarte con cualquier cosa que necesites. " .
               "Puedo contarte sobre nuestra comunidad, revisar estadísticas, o simplemente charlar contigo.\n\n" .
               "🔍 **Algunas cosas en las que soy especialmente bueno:**\n" .
               "• Información sobre nuestros usuarios\n" .
               "• Estado y estadísticas de la plataforma\n" .
               "• Datos actualizados del sistema\n" .
               "• ¡Y cualquier duda que tengas!\n\n" .
               "¿Podrías contarme un poco más sobre lo que buscas? Me encanta poder ayudar 😊";
    }

    /**
     * Obtener datos de contexto basados en la pregunta
     */
    private function getContextData(string $question): array
    {
        $contextData = [];
        $questionLower = strtolower($question);

        // Si pregunta sobre usuarios
        if (str_contains($questionLower, 'usuario') || str_contains($questionLower, 'user')) {
            $contextData['users'] = [
                'total' => User::count(),
                'active' => User::whereNotNull('email_verified_at')->count(),
                'recent' => User::where('created_at', '>=', now()->subDays(7))->count(),
            ];

            // Si pregunta específicamente por correos o emails
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

            // Si pregunta por nombres o listado completo
            if (str_contains($questionLower, 'nombre') || str_contains($questionLower, 'listado') || str_contains($questionLower, 'lista')) {
                $contextData['user_list'] = User::select('name', 'email', 'created_at')
                    ->get()
                    ->map(function($user) {
                        return [
                            'name' => $user->name,
                            'email' => $user->email,
                            'registered' => $user->created_at->format('d/m/Y')
                        ];
                    });
            }
        }

        // Si pregunta sobre estadísticas generales
        if (str_contains($questionLower, 'estadistic') || str_contains($questionLower, 'total') || str_contains($questionLower, 'cuant')) {
            $contextData['statistics'] = [
                'total_users' => User::count(),
                'verified_users' => User::whereNotNull('email_verified_at')->count(),
                'unverified_users' => User::whereNull('email_verified_at')->count(),
                'recent_registrations' => User::where('created_at', '>=', now()->subDays(7))->count(),
                'database_tables' => DB::select("SHOW TABLES"),
            ];
        }

        return $contextData;
    }

    /**
     * Construir el prompt con contexto para OpenAI
     */
    private function buildPrompt(string $question, array $contextData): string
    {
        $prompt = "Pregunta del usuario: {$question}\n\n";
        
        if (!empty($contextData)) {
            $prompt .= "Información disponible de la plataforma:\n";
            
            if (isset($contextData['users'])) {
                $users = $contextData['users'];
                $prompt .= "- Total de usuarios registrados: {$users['total']}\n";
                $prompt .= "- Usuarios activos (verificados): {$users['active']}\n";
                $prompt .= "- Usuarios registrados en los últimos 7 días: {$users['recent']}\n";
            }

            if (isset($contextData['user_emails'])) {
                $prompt .= "\nListado de correos de usuarios:\n";
                foreach ($contextData['user_emails'] as $user) {
                    $status = $user['verified'] === 'Sí' ? 'Verificado' : 'No verificado';
                    $prompt .= "- {$user['name']}: {$user['email']} ({$status})\n";
                }
            }

            if (isset($contextData['user_list'])) {
                $prompt .= "\nListado completo de usuarios:\n";
                foreach ($contextData['user_list'] as $user) {
                    $prompt .= "- {$user['name']} ({$user['email']}) - Registrado: {$user['registered']}\n";
                }
            }

            if (isset($contextData['statistics'])) {
                $stats = $contextData['statistics'];
                $prompt .= "- Total de usuarios en la plataforma: {$stats['total_users']}\n";
                $prompt .= "- Usuarios verificados: {$stats['verified_users']}\n";
                $prompt .= "- Usuarios no verificados: {$stats['unverified_users']}\n";
                $prompt .= "- Registros recientes (7 días): {$stats['recent_registrations']}\n";
            }
            
            $prompt .= "\nPor favor, responde la pregunta basándote en esta información.";
        } else {
            $prompt .= "\nNo tengo información específica sobre este tema en la base de datos actual.";
        }

        return $prompt;
    }

    /**
     * Obtener información sobre Capi
     */
    public function about()
    {
        return response()->json([
            'name' => 'Capi',
            'title' => 'Mascota oficial de INSECAP',
            'description' => 'Soy Capi, la mascota de INSECAP y tu compañero digital. Me crearon para ayudarte con todo lo que necesites en nuestra plataforma, como si fuéramos amigos charlando.',
            'personality' => 'Amigable, conversacional y siempre dispuesto a ayudar',
            'organization' => 'INSECAP',
            'capabilities' => [
                'Conversar de manera natural y amigable',
                'Consultar estadísticas de usuarios',
                'Proporcionar información sobre la plataforma',
                'Responder preguntas sobre datos almacenados',
                'Ser tu compañero confiable las 24 horas'
            ],
            'version' => '1.0.0',
            'mode' => env('CAPI_DEMO_MODE', true) ? 'demo' : 'openai',
            'status' => 'online',
            'greeting' => '¡Hola! Soy Capi, la mascota de INSECAP 🐾 ¿En qué puedo ayudarte hoy?'
        ]);
    }
}
