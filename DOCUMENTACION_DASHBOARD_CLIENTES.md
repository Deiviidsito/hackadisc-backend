# Documentación de Endpoints de Analíticas - Dashboard de Clientes

## Resumen de Mejoras Implementadas

### ✅ Endpoints Operativos

1. **GET /api/clientes-dashboard** - Lista básica de clientes
2. **GET /api/clientes-dashboard-avanzado** - Lista avanzada con filtros
3. **GET /api/cliente-dashboard/{nombreCliente}** - Dashboard específico de cliente

### 🔧 Correcciones y Optimizaciones Realizadas

#### 1. Corrección de Nombres de Campos

-   ✅ Corregidos todos los nombres de columnas según la estructura real de la base de datos
-   ✅ Relación corregida: `Venta.CodigoCotizacion` → `Factura.numero`
-   ✅ Campo corregido: `estado_id` en lugar de `estado_factura_id`

#### 2. Optimización de Consultas

-   ✅ Consultas batch para facturas y historiales (reducción de N+1 queries)
-   ✅ Uso de `whereIn()` para optimizar búsquedas múltiples
-   ✅ Agregado de índices en migraciones para mejorar rendimiento

#### 3. Mejora en Cálculo de Días de Comercialización

-   ✅ Manejo de casos sin facturas asociadas
-   ✅ Respaldo con fechas de ventas cuando no hay historial de facturas
-   ✅ Cálculo desde inicio hasta fecha actual como último recurso

#### 4. Estadísticas de Facturas Mejoradas

-   ✅ Total de facturas, pagadas, pendientes y vencidas
-   ✅ Porcentaje de facturas pagadas
-   ✅ Ingresos totales vs ingresos pagados
-   ✅ Valor promedio de facturas

## Endpoints Documentados

### 1. Lista Básica de Clientes Dashboard

```http
GET /api/clientes-dashboard
```

**Response (200):**

```json
{
    "success": true,
    "data": [
        {
            "nombre_cliente": "Syncore Montajes Industriales",
            "total_ventas": 217,
            "total_ingresos": 189874200.0,
            "ventas_canceladas": 2,
            "porcentaje_facturas_pagadas": 0,
            "primera_comercializacion": "2024-01-15",
            "ultima_comercializacion": "2024-12-10",
            "estado_actividad": "Activo"
        }
    ],
    "total_clientes": 25
}
```

### 2. Lista Avanzada con Filtros y Paginación

```http
GET /api/clientes-dashboard-avanzado?limit=10&offset=0&sort_by=total_ingresos&order=desc&estado_actividad=Activo&monto_minimo=100000&ventas_minimas=5
```

**Parámetros de consulta:**

-   `limit` (int): Máximo 200, default 50
-   `offset` (int): Desplazamiento, default 0
-   `sort_by` (string): `total_ventas`, `total_ingresos`, `porcentaje_facturas_pagadas`, `nombre_cliente`
-   `order` (string): `asc` o `desc`
-   `estado_actividad` (string): `Activo`, `Poco Activo`, `Inactivo`
-   `monto_minimo` (float): Monto mínimo de ingresos
-   `ventas_minimas` (int): Número mínimo de ventas

**Response (200):**

```json
{
  "success": true,
  "data": [...],
  "pagination": {
    "total_clientes": 150,
    "limit": 10,
    "offset": 0,
    "has_more": true
  },
  "filtros_aplicados": {
    "estado_actividad": "Activo",
    "monto_minimo": 100000,
    "ventas_minimas": 5,
    "sort_by": "total_ingresos",
    "order": "desc"
  }
}
```

### 3. Dashboard Específico de Cliente

```http
GET /api/cliente-dashboard/{nombreCliente}
```

**Ejemplo:**

```http
GET /api/cliente-dashboard/Syncore%20Montajes%20Industriales
```

**Response (200):**

