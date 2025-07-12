# Documentación de Endpoints GET para Dashboard

## 🚀 Endpoints GET Simplificados para Dashboard - DATOS COMPLETOS

He creado **22 nuevos endpoints GET** que facilitan enormemente la integración en el frontend. **CAMBIO IMPORTANTE: Todos los endpoints ahora retornan TODOS los datos históricos de la base de datos** sin filtros de fecha, permitiendo que el frontend implemente filtrado dinámico.

### 🔄 CAMBIO IMPORTANTE - DATOS COMPLETOS

-   **Antes**: Los endpoints retornaban datos filtrados por año (predeterminado 2024)
-   **Ahora**: Los endpoints retornan TODOS los datos his## 🎉 Resumen

Con estos **25 nuevos endpoints GET**, la integración del frontend es ahora **extremadamente sencilla**:

-   ✅ **22 endpoints** retornando **datos completos históricos**
-   ✅ **3 endpoints de analíticas por cliente** para dashboards personalizados
-   ✅ **Filtrado dinámico** implementable en el frontend
-   ✅ **Respuestas instantáneas** con todos los datos
-   ✅ **Fácil testing** desde browser o herramientas HTTP
-   ✅ **Código JavaScript listo** para usar en React
-   ✅ **Máxima flexibilidad** para analytics y dashboards
-   ✅ **Navegación por cliente** para analíticas personalizadas

¡Tu dashboard ahora tiene acceso completo a todos los datos históricos con filtrado dinámico y analíticas personalizadas por cliente! 🎯📊👥a base de datos

-   **Beneficio**: El frontend puede implementar filtrado dinámico por fechas, años, clientes, etc.
-   **Flexibilidad**: Mayor control en el frontend para analytics y visualizaciones

---

## 📋 Lista Completa de Endpoints GET

### 🎯 **Endpoints GET con Datos Completos**

| Endpoint                                      | Descripción                        | Datos Retornados               |
| --------------------------------------------- | ---------------------------------- | ------------------------------ |
| `GET /api/dashboard/ventas-mes`               | Ventas por mes                     | Todos los años disponibles     |
| `GET /api/dashboard/resumen-anual`            | Resumen anual                      | Todos los años disponibles     |
| `GET /api/dashboard/tiempo-pago-promedio`     | Tiempo promedio de pago            | Todas las facturas históricas  |
| `GET /api/dashboard/distribucion-pagos`       | Distribución de tiempos de pago    | Todas las facturas históricas  |
| `GET /api/dashboard/morosidad-clientes`       | Morosidad por cliente              | Todas las facturas históricas  |
| `GET /api/dashboard/tiempo-etapas`            | Tiempo entre etapas (0→1)          | Todas las ventas históricas    |
| `GET /api/dashboard/etapas-por-cliente`       | Etapas por cliente                 | Todas las ventas históricas    |
| `GET /api/dashboard/distribucion-etapas`      | Distribución de etapas             | Todas las ventas históricas    |
| `GET /api/dashboard/tiempo-facturacion`       | Tiempo terminación→facturación     | Todas las facturas históricas  |
| `GET /api/dashboard/facturacion-por-cliente`  | Facturación por cliente            | Todas las facturas históricas  |
| `GET /api/dashboard/distribucion-facturacion` | Distribución facturación           | Todas las facturas históricas  |
| `GET /api/dashboard/tipos-flujo`              | Análisis tipos de flujo            | Todas las ventas históricas    |
| `GET /api/dashboard/preferencias-flujo`       | Preferencias por flujo             | Todas las ventas históricas    |
| `GET /api/dashboard/eficiencia-flujo`         | Eficiencia por flujo               | Todas las ventas históricas    |
| `GET /api/dashboard/pago-tiempo-completo`     | Tiempo de pago completo            | Todas las ventas históricas    |
| `GET /api/dashboard/clientes-lista`           | Lista de clientes con estadísticas | Todos los clientes registrados |

### 🎯 **Endpoints GET con Analíticas por Cliente**

| Endpoint                                                    | Descripción                         | Datos Retornados                            |
| ----------------------------------------------------------- | ----------------------------------- | ------------------------------------------- |
| `GET /api/clientes/listar`                                  | Lista completa de clientes          | Todos los clientes con estadísticas básicas |
| `GET /api/clientes/{id}/analytics`                          | Dashboard personalizado por cliente | Analíticas completas del cliente específico |
| `GET /api/clientes/{id}/comparar?cliente_comparacion={id2}` | Comparar dos clientes               | Métricas comparativas entre clientes        |
| `GET /api/dashboard/pago-tiempo-completo`                   | Tiempo de pago completo             | Todas las ventas históricas                 |

