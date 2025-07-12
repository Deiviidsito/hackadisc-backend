# Dashboard Frontend - Análisis de Tiempos de Pago

## 🎨 Diseño y Arquitectura del Dashboard

### Estructura de Componentes Recomendada

```
src/
├── components/
│   ├── Dashboard/
│   │   ├── DashboardLayout.jsx
│   │   ├── Sidebar.jsx
│   │   └── Header.jsx
│   ├── Charts/
│   │   ├── DistribucionChart.jsx
│   │   ├── TendenciasChart.jsx
│   │   ├── MorosidadChart.jsx
│   │   └── MetricasCard.jsx
│   ├── Tables/
│   │   ├── ClientesMorososTable.jsx
│   │   ├── FacturasPendientesTable.jsx
│   │   └── TopClientesTable.jsx
│   ├── Filters/
│   │   ├── DateRangeFilter.jsx
│   │   ├── TipoFacturaFilter.jsx
│   │   └── FilterPanel.jsx
│   └── UI/
│       ├── LoadingSpinner.jsx
│       ├── ErrorBoundary.jsx
│       └── StatusBadge.jsx
├── hooks/
│   ├── useApiData.js
│   ├── useFilters.js
│   └── useChartData.js
├── services/
│   ├── apiService.js
│   └── chartUtils.js
└── utils/
    ├── dateUtils.js
    ├── formatters.js
    └── constants.js
```

## 📊 Librerías Recomendadas

### Core de Gráficos

```bash
npm install recharts chart.js react-chartjs-2
npm install @mui/material @emotion/react @emotion/styled
npm install @mui/icons-material @mui/x-date-pickers
```

### Utilidades Adicionales

```bash
npm install date-fns axios react-query
npm install react-table @tanstack/react-table
npm install react-router-dom framer-motion
```

## 🎯 Páginas y Secciones del Dashboard

### 1. Vista General (Overview)

-   **KPIs principales** en cards grandes
-   **Gráfico de tendencias** temporal
-   **Resumen de distribución** de tiempos
-   **Alertas críticas** destacadas

### 2. Análisis de Pagos

-   **Distribución de tiempos** (dona/barras)
-   **Comparativa temporal** (líneas)
-   **Top clientes** (ranking)
-   **Casos extremos** (tabla detallada)

### 3. Morosidad

-   **Matriz de clientes** por riesgo
-   **Tendencias de morosidad** temporal
-   **Facturas críticas** (>90 días)
-   **Predicciones** de pago

### 4. Reportes

-   **Exportación** a Excel/PDF
-   **Reportes programados**
-   **Comparativas** históricas
-   **Configuración** de alertas

## 📈 Implementación de Gráficos

### 1. Distribución de Tiempos de Pago (Donut Chart)

```jsx
// components/Charts/DistribucionChart.jsx
import React from "react";
import {
    PieChart,
    Pie,
    Cell,
    ResponsiveContainer,
    Tooltip,
    Legend,
} from "recharts";

const COLORES_DISTRIBUCION = {
    inmediato: "#4CAF50", // Verde
    muy_rapido: "#8BC34A", // Verde claro
    rapido: "#FFEB3B", // Amarillo
    normal: "#FF9800", // Naranja
    lento: "#FF5722", // Rojo naranja
    muy_lento: "#F44336", // Rojo
    critico: "#9C27B0", // Púrpura
};

const DistribucionChart = ({ data }) => {
    const chartData = Object.entries(data.distribucion || {}).map(
        ([key, value]) => ({
            name: value.descripcion,
            value: value.count,
            porcentaje: value.porcentaje,
            color: COLORES_DISTRIBUCION[key],
            categoria: key,
        })
    );

    const CustomTooltip = ({ active, payload }) => {
        if (active && payload && payload.length) {
            const data = payload[0].payload;
            return (
                <div className="bg-white p-3 border rounded shadow-lg">
                    <p className="font-semibold">{data.name}</p>
                    <p className="text-blue-600">{data.value} facturas</p>
                    <p className="text-gray-600">{data.porcentaje}%</p>
                </div>
            );
        }
        return null;
    };

    return (
        <div className="bg-white p-6 rounded-lg shadow-md">
            <h3 className="text-lg font-semibold mb-4">
                Distribución de Tiempos de Pago
            </h3>
            <ResponsiveContainer width="100%" height={400}>
                <PieChart>
                    <Pie
                        data={chartData}
                        cx="50%"
                        cy="50%"
                        innerRadius={60}
                        outerRadius={140}
                        paddingAngle={2}
                        dataKey="value"
                    >
                        {chartData.map((entry, index) => (
                            <Cell key={`cell-${index}`} fill={entry.color} />
                        ))}
                    </Pie>
                    <Tooltip content={<CustomTooltip />} />
                    <Legend
                        verticalAlign="bottom"
                        height={36}
                        formatter={(value, entry) =>
                            `${value} (${entry.payload.porcentaje}%)`
                        }
                    />
                </PieChart>
            </ResponsiveContainer>
        </div>
    );
};

export default DistribucionChart;
```

### 2. Tendencias Temporales (Line Chart)

```jsx
// components/Charts/TendenciasChart.jsx
import React from "react";
import {
    LineChart,
    Line,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip,
    Legend,
    ResponsiveContainer,
} from "recharts";

const TendenciasChart = ({ data, period = "monthly" }) => {
    return (
        <div className="bg-white p-6 rounded-lg shadow-md">
            <h3 className="text-lg font-semibold mb-4">
                Tendencias de Tiempo de Pago
            </h3>
            <ResponsiveContainer width="100%" height={300}>
                <LineChart data={data}>
                    <CartesianGrid strokeDasharray="3 3" />
                    <XAxis dataKey="periodo" tick={{ fontSize: 12 }} />
                    <YAxis
                        label={{
                            value: "Días promedio",
                            angle: -90,
                            position: "insideLeft",
                        }}
                        tick={{ fontSize: 12 }}
                    />
                    <Tooltip
                        formatter={(value, name) => [`${value} días`, name]}
                        labelFormatter={(label) => `Período: ${label}`}
                    />
                    <Legend />
                    <Line
                        type="monotone"
                        dataKey="tiempo_promedio"
                        stroke="#2196F3"
                        strokeWidth={3}
                        dot={{ r: 6 }}
                        name="Tiempo Promedio"
                    />
                    <Line
                        type="monotone"
                        dataKey="mediana"
                        stroke="#FF9800"
                        strokeWidth={2}
                        strokeDasharray="5 5"
                        name="Mediana"
                    />
                </LineChart>
            </ResponsiveContainer>
        </div>
    );
};

export default TendenciasChart;
```

### 3. KPIs Cards

