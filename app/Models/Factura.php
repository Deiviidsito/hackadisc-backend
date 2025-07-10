<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Factura extends Model
{
    // Clave primaria no incremental
    protected $primaryKey = 'numero';
    public $incrementing  = false;
    protected $keyType    = 'string';

    protected $fillable = [
        'numero',
        'FechaFacturacion',
        'NumeroEstadosFactura',
    ];

    protected $casts = [
        'FechaFacturacion'     => 'date',
        'NumeroEstadosFactura' => 'integer',
    ];

    /**
     * Venta asociada (una factura, una venta).
     */
    public function venta()
    {
        return $this->hasOne(Venta::class, 'CodigoCotizacion', 'numero');
    }

    /**
     * Historial de estados de esta factura.
     */
    public function estados()
    {
        return $this->hasMany(EstadoFactura::class, 'factura_numero', 'numero');
    }
}