### 🔧 **Endpoints GET con Parámetros Personalizables**

| Endpoint                                | Parámetros de Query                        | Ejemplo de Uso                                                      |
| --------------------------------------- | ------------------------------------------ | ------------------------------------------------------------------- |
| `GET /api/dashboard/ventas-mes-custom`  | año, mes_inicio, mes_fin                   | `/api/dashboard/ventas-mes-custom?año=2024&mes_inicio=6&mes_fin=12` |
| `GET /api/dashboard/tiempo-pago-custom` | año, mes, tipo_factura, incluir_pendientes | `/api/dashboard/tiempo-pago-custom?año=2024&tipo_factura=cliente`   |
| `GET /api/dashboard/morosidad-custom`   | año, tipo_factura                          | `/api/dashboard/morosidad-custom?año=2024&tipo_factura=sence`       |
| `GET /api/dashboard/etapas-custom`      | año, mes_inicio, mes_fin, incluir_detalles | `/api/dashboard/etapas-custom?año=2024&incluir_detalles=true`       |
| `GET /api/dashboard/facturacion-custom` | año, mes, tipo_factura                     | `/api/dashboard/facturacion-custom?año=2024&mes=8`                  |
| `GET /api/dashboard/tipos-flujo-custom` | año, mes                                   | `/api/dashboard/tipos-flujo-custom?año=2024&mes=12`                 |

### 🎯 **Endpoint Especial - Dashboard Completo**

| Endpoint                      | Descripción                                                   |
| ----------------------------- | ------------------------------------------------------------- |
| `GET /api/dashboard/completo` | **Retorna TODOS los datos del dashboard en una sola llamada** |

---

## 🔗 Ejemplos de Uso en JavaScript

### 1. Cargar Dashboard Completo (Recomendado)

```javascript
// Cargar todo el dashboard de una vez
const cargarDashboardCompleto = async () => {
    try {
        const response = await fetch(
            "http://localhost:8000/api/dashboard/completo"
        );
        const data = await response.json();

        if (data.success) {
            console.log("Datos de ventas:", data.datos.ventas);
            console.log("Datos de tiempo de pago:", data.datos.tiempo_pago);
            console.log("Datos de etapas:", data.datos.tiempo_etapas);
            console.log("Datos de facturación:", data.datos.facturacion);
            console.log("Datos de tipos de flujo:", data.datos.tipos_flujo);
            console.log("Datos de pago completo:", data.datos.pago_completo);
        }
    } catch (error) {
        console.error("Error:", error);
    }
};
```

### 2. Cargar Datos Individuales

```javascript
// Cargar tiempo promedio de pago
const cargarTiempoPago = async () => {
    const response = await fetch(
        "http://localhost:8000/api/dashboard/tiempo-pago-promedio"
    );
    const data = await response.json();
    return data.datos;
};

// Cargar morosidad de clientes
const cargarMorosidad = async () => {
    const response = await fetch(
        "http://localhost:8000/api/dashboard/morosidad-clientes"
    );
    const data = await response.json();
    return data.datos;
};

// Cargar ventas por mes
const cargarVentas = async () => {
    const response = await fetch(
        "http://localhost:8000/api/dashboard/ventas-mes"
    );
    const data = await response.json();
    return data.datos;
};
```

### 3. Cargar Datos con Parámetros Personalizados

```javascript
// Cargar datos de un año específico
const cargarDatosPersonalizados = async (año = 2024, tipoFactura = "todas") => {
    const endpoints = [
        `http://localhost:8000/api/dashboard/tiempo-pago-custom?año=${año}&tipo_factura=${tipoFactura}`,
        `http://localhost:8000/api/dashboard/morosidad-custom?año=${año}&tipo_factura=${tipoFactura}`,
        `http://localhost:8000/api/dashboard/ventas-mes-custom?año=${año}`,
    ];

    const responses = await Promise.all(
        endpoints.map((url) => fetch(url).then((r) => r.json()))
    );

    return {
        tiempoPago: responses[0].datos,
        morosidad: responses[1].datos,
        ventas: responses[2].datos,
    };
};
```

---

## 🛠️ Cliente API Actualizado para React

```javascript
// services/dashboardApiService.js
const API_BASE_URL = "http://localhost:8000/api/dashboard";