```json
{
    "success": true,
    "data": {
        "cliente_nombre": "Syncore Montajes Industriales",
        "total_ventas": 217,
        "dias_comercializacion": 90,
        "facturas_estadisticas": {
            "total_facturas": 150,
            "facturas_pagadas": 45,
            "facturas_pendientes": 105,
            "facturas_vencidas": 5,
            "facturas_con_historial": 150,
            "porcentaje_pagadas": 30.0,
            "ingresos_total_facturas": 189874200.0,
            "ingresos_pagados": 56962260.0,
            "porcentaje_ingresos_pagados": 30.0,
            "valor_promedio_factura": 1265828.0
        },
        "ventas_canceladas": 2,
        "total_ingresos": 189874200.0,
        "estadisticas_adicionales": {
            "ventas_en_proceso": 50,
            "ventas_terminadas": 165,
            "ventas_terminadas_sence": 0,
            "ventas_reprogramadas": 0,
            "ventas_perdidas": 0,
            "tiempo_promedio_completar_dias": 45.2,
            "valor_promedio_comercializacion": 875000.0,
            "ticket_promedio": 875000
        },
        "informacion_temporal": {
            "primera_venta": "2024-01-15",
            "ultima_venta": "2024-12-10",
            "dias_actividad": 329,
            "estado_actividad": "Activo"
        },
        "metricas_rendimiento": {
            "ventas_por_mes": 19.8,
            "ingresos_por_mes": 17332108.5,
            "ticket_promedio": 875000.0,
            "conversion_facturas": 69.1
        }
    }
}
```

### 4. Línea de Tiempo de Comercialización

```http
GET /api/linea-tiempo-comercializacion
```

**Parámetros de consulta:**

-   `cliente` (requerido): Nombre del cliente (URL encoded)
-   `fecha_inicio` (opcional): Fecha de inicio del filtro (YYYY-MM-DD)
-   `fecha_fin` (opcional): Fecha de fin del filtro (YYYY-MM-DD)
-   `agrupar_por` (opcional): Tipo de agrupación temporal ('mes', 'trimestre', 'año'). Default: 'mes'

**Ejemplo:**

```http
GET /api/linea-tiempo-comercializacion?cliente=Syncore%20Montajes%20Industriales&agrupar_por=mes&fecha_inicio=2024-01-01&fecha_fin=2024-12-31
```

**Response (200):**

```json
{
    "success": true,
    "data": {
        "cliente_nombre": "Syncore Montajes Industriales",
        "agrupar_por": "mes",
        "periodos": [
            {
                "periodo": "2024-12",
                "facturas_emitidas": 2,
                "facturas_en_proceso": 0,
                "facturas_pagadas": 0,
                "facturas_vencidas": 0,
                "facturas_anuladas": 0,
                "valor_total_emitidas": 0,
                "valor_total_pagadas": 0,
                "dias_promedio_para_pago": 0,
                "porcentaje_pagadas": 0.0
            },
            {
                "periodo": "2025-01",
                "facturas_emitidas": 19,
                "facturas_en_proceso": 0,
                "facturas_pagadas": 21,
                "facturas_vencidas": 0,
                "facturas_anuladas": 0,
                "valor_total_emitidas": 0,
                "valor_total_pagadas": 86188200,
                "dias_promedio_para_pago": 8.5,
                "porcentaje_pagadas": 110.5
            }
        ],
        "resumen": {
            "total_facturas_analizadas": 16,
            "facturas_pagadas": 13,
            "facturas_vencidas": 0,
            "facturas_pendientes": 3,
            "porcentaje_pagadas": 81.3,
            "valor_total_facturas": 20093800,
            "valor_total_pagado": 20093800,
            "porcentaje_valor_pagado": 100.0,
            "tiempo_promedio_para_pago_dias": 34.8,
            "tiempo_minimo_para_pago_dias": 7,
            "tiempo_maximo_para_pago_dias": 47
        }
    }
}
```

**Descripción de campos:**

-   **periodos**: Array de períodos temporales con métricas agregadas

    -   `periodo`: Identificador del período (ej: "2024-12", "2025-Q1", "2025")
    -   `facturas_emitidas`: Número de facturas emitidas en el período
    -   `facturas_pagadas`: Número de facturas pagadas en el período
    -   `valor_total_pagadas`: Valor total de facturas pagadas
    -   `dias_promedio_para_pago`: Días promedio para pago de facturas
    -   `porcentaje_pagadas`: Porcentaje de facturas pagadas vs emitidas

-   **resumen**: Estadísticas generales del cliente
    -   `total_facturas_analizadas`: Total de facturas procesadas
    -   `tiempo_promedio_para_pago_dias`: Tiempo promedio global de pago
    -   `valor_total_pagado`: Valor total cobrado

**Estados de facturas:**

