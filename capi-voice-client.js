/**
 * Cliente JavaScript para interactuar con Capi por voz
 * Incluye grabación de audio, envío a la API y reproducción de respuestas
 */

class CapiVoiceClient {
    constructor(baseUrl = "http://localhost:8000/api/capi") {
        this.baseUrl = baseUrl;
        this.mediaRecorder = null;
        this.audioChunks = [];
        this.isRecording = false;
        this.currentAudio = null;
    }

    /**
     * Inicializar el cliente y solicitar permisos de micrófono
     */
    async init() {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                audio: true,
            });
            console.log("🎤 Micrófono autorizado");
            stream.getTracks().forEach((track) => track.stop()); // Liberar por ahora
            return true;
        } catch (error) {
            console.error("❌ Error al acceder al micrófono:", error);
            return false;
        }
    }

    /**
     * Iniciar grabación de audio
     */
    async startRecording() {
        if (this.isRecording) {
            console.warn("⚠️ Ya se está grabando");
            return;
        }

        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                audio: true,
            });
            this.mediaRecorder = new MediaRecorder(stream);
            this.audioChunks = [];

            this.mediaRecorder.ondataavailable = (event) => {
                if (event.data.size > 0) {
                    this.audioChunks.push(event.data);
                }
            };

            this.mediaRecorder.onstop = () => {
                const audioBlob = new Blob(this.audioChunks, {
                    type: "audio/webm",
                });
                this.sendAudioToCapi(audioBlob);
            };

            this.mediaRecorder.start();
            this.isRecording = true;
            console.log("🎤 Grabación iniciada");
        } catch (error) {
            console.error("❌ Error al iniciar grabación:", error);
        }
    }

    /**
     * Detener grabación y enviar a Capi
     */
    stopRecording() {
        if (!this.isRecording) {
            console.warn("⚠️ No se está grabando");
            return;
        }

        this.mediaRecorder.stop();
        this.isRecording = false;
        console.log("⏹️ Grabación detenida");

        // Liberar el micrófono
        this.mediaRecorder.stream.getTracks().forEach((track) => track.stop());
    }

    /**
     * Enviar audio a Capi y recibir respuesta
     */
    async sendAudioToCapi(audioBlob) {
        const formData = new FormData();
        formData.append("audio", audioBlob, "voice_message.webm");

        try {
            console.log("📤 Enviando audio a Capi...");

            const response = await fetch(`${this.baseUrl}/voice/chat`, {
                method: "POST",
                body: formData,
            });

            const result = await response.json();

            if (result.success) {
                console.log("✅ Respuesta de Capi recibida");
                console.log("📝 Transcripción:", result.transcription);
                console.log("💬 Respuesta texto:", result.text_response);

                // Reproducir respuesta en audio
                this.playAudioResponse(result.audio_response_url);

                return result;
            } else {
                console.error("❌ Error en la respuesta:", result.message);
                return null;
            }
        } catch (error) {
            console.error("❌ Error al comunicarse con Capi:", error);
            return null;
        }
    }

    /**
     * Reproducir respuesta de audio de Capi
     */
    playAudioResponse(audioUrl) {
        if (this.currentAudio) {
            this.currentAudio.pause();
        }

        this.currentAudio = new Audio(audioUrl);

        this.currentAudio.onplay = () => {
            console.log("🔊 Reproduciendo respuesta de Capi");
        };

        this.currentAudio.onended = () => {
            console.log("✅ Respuesta de Capi terminada");
        };

        this.currentAudio.onerror = () => {
            console.error("❌ Error al reproducir audio");
        };

        this.currentAudio.play();
    }

    /**
     * Convertir solo texto a voz (sin grabación)
     */
    async textToSpeech(text) {
        try {
            const response = await fetch(
                `${this.baseUrl}/voice/text-to-speech`,
                {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                    },
                    body: JSON.stringify({ text }),
                }
            );

            const result = await response.json();

            if (result.success) {
                console.log("🔊 Audio generado para texto");
                this.playAudioResponse(result.audio_url);
                return result.audio_url;
            } else {
                console.error("❌ Error al generar audio:", result.message);
                return null;
            }
        } catch (error) {
            console.error("❌ Error en text-to-speech:", error);
            return null;
        }
    }

    /**
     * Detener cualquier audio que se esté reproduciendo
     */
    stopAudio() {
        if (this.currentAudio) {
            this.currentAudio.pause();
            this.currentAudio.currentTime = 0;
            console.log("⏹️ Audio detenido");
        }
    }
}

