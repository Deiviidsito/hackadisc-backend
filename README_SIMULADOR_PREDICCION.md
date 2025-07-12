# 🔮 Simulador de Predicción de Tiempo de Pago - Guía de Integración

## 📋 Descripción

El **Simulador de Predicción de Tiempo de Pago** es una herramienta avanzada con inteligencia artificial que analiza el comportamiento histórico de pagos de los clientes y genera predicciones precisas sobre futuros tiempos de pago. Esta funcionalidad está diseñada para integrarse seamlessly en tu página de analíticas por compañía.

## 🚀 Características Principales

- ✅ **Análisis Predictivo con IA**: Utiliza algoritmos de machine learning
- ✅ **Simulación de Escenarios**: Normal, crisis, bonanza y estacionalidad
- ✅ **Score de Confiabilidad**: Evaluación automática del riesgo del cliente
- ✅ **Recomendaciones Inteligentes**: Sugerencias personalizadas por cliente
- ✅ **Alertas Automáticas**: Detecta patrones anómalos
- ✅ **API REST Completa**: Fácil integración frontend

## 📡 Endpoint de la API

### URL del Endpoint
```
GET /api/clientes-analytics/{clienteId}/simulador-prediccion
```

### Parámetros
| Parámetro | Tipo | Requerido | Descripción |
|-----------|------|-----------|-------------|
| `clienteId` | integer | Sí | ID único del cliente a analizar |

### Headers Requeridos
```http
Accept: application/json
Content-Type: application/json
Authorization: Bearer {token}  // Si tu API requiere autenticación
```

## 📊 Estructura de la Respuesta

```json
{
  "cliente_id": 519,
  "resumen_datos": {
    "total_facturas": 145,
    "facturas_pagadas": 123,
    "facturas_pendientes": 22,
    "periodo_analizado": "2024-01-01 a 2025-07-12"
  },
  "analisis_patrones": {
    "tiempo_promedio_pago": 42.5,
    "consistencia_pago": 87.3,
    "volatilidad": 15.2,
    "patron_detectado": "consistente",
    "anomalias_detectadas": []
  },
  "predicciones_ia": {
    "regresion_lineal": {
      "disponible": true,
      "prediccion_proximo_pago": 39.2,
      "confianza": 84.5
    },
    "promedio_movil": {
      "disponible": true,
      "prediccion": 41.0,
      "tendencia": "mejorando"
    },
    "red_neuronal": {
      "disponible": false,
      "mensaje": "Datos insuficientes para entrenamiento"
    },
    "algoritmo_bayesiano": {
      "disponible": true,
      "probabilidad_pago_30_dias": 0.78,
      "probabilidad_pago_60_dias": 0.94
    }
  },
  "simulacion_escenarios": {
    "escenario_normal": {
      "promedio_proyectado": 42.5,
      "mediana_proyectada": 38.0,
      "confianza": 85
    },
    "escenario_crisis": {
      "promedio_proyectado": 55.3,
      "mediana_proyectada": 49.4,
      "confianza": 70
    },
    "escenario_bonanza": {
      "promedio_proyectado": 34.0,
      "mediana_proyectada": 30.4,
      "confianza": 75
    },
    "escenario_estacional": {
      "disponible": true,
      "mejor_mes": "marzo",
      "peor_mes": "diciembre",
      "diferencia_estacional": 15.2
    }
  },
  "score_confiabilidad": {
    "score": 87,
    "categoria": "Excelente",
    "nivel_confianza": "ALTA",
    "factores_evaluados": {
      "historial_pagos": 40,
      "consistencia": 35,
      "volumen_transacciones": 30,
      "tendencia_reciente": 25
    }
  },
  "recomendaciones": [
    "Cliente de alta confiabilidad - apto para líneas de crédito extendidas",
    "Patrón de pago muy consistente - ideal para facturación automática",
    "Considerar descuentos por pronto pago para optimizar flujo de caja"
  ],
  "alertas": [],
  "analisis_riesgo": {
    "nivel_riesgo": "BAJO",
    "exposicion_recomendada": "Alta",
    "limite_credito_sugerido": 2500000,
    "condiciones_pago_optimas": {
      "plazo_recomendado": 30,
      "tipo": "estándar",
      "descuento_pronto_pago": 2
    }
  }
}
```