-   1: Emitida (azul #3B82F6)
-   2: En Proceso (amarillo #F59E0B)
-   3: Pagada (verde #10B981)
-   4: Vencida (rojo #EF4444)
-   5: Anulada (gris #6B7280)

**Nota importante:** Solo incluye facturas de ventas con estado "Terminado" (estado_venta_id = 1 o 3) para asegurar información financiera confiable.

## Estados de Actividad del Cliente

-   **Activo**: Última venta hace ≤ 30 días
-   **Poco Activo**: Última venta hace 31-90 días
-   **Inactivo**: Última venta hace > 90 días

## Estados de Venta

-   `0`: En Proceso
-   `1`: Terminada
-   `2`: Cancelada
-   `3`: Terminada SENCE
-   `6`: Reprogramada
-   `7`: Perdida

## Estados de Factura

-   `1`: Pendiente
-   `2`: En Proceso
-   `3`: Pagada
-   `4`: Vencida

## Manejo de Errores

### Error 400 - Validación

```json
{
    "success": false,
    "error": "El nombre del cliente debe tener al menos 2 caracteres"
}
```

### Error 404 - No Encontrado

```json
{
    "success": false,
    "error": "Cliente no encontrado"
}
```

### Error 500 - Error del Servidor

```json
{
    "success": false,
    "error": "Error al generar estadísticas del cliente"
}
```

## Optimizaciones de Rendimiento

### Configuraciones Automáticas

-   **Tiempo límite**: 120-180 segundos para cálculos complejos
-   **Memoria**: 256-512MB según la complejidad del endpoint
-   **Consultas batch**: Reducción significativa de queries a la base de datos

### Recomendaciones de Uso

-   Para datasets grandes, usar el endpoint avanzado con paginación
-   Implementar caché en frontend para listas de clientes frecuentemente consultadas
-   Usar filtros para reducir la carga de datos procesados

## Próximas Mejoras Sugeridas

### 🚀 Funcionalidades Opcionales

1. **Caché de resultados** - Para consultas frecuentes
2. **Exportación a Excel/CSV** - Para reportes
3. **Comparación entre clientes** - Dashboard comparativo
4. **Alertas automáticas** - Para clientes inactivos o facturas vencidas
5. **Filtros por fechas** - Para análisis temporal específico

### 📊 Métricas Adicionales

1. **Tendencia de crecimiento** - Comparación período anterior
2. **Predicción de ingresos** - Basado en histórico
3. **Análisis de estacionalidad** - Patrones por meses/trimestres
4. **Scoring de clientes** - Clasificación por valor y riesgo

## Testing Realizado

✅ **Endpoints probados y funcionando:**

-   Lista básica de clientes: StatusCode 200
-   Lista avanzada con filtros: StatusCode 200
-   Dashboard específico de cliente: StatusCode 200

✅ **Validaciones verificadas:**

-   Manejo de clientes no encontrados
-   Validación de parámetros de entrada
-   Manejo de errores de base de datos

✅ **Rendimiento optimizado:**

-   Reducción de consultas N+1
-   Optimización de consultas con JOIN
-   Manejo eficiente de memoria y tiempo

Los endpoints están listos para uso en producción y son compatibles con dashboards empresariales modernos.

## Casos de Uso Específicos

### 📈 Dashboard de Línea de Tiempo

El endpoint `/api/linea-tiempo-comercializacion` es ideal para:

1. **Visualización de progreso temporal**: Crear gráficos de línea mostrando la evolución de estados de facturas
2. **Análisis de eficiencia de cobranza**: Identificar períodos con mejor/peor rendimiento de pago
3. **Comparación entre períodos**: Analizar tendencias mes a mes o trimestre a trimestre
4. **Predicción de cash flow**: Basado en patrones históricos de pago

### 🎨 Implementación en Frontend

```javascript
// Ejemplo de uso básico
const lineaTiempo = await fetch(
    `/api/linea-tiempo-comercializacion?cliente=${encodeURIComponent(
        nombreCliente
    )}&agrupar_por=mes`
).then((response) => response.json());

// Para gráfico de Chart.js o similar
const datasetFacturasEmitidas = {
    label: "Facturas Emitidas",
    data: lineaTiempo.data.periodos.map((p) => p.facturas_emitidas),
    borderColor: "#3B82F6",
    backgroundColor: "rgba(59, 130, 246, 0.1)",
};

const datasetFacturasPagadas = {
    label: "Facturas Pagadas",
    data: lineaTiempo.data.periodos.map((p) => p.facturas_pagadas),
    borderColor: "#10B981",
    backgroundColor: "rgba(16, 185, 129, 0.1)",
};

const labels = lineaTiempo.data.periodos.map((p) => p.periodo);
```

### 💡 Tips para el Frontend

-   **Colores consistentes**: Usa los colores específicos de cada estado para mantener coherencia visual
-   **Filtros interactivos**: Permite cambiar entre mes/trimestre/año dinámicamente
-   **Tooltips informativos**: Muestra detalles del período al hacer hover
-   **Responsive**: Adapta la visualización para móviles y tablets
-   **Loading states**: Muestra spinners durante la carga (puede tardar 3-5 segundos)