```jsx
// components/Charts/MetricasCard.jsx
import React from "react";
import { TrendingUp, TrendingDown, Clock, DollarSign } from "lucide-react";

const MetricaCard = ({
    titulo,
    valor,
    subtitulo,
    tendencia,
    icono: Icono,
    color = "blue",
}) => {
    const colorClasses = {
        blue: "bg-blue-50 text-blue-600 border-blue-200",
        green: "bg-green-50 text-green-600 border-green-200",
        red: "bg-red-50 text-red-600 border-red-200",
        orange: "bg-orange-50 text-orange-600 border-orange-200",
    };

    return (
        <div className={`p-6 rounded-lg border-2 ${colorClasses[color]}`}>
            <div className="flex items-center justify-between">
                <div>
                    <p className="text-sm font-medium text-gray-600">
                        {titulo}
                    </p>
                    <p className="text-3xl font-bold mt-2">{valor}</p>
                    <p className="text-sm text-gray-500 mt-1">{subtitulo}</p>
                </div>
                <div className="flex flex-col items-center">
                    <Icono size={32} className="mb-2" />
                    {tendencia && (
                        <div
                            className={`flex items-center text-xs ${
                                tendencia > 0
                                    ? "text-green-600"
                                    : "text-red-600"
                            }`}
                        >
                            {tendencia > 0 ? (
                                <TrendingUp size={16} />
                            ) : (
                                <TrendingDown size={16} />
                            )}
                            <span className="ml-1">{Math.abs(tendencia)}%</span>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
};

const MetricasPanel = ({ data }) => {
    return (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <MetricaCard
                titulo="Tiempo Promedio de Pago"
                valor={`${data.tiempo_promedio_pago} días`}
                subtitulo={`Mediana: ${data.estadisticas?.mediana_dias_pago} días`}
                icono={Clock}
                color="blue"
            />
            <MetricaCard
                titulo="Facturas Pagadas"
                valor={`${data.resumen?.porcentaje_pagadas}%`}
                subtitulo={`${data.resumen?.facturas_pagadas} de ${data.resumen?.facturas_analizadas}`}
                icono={DollarSign}
                color="green"
            />
            <MetricaCard
                titulo="Facturas Pendientes"
                valor={data.resumen?.facturas_pendientes}
                subtitulo="Requieren seguimiento"
                icono={Clock}
                color="orange"
            />
            <MetricaCard
                titulo="Casos Críticos"
                valor={data.distribucion?.critico?.count || 0}
                subtitulo=">90 días sin pago"
                icono={TrendingDown}
                color="red"
            />
        </div>
    );
};

export default MetricasPanel;
```

### 4. Tabla de Clientes Morosos

```jsx
// components/Tables/ClientesMorososTable.jsx
import React, { useMemo } from "react";
import { useTable, useSortBy, usePagination } from "react-table";

const StatusBadge = ({ status }) => {
    const colorMap = {
        excelente: "bg-green-100 text-green-800",
        bueno: "bg-blue-100 text-blue-800",
        regular: "bg-yellow-100 text-yellow-800",
        malo: "bg-orange-100 text-orange-800",
        critico: "bg-red-100 text-red-800",
    };

    return (
        <span
            className={`px-2 py-1 rounded-full text-xs font-medium ${colorMap[status]}`}
        >
            {status.charAt(0).toUpperCase() + status.slice(1)}
        </span>
    );
};

const ClientesMorososTable = ({ data }) => {
    const columns = useMemo(
        () => [
            {
                Header: "Cliente",
                accessor: "cliente",
                Cell: ({ value }) => (
                    <div
                        className="font-medium text-gray-900 truncate max-w-48"
                        title={value}
                    >
                        {value}
                    </div>
                ),
            },
            {
                Header: "Facturas Totales",
                accessor: "facturas_totales",
                Cell: ({ value }) => (
                    <span className="text-gray-600">{value}</span>
                ),
            },
            {
                Header: "% Pagadas",
                accessor: "porcentaje_pagadas",
                Cell: ({ value }) => (
                    <div className="flex items-center">
                        <div className="w-12 bg-gray-200 rounded-full h-2 mr-2">
                            <div
                                className={`h-2 rounded-full ${
                                    value >= 80
                                        ? "bg-green-500"
                                        : value >= 60
                                        ? "bg-yellow-500"
                                        : "bg-red-500"
                                }`}
                                style={{ width: `${value}%` }}
                            ></div>
                        </div>
                        <span className="text-sm font-medium">{value}%</span>
                    </div>
                ),
            },
            {
                Header: "Tiempo Promedio",
                accessor: "tiempo_promedio_pago_dias",
                Cell: ({ value }) => `${value} días`,
            },
            {
                Header: "Días Pendientes",
                accessor: "dias_promedio_pendientes",
                Cell: ({ value }) => (
                    <span
                        className={
                            value > 60
                                ? "text-red-600 font-semibold"
                                : "text-gray-600"
                        }
                    >
                        {value} días
                    </span>
                ),
            },
            {
                Header: "Estado",
                accessor: "clasificacion_morosidad",
                Cell: ({ value }) => <StatusBadge status={value} />,
            },
        ],
        []
    );

    const {
        getTableProps,
        getTableBodyProps,
        headerGroups,
        page,
        prepareRow,
        canPreviousPage,
        canNextPage,
        pageOptions,
        pageCount,
        gotoPage,
        nextPage,
        previousPage,
        setPageSize,
        state: { pageIndex, pageSize },
    } = useTable(
        {
            columns,
            data: data?.clientes || [],
            initialState: { pageIndex: 0, pageSize: 10 },
        },
        useSortBy,
        usePagination
    );

    return (
        <div className="bg-white rounded-lg shadow-md p-6">
            <h3 className="text-lg font-semibold mb-4">
                Análisis de Morosidad por Cliente
            </h3>

            <div className="overflow-x-auto">
                <table
                    {...getTableProps()}
                    className="min-w-full divide-y divide-gray-200"
                >
                    <thead className="bg-gray-50">
                        {headerGroups.map((headerGroup) => (
                            <tr {...headerGroup.getHeaderGroupProps()}>
                                {headerGroup.headers.map((column) => (
                                    <th
                                        {...column.getHeaderProps(
                                            column.getSortByToggleProps()
                                        )}
                                        className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                                    >
                                        {column.render("Header")}
                                        <span>
                                            {column.isSorted
                                                ? column.isSortedDesc
                                                    ? " 🔽"
                                                    : " 🔼"
                                                : ""}
                                        </span>
                                    </th>
                                ))}
                            </tr>
                        ))}
                    </thead>
                    <tbody
                        {...getTableBodyProps()}
                        className="bg-white divide-y divide-gray-200"
                    >
                        {page.map((row) => {
                            prepareRow(row);
                            return (
                                <tr
                                    {...row.getRowProps()}
                                    className="hover:bg-gray-50"
                                >
                                    {row.cells.map((cell) => (
                                        <td
                                            {...cell.getCellProps()}
                                            className="px-6 py-4 whitespace-nowrap"
                                        >
                                            {cell.render("Cell")}
                                        </td>
                                    ))}
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>

            {/* Paginación */}
            <div className="flex items-center justify-between mt-4">
                <div className="flex items-center">
                    <span className="text-sm text-gray-700">
                        Mostrando {pageIndex * pageSize + 1} a{" "}
                        {Math.min(
                            (pageIndex + 1) * pageSize,
                            data?.clientes?.length || 0
                        )}{" "}
                        de {data?.clientes?.length || 0} resultados
                    </span>
                </div>
                <div className="flex items-center space-x-2">
                    <button
                        onClick={() => previousPage()}
                        disabled={!canPreviousPage}
                        className="px-3 py-2 text-sm bg-white border border-gray-300 rounded-md disabled:opacity-50"
                    >
                        Anterior
                    </button>
                    <button
                        onClick={() => nextPage()}
                        disabled={!canNextPage}
                        className="px-3 py-2 text-sm bg-white border border-gray-300 rounded-md disabled:opacity-50"
                    >
                        Siguiente
                    </button>
                </div>
            </div>
        </div>
    );
};

export default ClientesMorososTable;
```

## 🔧 Servicios y Hooks

### API Service