export const dashboardAPI = {
    // ============ ENDPOINTS SIMPLES ============

    // Cargar dashboard completo
    obtenerDashboardCompleto: async () => {
        const response = await fetch(`${API_BASE_URL}/completo`);
        return response.json();
    },

    // Datos principales
    obtenerTiempoPago: async () => {
        const response = await fetch(`${API_BASE_URL}/tiempo-pago-promedio`);
        return response.json();
    },

    obtenerDistribucionPagos: async () => {
        const response = await fetch(`${API_BASE_URL}/distribucion-pagos`);
        return response.json();
    },

    obtenerMorosidad: async () => {
        const response = await fetch(`${API_BASE_URL}/morosidad-clientes`);
        return response.json();
    },

    obtenerVentasPorMes: async () => {
        const response = await fetch(`${API_BASE_URL}/ventas-mes`);
        return response.json();
    },

    obtenerTiempoEtapas: async () => {
        const response = await fetch(`${API_BASE_URL}/tiempo-etapas`);
        return response.json();
    },

    obtenerTiposFlujo: async () => {
        const response = await fetch(`${API_BASE_URL}/tipos-flujo`);
        return response.json();
    },

    // ============ ENDPOINTS PERSONALIZABLES ============

    obtenerTiempoPagoCustom: async (params = {}) => {
        const queryString = new URLSearchParams(params).toString();
        const response = await fetch(
            `${API_BASE_URL}/tiempo-pago-custom?${queryString}`
        );
        return response.json();
    },

    obtenerMorosidadCustom: async (params = {}) => {
        const queryString = new URLSearchParams(params).toString();
        const response = await fetch(
            `${API_BASE_URL}/morosidad-custom?${queryString}`
        );
        return response.json();
    },

    obtenerVentasCustom: async (params = {}) => {
        const queryString = new URLSearchParams(params).toString();
        const response = await fetch(
            `${API_BASE_URL}/ventas-mes-custom?${queryString}`
        );
        return response.json();
    },

    obtenerEtapasCustom: async (params = {}) => {
        const queryString = new URLSearchParams(params).toString();
        const response = await fetch(
            `${API_BASE_URL}/etapas-custom?${queryString}`
        );
        return response.json();
    },
};

export default dashboardAPI;
```

---

## 🎯 Hook React Simplificado

```javascript
// hooks/useDashboard.js
import { useState, useEffect } from "react";
import { dashboardAPI } from "../services/dashboardApiService";

export const useDashboard = (loadComplete = true) => {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        const cargarDatos = async () => {
            try {
                setLoading(true);
                setError(null);

                let resultado;

                if (loadComplete) {
                    // Cargar dashboard completo de una vez
                    resultado = await dashboardAPI.obtenerDashboardCompleto();
                } else {
                    // Cargar datos principales por separado
                    const [tiempoPago, morosidad, ventas, etapas] =
                        await Promise.all([
                            dashboardAPI.obtenerTiempoPago(),
                            dashboardAPI.obtenerMorosidad(),
                            dashboardAPI.obtenerVentasPorMes(),
                            dashboardAPI.obtenerTiempoEtapas(),
                        ]);

                    resultado = {
                        success: true,
                        datos: {
                            tiempo_pago: tiempoPago.datos,
                            morosidad: morosidad.datos,
                            ventas: ventas.datos,
                            tiempo_etapas: etapas.datos,
                        },
                    };
                }

                if (resultado.success) {
                    setData(resultado.datos);
                } else {
                    setError(resultado.message || "Error al cargar datos");
                }
            } catch (err) {
                setError(err.message);
                console.error("Error cargando dashboard:", err);
            } finally {
                setLoading(false);
            }
        };

        cargarDatos();
    }, [loadComplete]);

    return {
        data,
        loading,
        error,
        refetch: () => {
            cargarDatos();
        },
    };
};

