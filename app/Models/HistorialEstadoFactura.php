<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HistorialEstadoFactura extends Model
{
    protected $table = 'historial_estados_factura';
    protected $fillable = [
        'factura_numero',
        'idComercializacion',
        'estado_id',
        'fecha',
        'pagado',
        'observacion',
        'usuario_email',
    ];

    public function factura()
    {
        return $this->belongsTo(Factura::class, 'factura_numero', 'numero');
    }

    public function estadoFactura()
    {
        return $this->belongsTo(EstadoFactura::class, 'estado_id', 'id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_email', 'email');
    }
}
