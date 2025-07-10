<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EstadoVenta extends Model
{
    protected $fillable = ['id', 'nombre', 'descripcion'];

    public $incrementing = false; // Porque estamos usando IDs personalizados (0 a 7)

    // Relación inversa
    public function ventas()
    {
        return $this->hasMany(Venta::class, 'estado_venta_id');
    }

    public function estadoVenta()
    {
        return $this->belongsTo(EstadoVenta::class, 'estado_venta_id');
    }

}

