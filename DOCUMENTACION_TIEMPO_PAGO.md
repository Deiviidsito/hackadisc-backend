# API Documentación - Análisis de Tiempos de Pago

## Endpoints Disponibles

### 1. Cálculo Tiempo Promedio de Pago

**POST** `/api/tiempo-pago/promedio`

Analiza el tiempo promedio desde emisión de factura hasta recepción de pago.

#### Parámetros (opcionales):

```json
{
    "año": 2024,
    "mes": 10,
    "tipo_factura": "cliente", // "sence", "cliente", "todas"
    "incluir_pendientes": false
}
```

#### Respuesta:

```json
{
    "success": true,
    "message": "Análisis tiempo facturación → pago completado exitosamente",
    "datos": {
        "resumen": {
            "comercializaciones_analizadas": 15674,
            "facturas_analizadas": 10019,
            "facturas_pagadas": 7747,
            "facturas_pendientes": 2272,
            "porcentaje_pagadas": 77.32
        },
        "tiempo_promedio_pago": 33.46,
        "estadisticas": {
            "mediana_dias_pago": 34,
            "minimo_dias_pago": 0,
            "maximo_dias_pago": 351
        }
    }
}
```

### 2. Distribución de Tiempos de Pago

**POST** `/api/tiempo-pago/distribucion`

Analiza la distribución de tiempos de pago en rangos predefinidos.

#### Parámetros (opcionales):

```json
{
    "año": 2024,
    "mes": 10,
    "tipo_factura": "todas"
}
```

#### Respuesta:

```json
{
    "success": true,
    "datos": {
        "total_facturas_pagadas": 7747,
        "distribucion": {
            "inmediato": {
                "min": 0,
                "max": 0,
                "count": 125,
                "porcentaje": 1.61,
                "descripcion": "Mismo día"
            },
            "muy_rapido": {
                "min": 1,
                "max": 7,
                "count": 1147,
                "porcentaje": 14.81,
                "descripcion": "1-7 días"
            }
        }
    }
}
```

### 3. Análisis de Morosidad por Cliente

**POST** `/api/tiempo-pago/morosidad`

Analiza el comportamiento de pago por cliente individual.

#### Parámetros (opcionales):

```json
{
    "año": 2024,
    "mes": 10,
    "tipo_factura": "todas"
}
```

## Códigos de Estado

-   **200**: Éxito
-   **400**: Parámetros inválidos
-   **500**: Error del servidor

## Notas Importantes

1. **Performance**: Los endpoints pueden tardar varios segundos con datasets grandes
2. **Cache**: Se recomienda implementar cache para consultas frecuentes
3. **Filtros**: Usar filtros de fecha para mejorar performance
4. **Tipos de Factura**:
    - `sence`: Facturas de capacitación SENCE
    - `cliente`: Facturas directas a cliente
    - `todas`: Incluye ambos tipos
