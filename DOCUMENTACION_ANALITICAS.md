# Documentación de Endpoints de Analíticas

## Conceptos Estadísticos Implementados

### Métricas Básicas

-   **Promedio (Media Aritmética)**: Suma de todos los valores dividida por el número total de observaciones
-   **Mediana**: Valor central cuando los datos están ordenados. Menos sensible a valores extremos que el promedio
-   **Moda**: Valor que aparece con mayor frecuencia en el conjunto de datos
-   **Mínimo/Máximo**: Valores extremos del conjunto de datos
-   **Rango**: Diferencia entre el valor máximo y mínimo

### Métricas de Dispersión

-   **Desviación Estándar**: Medida de dispersión que indica qué tan alejados están los valores del promedio
    -   Valores bajos: datos concentrados cerca del promedio
    -   Valores altos: datos muy dispersos
-   **Varianza**: Cuadrado de la desviación estándar
-   **Coeficiente de Variación**: Desviación estándar dividida por la media, útil para comparar dispersión entre datasets

### Percentiles y Cuartiles

-   **Percentiles**: Valores que dividen el conjunto de datos en 100 partes iguales
    -   P25 (Q1): 25% de los datos están por debajo de este valor
    -   P50 (Q2): Mediana - 50% de los datos están por debajo
    -   P75 (Q3): 75% de los datos están por debajo de este valor
    -   P90, P95: Útiles para identificar valores atípicos
-   **Rango Intercuartílico (IQR)**: Diferencia entre Q3 y Q1, mide la dispersión del 50% central de los datos

### Interpretación de Métricas en Contexto de Pagos

