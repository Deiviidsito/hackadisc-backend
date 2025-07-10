# Documentación de Endpoints de Analíticas

## Endpoints Implementados

### 1. Generar Estadísticas de Pago

**POST** `/api/generar-estadisticas-pago`

Genera y almacena las estadísticas de tiempo de pago analizando todas las facturas y sus historiales.

**Respuesta:**

```json
{
    "success": true,
    "message": "Estadísticas de pago generadas correctamente.",
    "estadisticas_generadas": 1250
}
```

### 2. Resumen de Estadísticas de Pago (Completo)

**GET** `/api/resumen-estadisticas-pago`

Obtiene un resumen completo con todas las estadísticas avanzadas implementadas.

**Respuesta:**

```json
{
    "success": true,
    "data": {
        "resumen_general": {
            "total_facturas": 1250,
            "facturas_pagadas": 980,
            "facturas_pendientes": 270,
            "porcentaje_pagadas": 78.4
        },
        "estadisticas_tiempo_pago": {
            "promedio": {
                "dias": 45.67,
                "meses": 1.5
            },
            "mediana_dias": 38.5,
            "moda_dias": 30,
            "minimo_dias": 1,
            "maximo_dias": 180,
            "desviacion_estandar_dias": 25.43,
            "percentiles": {
                "p5": 8.25,
                "p10": 12.5,
                "p25": 25.0,
                "p50": 38.5,
                "p75": 62.0,
                "p90": 85.0,
                "p95": 95.75
            },
            "iqr": {
                "q1": 25.0,
                "q3": 62.0,
                "valor": 37.0
            },
            "total_facturas_analizadas": 980
        }
    }
}
```

### 3. Estadísticas por Cliente

**GET** `/api/estadisticas-por-cliente`

Obtiene estadísticas agrupadas por cliente con paginación y ordenamiento.

**Parámetros:**

-   `limit` (int): Número de resultados por página (default: 50)
-   `offset` (int): Desplazamiento para paginación (default: 0)
-   `sort_by` (string): Campo para ordenar (promedio_dias, total_facturas, porcentaje_pagadas)
-   `order` (string): Dirección del ordenamiento (asc, desc)

**Ejemplo:** `/api/estadisticas-por-cliente?limit=20&offset=0&sort_by=promedio_dias&order=desc`

**Respuesta:**

```json
{
    "success": true,
    "data": [
        {
            "cliente_nombre": "Cliente ABC S.A.",
            "total_facturas": 45,
            "facturas_pagadas": 38,
            "facturas_pendientes": 7,
            "porcentaje_pagadas": 84.44,
            "promedio_dias_pago": 52.3,
            "minimo_dias_pago": 15,
            "maximo_dias_pago": 95,
            "desviacion_estandar_dias": 18.7
        }
    ],
    "pagination": {
        "total": 150,
        "limit": 20,
        "offset": 0,
        "has_more": true
    }
}
```

### 4. Tendencias Temporales

**GET** `/api/tendencias-temporales`

Analiza tendencias de pago agrupadas por período temporal.

**Parámetros:**

-   `group_by` (string): Tipo de agrupación (month, quarter, year)
-   `year` (int): Filtrar por año específico (opcional)

**Ejemplo:** `/api/tendencias-temporales?group_by=month&year=2024`

**Respuesta:**

```json
{
    "success": true,
    "data": {
        "agrupacion": "month",
        "año_filtro": 2024,
        "tendencias": [
            {
                "periodo": "2024-01",
                "facturas_pagadas": 85,
                "promedio_dias_pago": 42.5,
                "minimo_dias_pago": 5,
                "maximo_dias_pago": 95,
                "desviacion_estandar_dias": 22.1
            },
            {
                "periodo": "2024-02",
                "facturas_pagadas": 92,
                "promedio_dias_pago": 38.2,
                "minimo_dias_pago": 8,
                "maximo_dias_pago": 87,
                "desviacion_estandar_dias": 19.5
            }
        ],
        "total_periodos": 12
    }
}
```

### 5. Distribución de Pagos por Rangos

**GET** `/api/distribucion-pagos`

Analiza la distribución de tiempos de pago en rangos predefinidos.

**Respuesta:**

