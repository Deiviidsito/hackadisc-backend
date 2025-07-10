<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EstadoFactura extends Model
{
    // No incrementa automáticamente porque usamos IDs personalizados (0–n)
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'nombre',
        'descripcion',
    ];

    /**
     * Estados históricos de facturas que usan este estado.
     */
    public function facturaEstados()
    {
        return $this->hasMany(EstadoFactura::class, 'estado_id');
    }
}