```javascript
// services/apiService.js
import axios from "axios";

const API_BASE_URL =
    process.env.REACT_APP_API_URL || "http://localhost:8000/api";

const apiClient = axios.create({
    baseURL: API_BASE_URL,
    headers: {
        "Content-Type": "application/json",
    },
});

export const tiempoPagoAPI = {
    // Obtener tiempo promedio de pago
    obtenerTiempoPromedio: async (filtros = {}) => {
        const response = await apiClient.post("/tiempo-pago/promedio", filtros);
        return response.data;
    },

    // Obtener distribución de tiempos
    obtenerDistribucion: async (filtros = {}) => {
        const response = await apiClient.post(
            "/tiempo-pago/distribucion",
            filtros
        );
        return response.data;
    },

    // Obtener análisis de morosidad
    obtenerMorosidad: async (filtros = {}) => {
        const response = await apiClient.post(
            "/tiempo-pago/morosidad",
            filtros
        );
        return response.data;
    },

    // Obtener múltiples métricas de una vez
    obtenerDashboardCompleto: async (filtros = {}) => {
        const [promedio, distribucion, morosidad] = await Promise.all([
            tiempoPagoAPI.obtenerTiempoPromedio(filtros),
            tiempoPagoAPI.obtenerDistribucion(filtros),
            tiempoPagoAPI.obtenerMorosidad(filtros),
        ]);

        return {
            promedio: promedio.datos,
            distribucion: distribucion.datos,
            morosidad: morosidad.datos,
        };
    },
};

export default apiClient;
```

### Custom Hook para Datos

```javascript
// hooks/useApiData.js
import { useState, useEffect } from "react";
import { tiempoPagoAPI } from "../services/apiService";

export const useDashboardData = (filtros = {}) => {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    const fetchData = async () => {
        try {
            setLoading(true);
            setError(null);

            const result = await tiempoPagoAPI.obtenerDashboardCompleto(
                filtros
            );
            setData(result);
        } catch (err) {
            setError(err.message);
            console.error("Error fetching dashboard data:", err);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchData();
    }, [JSON.stringify(filtros)]); // Re-fetch when filters change

    return {
        data,
        loading,
        error,
        refetch: fetchData,
    };
};

export const useFilters = () => {
    const [filtros, setFiltros] = useState({
        año: new Date().getFullYear(),
        mes: null,
        tipo_factura: "todas",
    });

    const updateFiltro = (key, value) => {
        setFiltros((prev) => ({ ...prev, [key]: value }));
    };

    const resetFiltros = () => {
        setFiltros({
            año: new Date().getFullYear(),
            mes: null,
            tipo_factura: "todas",
        });
    };

    return {
        filtros,
        updateFiltro,
        resetFiltros,
    };
};
```

## 🎨 Layout Principal del Dashboard

```jsx
// components/Dashboard/DashboardLayout.jsx
import React from "react";
import { useDashboardData, useFilters } from "../../hooks/useApiData";
import MetricasPanel from "../Charts/MetricasCard";
import DistribucionChart from "../Charts/DistribucionChart";
import TendenciasChart from "../Charts/TendenciasChart";
import ClientesMorososTable from "../Tables/ClientesMorososTable";
import FilterPanel from "../Filters/FilterPanel";
import LoadingSpinner from "../UI/LoadingSpinner";

const DashboardLayout = () => {
    const { filtros, updateFiltro } = useFilters();
    const { data, loading, error, refetch } = useDashboardData(filtros);

    if (loading) return <LoadingSpinner />;
    if (error) return <div className="text-red-600">Error: {error}</div>;

    return (
        <div className="min-h-screen bg-gray-50">
            {/* Header */}
            <div className="bg-white shadow-sm border-b">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                    <h1 className="text-2xl font-bold text-gray-900">
                        Dashboard de Análisis de Pagos
                    </h1>
                    <p className="text-gray-600 mt-1">
                        Análisis en tiempo real de tiempos de pago y morosidad
                    </p>
                </div>
            </div>

            {/* Main Content */}
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                {/* Filtros */}
                <FilterPanel
                    filtros={filtros}
                    onFiltroChange={updateFiltro}
                    onRefresh={refetch}
                />

                {/* KPIs */}
                {data?.promedio && <MetricasPanel data={data.promedio} />}

                {/* Gráficos principales */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                    {data?.distribucion && (
                        <DistribucionChart data={data.distribucion} />
                    )}

                    {data?.promedio && (
                        <TendenciasChart
                            data={
                                [
                                    // Aquí irían datos históricos para mostrar tendencias
                                    // Por ahora mostramos datos mock
                                ]
                            }
                        />
                    )}
                </div>

                {/* Tabla de morosidad */}
                {data?.morosidad && (
                    <ClientesMorososTable data={data.morosidad} />
                )}
            </div>
        </div>
    );
};

export default DashboardLayout;
```

## 📱 Diseño Responsive y UX

### Principios de Diseño:

1. **Mobile First**: Diseño que funciona primero en móviles
2. **Color Psychology**:

    - Verde: Pagos rápidos/buenos
    - Amarillo/Naranja: Alertas/atención
    - Rojo: Crítico/moroso
    - Azul: Información/neutral

3. **Jerarquía Visual**:

    - KPIs destacados en la parte superior
    - Gráficos principales en el centro
    - Detalles/tablas en la parte inferior

4. **Interactividad**:
    - Tooltips informativos en gráficos
    - Filtros en tiempo real
    - Drill-down en datos
    - Exportación de reportes

## 🚀 Implementación Paso a Paso

### Fase 1: Setup Básico

1. Instalar dependencias
2. Configurar routing
3. Crear estructura de componentes

### Fase 2: Conectividad API

1. Implementar servicios API
2. Crear hooks personalizados
3. Manejo de estados y errores

### Fase 3: Visualizaciones

1. Implementar gráficos básicos
2. Agregar interactividad
3. Optimizar performance

### Fase 4: UX Avanzada

1. Filtros dinámicos
2. Exportación de datos
3. Notificaciones en tiempo real
4. Modo oscuro (opcional)

## 🔌 Documentación Completa de APIs - TODOS LOS ENDPOINTS

### 📡 Configuración Base

```javascript
const API_BASE_URL = "http://localhost:8000/api";

const apiHeaders = {
    "Content-Type": "application/json",
    Accept: "application/json",
    "X-Requested-With": "XMLHttpRequest",
};

// Cliente HTTP configurado
const apiClient = axios.create({
    baseURL: API_BASE_URL,
    headers: apiHeaders,
    timeout: 30000, // 30 segundos timeout
});
```

---

## 🔐 Endpoints de Autenticación

### 1. Login de Usuario

**POST** `/login`

```javascript
// Parámetros:
{
  "email": "usuario@ejemplo.com",
  "password": "contraseña123"
}

// Respuesta exitosa:
{
  "success": true,
  "message": "Login exitoso",
  "user": {
    "id": 1,
    "name": "Nombre Usuario",
    "email": "usuario@ejemplo.com"
  },
  "token": "bearer_token_aqui"
}
```

### 2. Obtener Usuario Actual

**GET** `/user`

```javascript
// Headers: Authorization: Bearer {token}
// Respuesta:
{
  "id": 1,
  "name": "Nombre Usuario",
  "email": "usuario@ejemplo.com"
}
```

### 3. Logout

**POST** `/logout`

```javascript
// Headers: Authorization: Bearer {token}
// Respuesta:
{
  "success": true,
  "message": "Logout exitoso"
}
```

### 4. Obtener Todos los Usuarios (Admin)

**GET** `/admin/users`

```javascript
// Solo para administradores
// Respuesta: Array de usuarios
```

---

## 📈 Endpoints de Ventas Totales

### 1. Cálculo de Ventas por Mes

**POST** `/ventas/calcular-por-mes`

```javascript
// Parámetros opcionales:
{
  "año": 2024,          // número
  "mes_inicio": 1,      // número (1-12)
  "mes_fin": 12         // número (1-12)
}

// Respuesta:
{
  "success": true,
  "datos": {
    "ventas_por_mes": [
      {
        "año": 2024,
        "mes": 10,
        "nombre_mes": "octubre",
        "total_ventas": 15674,
        "valor_total": 383740356.90,
        "valor_promedio": 24478.23
      }
      // ... más meses
    ],
    "mejor_mes": {
      "año": 2024,
      "mes": 10,
      "nombre_mes": "octubre",
      "total_ventas": 15674
    },
    "resumen_anual": {
      "total_ventas_año": 15674,
      "valor_total_año": 383740356.90,
      "promedio_mensual": 1306.17
    }
  }
}
```