```json
{
    "success": true,
    "data": {
        "distribucion": [
            {
                "rango": "0-7 días (Muy rápido)",
                "min_dias": 0,
                "max_dias": 7,
                "cantidad_facturas": 45,
                "porcentaje": 4.59,
                "promedio_dias_rango": 4.2
            },
            {
                "rango": "8-15 días (Rápido)",
                "min_dias": 8,
                "max_dias": 15,
                "cantidad_facturas": 125,
                "porcentaje": 12.76,
                "promedio_dias_rango": 11.8
            },
            {
                "rango": "16-30 días (Normal)",
                "min_dias": 16,
                "max_dias": 30,
                "cantidad_facturas": 280,
                "porcentaje": 28.57,
                "promedio_dias_rango": 23.5
            }
        ],
        "resumen": {
            "total_facturas_analizadas": 980,
            "tiempo_promedio_global": 45.67,
            "desviacion_estandar_global": 25.43,
            "rango_mas_comun": "16-30 días (Normal)",
            "porcentaje_rango_mas_comun": 28.57
        }
    }
}
```

### 6. Análisis Comparativo entre Períodos

**GET** `/api/analisis-comparativo`

Compara estadísticas entre dos períodos de tiempo específicos.

**Parámetros Requeridos:**

-   `fecha_inicio_periodo1` (date): Fecha inicio del primer período
-   `fecha_fin_periodo1` (date): Fecha fin del primer período
-   `fecha_inicio_periodo2` (date): Fecha inicio del segundo período
-   `fecha_fin_periodo2` (date): Fecha fin del segundo período

**Ejemplo:** `/api/analisis-comparativo?fecha_inicio_periodo1=2024-01-01&fecha_fin_periodo1=2024-06-30&fecha_inicio_periodo2=2024-07-01&fecha_fin_periodo2=2024-12-31`

**Respuesta:**

```json
{
    "success": true,
    "data": {
        "periodo_1": {
            "nombre": "Período 1",
            "fechas": "2024-01-01 a 2024-06-30",
            "total_facturas": 520,
            "promedio_dias": 48.5,
            "mediana_dias": 42.0,
            "minimo_dias": 2,
            "maximo_dias": 120,
            "desviacion_estandar": 24.8,
            "percentiles": {
                /* ... */
            }
        },
        "periodo_2": {
            "nombre": "Período 2",
            "fechas": "2024-07-01 a 2024-12-31",
            "total_facturas": 460,
            "promedio_dias": 42.8,
            "mediana_dias": 38.5,
            "minimo_dias": 1,
            "maximo_dias": 95,
            "desviacion_estandar": 21.2,
            "percentiles": {
                /* ... */
            }
        },
        "comparacion": {
            "facturas": {
                "diferencia_absoluta": -60,
                "porcentaje_cambio": -11.54,
                "interpretacion": "Disminución"
            },
            "tiempo_promedio": {
                "diferencia_dias": -5.7,
                "porcentaje_cambio": -11.75,
                "interpretacion": "Disminución (mejor)"
            },
            "mediana": {
                "diferencia_dias": -3.5,
                "porcentaje_cambio": -8.33,
                "interpretacion": "Disminución (mejor)"
            },
            "resumen": {
                "mejora_general": true,
                "empeora_general": false,
                "resultado_mixto": false
            }
        }
    }
}
```

## Optimizaciones Implementadas

### Para Datasets Grandes:

1. **Consultas Optimizadas:**

    - Uso de consultas SQL con `selectRaw()` para cálculos directos en la base de datos
    - Evita cargar todos los datos en memoria

2. **Cálculos Eficientes:**

    - Algoritmos optimizados para percentiles con interpolación lineal
    - Cálculo de moda usando `array_count_values()` (O(n))
    - Desviación estándar calculada en una sola pasada

3. **Paginación:**

    - Endpoints con paginación para evitar timeouts
    - Límites configurables por el usuario

4. **Índices Recomendados:**
    ```sql
    -- Para mejorar rendimiento en consultas de analíticas
    CREATE INDEX idx_datos_estadistica_factura_pagada ON datos_estadisticas(factura_pagada);
    CREATE INDEX idx_datos_estadistica_fecha_pago ON datos_estadisticas(fecha_pago_final);
    CREATE INDEX idx_datos_estadistica_cliente ON datos_estadisticas(cliente_nombre);
    CREATE INDEX idx_datos_estadistica_dias_pago ON datos_estadisticas(dias_para_pago);
    ```

## Tiempo Estimado de Ejecución

-   **Datasets pequeños (< 10,000 registros):** < 1 segundo
-   **Datasets medianos (10,000 - 100,000 registros):** 1-5 segundos
-   **Datasets grandes (100,000 - 1,000,000 registros):** 5-30 segundos
-   **Datasets muy grandes (> 1,000,000 registros):** 30-120 segundos

### Notas de Rendimiento:

-   El endpoint más pesado es la generación inicial de estadísticas
-   Los endpoints de consulta son muy rápidos ya que operan sobre datos pre-procesados
-   Se recomienda ejecutar la generación de estadísticas de forma asíncrona para datasets muy grandes
