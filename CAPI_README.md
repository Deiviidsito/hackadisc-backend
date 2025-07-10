# Capi - Agente de IA 🤖

Capi es un asistente de IA integrado en la plataforma que puede responder preguntas sobre los datos almacenados en la base de datos, tanto por **texto** como por **voz**.

## ✨ Características Principales

-   💬 **Chat de texto**: Conversación natural con respuestas inteligentes
-   🎤 **Reconocimiento de voz**: Habla con Capi usando tu micrófono (Speech-to-Text)
-   🔊 **Síntesis de voz**: Capi te responde con voz natural (Text-to-Speech)
-   📊 **Acceso a datos**: Consulta información real de usuarios y estadísticas
-   🎯 **Personalidad única**: Mascota amigable de INSECAP con emojis y respuestas cálidas
-   ⚡ **Tiempo real**: Respuestas rápidas y naturales

## Configuración

1. Añade tu clave de API de OpenAI en el archivo `.env`:

```
OPENAI_API_KEY=tu_clave_de_openai_aqui
OPENAI_ORGANIZATION=tu_organizacion_aqui (opcional)
CAPI_DEMO_MODE=true (para pruebas sin OpenAI real)
```

## 📝 Endpoints de Texto

### POST /api/capi/ask

Realiza una pregunta a Capi por texto

**Request:**

```json
{
    "question": "¿Cuántos usuarios hay registrados en la plataforma?"
}
```

**Response:**

```json
{
    "success": true,
    "question": "¿Cuántos usuarios hay registrados en la plataforma?",
    "response": "¡Hola! 😊 Actualmente tenemos 5 usuarios registrados en nuestra plataforma de INSECAP. De estos, 4 están activos y verificados, ¡lo cual es genial! 🎉 En la última semana no hemos tenido nuevos registros, pero estamos siempre listos para recibir más miembros en nuestra comunidad. ¿Te gustaría saber algo más específico sobre nuestros usuarios?",
    "agent": "Capi",
    "mode": "openai"
}
```

### GET /api/capi/about

Obtiene información sobre Capi

**Response:**

```json
{
    "name": "Capi",
    "description": "Asistente de IA para la plataforma de gestión médica",
    "capabilities": [
        "Consultar estadísticas de usuarios",
        "Proporcionar información sobre la plataforma",
        "Responder preguntas sobre datos almacenados",
        "Chat por voz (Speech-to-Text y Text-to-Speech)",
        "Listar correos de usuarios autorizados",
        "Asistir con consultas generales del sistema"
    ],
    "version": "2.0.0"
}
```

## 🎤 Endpoints de Voz

### POST /api/capi/voice/chat

Chat completo por voz: audio → transcripción → respuesta → audio

**Request:**

-   `Content-Type: multipart/form-data`
-   `audio`: Archivo de audio (MP3, WAV, M4A, WebM, max 25MB)

**Response:**

```json
{
    "success": true,
    "transcription": "Hola Capi, ¿cuántos usuarios hay en la plataforma?",
    "text_response": "¡Hola! 😊 Actualmente tenemos 5 usuarios registrados...",
    "audio_response_url": "http://localhost:8000/api/capi/voice/audio/capi_response_1234567890.mp3",
    "agent": "Capi",
    "mode": "openai"
}
```

### POST /api/capi/voice/text-to-speech

Convertir solo texto a voz (para usar con chat de texto existente)

**Request:**

```json
{
    "text": "¡Hola! Soy Capi, la mascota de INSECAP. ¿En qué puedo ayudarte?"
}
```

**Response:**

```json
{
    "success": true,
    "audio_url": "http://localhost:8000/api/capi/voice/audio/capi_response_1234567890.mp3",
    "text": "¡Hola! Soy Capi, la mascota de INSECAP. ¿En qué puedo ayudarte?"
}
```

### GET /api/capi/voice/audio/{file}

Servir archivos de audio generados

**Response:**

-   Archivo MP3 de audio con la respuesta de Capi

## 📱 Ejemplos de Uso