## 🔧 Integración Frontend

### 1. JavaScript Vanilla / Fetch API

```javascript
/**
 * Obtiene datos del simulador de predicción para un cliente
 * @param {number} clienteId - ID del cliente
 * @returns {Promise<Object|null>} Datos del simulador o null en caso de error
 */
async function obtenerSimuladorPrediccion(clienteId) {
    try {
        const response = await fetch(`/api/clientes-analytics/${clienteId}/simulador-prediccion`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${localStorage.getItem('authToken')}`
            }
        });
        
        if (!response.ok) {
            throw new Error(`HTTP Error: ${response.status} - ${response.statusText}`);
        }
        
        const datos = await response.json();
        console.log('✅ Simulador cargado exitosamente:', datos);
        return datos;
    } catch (error) {
        console.error('❌ Error al obtener simulador de predicción:', error);
        mostrarErrorUsuario('No se pudo cargar el simulador de predicción');
        return null;
    }
}

/**
 * Integra el simulador en la página de analíticas
 * @param {number} clienteId - ID del cliente actual
 */
async function integrarSimuladorEnAnalytics(clienteId) {
    // Mostrar loading
    mostrarLoadingSimulador();
    
    // Obtener datos
    const datosSimulador = await obtenerSimuladorPrediccion(clienteId);
    
    if (datosSimulador) {
        // Renderizar componente
        renderizarSimulador(datosSimulador);
        
        // Inicializar interacciones
        inicializarInteraccionesSimulador();
    } else {
        // Mostrar estado de error
        mostrarErrorSimulador();
    }
}
```

### 2. jQuery/AJAX

```javascript
/**
 * Carga el simulador usando jQuery
 * @param {number} clienteId - ID del cliente
 */
function cargarSimuladorJQuery(clienteId) {
    $.ajax({
        url: `/api/clientes-analytics/${clienteId}/simulador-prediccion`,
        method: 'GET',
        dataType: 'json',
        headers: {
            'Authorization': `Bearer ${getAuthToken()}`
        },
        beforeSend: function() {
            $('#simulador-container').html('<div class="loading">Cargando simulador...</div>');
        },
        success: function(response) {
            console.log('Simulador cargado:', response);
            renderizarSimuladorJQuery(response);
        },
        error: function(xhr, status, error) {
            console.error('Error AJAX:', error);
            $('#simulador-container').html('<div class="error">Error al cargar simulador</div>');
        }
    });
}
```

### 3. React Hook

```javascript
import { useState, useEffect } from 'react';

/**
 * Hook personalizado para el simulador de predicción
 * @param {number} clienteId - ID del cliente
 * @returns {Object} Estado del simulador
 */