### 2. Resumen Anual de Ventas

**GET** `/ventas/resumen-anual`

```javascript
// Sin parámetros
// Respuesta:
{
  "success": true,
  "datos": {
    "resumen_por_año": [
      {
        "año": 2024,
        "total_ventas": 15674,
        "valor_total": 383740356.90,
        "mejor_mes": "octubre",
        "meses_activos": 1
      }
      // ... más años
    ]
  }
}
```

### 3. Importación de Datos

**POST** `/importarVentasJson`

```javascript
// Para importar datos desde archivo JSON
// Multipart form data con archivo
```

**POST** `/importarUsuariosJson`

```javascript
// Para importar usuarios desde JSON
// Multipart form data con archivo
```

---

## ⏱️ Endpoints de Análisis de Pagos (Tiempo Completo)

### 1. Análisis Tiempo de Pago Completo

**POST** `/pagos/analizar-tiempo-completo`

```javascript
// Parámetros opcionales:
{
  "año": 2024,
  "fecha_inicio": "2024-01-01",  // formato YYYY-MM-DD
  "fecha_fin": "2024-12-31"      // formato YYYY-MM-DD
}

// Respuesta:
{
  "success": true,
  "datos": {
    "tiempo_promedio_dias": 45.23,
    "mediana_dias": 42,
    "minimo_dias": 0,
    "maximo_dias": 365,
    "total_comercializaciones": 15674,
    "comercializaciones_pagadas": 8532,
    "porcentaje_pagadas": 54.42
  }
}
```

### 2. Consultar Fiabilidad de Cliente

**POST** `/clientes/consultar-fiabilidad`

```javascript
// Parámetros requeridos:
{
  "nombre_cliente": "NOMBRE DEL CLIENTE",  // string requerido
  "anio": 2024                             // número requerido
}

// Respuesta:
{
  "success": true,
  "datos": {
    "cliente": "NOMBRE DEL CLIENTE",
    "año_analizado": 2024,
    "total_comercializaciones": 45,
    "comercializaciones_pagadas": 38,
    "porcentaje_pagadas": 84.44,
    "tiempo_promedio_pago": 32.5,
    "mediana_pago": 30,
    "prediccion_pagos_pendientes": [
      {
        "codigo_cotizacion": "ANT123456",
        "fecha_estimada_pago": "2024-08-15",
        "dias_estimados": 35
      }
    ]
  }
}
```

---

## 🔄 Endpoints de Análisis Tiempo entre Etapas

### 1. Tiempo Promedio entre Etapas (0→1)

**POST** `/tiempo-etapas/promedio`

```javascript
// Parámetros opcionales:
{
  "año": 2024,
  "mes_inicio": 1,
  "mes_fin": 12,
  "incluir_detalles": false    // boolean
}

// Respuesta:
{
  "success": true,
  "datos": {
    "tiempo_promedio_desarrollo": 28.45,
    "mediana_desarrollo": 25,
    "estadisticas": {
      "minimo": 1,
      "maximo": 180,
      "desviacion_estandar": 15.2
    },
    "ventas_analizadas": 15674,
    "detalles_por_venta": []  // Si incluir_detalles = true
  }
}
```

### 2. Análisis de Tiempos por Cliente

**POST** `/tiempo-etapas/por-cliente`

```javascript
// Parámetros opcionales:
{
  "año": 2024,
  "mes_inicio": 1,
  "mes_fin": 12
}

// Respuesta:
{
  "success": true,
  "datos": {
    "clientes": [
      {
        "cliente": "NOMBRE CLIENTE",
        "tiempo_promedio_desarrollo": 45.2,
        "tiempo_minimo": 15,
        "tiempo_maximo": 90,
        "total_ventas": 25,
        "ranking_velocidad": "lento"
      }
      // ... más clientes ordenados por tiempo promedio desc
    ]
  }
}
```

### 3. Distribución de Tiempos de Desarrollo

**POST** `/tiempo-etapas/distribucion`

```javascript
// Parámetros opcionales:
{
  "año": 2024,
  "mes_inicio": 1,
  "mes_fin": 12
}

// Respuesta:
{
  "success": true,
  "datos": {
    "distribucion": {
      "muy_rapido": {
        "min": 0,
        "max": 7,
        "count": 1250,
        "porcentaje": 8.0,
        "descripcion": "0-7 días"
      },
      "rapido": {
        "min": 8,
        "max": 15,
        "count": 3400,
        "porcentaje": 21.7,
        "descripcion": "8-15 días"
      }
      // ... más rangos
    }
  }
}
```

### 4. Verificar Base de Datos (Testing)

**GET** `/tiempo-etapas/verificar-bd`

```javascript
// Sin parámetros
// Respuesta: Información de debug sobre estructura de BD
```

---

## 💰 Endpoints de Análisis Tiempo Facturación

### 1. Tiempo Terminación → Facturación

**POST** `/tiempo-facturacion/promedio`

```javascript
// Parámetros opcionales:
{
  "año": 2024,
  "mes": 10,
  "tipo_factura": "todas"    // "todas"|"sence"|"cliente"
}

// Respuesta:
{
  "success": true,
  "datos": {
    "tiempo_promedio_facturacion": 12.3,
    "mediana_facturacion": 10,
    "comercializaciones_analizadas": 15674,
    "facturas_emitidas": 10019,
    "distribucion_por_tipo": {
      "facturas_sence": 245,
      "facturas_cliente": 9774
    }
  }
}
```

### 2. Análisis Facturación por Cliente

**POST** `/tiempo-facturacion/por-cliente`

```javascript
// Parámetros opcionales:
{
  "año": 2024,
  "mes": 10,
  "tipo_factura": "todas"
}

// Respuesta:
{
  "success": true,
  "datos": {
    "clientes": [
      {
        "cliente": "NOMBRE CLIENTE",
        "tiempo_promedio_facturacion": 8.5,
        "facturas_sence": 2,
        "facturas_cliente": 15,
        "valor_total_comercializaciones": 45000000
      }
      // ... más clientes
    ]
  }
}
```

### 3. Distribución Tiempos Facturación

**POST** `/tiempo-facturacion/distribucion`

```javascript
// Parámetros opcionales:
{
  "año": 2024,
  "tipo_factura": "todas"
}

// Respuesta: Similar a distribución de etapas con rangos específicos para facturación
```

---

## � Endpoints de Análisis de Tiempo de Pago

### 1. Tiempo Promedio de Pago

**POST** `/tiempo-pago/promedio`

Calcula el tiempo promedio desde emisión de factura hasta recepción de pago efectivo.

#### Parámetros del Body (todos opcionales):

```json
{
    "año": 2024, // número - Filtrar por año específico
    "mes": 10, // número (1-12) - Filtrar por mes específico
    "tipo_factura": "todas", // string - "todas"|"cliente"|"sence"
    "incluir_pendientes": false // boolean - Incluir facturas sin pago
}
```

