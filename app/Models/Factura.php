<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Venta;
use App\Models\FacturaEstado;

class Factura extends Model
{
    protected $primaryKey   = 'numero';
    public    $incrementing = false;
    protected $keyType      = 'string';

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
     * La venta asociada a esta factura.
     * 
     * -> facturas.numero  ← ventas.CodigoCotizacion
     */
    public function venta()
    {
        return $this->hasOne(Venta::class, 'CodigoCotizacion', 'numero');
    }

    /**
     * Historial de estados de la factura.
     * 
     * -> facturas.numero       ← factura_estados.factura_numero  
     *    estado_facturas.id     ← factura_estados.estado_id  
     */
    public function estados()
    {
        return $this->hasMany(EstadoFactura::class, 'factura_numero', 'numero');
    }
}