export const useSimuladorPrediccion = (clienteId) => {
    const [datos, setDatos] = useState(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    
    useEffect(() => {
        if (!clienteId) return;
        
        const cargarSimulador = async () => {
            setLoading(true);
            setError(null);
            
            try {
                const response = await fetch(`/api/clientes-analytics/${clienteId}/simulador-prediccion`);
                if (!response.ok) throw new Error('Error en la respuesta');
                
                const datosSimulador = await response.json();
                setDatos(datosSimulador);
            } catch (err) {
                setError(err.message);
            } finally {
                setLoading(false);
            }
        };
        
        cargarSimulador();
    }, [clienteId]);
    
    return { datos, loading, error, refetch: () => cargarSimulador() };
};

// Componente React
export const SimuladorPrediccion = ({ clienteId }) => {
    const { datos, loading, error } = useSimuladorPrediccion(clienteId);
    
    if (loading) return <div>Cargando simulador...</div>;
    if (error) return <div>Error: {error}</div>;
    if (!datos) return null;
    
    return (
        <div className="simulador-prediccion">
            <ScoreConfiabilidad score={datos.score_confiabilidad} />
            <AnalisisPatrones patrones={datos.analisis_patrones} />
            <SimulacionEscenarios escenarios={datos.simulacion_escenarios} />
            <Recomendaciones lista={datos.recomendaciones} />
        </div>
    );
};
```

## 🎨 Componentes de UI Recomendados

### 1. HTML Base

```html
<!-- Contenedor principal del simulador -->
<div class="simulador-container" id="simulador-prediccion">
    <!-- Header con título y badge IA -->
    <div class="simulador-header">
        <h3>🔮 Simulador de Predicción de Tiempo de Pago</h3>
        <span class="ai-badge">Con IA</span>
        <button class="refresh-btn" onclick="refrescarSimulador()">🔄</button>
    </div>
    
    <!-- Score de confiabilidad -->
    <div class="score-section">
        <div class="score-circle" id="score-circle">
            <span class="score-number" id="score-number">--</span>
            <span class="score-category" id="score-category">--</span>
        </div>
        <div class="score-details" id="score-details"></div>
    </div>
    
    <!-- Métricas principales -->
    <div class="metricas-principales" id="metricas-principales">
        <!-- Se genera dinámicamente -->
    </div>
    
    <!-- Simulación de escenarios -->
    <div class="escenarios-section" id="escenarios-section">
        <!-- Se genera dinámicamente -->
    </div>
    
    <!-- Recomendaciones IA -->
    <div class="recomendaciones-section" id="recomendaciones-section">
        <!-- Se genera dinámicamente -->
    </div>
    
    <!-- Alertas (si existen) -->
    <div class="alertas-section" id="alertas-section" style="display: none;">
        <!-- Se genera dinámicamente -->
    </div>
</div>
```

### 2. CSS Estilos

```css
/* Contenedor principal */
.simulador-container {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 12px;
    padding: 24px;
    margin: 20px 0;
    color: white;
    box-shadow: 0 8px 32px rgba(102, 126, 234, 0.3);
}

/* Header */
.simulador-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
}

.ai-badge {
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.3);
    padding: 6px 16px;
    border-radius: 20px;
    font-size: 0.85em;
    font-weight: 600;
}

.refresh-btn {
    background: rgba(255, 255, 255, 0.2);
    border: none;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.refresh-btn:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: rotate(180deg);
}

/* Score de confiabilidad */
.score-section {
    text-align: center;
    margin-bottom: 32px;
}

.score-circle {
    width: 140px;
    height: 140px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    border: 3px solid rgba(255, 255, 255, 0.3);
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    margin: 0 auto 16px;
    position: relative;
    overflow: hidden;
}

.score-circle::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: conic-gradient(from 0deg, transparent, rgba(255, 255, 255, 0.3));
    border-radius: 50%;
    animation: rotate 3s linear infinite;
}