#### Respuesta Exitosa (200):

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
            "porcentaje_pagadas": 77.32,
            "filtros_aplicados": {
                "año": 2024,
                "mes": 10,
                "tipo_factura": "todas",
                "incluir_pendientes": false
            }
        },
        "tiempo_promedio_pago": 33.46,
        "estadisticas": {
            "facturas_sence_pagadas": 0,
            "facturas_cliente_pagadas": 7747,
            "facturas_sence_pendientes": 0,
            "facturas_cliente_pendientes": 2272,
            "monto_total_pagado": 7802,
            "monto_total_pendiente": 383740356.9,
            "mediana_dias_pago": 34,
            "minimo_dias_pago": 0,
            "maximo_dias_pago": 351,
            "desviacion_estandar_pago": 23.5
        },
        "distribucion_tiempos": {
            "inmediato": {
                "min": 0,
                "max": 0,
                "count": 125,
                "descripcion": "Mismo día",
                "porcentaje": 1.61
            },
            "muy_rapido": {
                "min": 1,
                "max": 7,
                "count": 1147,
                "descripcion": "1-7 días",
                "porcentaje": 14.81
            }
            // ... más rangos
        },
        "casos_extremos": {
            "pago_mas_rapido": {
                "codigo_cotizacion": "ANT225598-1",
                "cliente": "WORLD CLASS",
                "numero_factura": "30228",
                "fecha_facturacion": "2025-05-28",
                "fecha_pago": "28/05/2025",
                "dias_pago": 0,
                "monto_pagado": 1,
                "tipo_factura": "facturas_cliente",
                "estado": "pagada"
            },
            "pago_mas_lento": {
                "codigo_cotizacion": "CAL194794",
                "cliente": "Metso Industrial Services SpA",
                "numero_factura": "19696",
                "fecha_facturacion": "2022-11-28",
                "fecha_pago": "14/11/2023",
                "dias_pago": 351,
                "monto_pagado": 3,
                "tipo_factura": "facturas_cliente",
                "estado": "pagada"
            }
        },
        "top_clientes_mas_lentos": [
            {
                "codigo_cotizacion": "ANT192182-1",
                "cliente": "CONSTRUCTORA PEHUENCHE LTDA",
                "numero_factura": "397",
                "fecha_facturacion": "2023-02-03",
                "fecha_pago": "03/03/2023",
                "dias_pago": 28,
                "monto_pagado": 1,
                "tipo_factura": "facturas_cliente",
                "estado": "pagada"
            }
            // ... más clientes
        ],
        "top_clientes_mas_rapidos": [
            {
                "codigo_cotizacion": "ANT226315-1",
                "cliente": "GREENWORK TECHNOLOGY SPA",
                "numero_factura": "30600",
                "fecha_facturacion": "2025-06-22",
                "fecha_pago": "23/06/2025",
                "dias_pago": 1,
                "monto_pagado": 1,
                "tipo_factura": "facturas_cliente",
                "estado": "pagada"
            }
            // ... más clientes
        ],
        "facturas_pendientes_criticas": []
    },
    "metadata": {
        "tiempo_ejecucion_ms": 996.95,
        "timestamp": "2025-07-12 08:35:50",
        "total_registros_json": 15674
    }
}
```

#### Ejemplo de Uso en JavaScript:

```javascript
const obtenerTiempoPromedio = async (filtros = {}) => {
    try {
        const response = await fetch(
            "http://localhost:8000/api/tiempo-pago/promedio",
            {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                },
                body: JSON.stringify(filtros),
            }
        );

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();
        return data;
    } catch (error) {
        console.error("Error:", error);
        throw error;
    }
};

// Uso:
const datos = await obtenerTiempoPromedio({
    año: 2024,
    mes: 10,
    tipo_factura: "cliente",
});
```

---

### 2. Distribución de Tiempos de Pago

**POST** `/tiempo-pago/distribucion`

Analiza la distribución de tiempos de pago en rangos predefinidos.

#### Parámetros del Body (todos opcionales):

```json
{
    "año": 2024, // número - Filtrar por año específico
    "mes": 10, // número (1-12) - Filtrar por mes específico
    "tipo_factura": "todas" // string - "todas"|"cliente"|"sence"
}
```

#### Respuesta Exitosa (200):

```json
{
    "success": true,
    "message": "Distribución de tiempos de pago generada exitosamente",
    "datos": {
        "total_facturas_pagadas": 7747,
        "filtros_aplicados": {
            "año": 2024,
            "mes": 10,
            "tipo_factura": "todas"
        },
        "distribucion": {
            "inmediato": {
                "min": 0,
                "max": 0,
                "count": 125,
                "descripcion": "Mismo día",
                "porcentaje": 1.61,
                "ejemplos": [
                    {
                        "codigo_cotizacion": "ANT225598-1",
                        "cliente": "WORLD CLASS",
                        "numero_factura": "30228",
                        "dias_pago": 0,
                        "monto_pagado": 1,
                        "tipo_factura": "facturas_cliente"
                    }
                ]
            },
            "muy_rapido": {
                "min": 1,
                "max": 7,
                "count": 1147,
                "descripcion": "1-7 días",
                "porcentaje": 14.81,
                "ejemplos": [
                    {
                        "codigo_cotizacion": "ANT226315-1",
                        "cliente": "GREENWORK TECHNOLOGY SPA",
                        "numero_factura": "30600",
                        "dias_pago": 1,
                        "monto_pagado": 1,
                        "tipo_factura": "facturas_cliente"
                    }
                ]
            },
            "rapido": {
                "min": 8,
                "max": 15,
                "count": 569,
                "descripcion": "8-15 días",
                "porcentaje": 7.34,
                "ejemplos": []
            },
            "normal": {
                "min": 16,
                "max": 30,
                "count": 1387,
                "descripcion": "16-30 días",
                "porcentaje": 17.9,
                "ejemplos": []
            },
            "lento": {
                "min": 31,
                "max": 60,
                "count": 3766,
                "descripcion": "31-60 días",
                "porcentaje": 48.61,
                "ejemplos": []
            },
            "muy_lento": {
                "min": 61,
                "max": 90,
                "count": 623,
                "descripcion": "61-90 días",
                "porcentaje": 8.04,
                "ejemplos": []
            },
            "critico": {
                "min": 91,
                "max": 999,
                "count": 130,
                "descripcion": "91+ días",
                "porcentaje": 1.68,
                "ejemplos": []
            }
        },
        "estadisticas_generales": {
            "promedio_dias_pago": 33.46,
            "mediana_dias_pago": 34,
            "minimo_dias_pago": 0,
            "maximo_dias_pago": 351
        }
    }
}
```

#### Ejemplo de Uso:

```javascript
const obtenerDistribucion = async (filtros = {}) => {
    const response = await fetch(
        "http://localhost:8000/api/tiempo-pago/distribucion",
        {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(filtros),
        }
    );
    return response.json();
};

// Para React Component:
const [distribucionData, setDistribucionData] = useState(null);

