# 📊 DOCUMENTACIÓN DE ENDPOINTS DE ANALÍTICAS POR CLIENTE

## 🎯 DESCRIPCIÓN GENERAL

Esta documentación describe los endpoints creados para **analíticas personalizadas por cliente**. Estos endpoints permiten:

-   📋 Listar todos los clientes para selección en frontend
-   📊 Obtener dashboard completo personalizado por cliente
-   🔍 Comparar métricas entre dos clientes diferentes
-   📈 Analizar tendencias y comportamientos específicos

---

## 📋 ENDPOINTS DISPONIBLES

### 1. 📋 **GET /api/clientes/listar** - Lista de Clientes

**Propósito**: Obtener lista completa de clientes con estadísticas básicas para selector en frontend

**Respuesta Incluye**:

-   ID del cliente (para navegación)
-   Nombre del cliente
-   Total de ventas y facturas
-   Valor total de comercializaciones
-   Fecha de última actividad
-   Estado de actividad (activo, poco_activo, inactivo, muy_inactivo)
-   Resumen general del sistema

**Ejemplo de Uso**:

```javascript
// JavaScript/React
const cargarClientes = async () => {
    const response = await fetch("/api/clientes/listar");
    const data = await response.json();

    // Usar para llenar selector
    setClientes(data.datos.clientes);
};

// Ejemplo de selector React
<select onChange={(e) => navegarAAnalytics(e.target.value)}>
    <option value="">Seleccionar Cliente</option>
    {clientes.map((cliente) => (
        <option key={cliente.id} value={cliente.id}>
            {cliente.nombre} ({cliente.estadisticas.total_ventas} ventas)
        </option>
    ))}
</select>;
```

---

### 2. 📊 **GET /api/clientes/{id}/analytics** - Dashboard Completo por Cliente

**Propósito**: Obtener todas las analíticas personalizadas de un cliente específico

**Parámetros**:

-   `{id}`: ID del cliente a analizar

**Analíticas Incluidas**:

-   **Información del Cliente**: Datos básicos y identificación
-   **Resumen General**: Totales, promedios, período de actividad
-   **Ventas Históricas**: Detalle chronológico y agrupaciones temporales
-   **Análisis de Tiempos**: Desarrollo, facturación, pagos
-   **Análisis de Facturación**: Preferencias SENCE vs Cliente
-   **Análisis de Pagos**: Morosidad, tiempos, clasificaciones
-   **Comportamiento de Flujo**: Adopción de financiamiento SENCE
-   **Tendencias Temporales**: Evolución anual, crecimiento, estacionalidad
-   **Comparativa de Mercado**: Posición relativa, ranking

**Ejemplo de Uso**:

```javascript
// Cargar analíticas completas de un cliente
const cargarAnalyticsCliente = async (clienteId) => {
    const response = await fetch(`/api/clientes/${clienteId}/analytics`);
    const data = await response.json();

    // Usar datos para dashboard personalizado
    setResumenGeneral(data.datos.resumen_general);
    setVentasHistoricas(data.datos.ventas_historicas);
    setAnalisisTiempos(data.datos.analisis_tiempos);
    // ... más métricas
};

// Hook React personalizado
const useClienteAnalytics = (clienteId) => {
    const [analytics, setAnalytics] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        if (clienteId) {
            cargarAnalyticsCliente(clienteId).then((data) => {
                setAnalytics(data.datos);
                setLoading(false);
            });
        }
    }, [clienteId]);

    return { analytics, loading };
};
```

---

### 3. 🔍 **GET /api/clientes/{id}/comparar** - Comparar Dos Clientes

**Propósito**: Comparativa detallada entre dos clientes específicos

**Parámetros**:

-   `{id}`: ID del primer cliente
-   `cliente_comparacion`: ID del segundo cliente (query parameter)

**Comparativas Incluidas**:

-   Información de ambos clientes
-   Métricas comparadas lado a lado
-   Análisis de diferencias y fortalezas
-   Identificación de patrones diferenciales

**Ejemplo de Uso**:

```javascript
// Comparar dos clientes
const compararClientes = async (cliente1Id, cliente2Id) => {
    const response = await fetch(
        `/api/clientes/${cliente1Id}/comparar?cliente_comparacion=${cliente2Id}`
    );
    const data = await response.json();

    // Mostrar comparativa
    setComparativa(data.datos);
};

// Componente de comparación React
const ComparativaClientes = ({ cliente1Id, cliente2Id }) => {
    const { comparativa, loading } = useComparativaClientes(
        cliente1Id,
        cliente2Id
    );

    if (loading) return <div>Cargando comparativa...</div>;

    return (
        <div className="comparativa-grid">
            <div className="cliente-a">
                <h3>{comparativa.clientes.cliente_a.nombre}</h3>
                <p>
                    Ventas: {comparativa.metricas_comparadas.ventas.cliente_a}
                </p>
            </div>
            <div className="cliente-b">
                <h3>{comparativa.clientes.cliente_b.nombre}</h3>
                <p>
                    Ventas: {comparativa.metricas_comparadas.ventas.cliente_b}
                </p>
            </div>
        </div>
    );
};
```

---

### 4. 👥 **GET /api/dashboard/clientes-lista** - Endpoint Simplificado

**Propósito**: Versión simplificada del listado para dashboard general

**Uso**: Idéntico a `/api/clientes/listar` pero integrado en la estructura de endpoints de dashboard.