@keyframes rotate {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.score-number {
    font-size: 2.5em;
    font-weight: bold;
    z-index: 1;
}

.score-category {
    font-size: 0.9em;
    opacity: 0.9;
    z-index: 1;
}

/* Métricas */
.metricas-principales {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.metrica-card {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border-radius: 8px;
    padding: 16px;
    text-align: center;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.metrica-valor {
    font-size: 1.8em;
    font-weight: bold;
    margin-bottom: 4px;
}

.metrica-label {
    font-size: 0.85em;
    opacity: 0.8;
}

/* Escenarios */
.escenarios-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.escenario-card {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border-radius: 8px;
    padding: 16px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    transition: all 0.3s ease;
}

.escenario-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
}

.escenario-titulo {
    font-weight: 600;
    margin-bottom: 8px;
    text-transform: capitalize;
}

.escenario-valor {
    font-size: 1.4em;
    font-weight: bold;
    margin: 8px 0;
}

.escenario-confianza {
    font-size: 0.85em;
    opacity: 0.8;
}

/* Recomendaciones */
.recomendaciones-lista {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border-radius: 8px;
    padding: 20px;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.recomendacion-item {
    display: flex;
    align-items: flex-start;
    margin-bottom: 12px;
    padding: 12px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 6px;
    border-left: 4px solid rgba(255, 255, 255, 0.3);
}

.recomendacion-item:last-child {
    margin-bottom: 0;
}

.recomendacion-icon {
    margin-right: 12px;
    font-size: 1.2em;
}

/* Alertas */
.alertas-section {
    background: rgba(255, 107, 107, 0.2);
    border: 1px solid rgba(255, 107, 107, 0.4);
    border-radius: 8px;
    padding: 16px;
    margin-top: 16px;
}

.alerta-item {
    display: flex;
    align-items: center;
    margin-bottom: 8px;
}

.alerta-icon {
    margin-right: 8px;
    font-size: 1.1em;
}

/* Estados de carga */
.loading-simulador {
    text-align: center;
    padding: 60px 20px;
    color: rgba(255, 255, 255, 0.8);
}

.loading-spinner {
    width: 40px;
    height: 40px;
    border: 3px solid rgba(255, 255, 255, 0.3);
    border-top: 3px solid white;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 16px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Responsive */
@media (max-width: 768px) {
    .simulador-container {
        padding: 16px;
        margin: 10px 0;
    }
    
    .metricas-principales,
    .escenarios-grid {
        grid-template-columns: 1fr;
    }
    
    .score-circle {
        width: 120px;
        height: 120px;
    }
    
    .score-number {
        font-size: 2em;
    }
}
```

### 3. JavaScript para Renderizado

```javascript
/**
 * Renderiza el simulador completo en el DOM
 * @param {Object} datos - Datos del simulador obtenidos de la API
 */
function renderizarSimulador(datos) {
    // Renderizar score de confiabilidad
    renderizarScore(datos.score_confiabilidad);
    
    // Renderizar métricas principales
    renderizarMetricas(datos.analisis_patrones);
    
    // Renderizar escenarios
    renderizarEscenarios(datos.simulacion_escenarios);
    
    // Renderizar recomendaciones
    renderizarRecomendaciones(datos.recomendaciones);
    
    // Renderizar alertas si existen
    if (datos.alertas && datos.alertas.length > 0) {
        renderizarAlertas(datos.alertas);
    }
    
    // Animar entrada
    animarEntradaSimulador();
}

/**
 * Renderiza el score de confiabilidad
 * @param {Object} scoreData - Datos del score
 */
function renderizarScore(scoreData) {
    const scoreCircle = document.getElementById('score-circle');
    const scoreNumber = document.getElementById('score-number');
    const scoreCategory = document.getElementById('score-category');
    const scoreDetails = document.getElementById('score-details');
    
    // Actualizar valores
    scoreNumber.textContent = scoreData.score;
    scoreCategory.textContent = scoreData.categoria;
    
    // Aplicar color según categoría
    scoreCircle.className = `score-circle score-${scoreData.categoria.toLowerCase()}`;
    
    // Mostrar detalles
    scoreDetails.innerHTML = `
        <div class="score-info">
            <p><strong>Nivel de Confianza:</strong> ${scoreData.nivel_confianza}</p>
            <div class="factores-evaluados">
                <h4>Factores Evaluados:</h4>
                ${Object.entries(scoreData.factores_evaluados || {}).map(([factor, valor]) => 
                    `<span class="factor">${factor.replace('_', ' ')}: ${valor}%</span>`
                ).join('')}
            </div>
        </div>
    `;
    
    // Animar score
    animarScore(scoreData.score);
}

/**
 * Renderiza las métricas principales
 * @param {Object} patrones - Datos de análisis de patrones
 */
function renderizarMetricas(patrones) {
    const container = document.getElementById('metricas-principales');
    
    const metricas = [
        {
            valor: `${patrones.tiempo_promedio_pago} días`,
            label: 'Tiempo Promedio de Pago',
            icon: '⏱️'
        },
        {
            valor: `${patrones.consistencia_pago}%`,
            label: 'Consistencia',
            icon: '📊'
        },
        {
            valor: `${patrones.volatilidad}%`,
            label: 'Volatilidad',
            icon: '📈'
        },
        {
            valor: patrones.patron_detectado.replace('_', ' '),
            label: 'Patrón Detectado',
            icon: '🔍'
        }
    ];
    
    container.innerHTML = metricas.map(metrica => `
        <div class="metrica-card">
            <div class="metrica-icon">${metrica.icon}</div>
            <div class="metrica-valor">${metrica.valor}</div>
            <div class="metrica-label">${metrica.label}</div>
        </div>
    `).join('');
}

/**
 * Renderiza los escenarios de simulación
 * @param {Object} escenarios - Datos de simulación de escenarios
 */
function renderizarEscenarios(escenarios) {
    const container = document.getElementById('escenarios-section');
    
    container.innerHTML = `
        <h4>🎯 Simulación de Escenarios</h4>
        <div class="escenarios-grid">
            ${Object.entries(escenarios).map(([nombre, datos]) => {
                if (nombre === 'escenario_estacional' && !datos.disponible) {
                    return '';
                }
                
                return `
                    <div class="escenario-card escenario-${nombre.replace('escenario_', '')}">
                        <div class="escenario-titulo">
                            ${formatearNombreEscenario(nombre)}
                        </div>
                        <div class="escenario-valor">
                            ${datos.promedio_proyectado || datos.diferencia_estacional || '--'} 
                            ${nombre === 'escenario_estacional' ? 'días diff' : 'días'}
                        </div>
                        <div class="escenario-confianza">
                            Confianza: ${datos.confianza || '--'}%
                        </div>
                        ${datos.mejor_mes ? `
                            <div class="escenario-extra">
                                <small>Mejor: ${datos.mejor_mes}</small><br>
                                <small>Peor: ${datos.peor_mes}</small>
                            </div>
                        ` : ''}
                    </div>
                `;
            }).join('')}
        </div>
    `;
}

/**
 * Renderiza las recomendaciones
 * @param {Array} recomendaciones - Lista de recomendaciones
 */
function renderizarRecomendaciones(recomendaciones) {
    const container = document.getElementById('recomendaciones-section');
    
    container.innerHTML = `
        <h4>💡 Recomendaciones Inteligentes</h4>
        <div class="recomendaciones-lista">
            ${recomendaciones.map((rec, index) => `
                <div class="recomendacion-item">
                    <span class="recomendacion-icon">💡</span>
                    <span class="recomendacion-texto">${rec}</span>
                </div>
            `).join('')}
        </div>
    `;
}

/**
 * Renderiza alertas si existen
 * @param {Array} alertas - Lista de alertas
 */
function renderizarAlertas(alertas) {
    const container = document.getElementById('alertas-section');
    
    if (alertas.length > 0) {
        container.style.display = 'block';
        container.innerHTML = `
            <h4>⚠️ Alertas del Sistema</h4>
            <div class="alertas-lista">
                ${alertas.map(alerta => `
                    <div class="alerta-item">
                        <span class="alerta-icon">⚠️</span>
                        <span class="alerta-texto">${alerta}</span>
                    </div>
                `).join('')}
            </div>
        `;
    } else {
        container.style.display = 'none';
    }
}

// Funciones de utilidad
function formatearNombreEscenario(nombre) {
    const nombres = {
        'escenario_normal': 'Normal',
        'escenario_crisis': 'Crisis Económica',
        'escenario_bonanza': 'Bonanza Económica',
        'escenario_estacional': 'Variación Estacional'
    };
    return nombres[nombre] || nombre;
}

function animarScore(targetScore) {
    const element = document.getElementById('score-number');
    let currentScore = 0;
    const increment = targetScore / 100;
    
    const animation = setInterval(() => {
        currentScore += increment;
        if (currentScore >= targetScore) {
            currentScore = targetScore;
            clearInterval(animation);
        }
        element.textContent = Math.round(currentScore);
    }, 20);
}

function animarEntradaSimulador() {
    const container = document.getElementById('simulador-prediccion');
    container.style.opacity = '0';
    container.style.transform = 'translateY(20px)';
    
    setTimeout(() => {
        container.style.transition = 'all 0.6s ease';
        container.style.opacity = '1';
        container.style.transform = 'translateY(0)';
    }, 100);
}

// Estados de carga y error
function mostrarLoadingSimulador() {
    const container = document.getElementById('simulador-prediccion');
    container.innerHTML = `
        <div class="loading-simulador">
            <div class="loading-spinner"></div>
            <p>Analizando datos con IA...</p>
        </div>
    `;
}

function mostrarErrorSimulador() {
    const container = document.getElementById('simulador-prediccion');
    container.innerHTML = `
        <div class="error-simulador">
            <div class="error-icon">❌</div>
            <h3>Error al cargar el simulador</h3>
            <p>No se pudieron obtener las predicciones. Intente nuevamente.</p>
            <button onclick="location.reload()" class="retry-btn">🔄 Reintentar</button>
        </div>
    `;
}

function mostrarErrorUsuario(mensaje) {
    // Implementar notificación toast o modal
    console.error(mensaje);
}

// Función para refrescar el simulador
function refrescarSimulador() {
    const clienteId = getCurrentClienteId(); // Implementar según tu lógica
    integrarSimuladorEnAnalytics(clienteId);
}
```

## 🔄 Flujo de Integración Completo

### Paso 1: Preparar el HTML
Agrega el contenedor del simulador en tu página de analíticas:

```html
<!-- En tu página de analíticas existente -->
<div class="analytics-dashboard">
    <!-- Tus secciones existentes -->
    <div class="section"><!-- Datos generales --></div>
    <div class="section"><!-- Ventas --></div>
    <div class="section"><!-- Facturación --></div>
    
    <!-- NUEVA SECCIÓN: Simulador de Predicción -->
    <div class="section simulador-section">
        <div id="simulador-prediccion">
            <!-- Se carga dinámicamente -->
        </div>
    </div>
</div>
```

### Paso 2: Cargar en tu función principal
```javascript
// En tu función principal de carga de analíticas
async function cargarAnalyticsCompletas(clienteId) {
    try {
        // Mostrar loading general
        mostrarLoadingGeneral();
        
        // Cargar todas las secciones en paralelo
        const promesas = [
            cargarDatosGenerales(clienteId),
            cargarVentas(clienteId), 
            cargarFacturacion(clienteId),
            integrarSimuladorEnAnalytics(clienteId) // NUEVA LÍNEA
        ];
        
        await Promise.all(promesas);
        
        // Ocultar loading
        ocultarLoadingGeneral();
        
        console.log('✅ Analytics completas cargadas');
    } catch (error) {
        console.error('❌ Error cargando analytics:', error);
        mostrarErrorGeneral();
    }
}
```

### Paso 3: Inicializar en el evento DOMContentLoaded
```javascript
document.addEventListener('DOMContentLoaded', function() {
    // Obtener ID del cliente desde la URL o contexto
    const clienteId = obtenerClienteIdDesdeURL(); // o window.clienteId, etc.
    
    if (clienteId) {
        cargarAnalyticsCompletas(clienteId);
    } else {
        console.error('❌ No se encontró ID del cliente');
        mostrarErrorCliente();
    }
});
```

## 🔧 Configuración Avanzada

### Variables de Entorno
```javascript
// config.js
const CONFIG = {
    API_BASE_URL: process.env.API_URL || '/api',
    SIMULADOR_ENDPOINT: '/clientes-analytics/{clienteId}/simulador-prediccion',
    REFRESH_INTERVAL: 300000, // 5 minutos
    ANIMATION_DURATION: 600,
    DEBUG_MODE: process.env.NODE_ENV === 'development'
};
```

### Manejo de Cache
```javascript
class SimuladorCache {
    constructor(ttl = 300000) { // 5 minutos TTL
        this.cache = new Map();
        this.ttl = ttl;
    }
    
    get(clienteId) {
        const item = this.cache.get(clienteId);
        if (!item) return null;
        
        if (Date.now() - item.timestamp > this.ttl) {
            this.cache.delete(clienteId);
            return null;
        }
        
        return item.data;
    }
    
    set(clienteId, data) {
        this.cache.set(clienteId, {
            data,
            timestamp: Date.now()
        });
    }
    
    clear() {
        this.cache.clear();
    }
}

const simuladorCache = new SimuladorCache();
```

## 🧪 Testing

### Test de Integración
```javascript
// test-simulador.js
describe('Simulador de Predicción', () => {
    test('debe cargar datos correctamente', async () => {
        const clienteId = 519;
        const datos = await obtenerSimuladorPrediccion(clienteId);
        
        expect(datos).not.toBeNull();
        expect(datos.cliente_id).toBe(clienteId);
        expect(datos.score_confiabilidad).toBeDefined();
        expect(datos.score_confiabilidad.score).toBeGreaterThan(0);
    });
    
    test('debe manejar errores correctamente', async () => {
        const clienteId = 99999; // Cliente inexistente
        const datos = await obtenerSimuladorPrediccion(clienteId);
        
        expect(datos).toBeNull();
    });
});
```

### Test Manual con cURL
```bash
# Test básico
curl -X GET "http://localhost:8000/api/clientes-analytics/519/simulador-prediccion" \
     -H "Accept: application/json" \
     -H "Content-Type: application/json"

# Test con autenticación
curl -X GET "http://localhost:8000/api/clientes-analytics/519/simulador-prediccion" \
     -H "Accept: application/json" \
     -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

## 📱 Responsive Design

El simulador está optimizado para todas las pantallas:

- **Desktop**: Grid completo con todas las métricas visibles
- **Tablet**: Grid adaptativo con 2 columnas
- **Mobile**: Vista en columna única con navegación táctil

## 🔒 Consideraciones de Seguridad

1. **Autenticación**: Verificar que el usuario tenga permisos para ver el cliente
2. **Rate Limiting**: Implementar límites en el backend para evitar spam
3. **Validación**: Validar el clienteId en el frontend antes de enviar
4. **HTTPS**: Usar siempre conexiones seguras en producción

## 🚀 Deployment

### Checklist de Deployment
- [ ] Verificar que el endpoint esté disponible en producción
- [ ] Configurar variables de entorno correctas
- [ ] Testear con datos reales
- [ ] Verificar responsive design
- [ ] Configurar monitoreo de errores
- [ ] Documentar para el equipo

## 📞 Soporte

Para soporte técnico o consultas sobre la integración:

- **Desarrollador**: Tu equipo de desarrollo
- **Documentación API**: `/api/documentation`
- **Logs de Error**: Revisar consola del navegador y logs del servidor
- **Testing**: Usar herramientas como Postman para probar el endpoint

---

**¡Listo!** 🎉 Con esta guía tienes todo lo necesario para integrar exitosamente el Simulador de Predicción de Tiempo de Pago en tu página de analíticas.