useEffect(() => {
    obtenerDistribucion({ año: 2024, tipo_factura: "cliente" })
        .then((data) => {
            if (data.success) {
                setDistribucionData(data.datos);
            }
        })
        .catch(console.error);
}, []);
```

---

### 3. Análisis de Morosidad por Cliente

**POST** `/tiempo-pago/morosidad`

Analiza el comportamiento de pago por cliente individual.

#### Parámetros del Body (todos opcionales):

```json
{
    "año": 2024, // número - Filtrar por año específico
    "mes": 10, // número (1-12) - Filtrar por mes específico
    "tipo_factura": "todas" // string - "todas"|"cliente"|"sence"
}
```

#### Respuesta Exitosa (200):

```json
{
    "success": true,
    "message": "Análisis de morosidad por cliente completado exitosamente",
    "datos": {
        "total_clientes_analizados": 142,
        "filtros_aplicados": {
            "año": 2024,
            "mes": 10,
            "tipo_factura": "todas"
        },
        "clientes": [
            {
                "cliente": "SIEMENS",
                "facturas_totales": 25,
                "facturas_pagadas": 20,
                "facturas_pendientes": 5,
                "porcentaje_pagadas": 80.0,
                "tiempo_promedio_pago_dias": 45.5,
                "dias_promedio_pendientes": 67.2,
                "monto_total_pagado": 2500000,
                "monto_total_pendiente": 850000,
                "clasificacion_morosidad": "regular"
            },
            {
                "cliente": "FCAB",
                "facturas_totales": 18,
                "facturas_pagadas": 17,
                "facturas_pendientes": 1,
                "porcentaje_pagadas": 94.4,
                "tiempo_promedio_pago_dias": 28.3,
                "dias_promedio_pendientes": 15.0,
                "monto_total_pagado": 1800000,
                "monto_total_pendiente": 120000,
                "clasificacion_morosidad": "excelente"
            }
            // ... más clientes ordenados por morosidad
        ]
    }
}
```

#### Clasificaciones de Morosidad:

-   **"excelente"**: ≥90% pagadas y ≤30 días pendientes
-   **"bueno"**: ≥80% pagadas y ≤45 días pendientes
-   **"regular"**: ≥70% pagadas y ≤60 días pendientes
-   **"malo"**: ≥50% pagadas y ≤90 días pendientes
-   **"critico"**: <50% pagadas o >90 días pendientes

---

## 🔄 Endpoints de Análisis Tipos de Flujo

### 1. Análisis Comparativo de Tipos de Flujo

**POST** `/tipo-flujo/analizar`

Detecta automáticamente tipos de flujo y los compara.

#### Parámetros del Body (todos opcionales):

```json
{
    "año": 2024,
    "mes": 10
}
```

#### Respuesta Exitosa (200):

```json
{
    "success": true,
    "datos": {
        "tipos_flujo_detectados": {
            "flujo_completo": {
                "descripcion": "0→3→1 (Con financiamiento SENCE)",
                "cantidad": 245,
                "porcentaje": 1.56,
                "tiempo_promedio_total": 65.2,
                "valor_promedio": 850000
            },
            "flujo_simple": {
                "descripcion": "0→1 (Sin financiamiento SENCE)",
                "cantidad": 15429,
                "porcentaje": 98.44,
                "tiempo_promedio_total": 28.3,
                "valor_promedio": 24000
            }
        },
        "comparativa": {
            "diferencia_tiempo_promedio": 36.9,
            "diferencia_valor_promedio": 826000,
            "adopcion_sence": 1.56
        }
    }
}
```

### 2. Análisis de Preferencias de Clientes por Flujo

**POST** `/tipo-flujo/preferencias`

Analiza comportamiento individual de cada cliente por tipo de flujo.

### 3. Análisis de Eficiencia por Tipo de Flujo

**POST** `/tipo-flujo/eficiencia`

Compara eficiencia operacional entre tipos de flujo.

---

## 🔧 Cliente API Universal para React

### Servicio API Completo

```javascript
// services/apiService.js
import axios from "axios";

const API_BASE_URL =
    process.env.REACT_APP_API_URL || "http://localhost:8000/api";

const apiClient = axios.create({
    baseURL: API_BASE_URL,
    headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
    },
    timeout: 30000,
});

// Interceptor para manejo de errores
apiClient.interceptors.response.use(
    (response) => response,
    (error) => {
        console.error("API Error:", error);

        if (error.response?.status === 401) {
            localStorage.removeItem("auth_token");
            window.location.href = "/login";
        }

        return Promise.reject(error);
    }
);

export const apiService = {
    // ============ AUTENTICACIÓN ============
    auth: {
        login: (credentials) => apiClient.post("/login", credentials),
        logout: () => apiClient.post("/logout"),
        getUser: () => apiClient.get("/user"),
        getAllUsers: () => apiClient.get("/admin/users"),
    },

    // ============ VENTAS TOTALES ============
    ventas: {
        calcularPorMes: (filtros) =>
            apiClient.post("/ventas/calcular-por-mes", filtros),
        resumenAnual: () => apiClient.get("/ventas/resumen-anual"),
        importarVentas: (formData) =>
            apiClient.post("/importarVentasJson", formData, {
                headers: { "Content-Type": "multipart/form-data" },
            }),
    },

    // ============ ANÁLISIS DE PAGOS ============
    pagos: {
        analizarTiempoCompleto: (filtros) =>
            apiClient.post("/pagos/analizar-tiempo-completo", filtros),
        consultarFiabilidadCliente: (datos) =>
            apiClient.post("/clientes/consultar-fiabilidad", datos),
    },

    // ============ TIEMPO ENTRE ETAPAS ============
    etapas: {
        promedioEtapas: (filtros) =>
            apiClient.post("/tiempo-etapas/promedio", filtros),
        porCliente: (filtros) =>
            apiClient.post("/tiempo-etapas/por-cliente", filtros),
        distribucion: (filtros) =>
            apiClient.post("/tiempo-etapas/distribucion", filtros),
        verificarBD: () => apiClient.get("/tiempo-etapas/verificar-bd"),
    },

    // ============ TIEMPO FACTURACIÓN ============
    facturacion: {
        promedio: (filtros) =>
            apiClient.post("/tiempo-facturacion/promedio", filtros),
        porCliente: (filtros) =>
            apiClient.post("/tiempo-facturacion/por-cliente", filtros),
        distribucion: (filtros) =>
            apiClient.post("/tiempo-facturacion/distribucion", filtros),
    },

    // ============ TIEMPO DE PAGO ============
    tiempoPago: {
        promedio: (filtros) => apiClient.post("/tiempo-pago/promedio", filtros),
        distribucion: (filtros) =>
            apiClient.post("/tiempo-pago/distribucion", filtros),
        morosidad: (filtros) =>
            apiClient.post("/tiempo-pago/morosidad", filtros),
    },

    // ============ TIPOS DE FLUJO ============
    tipoFlujo: {
        analizar: (filtros) => apiClient.post("/tipo-flujo/analizar", filtros),
        preferencias: (filtros) =>
            apiClient.post("/tipo-flujo/preferencias", filtros),
        eficiencia: (filtros) =>
            apiClient.post("/tipo-flujo/eficiencia", filtros),
    },

    // ============ DEBUG ============
    debug: {
        testBasico: () => apiClient.get("/debug/test-basico"),
        testTablas: () => apiClient.get("/debug/test-tablas"),
        testJoin: () => apiClient.get("/debug/test-join"),
        analizarEstructura: () => apiClient.get("/debug/analizar-estructura"),
    },
};

export default apiService;
```

### Hook Universal para APIs

```javascript
// hooks/useApiData.js
import { useState, useEffect } from "react";
import { apiService } from "../services/apiService";

export const useApiData = (endpoint, params = {}, dependencies = []) => {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    const fetchData = async () => {
        try {
            setLoading(true);
            setError(null);

            // Resolver endpoint dinámicamente
            const endpointParts = endpoint.split(".");
            let apiMethod = apiService;

            for (const part of endpointParts) {
                apiMethod = apiMethod[part];
            }

            const response = await apiMethod(params);
            setData(response.data);
        } catch (err) {
            setError(err.response?.data?.message || err.message);
            console.error(`Error en ${endpoint}:`, err);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchData();
    }, dependencies);

    return {
        data,
        loading,
        error,
        refetch: fetchData,
    };
};

// Hooks específicos para cada módulo
export const useTiempoPago = (filtros = {}) => {
    return useApiData("tiempoPago.promedio", filtros, [
        JSON.stringify(filtros),
    ]);
};

export const useDistribucionPago = (filtros = {}) => {
    return useApiData("tiempoPago.distribucion", filtros, [
        JSON.stringify(filtros),
    ]);
};

export const useMorosidad = (filtros = {}) => {
    return useApiData("tiempoPago.morosidad", filtros, [
        JSON.stringify(filtros),
    ]);
};

export const useVentasPorMes = (filtros = {}) => {
    return useApiData("ventas.calcularPorMes", filtros, [
        JSON.stringify(filtros),
    ]);
};