-   **Días de Pago**: Tiempo transcurrido desde la fecha de factura hasta el pago
-   **Percentiles de Tiempo**: Ayudan a establecer SLAs y identificar patrones de pago
-   **Distribución por Rangos**: Clasifica comportamientos de pago (rápido, normal, lento, muy lento)
-   **Tendencias Temporales**: Identifica estacionalidades y cambios en comportamiento de pago

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
            "coeficiente_variacion": 0.557,
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
                "valor": 37.0,
                "interpretacion": "El 50% central de los pagos se realiza entre 25 y 62 días"
            },
            "total_facturas_analizadas": 980,
            "interpretacion": {
                "promedio": "En promedio, los clientes pagan en 45.67 días",
                "mediana": "La mitad de las facturas se pagan en 38.5 días o menos",
                "moda": "El tiempo de pago más frecuente es 30 días",
                "desviacion": "Los tiempos de pago varían ±25.43 días respecto al promedio",
                "percentil_90": "El 90% de las facturas se pagan en 85 días o menos",
                "percentil_95": "Solo el 5% de las facturas tardan más de 95.75 días en pagarse"
            }
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
            "desviacion_estandar_dias": 18.7,
            "mediana_dias_pago": 48.0,
            "percentiles": {
                "p25": 35.0,
                "p50": 48.0,
                "p75": 65.0,
                "p90": 78.0
            },
            "coeficiente_variacion": 0.357,
            "interpretacion": {
                "rendimiento": "Cliente con pagos relativamente puntuales",
                "consistencia": "Desviación baja indica comportamiento predecible",
                "percentil_90": "90% de sus facturas se pagan en 78 días o menos"
            }
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
                "desviacion_estandar_dias": 22.1,
                "mediana_dias_pago": 38.0,
                "percentiles": {
                    "p25": 25.0,
                    "p75": 58.0,
                    "p90": 72.0
                },
                "tendencia": "Mejora respecto al mes anterior",
                "interpretacion": "Tiempo de pago estable con tendencia positiva"
            },
            {
                "periodo": "2024-02",
                "facturas_pagadas": 92,
                "promedio_dias_pago": 38.2,
                "minimo_dias_pago": 8,
                "maximo_dias_pago": 87,
                "desviacion_estandar_dias": 19.5,
                "mediana_dias_pago": 35.0,
                "percentiles": {
                    "p25": 22.0,
                    "p75": 52.0,
                    "p90": 68.0
                },
                "tendencia": "Mejora significativa",
                "interpretacion": "Reducción notable en tiempos de pago"
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
                "promedio_dias_rango": 4.2,
                "interpretacion": "Pagos excepcionales - clientes muy puntuales",
                "impacto_flujo_caja": "Excelente para el flujo de caja"
            },
            {
                "rango": "8-15 días (Rápido)",
                "min_dias": 8,
                "max_dias": 15,
                "cantidad_facturas": 125,
                "porcentaje": 12.76,
                "promedio_dias_rango": 11.8,
                "interpretacion": "Pagos rápidos - comportamiento muy bueno",
                "impacto_flujo_caja": "Muy bueno para el flujo de caja"
            },
            {
                "rango": "16-30 días (Normal)",
                "min_dias": 16,
                "max_dias": 30,
                "cantidad_facturas": 280,
                "porcentaje": 28.57,
                "promedio_dias_rango": 23.5,
                "interpretacion": "Pagos dentro de términos comerciales estándar",
                "impacto_flujo_caja": "Aceptable - dentro de lo esperado"
            }
        ],
        "resumen": {
            "total_facturas_analizadas": 980,
            "tiempo_promedio_global": 45.67,
            "desviacion_estandar_global": 25.43,
            "rango_mas_comun": "16-30 días (Normal)",
            "porcentaje_rango_mas_comun": 28.57,
            "insight_principal": "La mayoría de clientes pagan dentro de términos comerciales normales",
            "recomendacion": "Enfocar esfuerzos de cobranza en facturas > 60 días"
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
            "coeficiente_variacion": 0.511,
            "percentiles": {
                "p25": 28.0,
                "p50": 42.0,
                "p75": 65.0,
                "p90": 85.0,
                "p95": 102.0
            },
            "interpretacion": "Período con mayor variabilidad en tiempos de pago"
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
            "coeficiente_variacion": 0.495,
            "percentiles": {
                "p25": 22.0,
                "p50": 38.5,
                "p75": 58.0,
                "p90": 72.0,
                "p95": 85.0
            },
            "interpretacion": "Período con mejores tiempos y menor variabilidad"
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
                "resultado_mixto": false,
                "conclusion": "Mejora significativa en el segundo período",
                "factores_clave": [
                    "Reducción en tiempo promedio de pago",
                    "Menor variabilidad (mayor predictibilidad)",
                    "Mejores percentiles en todos los rangos"
                ],
                "recomendaciones": [
                    "Mantener las prácticas implementadas en el período 2",
                    "Analizar qué factores contribuyeron a la mejora",
                    "Replicar estrategias exitosas en futuros períodos"
                ]
            }
        }
    }
}
```

## Nuevos Endpoints de Dashboard por Cliente

### 7. Lista de Clientes para Dashboard

**GET** `/api/clientes-dashboard`

Obtiene una lista de todos los clientes con estadísticas básicas para mostrar en una vista general del dashboard.

**Respuesta:**

```json
{
    "success": true,
    "data": [
        {
            "nombre_cliente": "TechCorp Solutions",
            "total_ventas": 143,
            "total_ingresos": 2850000,
            "ventas_canceladas": 12,
            "porcentaje_facturas_pagadas": 87.2,
            "primera_comercializacion": "2023-01-15",
            "ultima_comercializacion": "2024-12-20",
            "estado_actividad": "Activo"
        },
        {
            "nombre_cliente": "Innovación Global S.A.",
            "total_ventas": 89,
            "total_ingresos": 1650000,
            "ventas_canceladas": 8,
            "porcentaje_facturas_pagadas": 92.1,
            "primera_comercializacion": "2023-03-10",
            "ultima_comercializacion": "2024-11-30",
            "estado_actividad": "Activo"
        }
    ],
    "total_clientes": 45
}
```

### 8. Dashboard Completo de Cliente Específico

**GET** `/api/cliente-dashboard/{nombreCliente}`

Obtiene estadísticas completas de un cliente específico para mostrar en su dashboard individual.

**Parámetro:**

-   `nombreCliente` (string): Nombre del cliente (URL encoded si contiene espacios)

**Ejemplo:** `/api/cliente-dashboard/TechCorp%20Solutions`

**Respuesta:**

```json
{
    "success": true,
    "data": {
        "cliente_nombre": "TechCorp Solutions",
        "total_ventas": 143,
        "dias_comercializacion": 425,
        "facturas_estadisticas": {
            "total_facturas": 156,
            "facturas_pagadas": 136,
            "facturas_pendientes": 20,
            "porcentaje_pagadas": 87.2
        },
        "ventas_canceladas": 12,
        "total_ingresos": 2850000,
        "estadisticas_adicionales": {
            "ventas_en_proceso": 15,
            "ventas_terminadas": 98,
            "ventas_terminadas_sence": 18,
            "ventas_reprogramadas": 0,
            "ventas_perdidas": 0,
            "tiempo_promedio_completar_dias": 45.3,
            "valor_promedio_comercializacion": 19930,
            "ticket_promedio": 19930
        }
    }
}
```

### Interpretación de Métricas del Dashboard:

#### Métricas Principales:

-   **total_ventas**: Cantidad total de comercializaciones realizadas con este cliente
-   **dias_comercializacion**: Días transcurridos desde la primera comercialización hasta el último cambio de estado en facturas
-   **facturas_estadisticas**: Resumen del estado de todas las facturas del cliente
-   **ventas_canceladas**: Cantidad de ventas que fueron canceladas (estado 2)
-   **total_ingresos**: Suma total de todos los valores finales de comercialización

#### Estados de Actividad:

-   **Activo**: Última comercialización hace ≤30 días
-   **Poco Activo**: Última comercialización hace 31-90 días
-   **Inactivo**: Última comercialización hace >90 días

#### Estados de Venta:

-   **En Proceso** (0): Ventas que aún están siendo desarrolladas
-   **Terminada** (1): Ventas completadas listas para facturar
-   **Cancelada** (2): Ventas que fueron canceladas
-   **Terminada SENCE** (3): Ventas con facturación parcial SENCE
-   **Reprogramada** (6): Ventas que fueron reprogramadas
-   **Perdida** (7): Ventas que se perdieron

#### Casos de Uso para Frontend:

1. **Vista de Lista**: Usar `/clientes-dashboard` para mostrar tabla con todos los clientes
2. **Dashboard Individual**: Usar `/cliente-dashboard/{nombre}` para vista detallada
3. **Filtros Sugeridos**: Por estado_actividad, rango de ingresos, porcentaje de facturas pagadas
4. **Ordenamiento**: Por total_ingresos, total_ventas, porcentaje_facturas_pagadas
5. **Alertas**: Clientes con porcentaje_facturas_pagadas <70% o estado "Inactivo"

## Interpretación de Métricas para Toma de Decisiones

### Análisis de Flujo de Caja

-   **Percentil 25 (P25)**: El 25% de las facturas se cobran en este tiempo o menos - ideal para proyecciones optimistas
-   **Percentil 75 (P75)**: El 75% de las facturas se cobran en este tiempo o menos - útil para proyecciones conservadoras
-   **Percentil 90 (P90)**: Solo el 10% de las facturas tardan más - útil para identificar casos problemáticos

### Indicadores de Alerta Temprana

-   **Desviación Estándar Alta (>30 días)**: Indica comportamiento impredecible de pago
-   **Coeficiente de Variación >0.6**: Muy alta variabilidad, requiere segmentación de clientes
-   **Percentil 95 >90 días**: 5% de facturas con riesgo de incobrabilidad

### Segmentación Automática de Clientes

```
Clientes Excelentes: P90 < 30 días
Clientes Buenos: P90 entre 30-60 días
Clientes Regulares: P90 entre 60-90 días
Clientes Problemáticos: P90 > 90 días
```

### Métricas de KPI Sugeridas

-   **DSO (Days Sales Outstanding)**: Usar mediana en lugar de promedio para mayor precisión
-   **% Facturas <30 días**: Meta recomendada >60%
-   **% Facturas >90 días**: Meta recomendada <5%
-   **Tendencia Trimestral**: Comparar P50 entre trimestres

### Alertas Automáticas Recomendadas

-   **Cliente individual**: Desviación >200% respecto a su histórico
-   **Global**: Incremento >15% en mediana mensual
-   **Por rango**: Incremento >20% en categoría "lento" o "muy lento"

## Casos de Uso Empresariales

### 1. Planificación Financiera

-   Usar P25, P50, P75 para escenarios optimista, realista y conservador
-   Proyectar flujo de caja basado en distribución histórica
-   Establecer metas de cobranza realistas por equipo comercial

### 2. Gestión de Riesgo Crediticio

-   Identificar clientes con comportamiento errático (alta desviación estándar)
-   Establecer límites de crédito basados en percentiles históricos
-   Detectar deterioro temprano en comportamiento de pago

### 3. Optimización de Procesos de Cobranza

-   Priorizar esfuerzos en facturas que excedan P75 del cliente
-   Personalizar estrategias de cobranza por segmento de cliente
-   Medir efectividad de nuevas políticas comparando períodos

### 4. Análisis de Rentabilidad

-   Calcular costo de capital de facturas por cobrar
-   Evaluar impacto de descuentos por pronto pago
-   Optimizar términos comerciales por segmento

## Optimizaciones Implementadas

### Para Datasets Grandes:

1. **Consultas Optimizadas:**

    - Uso de consultas SQL con `selectRaw()` para cálculos directos en la base de datos
    - Evita cargar todos los datos en memoria
    - Implementación de chunking para procesamiento por lotes

2. **Algoritmos Estadísticos Eficientes:**

    - **Percentiles**: Implementación con interpolación lineal optimizada
    - **Moda**: Algoritmo O(n) usando `array_count_values()`
    - **Desviación Estándar**: Cálculo en una sola pasada para minimizar iteraciones
    - **Mediana**: Algoritmo optimizado para datasets ordenados

3. **Gestión de Memoria:**

    - Procesamiento en chunks de 1000 registros
    - Liberación de memoria entre chunks con `unset()`
    - Aumento dinámico de límites de memoria cuando es necesario

4. **Paginación Inteligente:**

    - Endpoints con paginación para evitar timeouts
    - Límites configurables por el usuario
    - Offset optimizado para consultas grandes

5. **Índices de Base de Datos Recomendados:**

    ```sql
    -- Índices críticos para rendimiento óptimo
    CREATE INDEX idx_datos_estadistica_factura_pagada ON datos_estadisticas(factura_pagada);
    CREATE INDEX idx_datos_estadistica_fecha_pago ON datos_estadisticas(fecha_pago_final);
    CREATE INDEX idx_datos_estadistica_cliente ON datos_estadisticas(cliente_nombre);
    CREATE INDEX idx_datos_estadistica_dias_pago ON datos_estadisticas(dias_para_pago);
    CREATE INDEX idx_datos_estadistica_fecha_factura ON datos_estadisticas(fecha_factura);

    -- Índices compuestos para consultas complejas
    CREATE INDEX idx_datos_estadistica_cliente_pagada ON datos_estadisticas(cliente_nombre, factura_pagada);
    CREATE INDEX idx_datos_estadistica_fecha_rango ON datos_estadisticas(fecha_factura, fecha_pago_final);
    ```

## Configuración de Rendimiento

### Ajustes de PHP recomendados:

```ini
max_execution_time = 600        # 10 minutos para datasets grandes
memory_limit = 1G              # 1GB para cálculos estadísticos complejos
post_max_size = 100M           # Para importación de archivos grandes
upload_max_filesize = 100M     # Para importación de archivos grandes
```

### Configuración de MySQL/MariaDB:

```sql
-- Optimizaciones para consultas analíticas
SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION';
SET SESSION sort_buffer_size = 256M;
SET SESSION read_buffer_size = 128M;
```

## Tiempo Estimado de Ejecución

### Generación Inicial de Estadísticas:

-   **Datasets pequeños (< 10,000 registros):** 2-5 segundos
-   **Datasets medianos (10,000 - 100,000 registros):** 5-15 segundos
-   **Datasets grandes (100,000 - 1,000,000 registros):** 15-60 segundos
-   **Datasets muy grandes (> 1,000,000 registros):** 60-300 segundos

### Consultas de Analíticas (datos pre-procesados):

-   **Resumen estadísticas:** < 1 segundo
-   **Estadísticas por cliente (paginado):** 1-3 segundos
-   **Tendencias temporales:** 2-5 segundos
-   **Distribución por rangos:** 1-2 segundos
-   **Análisis comparativo:** 3-8 segundos

### Factores que Afectan el Rendimiento:

1. **Número total de facturas en el sistema**
2. **Complejidad de las consultas (filtros, agrupaciones)**
3. **Disponibilidad de índices de base de datos**
4. **Recursos del servidor (CPU, RAM, I/O)**
5. **Configuración de PHP y MySQL**

### Recomendaciones de Monitoreo:

-   **Timeouts**: Configurar alertas si endpoints tardan >30 segundos
-   **Memoria**: Monitorear uso de RAM durante ejecución
-   **Base de datos**: Revisar slow query log para optimizar consultas
-   **Concurrencia**: Limitar ejecuciones simultáneas de generación de estadísticas

### Notas de Rendimiento:

-   El endpoint más pesado es la **generación inicial de estadísticas**
-   Los endpoints de **consulta son muy rápidos** ya que operan sobre datos pre-procesados
-   Se recomienda ejecutar la **generación de estadísticas de forma asíncrona** para datasets muy grandes
-   **Cache recomendado**: Implementar cache Redis/Memcached para consultas frecuentes
-   **Programación**: Ejecutar re-generación de estadísticas en horarios de bajo tráfico

## Troubleshooting Común

### Error: "Maximum execution time exceeded"

**Solución**: Aumentar `max_execution_time` en php.ini o ejecutar en background

### Error: "Allowed memory size exhausted"

**Solución**: Aumentar `memory_limit` en php.ini o implementar procesamiento adicional por chunks

### Consultas muy lentas

**Solución**: Verificar que los índices recomendados estén creados y actualizados

### Resultados inconsistentes

**Solución**: Re-generar estadísticas desde cero, verificar integridad de datos

## Anexo: Fórmulas Estadísticas Implementadas

### Cálculos de Tendencia Central:

```
Media (μ) = Σ(xi) / n

