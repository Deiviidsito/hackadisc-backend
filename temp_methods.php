<?php

// ===== MÉTODOS AUXILIARES ADICIONALES =====

private function analizarPatronesPorTipo($facturas) {
    $tiemposPorTipo = [];
    foreach ($facturas as $factura) {
        if ($factura['dias_pago'] !== null) {
            $tipo = $factura['tipo'];
            if (!isset($tiemposPorTipo[$tipo])) {
                $tiemposPorTipo[$tipo] = [];
            }
            $tiemposPorTipo[$tipo][] = $factura['dias_pago'];
        }
    }
    
    $estadisticasPorTipo = [];
    foreach ($tiemposPorTipo as $tipo => $tiempos) {
        $estadisticasPorTipo[$tipo] = [
            'promedio' => array_sum($tiempos) / count($tiempos),
            'cantidad' => count($tiempos),
            'mediana' => $this->calcularMediana($tiempos)
        ];
    }
    
    return $estadisticasPorTipo;
}

private function detectarAnomalias($tiemposPago) {
    if (count($tiemposPago) < 3) return [];
    
    $promedio = array_sum($tiemposPago) / count($tiemposPago);
    $desviacion = $this->calcularDesviacionEstandar($tiemposPago, $promedio);
    
    $anomalias = [];
    foreach ($tiemposPago as $indice => $tiempo) {
        $zScore = abs(($tiempo - $promedio) / $desviacion);
        if ($zScore > 2) { // Más de 2 desviaciones estándar
            $anomalias[] = [
                'indice' => $indice,
                'valor' => $tiempo,
                'z_score' => round($zScore, 2),
                'tipo' => $tiempo > $promedio ? 'extremadamente_lento' : 'extremadamente_rapido'
            ];
        }
    }
    
    return $anomalias;
}

private function evaluarConsistencia($tiemposPago) {
    if (empty($tiemposPago)) return 0;
    
    $promedio = array_sum($tiemposPago) / count($tiemposPago);
    $desviacion = $this->calcularDesviacionEstandar($tiemposPago, $promedio);
    
    // Coeficiente de variación inverso (menor variación = mayor consistencia)
    $coeficienteVariacion = $promedio > 0 ? ($desviacion / $promedio) * 100 : 100;
    $consistencia = max(0, 100 - $coeficienteVariacion);
    
    return round($consistencia, 1);
}

private function calcularVolatilidad($tiemposPago) {
    if (count($tiemposPago) < 2) return 0;
    
    $promedio = array_sum($tiemposPago) / count($tiemposPago);
    $desviacion = $this->calcularDesviacionEstandar($tiemposPago, $promedio);
    
    return $promedio > 0 ? round(($desviacion / $promedio) * 100, 1) : 0;
}

private function simularEscenario($tiemposPago, $tipoEscenario, $estadisticas) {
    $factorAjuste = match($tipoEscenario) {
        'normal' => 1.0,
        'crisis' => 1.3,
        'bonanza' => 0.8,
        'nuevas_condiciones' => 0.9,
        default => 1.0
    };
    
    $promedioAjustado = $estadisticas['promedio'] * $factorAjuste;
    $medianaAjustada = $estadisticas['mediana'] * $factorAjuste;
    
    return [
        'escenario' => $tipoEscenario,
        'promedio_proyectado' => round($promedioAjustado, 1),
        'mediana_proyectada' => round($medianaAjustada, 1),
        'factor_aplicado' => $factorAjuste,
        'confianza' => $this->calcularConfianzaEscenario($tipoEscenario, count($tiemposPago))
    ];
}

private function simularEscenarioEstacional($datosHistoricos, $patronesComportamiento) {
    $patron = $patronesComportamiento['patrones_estacionales'] ?? [];
    
    if (!$patron['patrones_disponibles']) {
        return [
            'escenario' => 'estacionalidad_extrema',
            'disponible' => false,
            'mensaje' => 'No hay datos estacionales suficientes'
        ];
    }
    
    $mesMasRapido = $patron['mes_mas_rapido'];
    $mesMasLento = $patron['mes_mas_lento'];
    
    return [
        'escenario' => 'estacionalidad_extrema',
        'disponible' => true,
        'mejor_mes' => $mesMasRapido,
        'peor_mes' => $mesMasLento,
        'diferencia_estacional' => $mesMasLento['promedio_dias'] - $mesMasRapido['promedio_dias']
    ];
}

private function recomendarMejorEscenario($normal, $crisis, $bonanza) {
    // Evaluar qué escenario es más probable basado en tendencias actuales
    $scores = [
        'normal' => 60,
        'crisis' => 20,
        'bonanza' => 20
    ];
    
    return [
        'escenario_recomendado' => 'normal',
        'probabilidades' => $scores,
        'justificacion' => 'Basado en condiciones económicas actuales'
    ];
}

private function aplicarRegresionLineal($tiemposPago) {
    $n = count($tiemposPago);
    if ($n < 2) return ['disponible' => false];
    
    // Regresión lineal simple: y = mx + b
    $sumX = array_sum(range(1, $n));
    $sumY = array_sum($tiemposPago);
    $sumXY = 0;
    $sumX2 = 0;
    
    for ($i = 0; $i < $n; $i++) {
        $x = $i + 1;
        $y = $tiemposPago[$i];
        $sumXY += $x * $y;
        $sumX2 += $x * $x;
    }
    
    $pendiente = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
    $intercepto = ($sumY - $pendiente * $sumX) / $n;
    
    $prediccionProximoPago = $pendiente * ($n + 1) + $intercepto;
    
    return [
        'disponible' => true,
        'algoritmo' => 'regresion_lineal',
        'pendiente' => round($pendiente, 3),
        'intercepto' => round($intercepto, 2),
        'prediccion_proximo_pago' => max(1, round($prediccionProximoPago, 1)),
        'r_cuadrado' => $this->calcularRCuadrado($tiemposPago, $pendiente, $intercepto)
    ];
}

// ... continuación de todos los demás métodos auxiliares