### 📋 **Ejemplo Real de Respuesta - Lista de Clientes**

```json
{
    "success": true,
    "message": "Lista de clientes obtenida exitosamente",
    "datos": {
        "clientes": [
            {
                "id": 299,
                "insecap_id": 65,
                "nombre": " PUCARA S.A.",
                "estadisticas": {
                    "total_ventas": 21,
                    "total_facturas": 0,
                    "valor_total_comercializaciones": 4575200,
                    "ultima_actividad": "2023-11-27T00:00:00.000000Z",
                    "estado_actividad": "muy_inactivo"
                }
            },
            {
                "id": 215,
                "insecap_id": 1063,
                "nombre": "AGRÍCOLA SAN CLEMENTE LTDA.",
                "estadisticas": {
                    "total_ventas": 18,
                    "total_facturas": 0,
                    "valor_total_comercializaciones": 17360429,
                    "ultima_actividad": "2025-03-11T00:00:00.000000Z",
                    "estado_actividad": "inactivo"
                }
            }
        ],
        "total_clientes": 554,
        "resumen": {
            "total_ventas_sistema": 1250,
            "total_facturas_sistema": 85,
            "valor_total_sistema": 125000000
        }
    }
}
```

### 📊 **Ejemplo Real de Respuesta - Analíticas de Cliente**

```json
{
    "success": true,
    "message": "Analíticas completas para  PUCARA S.A.",
    "datos": {
        "cliente_info": {
            "id": 299,
            "insecap_id": 65,
            "nombre": " PUCARA S.A."
        },
        "resumen_general": {
            "total_ventas": 21,
            "valor_total_comercializaciones": 4575200,
            "valor_promedio_venta": 217866.67,
            "periodo_actividad": {
                "primera_venta": "2023-02-15T00:00:00.000000Z",
                "ultima_venta": "2023-11-27T00:00:00.000000Z",
                "años_como_cliente": 2.4
            }
        },
        "ventas_historicas": {
            "ventas_recientes": [
                {
                    "codigo_cotizacion": "CAL201588-3",
                    "fecha_inicio": "2023-11-27T00:00:00.000000Z",
                    "valor_comercializacion": "160000.00"
                }
            ],
            "agrupacion_anual": [
                {
                    "año": 2023,
                    "cantidad": 21,
                    "valor_total": "4575200.00"
                }
            ],
            "total_historico": 21
        },
        "analisis_pagos": {
            "total_facturas": 0,
            "facturas_pagadas": 0,
            "facturas_pendientes": 0,
            "porcentaje_pago": 0,
            "clasificacion_pago": "sin_datos"
        },
        "timestamp": "2025-07-12T10:33:53.014963Z"
    }
}
```

---

## 🛠️ IMPLEMENTACIÓN EN FRONTEND

### Flujo de Navegación Recomendado

```javascript
// 1. Página Principal del Dashboard
const DashboardPrincipal = () => {
    const [clientes, setClientes] = useState([]);

    useEffect(() => {
        // Cargar lista de clientes al iniciar
        fetch("/api/dashboard/clientes-lista")
            .then((res) => res.json())
            .then((data) => setClientes(data.datos.clientes));
    }, []);

    const navegarACliente = (clienteId) => {
        // Navegar a página de analíticas del cliente
        navigate(`/cliente/${clienteId}/analytics`);
    };

    return (
        <div>
            <h1>Dashboard General</h1>
            <ClienteSelector
                clientes={clientes}
                onSeleccionar={navegarACliente}
            />
            {/* Dashboard general */}
        </div>
    );
};

// 2. Página de Analíticas por Cliente
const AnalyticsCliente = ({ clienteId }) => {
    const { analytics, loading } = useClienteAnalytics(clienteId);

    if (loading) return <LoadingSpinner />;

    return (
        <div className="analytics-cliente">
            <HeaderCliente cliente={analytics.cliente_info} />
            <ResumenGeneral datos={analytics.resumen_general} />
            <VentasHistoricas datos={analytics.ventas_historicas} />
            <AnalisisTiempos datos={analytics.analisis_tiempos} />
            <AnalisisFacturacion datos={analytics.analisis_facturacion} />
            <AnalisisPagos datos={analytics.analisis_pagos} />
            <TendenciasTemporales datos={analytics.tendencias_temporales} />
            <ComparativaMercado datos={analytics.comparativa_mercado} />
        </div>
    );
};
```

### Componentes Sugeridos

```javascript
// Selector de clientes con búsqueda
const ClienteSelector = ({ clientes, onSeleccionar }) => {
    const [busqueda, setBusqueda] = useState("");

    const clientesFiltrados = clientes.filter((cliente) =>
        cliente.nombre.toLowerCase().includes(busqueda.toLowerCase())
    );

    return (
        <div className="cliente-selector">
            <input
                type="text"
                placeholder="Buscar cliente..."
                value={busqueda}
                onChange={(e) => setBusqueda(e.target.value)}
            />
            <div className="clientes-grid">
                {clientesFiltrados.map((cliente) => (
                    <ClienteCard
                        key={cliente.id}
                        cliente={cliente}
                        onClick={() => onSeleccionar(cliente.id)}
                    />
                ))}
            </div>
        </div>
    );
};

// Tarjeta de cliente con estadísticas
const ClienteCard = ({ cliente, onClick }) => (
    <div className="cliente-card" onClick={onClick}>
        <h3>{cliente.nombre}</h3>
        <div className="estadisticas">
            <span>💼 {cliente.estadisticas.total_ventas} ventas</span>
            <span>📄 {cliente.estadisticas.total_facturas} facturas</span>
            <span>
                💰 $
                {cliente.estadisticas.valor_total_comercializaciones.toLocaleString()}
            </span>
            <span className={`estado ${cliente.estadisticas.estado_actividad}`}>
                {cliente.estadisticas.estado_actividad}
            </span>
        </div>
    </div>
);
```

