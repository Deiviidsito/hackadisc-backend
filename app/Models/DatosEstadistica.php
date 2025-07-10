<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DatosEstadistica extends Model
{
    protected $table = 'datos_estadisticas';
    
    protected $fillable = [
        'idComercializacion',
        'factura_numero',
        'fecha_emision_factura',
        'fecha_pago_final',
        'dias_para_pago',
        'meses_para_pago',
        'factura_pagada',
        'monto_factura',
        'cliente_nombre',
    ];
    
    protected $casts = [
        'fecha_emision_factura' => 'date',
        'fecha_pago_final' => 'date',
        'factura_pagada' => 'boolean',
        'monto_factura' => 'decimal:2',
        'meses_para_pago' => 'decimal:2',
    ];
    
    // Relaciones
    public function venta()
    {
        return $this->belongsTo(Venta::class, 'idComercializacion', 'idComercializacion');
    }
    
    public function factura()
    {
        return $this->belongsTo(Factura::class, 'factura_numero', 'numero');
    }
}