export const useTiempoEtapas = (filtros = {}) => {
    return useApiData("etapas.promedioEtapas", filtros, [
        JSON.stringify(filtros),
    ]);
};

export const useTipoFlujo = (filtros = {}) => {
    return useApiData("tipoFlujo.analizar", filtros, [JSON.stringify(filtros)]);
};
```

### Script de Testing Completo

```javascript
// scripts/testAllApis.js
import { apiService } from "../services/apiService";

const testEndpoint = async (name, apiCall) => {
    try {
        console.log(`🧪 Probando ${name}...`);
        const response = await apiCall();
        console.log(`✅ ${name}: OK`, response.data?.success ? "✓" : "⚠️");
        return { name, status: "success", data: response.data };
    } catch (error) {
        console.error(`❌ ${name}: Error`, error.message);
        return { name, status: "error", error: error.message };
    }
};

export const testAllEndpoints = async () => {
    console.log("🚀 Iniciando pruebas de todos los endpoints...\n");

    const tests = [
        // Ventas
        [
            "Ventas por Mes",
            () => apiService.ventas.calcularPorMes({ año: 2024 }),
        ],
        ["Resumen Anual", () => apiService.ventas.resumenAnual()],

        // Tiempo de Pago
        [
            "Tiempo Pago Promedio",
            () => apiService.tiempoPago.promedio({ año: 2024 }),
        ],
        [
            "Distribución Pago",
            () => apiService.tiempoPago.distribucion({ año: 2024 }),
        ],
        ["Morosidad", () => apiService.tiempoPago.morosidad({ año: 2024 })],

        // Etapas
        [
            "Tiempo Etapas",
            () => apiService.etapas.promedioEtapas({ año: 2024 }),
        ],
        [
            "Etapas por Cliente",
            () => apiService.etapas.porCliente({ año: 2024 }),
        ],
        [
            "Distribución Etapas",
            () => apiService.etapas.distribucion({ año: 2024 }),
        ],

        // Facturación
        [
            "Tiempo Facturación",
            () => apiService.facturacion.promedio({ año: 2024 }),
        ],
        [
            "Facturación por Cliente",
            () => apiService.facturacion.porCliente({ año: 2024 }),
        ],

        // Tipos de Flujo
        ["Tipos de Flujo", () => apiService.tipoFlujo.analizar({ año: 2024 })],
        [
            "Preferencias Flujo",
            () => apiService.tipoFlujo.preferencias({ año: 2024 }),
        ],
        [
            "Eficiencia Flujo",
            () => apiService.tipoFlujo.eficiencia({ año: 2024 }),
        ],

        // Debug
        ["Debug Básico", () => apiService.debug.testBasico()],
        ["Debug Tablas", () => apiService.debug.testTablas()],
    ];

    const results = [];

    for (const [name, apiCall] of tests) {
        const result = await testEndpoint(name, apiCall);
        results.push(result);
        await new Promise((resolve) => setTimeout(resolve, 500)); // Esperar entre requests
    }

    console.log("\n📊 Resumen de pruebas:");
    const successful = results.filter((r) => r.status === "success").length;
    const failed = results.filter((r) => r.status === "error").length;

    console.log(`✅ Exitosas: ${successful}`);
    console.log(`❌ Fallidas: ${failed}`);
    console.log(
        `📈 Tasa de éxito: ${((successful / results.length) * 100).toFixed(1)}%`
    );

    return results;
};
```

---

## 📋 Lista Completa de Endpoints Disponibles

### ✅ **AUTENTICACIÓN** (4 endpoints)

-   `POST /login` - Iniciar sesión
-   `GET /user` - Obtener usuario actual
-   `POST /logout` - Cerrar sesión
-   `GET /admin/users` - Listar usuarios (admin)

### ✅ **VENTAS TOTALES** (3 endpoints)

-   `POST /ventas/calcular-por-mes` - Cálculo de ventas por mes
-   `GET /ventas/resumen-anual` - Resumen ejecutivo anual
-   `POST /importarVentasJson` - Importar datos JSON

### ✅ **ANÁLISIS DE PAGOS** (2 endpoints)

-   `POST /pagos/analizar-tiempo-completo` - Tiempo de pago completo
-   `POST /clientes/consultar-fiabilidad` - Fiabilidad por cliente

### ✅ **TIEMPO ENTRE ETAPAS** (4 endpoints)

-   `POST /tiempo-etapas/promedio` - Tiempo promedio 0→1
-   `POST /tiempo-etapas/por-cliente` - Análisis por cliente
-   `POST /tiempo-etapas/distribucion` - Distribución de tiempos
-   `GET /tiempo-etapas/verificar-bd` - Verificación BD

### ✅ **TIEMPO FACTURACIÓN** (3 endpoints)

-   `POST /tiempo-facturacion/promedio` - Tiempo 1→facturación
-   `POST /tiempo-facturacion/por-cliente` - Facturación por cliente
-   `POST /tiempo-facturacion/distribucion` - Distribución facturación

### ✅ **TIEMPO DE PAGO** (3 endpoints)

-   `POST /tiempo-pago/promedio` - Tiempo factura→pago
-   `POST /tiempo-pago/distribucion` - Distribución de pagos
-   `POST /tiempo-pago/morosidad` - Morosidad por cliente

### ✅ **TIPOS DE FLUJO** (3 endpoints)

-   `POST /tipo-flujo/analizar` - Comparación flujos
-   `POST /tipo-flujo/preferencias` - Preferencias por cliente
-   `POST /tipo-flujo/eficiencia` - Eficiencia por flujo

### ✅ **DEBUG** (4 endpoints)

-   `GET /debug/test-basico` - Test básico
-   `GET /debug/test-tablas` - Test tablas BD
-   `GET /debug/test-join` - Test joins
-   `GET /debug/analizar-estructura` - Análisis completo

---

## 🎯 **Total: 26 Endpoints Documentados**

Ahora tienes documentación completa de **TODOS los endpoints** con:

-   ✅ Parámetros de entrada
-   ✅ Estructuras de respuesta
-   ✅ Ejemplos de uso en JavaScript
-   ✅ Servicios API configurados
-   ✅ Hooks React personalizados
-   ✅ Scripts de testing
-   ✅ Manejo de errores centralizado

¿Te gustaría que profundice en algún endpoint específico o necesitas ayuda con la implementación de alguna funcionalidad particular?

-   **"bueno"**: ≥80% pagadas y ≤45 días pendientes
-   **"regular"**: ≥70% pagadas y ≤60 días pendientes
-   **"malo"**: ≥50% pagadas y ≤90 días pendientes
-   **"critico"**: <50% pagadas o >90 días pendientes

---

## 📈 Endpoints Adicionales Disponibles

### 4. Análisis de Ventas Totales

**POST** `/ventas/calcular-por-mes`

```json
// Parámetros:
{
  "año": 2024,
  "mes_inicio": 1,
  "mes_fin": 12
}

// Respuesta incluye:
{
  "datos": {
    "ventas_por_mes": [...],
    "total_anual": 45000000,
    "promedio_mensual": 3750000,
    "mejor_mes": "octubre"
  }
}
```

### 5. Análisis de Tiempo entre Etapas

**POST** `/tiempo-etapas/promedio`

```json
// Parámetros:
{
    "año": 2024,
    "mes_inicio": 1,
    "mes_fin": 12,
    "incluir_detalles": false
}

// Respuesta incluye tiempo de estado 0 → estado 1
```

### 6. Análisis de Tipos de Flujo

**POST** `/tipo-flujo/analizar`

```json
// Parámetros:
{
    "año": 2024,
    "mes": 10
}