---

## 📊 MÉTRICAS DISPONIBLES POR CLIENTE

### Resumen General

-   Total de ventas y facturas
-   Valor total y promedio de comercializaciones
-   Período de actividad (primera/última venta)
-   Años como cliente

### Análisis de Tiempos

-   Tiempo promedio de desarrollo (estado 0→1)
-   Distribución de tiempos por rangos
-   Identificación de proyectos más/menos eficientes

### Análisis de Facturación

-   Preferencias de facturación (SENCE vs Cliente)
-   Porcentaje de uso de financiamiento
-   Patrones de facturación

### Análisis de Pagos

-   Clasificación de morosidad (excelente, bueno, regular, malo, crítico)
-   Tiempos promedio de pago
-   Facturas pagadas vs pendientes
-   Montos involucrados

### Comportamiento de Flujo

-   Adopción de financiamiento SENCE
-   Preferencias de flujo comercial
-   Eficiencia por tipo de flujo

### Tendencias Temporales

-   Evolución anual de ventas y valores
-   Análisis de crecimiento
-   Patrones de estacionalidad

### Comparativa de Mercado

-   Posición relativa en el mercado
-   Ranking general entre clientes
-   Comparación con promedios del mercado

---

## 🎯 CASOS DE USO

### 1. Selector de Cliente en Dashboard

```javascript
// Cargar y mostrar lista para selección
fetch("/api/clientes/listar")
    .then((res) => res.json())
    .then((data) => {
        // Usar data.datos.clientes para selector
    });
```

### 2. Dashboard Personalizado por Cliente

```javascript
// Cargar analíticas completas
fetch(`/api/clientes/${clienteId}/analytics`)
    .then((res) => res.json())
    .then((data) => {
        // Usar data.datos para dashboard personalizado
    });
```

### 3. Comparación Entre Clientes

```javascript
// Comparar dos clientes específicos
fetch(`/api/clientes/123/comparar?cliente_comparacion=456`)
    .then((res) => res.json())
    .then((data) => {
        // Usar data.datos para vista comparativa
    });
```

### 4. Análisis de Cartera de Clientes

```javascript
// Obtener todos los clientes y analizar patrones
const analizarCartera = async () => {
    const clientes = await fetch("/api/clientes/listar").then((r) => r.json());

    // Identificar mejores clientes
    const mejoresClientes = clientes.datos.clientes
        .filter((c) => c.estadisticas.estado_actividad === "activo")
        .sort(
            (a, b) =>
                b.estadisticas.valor_total_comercializaciones -
                a.estadisticas.valor_total_comercializaciones
        )
        .slice(0, 10);

    return mejoresClientes;
};
```

---

## ✅ RESUMEN

Con estos **4 nuevos endpoints** tienes la capacidad completa para:

-   ✅ **Listar clientes** con estadísticas para selección
-   ✅ **Dashboard personalizado** con analíticas completas por cliente
-   ✅ **Comparar clientes** para benchmarking interno
-   ✅ **Integración sencilla** en frontend React

**Flujo de Navegación**:

1. Dashboard general → Lista de clientes
2. Seleccionar cliente → Navegar a analíticas personalizadas
3. Ver métricas completas del cliente específico
4. Opcionalmente comparar con otros clientes

¡Ahora puedes crear páginas de analíticas personalizadas para cada cliente de tu empresa! 🎯📊

---

## 🚀 GUÍA DE IMPLEMENTACIÓN FRONTEND

### 📋 **PASO 1: Página de Lista de Clientes**

#### React/Next.js - Componente Principal

