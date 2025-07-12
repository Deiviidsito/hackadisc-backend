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