// Ejemplo de uso en una página web
class CapiVoiceUI {
    constructor() {
        this.client = new CapiVoiceClient();
        this.initUI();
    }

    async initUI() {
        // Crear elementos de UI
        const container = document.createElement("div");
        container.innerHTML = `
            <div style="padding: 20px; max-width: 500px; margin: 0 auto; font-family: Arial, sans-serif;">
                <h2>🤖 Chat de Voz con Capi</h2>
                
                <div style="margin: 20px 0;">
                    <button id="recordBtn" style="background: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;">
                        🎤 Iniciar Grabación
                    </button>
                    <button id="stopBtn" style="background: #dc3545; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; margin-left: 10px;" disabled>
                        ⏹️ Detener
                    </button>
                </div>

                <div style="margin: 20px 0;">
                    <input type="text" id="textInput" placeholder="O escribe un texto para que Capi lo diga..." style="width: 300px; padding: 8px;">
                    <button id="speakBtn" style="background: #28a745; color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer; margin-left: 10px;">
                        🔊 Hablar
                    </button>
                </div>

                <div id="status" style="margin: 20px 0; padding: 10px; background: #f8f9fa; border-radius: 5px;">
                    Listo para hablar con Capi
                </div>

                <div id="transcription" style="margin: 20px 0; padding: 10px; background: #e9ecef; border-radius: 5px; display: none;">
                    <strong>Tu dijiste:</strong> <span id="transcriptionText"></span>
                </div>

                <div id="response" style="margin: 20px 0; padding: 10px; background: #d1ecf1; border-radius: 5px; display: none;">
                    <strong>Capi responde:</strong> <span id="responseText"></span>
                </div>
            </div>
        `;

        document.body.appendChild(container);

        // Configurar eventos
        this.setupEvents();

        // Inicializar cliente
        const initialized = await this.client.init();
        if (initialized) {
            this.updateStatus("✅ Micrófono listo. ¡Puedes hablar con Capi!");
        } else {
            this.updateStatus("❌ No se pudo acceder al micrófono");
        }
    }

    setupEvents() {
        const recordBtn = document.getElementById("recordBtn");
        const stopBtn = document.getElementById("stopBtn");
        const speakBtn = document.getElementById("speakBtn");
        const textInput = document.getElementById("textInput");

        recordBtn.addEventListener("click", () => {
            this.client.startRecording();
            recordBtn.disabled = true;
            stopBtn.disabled = false;
            this.updateStatus("🎤 Grabando... Habla ahora");
        });

        stopBtn.addEventListener("click", () => {
            this.client.stopRecording();
            recordBtn.disabled = false;
            stopBtn.disabled = true;
            this.updateStatus("📤 Enviando a Capi...");
        });

        speakBtn.addEventListener("click", () => {
            const text = textInput.value.trim();
            if (text) {
                this.client.textToSpeech(text);
                this.updateStatus("🔊 Capi está hablando...");
            }
        });

        // Override del cliente para mostrar respuestas en UI
        const originalSendAudio = this.client.sendAudioToCapi.bind(this.client);
        this.client.sendAudioToCapi = async (audioBlob) => {
            const result = await originalSendAudio(audioBlob);
            if (result) {
                this.showTranscription(result.transcription);
                this.showResponse(result.text_response);
                this.updateStatus("🔊 Escucha la respuesta de Capi");
            } else {
                this.updateStatus("❌ Error al procesar tu mensaje");
            }
            return result;
        };
    }

    updateStatus(message) {
        document.getElementById("status").textContent = message;
    }

    showTranscription(text) {
        document.getElementById("transcriptionText").textContent = text;
        document.getElementById("transcription").style.display = "block";
    }

    showResponse(text) {
        document.getElementById("responseText").textContent = text;
        document.getElementById("response").style.display = "block";
    }
}

// Para usar en una página web, simplemente:
// const capiUI = new CapiVoiceUI();

// Para usar solo el cliente sin UI:
// const client = new CapiVoiceClient();
// await client.init();
// await client.startRecording();
// // ... hablar ...
// client.stopRecording();