Mediana = {
    x(n+1)/2                    si n es impar
    (x(n/2) + x(n/2+1)) / 2     si n es par
}

Moda = valor con mayor frecuencia en el dataset
```

### Cálculos de Dispersión:

```
Varianza (σ²) = Σ(xi - μ)² / n

Desviación Estándar (σ) = √(σ²)

Coeficiente de Variación (CV) = σ / μ

Rango Intercuartílico (IQR) = Q3 - Q1
```

### Cálculos de Percentiles:

```
Para percentil P en dataset ordenado:
- Posición = (P/100) * (n-1)
- Si posición es entero: valor en esa posición
- Si posición es decimal: interpolación lineal entre valores adyacentes
```

### Interpretación de Coeficiente de Variación:

-   **CV < 0.3**: Baja variabilidad (comportamiento muy predecible)
-   **CV 0.3-0.6**: Variabilidad moderada (comportamiento estable)
-   **CV > 0.6**: Alta variabilidad (comportamiento impredecible)

## Glosario de Términos

**DSO (Days Sales Outstanding)**: Métrica que mide cuántos días tarda una empresa en cobrar sus ventas a crédito

**P25, P50, P75**: Percentiles 25, 50 y 75. El P50 es equivalente a la mediana

**IQR (Interquartile Range)**: Medida de dispersión que contiene el 50% central de los datos

**Outliers**: Valores atípicos que se alejan significativamente del patrón general

**Chunks**: Técnica de procesamiento que divide grandes datasets en segmentos manejables

**Interpolación Lineal**: Método para estimar valores entre dos puntos conocidos

**Query Optimization**: Técnicas para mejorar el rendimiento de consultas a la base de datos