### Chat de Texto

```javascript
// Hacer una pregunta por texto
const response = await fetch("/api/capi/ask", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
        question: "¿Cuántos usuarios activos tenemos?",
    }),
});
```

### Chat de Voz (JavaScript)

```javascript
// Usar el cliente de voz (ver capi-voice-client.js)
const client = new CapiVoiceClient();
await client.init();

// Iniciar grabación
await client.startRecording();
// ... usuario habla ...
client.stopRecording(); // Automáticamente procesa y responde

// O convertir texto a voz
await client.textToSpeech("Hola Capi, cuéntame sobre la plataforma");
```

### Ejemplo con cURL

```bash
# Chat de texto
curl -X POST http://localhost:8000/api/capi/ask \
  -H "Content-Type: application/json" \
  -d '{"question": "¿Cuántos usuarios hay?"}'

# Convertir texto a voz
curl -X POST http://localhost:8000/api/capi/voice/text-to-speech \
  -H "Content-Type: application/json" \
  -d '{"text": "Hola, soy Capi"}'

# Enviar audio (requiere archivo de audio)
curl -X POST http://localhost:8000/api/capi/voice/chat \
  -F "audio=@mensaje.mp3"
```

## 💡 Ejemplos de Preguntas

### Estadísticas de Usuarios

-   "¿Cuántos usuarios hay en la plataforma?"
-   "¿Cuántos usuarios activos tenemos?"
-   "¿Cuántos usuarios se registraron esta semana?"
-   "Dame estadísticas generales de la plataforma"

### Información de Correos (Modo Autorizado)

-   "¿Puedes mostrarme los correos de usuarios?"
-   "Lista todos los emails de usuarios verificados"
-   "¿Qué usuarios tienen correo registrado?"

### Conversacional

-   "Hola Capi, ¿cómo estás?"
-   "Cuéntame sobre la plataforma INSECAP"
-   "¿Qué puedes hacer por mí?"
-   "Ayúdame con información de usuarios"

## 🔧 Funcionalidades Técnicas

### Análisis Inteligente

Capi puede analizar preguntas relacionadas con:

-   **Usuarios**: Estadísticas de registros, usuarios activos, correos
-   **Estadísticas generales**: Información sobre el estado de la plataforma
-   **Consultas específicas**: Basadas en los datos disponibles en la base de datos
-   **Conversación natural**: Respuestas con personalidad y emojis

### Tecnologías Utilizadas

-   **OpenAI GPT-3.5-turbo**: Para generación de respuestas naturales
-   **OpenAI Whisper**: Para reconocimiento de voz (Speech-to-Text)
-   **OpenAI TTS**: Para síntesis de voz (Text-to-Speech)
-   **Laravel**: Backend API
-   **JavaScript**: Cliente web para grabación y reproducción

### Configuración de Voz

-   **Idioma**: Español (es)
-   **Voz**: Nova (femenina, amigable)
-   **Formato de entrada**: MP3, WAV, M4A, WebM (max 25MB)
-   **Formato de salida**: MP3
-   **Modelos**: whisper-1 (STT), tts-1 (TTS)

## 🚀 Archivos de Demostración

1. **`capi-voice-client.js`**: Cliente JavaScript completo para chat de voz
2. **`capi-voice-demo.html`**: Página web demo funcional con UI
3. **`test_capi_voice.php`**: Script de pruebas para endpoints de voz
4. **`test_capi_legible.php`**: Script de pruebas para chat de texto

## 🎯 Modos de Operación

### Modo Demo (`CAPI_DEMO_MODE=true`)

-   Respuestas simuladas sin usar OpenAI real
-   Audio de prueba generado (archivos vacíos)
-   Ideal para desarrollo y pruebas sin consumir tokens

### Modo Producción (`CAPI_DEMO_MODE=false`)

-   Usa OpenAI real para todas las funcionalidades
-   Requiere clave API válida y créditos
-   Funcionalidad completa de STT, IA y TTS

¡Capi está listo para conversar contigo tanto por texto como por voz! 🎉
