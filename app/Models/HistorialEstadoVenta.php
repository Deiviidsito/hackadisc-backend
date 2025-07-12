<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HistorialEstadoVenta extends Model
{
    protected $table = 'historial_estados_venta';
    protected $fillable = [
        'venta_id',
        'idComercializacion',
        'estado_venta_id',
        'fecha',
        'numero_estado',
    ];

    public function venta()
    {
        return $this->belongsTo(Venta::class, 'venta_id', 'idComercializacion');
    }

    public function estadoVenta()
    {
        return $this->belongsTo(EstadoVenta::class, 'estado_venta_id', 'id');
    }
}