```jsx
// pages/clientes/index.jsx
import React, { useState, useEffect } from "react";
import { useRouter } from "next/router";

export default function ListaClientes() {
    const [clientes, setClientes] = useState([]);
    const [loading, setLoading] = useState(true);
    const [filtro, setFiltro] = useState("");
    const [ordenPor, setOrdenPor] = useState("nombre");
    const router = useRouter();

    // Cargar lista de clientes al montar componente
    useEffect(() => {
        cargarClientes();
    }, []);

    const cargarClientes = async () => {
        try {
            setLoading(true);
            const response = await fetch("/api/clientes-analytics/");
            const data = await response.json();

            if (data.success) {
                setClientes(data.datos.clientes);
            } else {
                console.error("Error al cargar clientes:", data.message);
            }
        } catch (error) {
            console.error("Error de conexión:", error);
        } finally {
            setLoading(false);
        }
    };

    // Navegar a analíticas del cliente seleccionado
    const navegarAAnalytics = (clienteId, nombreCliente) => {
        router.push(
            `/clientes/${clienteId}/analytics?nombre=${encodeURIComponent(
                nombreCliente
            )}`
        );
    };

    // Filtrar y ordenar clientes
    const clientesFiltrados = clientes
        .filter(
            (cliente) =>
                cliente.nombre.toLowerCase().includes(filtro.toLowerCase()) ||
                cliente.estadisticas.estado_actividad.includes(
                    filtro.toLowerCase()
                )
        )
        .sort((a, b) => {
            switch (ordenPor) {
                case "ventas":
                    return (
                        b.estadisticas.total_ventas -
                        a.estadisticas.total_ventas
                    );
                case "valor":
                    return (
                        b.estadisticas.valor_total_comercializaciones -
                        a.estadisticas.valor_total_comercializaciones
                    );
                case "actividad":
                    return (
                        new Date(b.estadisticas.ultima_actividad || 0) -
                        new Date(a.estadisticas.ultima_actividad || 0)
                    );
                default:
                    return a.nombre.localeCompare(b.nombre);
            }
        });

    const getEstadoColor = (estado) => {
        switch (estado) {
            case "activo":
                return "text-green-600 bg-green-100";
            case "poco_activo":
                return "text-yellow-600 bg-yellow-100";
            case "inactivo":
                return "text-orange-600 bg-orange-100";
            case "muy_inactivo":
                return "text-red-600 bg-red-100";
            default:
                return "text-gray-600 bg-gray-100";
        }
    };

    if (loading) {
        return (
            <div className="flex justify-center items-center min-h-screen">
                <div className="animate-spin rounded-full h-32 w-32 border-b-2 border-blue-500"></div>
            </div>
        );
    }

    return (
        <div className="container mx-auto px-4 py-8">
            {/* Header */}
            <div className="mb-8">
                <h1 className="text-3xl font-bold text-gray-900 mb-2">
                    📊 Analíticas por Cliente
                </h1>
                <p className="text-gray-600">
                    Selecciona un cliente para ver sus analíticas personalizadas
                </p>
            </div>

            {/* Controles de Filtrado */}
            <div className="bg-white p-6 rounded-lg shadow-sm mb-6">
                <div className="flex flex-col md:flex-row gap-4">
                    <div className="flex-1">
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                            🔍 Buscar Cliente
                        </label>
                        <input
                            type="text"
                            placeholder="Buscar por nombre o estado..."
                            value={filtro}
                            onChange={(e) => setFiltro(e.target.value)}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                            📊 Ordenar por
                        </label>
                        <select
                            value={ordenPor}
                            onChange={(e) => setOrdenPor(e.target.value)}
                            className="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="nombre">Nombre A-Z</option>
                            <option value="ventas">Más Ventas</option>
                            <option value="valor">Mayor Valor</option>
                            <option value="actividad">Más Reciente</option>
                        </select>
                    </div>
                </div>
            </div>

            {/* Estadísticas Generales */}
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div className="bg-blue-50 p-4 rounded-lg">
                    <h3 className="text-sm font-medium text-blue-600">
                        Total Clientes
                    </h3>
                    <p className="text-2xl font-bold text-blue-900">
                        {clientes.length}
                    </p>
                </div>
                <div className="bg-green-50 p-4 rounded-lg">
                    <h3 className="text-sm font-medium text-green-600">
                        Clientes Activos
                    </h3>
                    <p className="text-2xl font-bold text-green-900">
                        {
                            clientes.filter(
                                (c) =>
                                    c.estadisticas.estado_actividad === "activo"
                            ).length
                        }
                    </p>
                </div>
                <div className="bg-yellow-50 p-4 rounded-lg">
                    <h3 className="text-sm font-medium text-yellow-600">
                        Total Ventas
                    </h3>
                    <p className="text-2xl font-bold text-yellow-900">
                        {clientes
                            .reduce(
                                (sum, c) => sum + c.estadisticas.total_ventas,
                                0
                            )
                            .toLocaleString()}
                    </p>
                </div>
                <div className="bg-purple-50 p-4 rounded-lg">
                    <h3 className="text-sm font-medium text-purple-600">
                        Valor Total
                    </h3>
                    <p className="text-2xl font-bold text-purple-900">
                        $
                        {clientes
                            .reduce(
                                (sum, c) =>
                                    sum +
                                    c.estadisticas
                                        .valor_total_comercializaciones,
                                0
                            )
                            .toLocaleString()}
                    </p>
                </div>
            </div>

            {/* Lista de Clientes */}
            <div className="bg-white rounded-lg shadow-sm overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Cliente
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Estado
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Ventas
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Valor Total
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Última Actividad
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200">
                            {clientesFiltrados.map((cliente) => (
                                <tr
                                    key={cliente.id}
                                    className="hover:bg-gray-50 cursor-pointer transition-colors"
                                    onClick={() =>
                                        navegarAAnalytics(
                                            cliente.id,
                                            cliente.nombre
                                        )
                                    }
                                >
                                    <td className="px-6 py-4 whitespace-nowrap">
                                        <div>
                                            <div className="text-sm font-medium text-gray-900">
                                                {cliente.nombre}
                                            </div>
                                            <div className="text-sm text-gray-500">
                                                ID: {cliente.id}
                                            </div>
                                        </div>
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap">
                                        <span
                                            className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${getEstadoColor(
                                                cliente.estadisticas
                                                    .estado_actividad
                                            )}`}
                                        >
                                            {cliente.estadisticas.estado_actividad.replace(
                                                "_",
                                                " "
                                            )}
                                        </span>
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {cliente.estadisticas.total_ventas.toLocaleString()}
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        $
                                        {cliente.estadisticas.valor_total_comercializaciones.toLocaleString()}
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {cliente.estadisticas.ultima_actividad
                                            ? new Date(
                                                  cliente.estadisticas.ultima_actividad
                                              ).toLocaleDateString()
                                            : "Sin actividad"}
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button
                                            onClick={(e) => {
                                                e.stopPropagation();
                                                navegarAAnalytics(
                                                    cliente.id,
                                                    cliente.nombre
                                                );
                                            }}
                                            className="text-blue-600 hover:text-blue-900 transition-colors"
                                        >
                                            Ver Analíticas →
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {clientesFiltrados.length === 0 && (
                    <div className="text-center py-12">
                        <p className="text-gray-500">
                            No se encontraron clientes que coincidan con el
                            filtro
                        </p>
                    </div>
                )}
            </div>
        </div>
    );
}
```

### 📊 **PASO 2: Página de Analíticas Individual**

```jsx
// pages/clientes/[id]/analytics.jsx
import React, { useState, useEffect } from "react";
import { useRouter } from "next/router";