// Hook para datos específicos
export const useTiempoPago = (params = {}) => {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        const cargarDatos = async () => {
            try {
                setLoading(true);
                const resultado =
                    Object.keys(params).length > 0
                        ? await dashboardAPI.obtenerTiempoPagoCustom(params)
                        : await dashboardAPI.obtenerTiempoPago();

                if (resultado.success) {
                    setData(resultado.datos);
                } else {
                    setError(resultado.message);
                }
            } catch (err) {
                setError(err.message);
            } finally {
                setLoading(false);
            }
        };

        cargarDatos();
    }, [JSON.stringify(params)]);

    return { data, loading, error };
};
```

---

## 🧪 Script de Testing para Todos los Endpoints

```javascript
// scripts/testDashboardEndpoints.js
const API_BASE_URL = "http://localhost:8000/api/dashboard";

const endpoints = [
    "ventas-mes",
    "resumen-anual",
    "tiempo-pago-promedio",
    "distribucion-pagos",
    "morosidad-clientes",
    "tiempo-etapas",
    "etapas-por-cliente",
    "distribucion-etapas",
    "tiempo-facturacion",
    "facturacion-por-cliente",
    "distribucion-facturacion",
    "tipos-flujo",
    "preferencias-flujo",
    "eficiencia-flujo",
    "pago-tiempo-completo",
    "completo",
];

const testEndpoint = async (endpoint) => {
    try {
        console.log(`🧪 Probando: ${endpoint}`);
        const response = await fetch(`${API_BASE_URL}/${endpoint}`);
        const data = await response.json();

        if (data.success) {
            console.log(`✅ ${endpoint}: OK`);
            return {
                endpoint,
                status: "success",
                dataKeys: Object.keys(data.datos || {}),
            };
        } else {
            console.log(`⚠️ ${endpoint}: ${data.message}`);
            return { endpoint, status: "warning", message: data.message };
        }
    } catch (error) {
        console.log(`❌ ${endpoint}: Error - ${error.message}`);
        return { endpoint, status: "error", error: error.message };
    }
};

const testAllEndpoints = async () => {
    console.log("🚀 Iniciando pruebas de endpoints GET...\n");

    const results = [];

    for (const endpoint of endpoints) {
        const result = await testEndpoint(endpoint);
        results.push(result);
        await new Promise((resolve) => setTimeout(resolve, 500)); // Pausa entre requests
    }

    console.log("\n📊 Resumen de pruebas:");
    const successful = results.filter((r) => r.status === "success").length;
    const warnings = results.filter((r) => r.status === "warning").length;
    const errors = results.filter((r) => r.status === "error").length;

    console.log(`✅ Exitosos: ${successful}`);
    console.log(`⚠️ Advertencias: ${warnings}`);
    console.log(`❌ Errores: ${errors}`);
    console.log(
        `📈 Tasa de éxito: ${((successful / results.length) * 100).toFixed(1)}%`
    );

    return results;
};

// Ejecutar en Node.js:
// node testDashboardEndpoints.js

// O en browser console:
testAllEndpoints().then((results) => console.table(results));
```

---

## 🎯 Ventajas de los Endpoints GET

### ✅ **Beneficios**

1. **Simplicidad**: No necesitas enviar datos POST
2. **Cacheable**: Los navegadores pueden cachear las respuestas GET
3. **Testeable**: Fácil de probar desde el navegador
4. **SEO Friendly**: Mejor para links directos
5. **Performance**: Menos overhead de datos

### 🚀 **Casos de Uso Recomendados**

-   **Dashboard Principal**: Usa `GET /api/dashboard/completo`
-   **Componentes Individuales**: Usa endpoints específicos
-   **Filtros Dinámicos**: Usa endpoints `-custom` con parámetros
-   **Desarrollo/Testing**: Usa endpoints simples para prototipado rápido

---

## 📱 Ejemplo de Componente React Completo

```javascript
// components/Dashboard/MainDashboard.jsx
import React from "react";
import { useDashboard } from "../../hooks/useDashboard";

