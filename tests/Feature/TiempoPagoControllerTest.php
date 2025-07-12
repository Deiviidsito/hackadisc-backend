<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TiempoPagoControllerTest extends TestCase
{
    /**
     * Test endpoint de cálculo de tiempo promedio
     */
    public function test_calculo_tiempo_promedio_pago()
    {
        $response = $this->postJson('/api/tiempo-pago/promedio');
        
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'datos' => [
                         'resumen',
                         'tiempo_promedio_pago',
                         'estadisticas',
                         'distribucion_tiempos'
                     ]
                 ]);
        
        $this->assertTrue($response->json('success'));
        $this->assertIsNumeric($response->json('datos.tiempo_promedio_pago'));
    }
    
    /**
     * Test endpoint de distribución de tiempos
     */
    public function test_distribucion_tiempos_pago()
    {
        $response = $this->postJson('/api/tiempo-pago/distribucion');
        
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'datos' => [
                         'total_facturas_pagadas',
                         'distribucion' => [
                             'inmediato',
                             'muy_rapido',
                             'rapido',
                             'normal',
                             'lento',
                             'muy_lento',
                             'critico'
                         ]
                     ]
                 ]);
        
        $this->assertGreaterThan(0, $response->json('datos.total_facturas_pagadas'));
    }
    
    /**
     * Test filtros por fecha
     */
    public function test_filtros_fecha()
    {
        $response = $this->postJson('/api/tiempo-pago/promedio', [
            'año' => 2024,
            'mes' => 10
        ]);
        
        $response->assertStatus(200);
        $filtros = $response->json('datos.resumen.filtros_aplicados');
        $this->assertEquals(2024, $filtros['año']);
        $this->assertEquals(10, $filtros['mes']);
    }
}