export default function AnalyticsCliente() {
    const [analytics, setAnalytics] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const router = useRouter();
    const { id, nombre } = router.query;

    useEffect(() => {
        if (id) {
            cargarAnalytics();
        }
    }, [id]);

    const cargarAnalytics = async () => {
        try {
            setLoading(true);
            setError(null);

            const response = await fetch(
                `/api/clientes-analytics/${id}/analytics`
            );
            const data = await response.json();

            if (data.success) {
                setAnalytics(data.datos);
            } else {
                setError(data.message || "Error al cargar analíticas");
            }
        } catch (error) {
            setError("Error de conexión: " + error.message);
        } finally {
            setLoading(false);
        }
    };

    const formatCurrency = (amount) => {
        return new Intl.NumberFormat("es-CL", {
            style: "currency",
            currency: "CLP",
        }).format(amount);
    };

    const formatDate = (dateString) => {
        if (!dateString) return "No disponible";
        return new Date(dateString).toLocaleDateString("es-CL");
    };

    if (loading) {
        return (
            <div className="flex justify-center items-center min-h-screen">
                <div className="animate-spin rounded-full h-32 w-32 border-b-2 border-blue-500"></div>
            </div>
        );
    }

    if (error) {
        return (
            <div className="container mx-auto px-4 py-8">
                <div className="bg-red-50 border border-red-200 rounded-lg p-6">
                    <h2 className="text-red-800 font-semibold mb-2">
                        Error al cargar analíticas
                    </h2>
                    <p className="text-red-600">{error}</p>
                    <button
                        onClick={() => router.back()}
                        className="mt-4 px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700"
                    >
                        Volver
                    </button>
                </div>
            </div>
        );
    }

    if (!analytics) {
        return (
            <div className="container mx-auto px-4 py-8">
                <p>No se encontraron datos de analíticas para este cliente.</p>
            </div>
        );
    }

    return (
        <div className="container mx-auto px-4 py-8">
            {/* Header con navegación */}
            <div className="mb-8">
                <div className="flex items-center justify-between">
                    <div>
                        <button
                            onClick={() => router.back()}
                            className="text-blue-600 hover:text-blue-800 mb-2 flex items-center"
                        >
                            ← Volver a Lista de Clientes
                        </button>
                        <h1 className="text-3xl font-bold text-gray-900">
                            📊 Analíticas de {analytics.cliente_info.nombre}
                        </h1>
                        <p className="text-gray-600">
                            Cliente ID: {analytics.cliente_info.id} | Insecap
                            ID: {analytics.cliente_info.insecap_id}
                        </p>
                    </div>
                    <div className="text-sm text-gray-500">
                        Última actualización: {formatDate(analytics.timestamp)}
                    </div>
                </div>
            </div>

            {/* Métricas Principales */}
            <div className="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div className="bg-blue-50 p-6 rounded-lg">
                    <h3 className="text-sm font-medium text-blue-600 mb-2">
                        Total Ventas
                    </h3>
                    <p className="text-3xl font-bold text-blue-900">
                        {analytics.resumen_general.total_ventas}
                    </p>
                </div>
                <div className="bg-green-50 p-6 rounded-lg">
                    <h3 className="text-sm font-medium text-green-600 mb-2">
                        Valor Total
                    </h3>
                    <p className="text-3xl font-bold text-green-900">
                        {formatCurrency(
                            analytics.resumen_general
                                .valor_total_comercializaciones
                        )}
                    </p>
                </div>
                <div className="bg-yellow-50 p-6 rounded-lg">
                    <h3 className="text-sm font-medium text-yellow-600 mb-2">
                        Promedio por Venta
                    </h3>
                    <p className="text-3xl font-bold text-yellow-900">
                        {formatCurrency(
                            analytics.resumen_general.valor_promedio_venta
                        )}
                    </p>
                </div>
                <div className="bg-purple-50 p-6 rounded-lg">
                    <h3 className="text-sm font-medium text-purple-600 mb-2">
                        Años como Cliente
                    </h3>
                    <p className="text-3xl font-bold text-purple-900">
                        {analytics.resumen_general.periodo_actividad.años_como_cliente.toFixed(
                            1
                        )}
                    </p>
                </div>
            </div>

            {/* Actividad Reciente */}
            <div className="bg-white rounded-lg shadow-sm p-6 mb-8">
                <h2 className="text-xl font-semibold mb-4">
                    📈 Actividad Reciente
                </h2>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h3 className="font-medium text-gray-700 mb-2">
                            Período de Actividad
                        </h3>
                        <p className="text-sm text-gray-600">
                            <strong>Primera venta:</strong>{" "}
                            {formatDate(
                                analytics.resumen_general.periodo_actividad
                                    .primera_venta
                            )}
                        </p>
                        <p className="text-sm text-gray-600">
                            <strong>Última venta:</strong>{" "}
                            {formatDate(
                                analytics.resumen_general.periodo_actividad
                                    .ultima_venta
                            )}
                        </p>
                    </div>
                    <div>
                        <h3 className="font-medium text-gray-700 mb-2">
                            Ventas por Año
                        </h3>
                        {analytics.ventas_historicas.agrupacion_anual.map(
                            (año) => (
                                <p
                                    key={año.año}
                                    className="text-sm text-gray-600"
                                >
                                    <strong>{año.año}:</strong> {año.cantidad}{" "}
                                    ventas -{" "}
                                    {formatCurrency(
                                        parseFloat(año.valor_total)
                                    )}
                                </p>
                            )
                        )}
                    </div>
                </div>
            </div>

            {/* Análisis de Pagos */}
            <div className="bg-white rounded-lg shadow-sm p-6 mb-8">
                <h2 className="text-xl font-semibold mb-4">
                    💰 Análisis de Pagos
                </h2>
                <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <h3 className="font-medium text-gray-700 mb-2">
                            Estado de Facturas
                        </h3>
                        <p className="text-sm text-gray-600">
                            Total: {analytics.analisis_pagos.total_facturas}
                        </p>
                        <p className="text-sm text-gray-600">
                            Pagadas: {analytics.analisis_pagos.facturas_pagadas}
                        </p>
                        <p className="text-sm text-gray-600">
                            Pendientes:{" "}
                            {analytics.analisis_pagos.facturas_pendientes}
                        </p>
                    </div>
                    <div>
                        <h3 className="font-medium text-gray-700 mb-2">
                            Comportamiento de Pago
                        </h3>
                        <p className="text-sm text-gray-600">
                            Porcentaje de pago:{" "}
                            {analytics.analisis_pagos.porcentaje_pago}%
                        </p>
                        <span
                            className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${
                                analytics.analisis_pagos.clasificacion_pago ===
                                "excelente"
                                    ? "bg-green-100 text-green-800"
                                    : analytics.analisis_pagos
                                          .clasificacion_pago === "bueno"
                                    ? "bg-blue-100 text-blue-800"
                                    : analytics.analisis_pagos
                                          .clasificacion_pago === "regular"
                                    ? "bg-yellow-100 text-yellow-800"
                                    : "bg-red-100 text-red-800"
                            }`}
                        >
                            {analytics.analisis_pagos.clasificacion_pago}
                        </span>
                    </div>
                    <div>
                        <h3 className="font-medium text-gray-700 mb-2">
                            Historia de Pagos
                        </h3>
                        {analytics.historia_pagos.resumen_comportamiento
                            .total_facturas > 0 ? (
                            <>
                                <p className="text-sm text-gray-600">
                                    Tiempo promedio:{" "}
                                    {
                                        analytics.historia_pagos
                                            .resumen_comportamiento
                                            .tiempo_promedio_pago
                                    }{" "}
                                    días
                                </p>
                                <p className="text-sm text-gray-600">
                                    Patrón:{" "}
                                    {analytics.historia_pagos.patron_pago}
                                </p>
                            </>
                        ) : (
                            <p className="text-sm text-gray-500">
                                Sin datos de historial
                            </p>
                        )}
                    </div>
                </div>
            </div>

            {/* Estimación de Pagos */}
            {analytics.estimacion_pagos.estimacion_disponible && (
                <div className="bg-white rounded-lg shadow-sm p-6 mb-8">
                    <h2 className="text-xl font-semibold mb-4">
                        🔮 Estimación de Pagos
                    </h2>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h3 className="font-medium text-gray-700 mb-2">
                                Escenarios de Pago
                            </h3>
                            <div className="space-y-2">
                                <p className="text-sm">
                                    <strong>Optimista:</strong>{" "}
                                    {
                                        analytics.estimacion_pagos
                                            .estimaciones_nueva_venta
                                            .escenario_optimista.dias_estimados
                                    }{" "}
                                    días
                                </p>
                                <p className="text-sm">
                                    <strong>Probable:</strong>{" "}
                                    {
                                        analytics.estimacion_pagos
                                            .estimaciones_nueva_venta
                                            .escenario_probable.dias_estimados
                                    }{" "}
                                    días
                                </p>
                                <p className="text-sm">
                                    <strong>Conservador:</strong>{" "}
                                    {
                                        analytics.estimacion_pagos
                                            .estimaciones_nueva_venta
                                            .escenario_conservador
                                            .dias_estimados
                                    }{" "}
                                    días
                                </p>
                            </div>
                        </div>
                        <div>
                            <h3 className="font-medium text-gray-700 mb-2">
                                Recomendaciones
                            </h3>
                            <ul className="text-sm text-gray-600 space-y-1">
                                {analytics.estimacion_pagos.recomendaciones.map(
                                    (rec, index) => (
                                        <li key={index}>• {rec}</li>
                                    )
                                )}
                            </ul>
                        </div>
                    </div>
                </div>
            )}

            {/* Análisis de Morosidad */}
            {analytics.analisis_morosidad.clasificacion !== "sin_datos" && (
                <div className="bg-white rounded-lg shadow-sm p-6 mb-8">
                    <h2 className="text-xl font-semibold mb-4">
                        ⚠️ Análisis de Morosidad
                    </h2>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h3 className="font-medium text-gray-700 mb-2">
                                Clasificación de Riesgo
                            </h3>
                            <span
                                className={`inline-flex px-3 py-1 text-sm font-semibold rounded-full ${
                                    analytics.analisis_morosidad
                                        .clasificacion === "excelente"
                                        ? "bg-green-100 text-green-800"
                                        : analytics.analisis_morosidad
                                              .clasificacion === "bueno"
                                        ? "bg-blue-100 text-blue-800"
                                        : analytics.analisis_morosidad
                                              .clasificacion === "regular"
                                        ? "bg-yellow-100 text-yellow-800"
                                        : analytics.analisis_morosidad
                                              .clasificacion === "riesgo"
                                        ? "bg-orange-100 text-orange-800"
                                        : "bg-red-100 text-red-800"
                                }`}
                            >
                                {analytics.analisis_morosidad.clasificacion.toUpperCase()}
                            </span>
                            <p className="text-sm text-gray-600 mt-2">
                                Facturas analizadas:{" "}
                                {
                                    analytics.analisis_morosidad
                                        .facturas_analizadas
                                }
                            </p>
                        </div>
                        <div>
                            <h3 className="font-medium text-gray-700 mb-2">
                                Recomendaciones Comerciales
                            </h3>
                            <ul className="text-sm text-gray-600 space-y-1">
                                {analytics.analisis_morosidad.recomendaciones_comerciales?.map(
                                    (rec, index) => (
                                        <li key={index}>• {rec}</li>
                                    )
                                )}
                            </ul>
                        </div>
                    </div>
                </div>
            )}

            {/* Ventas Recientes */}
            {analytics.ventas_historicas.ventas_recientes.length > 0 && (
                <div className="bg-white rounded-lg shadow-sm p-6 mb-8">
                    <h2 className="text-xl font-semibold mb-4">
                        📋 Ventas Recientes
                    </h2>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-2 text-left">
                                        Código
                                    </th>
                                    <th className="px-4 py-2 text-left">
                                        Fecha
                                    </th>
                                    <th className="px-4 py-2 text-left">
                                        Valor
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200">
                                {analytics.ventas_historicas.ventas_recientes
                                    .slice(0, 10)
                                    .map((venta, index) => (
                                        <tr key={index}>
                                            <td className="px-4 py-2">
                                                {venta.codigo_cotizacion}
                                            </td>
                                            <td className="px-4 py-2">
                                                {formatDate(venta.fecha_inicio)}
                                            </td>
                                            <td className="px-4 py-2">
                                                {formatCurrency(
                                                    parseFloat(
                                                        venta.valor_comercializacion
                                                    )
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            {/* Botón de Comparación */}
            <div className="flex justify-center">
                <button
                    onClick={() =>
                        router.push(`/clientes/comparar?cliente1=${id}`)
                    }
                    className="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                >
                    🔍 Comparar con Otro Cliente
                </button>
            </div>
        </div>
    );
}
```

### 🔍 **PASO 3: Página de Comparación (Opcional)**

```jsx
// pages/clientes/comparar.jsx
import React, { useState, useEffect } from "react";
import { useRouter } from "next/router";

export default function CompararClientes() {
    const [cliente1Id, setCliente1Id] = useState("");
    const [cliente2Id, setCliente2Id] = useState("");
    const [clientes, setClientes] = useState([]);
    const [comparacion, setComparacion] = useState(null);
    const [loading, setLoading] = useState(false);
    const router = useRouter();

    useEffect(() => {
        cargarClientes();
        if (router.query.cliente1) {
            setCliente1Id(router.query.cliente1);
        }
    }, [router.query]);

    const cargarClientes = async () => {
        try {
            const response = await fetch("/api/clientes-analytics/");
            const data = await response.json();
            if (data.success) {
                setClientes(data.datos.clientes);
            }
        } catch (error) {
            console.error("Error al cargar clientes:", error);
        }
    };

    const compararClientes = async () => {
        if (!cliente1Id || !cliente2Id) {
            alert("Selecciona ambos clientes para comparar");
            return;
        }

        try {
            setLoading(true);
            const response = await fetch(
                `/api/clientes-analytics/${cliente1Id}/compare/${cliente2Id}`
            );
            const data = await response.json();

            if (data.success) {
                setComparacion(data.datos);
            } else {
                alert("Error al comparar clientes: " + data.message);
            }
        } catch (error) {
            alert("Error de conexión: " + error.message);
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="container mx-auto px-4 py-8">
            <div className="mb-8">
                <button
                    onClick={() => router.back()}
                    className="text-blue-600 hover:text-blue-800 mb-2"
                >
                    ← Volver
                </button>
                <h1 className="text-3xl font-bold text-gray-900">
                    🔍 Comparar Clientes
                </h1>
            </div>

            {/* Selectores */}
            <div className="bg-white p-6 rounded-lg shadow-sm mb-8">
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                            Cliente 1
                        </label>
                        <select
                            value={cliente1Id}
                            onChange={(e) => setCliente1Id(e.target.value)}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md"
                        >
                            <option value="">Seleccionar cliente 1</option>
                            {clientes.map((cliente) => (
                                <option key={cliente.id} value={cliente.id}>
                                    {cliente.nombre}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                            Cliente 2
                        </label>
                        <select
                            value={cliente2Id}
                            onChange={(e) => setCliente2Id(e.target.value)}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md"
                        >
                            <option value="">Seleccionar cliente 2</option>
                            {clientes
                                .filter((c) => c.id != cliente1Id)
                                .map((cliente) => (
                                    <option key={cliente.id} value={cliente.id}>
                                        {cliente.nombre}
                                    </option>
                                ))}
                        </select>
                    </div>
                    <div>
                        <button
                            onClick={compararClientes}
                            disabled={loading || !cliente1Id || !cliente2Id}
                            className="w-full px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:bg-gray-400"
                        >
                            {loading ? "Comparando..." : "Comparar"}
                        </button>
                    </div>
                </div>
            </div>

            {/* Resultados de Comparación */}
            {comparacion && (
                <div className="bg-white rounded-lg shadow-sm p-6">
                    <h2 className="text-xl font-semibold mb-6">
                        📊 Resultados de Comparación
                    </h2>

                    {/* Métricas lado a lado */}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <h3 className="font-semibold text-lg mb-4 text-blue-600">
                                {comparacion.cliente_a.nombre}
                            </h3>
                            <div className="space-y-2">
                                <p>
                                    Ventas:{" "}
                                    {comparacion.metricas.ventas.cliente_a}
                                </p>
                                <p>
                                    Valor Total: $
                                    {comparacion.metricas.valor_total.cliente_a.toLocaleString()}
                                </p>
                                <p>
                                    Promedio: $
                                    {comparacion.metricas.valor_promedio.cliente_a.toLocaleString()}
                                </p>
                            </div>
                        </div>
                        <div>
                            <h3 className="font-semibold text-lg mb-4 text-green-600">
                                {comparacion.cliente_b.nombre}
                            </h3>
                            <div className="space-y-2">
                                <p>
                                    Ventas:{" "}
                                    {comparacion.metricas.ventas.cliente_b}
                                </p>
                                <p>
                                    Valor Total: $
                                    {comparacion.metricas.valor_total.cliente_b.toLocaleString()}
                                </p>
                                <p>
                                    Promedio: $
                                    {comparacion.metricas.valor_promedio.cliente_b.toLocaleString()}
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* Análisis */}
                    <div className="mt-8">
                        <h4 className="font-semibold mb-4">
                            📈 Análisis Comparativo
                        </h4>
                        <div className="space-y-2 text-sm text-gray-700">
                            <p>• {comparacion.analisis.ventas}</p>
                            <p>• {comparacion.analisis.valor_promedio}</p>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
```

### 🎯 **PASO 4: Configuración de Rutas (Next.js)**

```jsx
// next.config.js
module.exports = {
    async rewrites() {
        return [
            {
                source: "/api/:path*",
                destination: "http://localhost:8000/api/:path*", // URL de tu API Laravel
            },
        ];
    },
};
```

### 📱 **PASO 5: Navegación Principal**

```jsx
// components/Layout.jsx
import Link from "next/link";

export default function Layout({ children }) {
    return (
        <div className="min-h-screen bg-gray-50">
            <nav className="bg-white shadow-sm">
                <div className="container mx-auto px-4">
                    <div className="flex justify-between items-center py-4">
                        <Link
                            href="/"
                            className="text-xl font-bold text-gray-900"
                        >
                            📊 Analytics Dashboard
                        </Link>
                        <div className="space-x-4">
                            <Link
                                href="/dashboard"
                                className="text-gray-700 hover:text-gray-900"
                            >
                                Dashboard General
                            </Link>
                            <Link
                                href="/clientes"
                                className="text-gray-700 hover:text-gray-900"
                            >
                                🏢 Analíticas por Cliente
                            </Link>
                        </div>
                    </div>
                </div>
            </nav>
            <main>{children}</main>
        </div>
    );
}
```

---

## 🚀 **RESUMEN DE IMPLEMENTACIÓN**

### **URLs de Navegación:**

-   `/clientes` - Lista de todos los clientes
-   `/clientes/[id]/analytics` - Analíticas específicas del cliente
-   `/clientes/comparar` - Comparar dos clientes

### **APIs Utilizadas:**

-   `GET /api/clientes-analytics/` - Lista de clientes
-   `GET /api/clientes-analytics/{id}/analytics` - Analíticas del cliente
-   `GET /api/clientes-analytics/{id1}/compare/{id2}` - Comparación

### **Flujo de Usuario:**

1. **Landing** → Lista de clientes con filtros y estadísticas
2. **Selección** → Click en cliente → Navegación a analíticas personalizadas
3. **Analíticas** → Dashboard completo con métricas del cliente
4. **Comparación** → Opcional para benchmarking entre clientes

### **Características Clave:**

-   ✅ Interfaz responsive con Tailwind CSS
-   ✅ Filtrado y ordenamiento de clientes
-   ✅ Navegación intuitiva entre páginas
-   ✅ Visualización de métricas clave
-   ✅ Estados de carga y manejo de errores
-   ✅ Formateo de monedas y fechas
-   ✅ Clasificación visual de estados y riesgos

¡Con esta implementación tendrás un sistema completo de analíticas por cliente totalmente funcional! 🎯📊
