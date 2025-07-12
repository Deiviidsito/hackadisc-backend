<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EstadoFactura extends Model
{
    protected $fillable = [
        'factura_numero',
        'estado_id',
        'fecha',
        'pagado',
        'observacion',
        'usuario_email',
    ];

    protected $casts = [
        'fecha' => 'date',
        'pagado' => 'decimal:2',
    ];

    /**
     * La factura a la que pertenece este registro de estado.
     */
    public function factura()
    {
        return $this->belongsTo(Factura::class, 'factura_numero', 'numero');
    }

    /**
     * El estado (maestro) que representa este registro.
     */
    public function estado()
    {
        return $this->belongsTo(EstadoFactura::class, 'estado_id', 'id');
    }

    /**
     * Usuario que realizó esta transición de estado.
     */
    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_email', 'email');
    }
}