const MainDashboard = () => {
    const { data, loading, error } = useDashboard(true); // Cargar completo

    if (loading) {
        return (
            <div className="flex justify-center items-center h-64">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                <span className="ml-2">Cargando dashboard...</span>
            </div>
        );
    }

    if (error) {
        return (
            <div className="bg-red-50 border border-red-200 rounded-md p-4">
                <h3 className="text-red-800">Error al cargar dashboard</h3>
                <p className="text-red-700">{error}</p>
            </div>
        );
    }

    return (
        <div className="space-y-6">
            {/* KPIs principales */}
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div className="bg-white p-6 rounded-lg shadow">
                    <h3 className="text-sm font-medium text-gray-500">
                        Tiempo Promedio Pago
                    </h3>
                    <p className="text-2xl font-bold text-blue-600">
                        {data.tiempo_pago?.promedio?.tiempo_promedio_pago} días
                    </p>
                </div>

                <div className="bg-white p-6 rounded-lg shadow">
                    <h3 className="text-sm font-medium text-gray-500">
                        Facturas Pagadas
                    </h3>
                    <p className="text-2xl font-bold text-green-600">
                        {
                            data.tiempo_pago?.promedio?.resumen
                                ?.porcentaje_pagadas
                        }
                        %
                    </p>
                </div>

                <div className="bg-white p-6 rounded-lg shadow">
                    <h3 className="text-sm font-medium text-gray-500">
                        Clientes Analizados
                    </h3>
                    <p className="text-2xl font-bold text-purple-600">
                        {data.tiempo_pago?.morosidad?.total_clientes_analizados}
                    </p>
                </div>

                <div className="bg-white p-6 rounded-lg shadow">
                    <h3 className="text-sm font-medium text-gray-500">
                        Ventas 2024
                    </h3>
                    <p className="text-2xl font-bold text-orange-600">
                        {
                            data.ventas?.por_mes?.datos?.ventas_por_mes?.[0]
                                ?.total_ventas
                        }
                    </p>
                </div>
            </div>

            {/* Aquí agregar más componentes del dashboard */}
            {/* Gráficos, tablas, etc. */}
        </div>
    );
};

export default MainDashboard;
```

---

## � FILTRADO DINÁMICO EN EL FRONTEND

### 📊 Estrategia de Filtrado Recomendada

Con los endpoints retornando **todos los datos históricos**, ahora puedes implementar filtrado dinámico en el frontend:

```javascript
// Ejemplo de filtrado por año en React
const [datos, setDatos] = useState([]);
const [añoSeleccionado, setAñoSeleccionado] = useState(2024);

useEffect(() => {
    // Cargar todos los datos una vez
    fetch("/api/dashboard/ventas-mes")
        .then((res) => res.json())
        .then((data) => setDatos(data.datos));
}, []);

// Filtrar en el frontend
const datosFiltrados = datos.filter((item) => {
    const año = new Date(item.fecha).getFullYear();
    return año === añoSeleccionado;
});
```

### 🔄 Ventajas del Filtrado Frontend

-   **✅ Mejor UX**: Filtrado instantáneo sin llamadas al servidor
-   **✅ Menos tráfico**: Una sola carga inicial de datos
-   **✅ Flexibilidad**: Combinar múltiples filtros (año, mes, cliente)
-   **✅ Performance**: Caching de datos completos en el frontend
-   **✅ Analytics**: Capacidad de comparar años, tendencias, etc.

### 📈 Ejemplos de Filtros Dinámicos

```javascript
// Filtro por rango de fechas
const filtrarPorRango = (datos, fechaInicio, fechaFin) => {
    return datos.filter((item) => {
        const fecha = new Date(item.fecha);
        return fecha >= fechaInicio && fecha <= fechaFin;
    });
};

// Filtro por cliente
const filtrarPorCliente = (datos, clienteId) => {
    return datos.filter((item) => item.cliente_id === clienteId);
};

// Filtro combinado
const aplicarFiltros = (datos, filtros) => {
    return datos.filter((item) => {
        if (filtros.año && new Date(item.fecha).getFullYear() !== filtros.año)
            return false;
        if (filtros.cliente && item.cliente_id !== filtros.cliente)
            return false;
        if (filtros.estado && item.estado !== filtros.estado) return false;
        return true;
    });
};
```

---

## 🎉 Resumen

Con estos **22 nuevos endpoints GET**, la integración del frontend es ahora **extremadamente sencilla**:

-   ✅ **22 endpoints** retornando **datos completos históricos**
-   ✅ **Filtrado dinámico** implementable en el frontend
-   ✅ **Respuestas instantáneas** con todos los datos
-   ✅ **Fácil testing** desde browser o herramientas HTTP
-   ✅ **Código JavaScript listo** para usar en React
-   ✅ **Máxima flexibilidad** para analytics y dashboards

¡Tu dashboard ahora tiene acceso completo a todos los datos históricos con filtrado dinámico!