// Respuesta compara flujos Completo vs Simple
```

---

## 🛠️ Helper Functions para el Frontend

### Manejo de Errores Centralizado

```javascript
// utils/apiErrorHandler.js
export const handleApiError = (error) => {
    if (error.response) {
        // Error del servidor (4xx, 5xx)
        const status = error.response.status;
        const message = error.response.data?.message || "Error del servidor";

        switch (status) {
            case 400:
                return {
                    type: "warning",
                    message: "Parámetros inválidos: " + message,
                };
            case 404:
                return { type: "error", message: "Endpoint no encontrado" };
            case 500:
                return { type: "error", message: "Error interno del servidor" };
            default:
                return {
                    type: "error",
                    message: `Error ${status}: ${message}`,
                };
        }
    } else if (error.request) {
        // Error de red
        return {
            type: "error",
            message:
                "Error de conexión. Verifique que el servidor esté ejecutándose en http://localhost:8000",
        };
    } else {
        // Error de configuración
        return { type: "error", message: "Error interno: " + error.message };
    }
};
```

### Formateo de Datos

```javascript
// utils/dataFormatters.js
export const formatMoney = (amount) => {
    return new Intl.NumberFormat("es-CL", {
        style: "currency",
        currency: "CLP",
        minimumFractionDigits: 0,
    }).format(amount);
};

export const formatPercentage = (value) => {
    return `${value.toFixed(1)}%`;
};

export const formatDays = (days) => {
    if (days === 0) return "Mismo día";
    if (days === 1) return "1 día";
    return `${days} días`;
};

export const getStatusColor = (clasificacion) => {
    const colors = {
        excelente: "green",
        bueno: "blue",
        regular: "yellow",
        malo: "orange",
        critico: "red",
    };
    return colors[clasificacion] || "gray";
};
```

### Hook Personalizado para APIs

```javascript
// hooks/useTiempoPago.js
import { useState, useEffect } from "react";
import { tiempoPagoAPI } from "../services/apiService";
import { handleApiError } from "../utils/apiErrorHandler";

export const useTiempoPago = (filtros = {}) => {
    const [data, setData] = useState({
        promedio: null,
        distribucion: null,
        morosidad: null,
    });
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);

    const fetchAllData = async () => {
        setLoading(true);
        setError(null);

        try {
            const [promedioRes, distribucionRes, morosidadRes] =
                await Promise.allSettled([
                    tiempoPagoAPI.obtenerTiempoPromedio(filtros),
                    tiempoPagoAPI.obtenerDistribucion(filtros),
                    tiempoPagoAPI.obtenerMorosidad(filtros),
                ]);

            setData({
                promedio:
                    promedioRes.status === "fulfilled"
                        ? promedioRes.value.datos
                        : null,
                distribucion:
                    distribucionRes.status === "fulfilled"
                        ? distribucionRes.value.datos
                        : null,
                morosidad:
                    morosidadRes.status === "fulfilled"
                        ? morosidadRes.value.datos
                        : null,
            });

            // Manejar errores parciales
            const errors = [promedioRes, distribucionRes, morosidadRes]
                .filter((res) => res.status === "rejected")
                .map((res) => res.reason);

            if (errors.length > 0) {
                console.warn("Algunos endpoints fallaron:", errors);
            }
        } catch (err) {
            const errorInfo = handleApiError(err);
            setError(errorInfo.message);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchAllData();
    }, [JSON.stringify(filtros)]);

    return {
        data,
        loading,
        error,
        refetch: fetchAllData,
    };
};
```

---

## 🚀 Ejemplos de Implementación Completa

### Componente Dashboard con Manejo de Estados

```jsx
// components/Dashboard/MainDashboard.jsx
import React, { useState } from "react";
import { useTiempoPago } from "../../hooks/useTiempoPago";
import {
    formatMoney,
    formatPercentage,
    formatDays,
} from "../../utils/dataFormatters";

const MainDashboard = () => {
    const [filtros, setFiltros] = useState({
        año: new Date().getFullYear(),
        tipo_factura: "todas",
    });

    const { data, loading, error, refetch } = useTiempoPago(filtros);

    const handleFiltroChange = (key, value) => {
        setFiltros((prev) => ({ ...prev, [key]: value }));
    };

    if (loading) {
        return (
            <div className="flex justify-center items-center h-64">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                <span className="ml-2">Cargando datos...</span>
            </div>
        );
    }

    if (error) {
        return (
            <div className="bg-red-50 border border-red-200 rounded-md p-4">
                <div className="flex">
                    <div className="ml-3">
                        <h3 className="text-sm font-medium text-red-800">
                            Error al cargar datos
                        </h3>
                        <p className="mt-1 text-sm text-red-700">{error}</p>
                        <button
                            onClick={refetch}
                            className="mt-2 bg-red-600 text-white px-3 py-1 text-sm rounded hover:bg-red-700"
                        >
                            Reintentar
                        </button>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div className="space-y-6">
            {/* KPIs principales */}
            {data.promedio && (
                <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div className="bg-white p-6 rounded-lg shadow">
                        <h3 className="text-sm font-medium text-gray-500">
                            Tiempo Promedio
                        </h3>
                        <p className="text-2xl font-bold text-blue-600">
                            {formatDays(data.promedio.tiempo_promedio_pago)}
                        </p>
                    </div>

                    <div className="bg-white p-6 rounded-lg shadow">
                        <h3 className="text-sm font-medium text-gray-500">
                            Tasa de Pago
                        </h3>
                        <p className="text-2xl font-bold text-green-600">
                            {formatPercentage(
                                data.promedio.resumen.porcentaje_pagadas
                            )}
                        </p>
                    </div>

                    <div className="bg-white p-6 rounded-lg shadow">
                        <h3 className="text-sm font-medium text-gray-500">
                            Monto Pagado
                        </h3>
                        <p className="text-2xl font-bold text-green-600">
                            {formatMoney(
                                data.promedio.estadisticas.monto_total_pagado
                            )}
                        </p>
                    </div>

                    <div className="bg-white p-6 rounded-lg shadow">
                        <h3 className="text-sm font-medium text-gray-500">
                            Pendiente
                        </h3>
                        <p className="text-2xl font-bold text-red-600">
                            {formatMoney(
                                data.promedio.estadisticas.monto_total_pendiente
                            )}
                        </p>
                    </div>
                </div>
            )}

            {/* Gráficos */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {data.distribucion && (
                    <DistribucionChart data={data.distribucion} />
                )}

                {data.promedio && <TendenciasChart data={data.promedio} />}
            </div>

            {/* Tabla de morosidad */}
            {data.morosidad && <ClientesMorososTable data={data.morosidad} />}
        </div>
    );
};

export default MainDashboard;
```

---

## 🔍 Testing de APIs

### Script de Prueba Rápida

```javascript
// scripts/testApis.js
const BASE_URL = "http://localhost:8000/api";

const testEndpoint = async (endpoint, data = {}) => {
    try {
        console.log(`🧪 Probando ${endpoint}...`);

        const response = await fetch(`${BASE_URL}${endpoint}`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(data),
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const result = await response.json();
        console.log(`✅ ${endpoint} - Success:`, result.success);
        console.log(`📊 Datos disponibles:`, Object.keys(result.datos || {}));

        return result;
    } catch (error) {
        console.error(`❌ ${endpoint} - Error:`, error.message);
        return null;
    }
};

// Ejecutar pruebas
const runTests = async () => {
    console.log("🚀 Iniciando pruebas de APIs...\n");

    await testEndpoint("/tiempo-pago/promedio", { año: 2024 });
    await testEndpoint("/tiempo-pago/distribucion", { año: 2024 });
    await testEndpoint("/tiempo-pago/morosidad", { año: 2024 });

    console.log("\n✨ Pruebas completadas");
};

runTests();
```

¿Te gustaría que profundice en alguna sección específica o que te ayude con la implementación de algún componente en particular?
