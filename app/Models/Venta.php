<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Venta extends Model
{
    protected $primaryKey = 'idComercializacion';

    protected $fillable = [
        'CodigoCotizacion',
        'FechaInicio',
        'ClienteId',
        'NombreCliente',
        'CorreoCreador',
        'ValorFinalComercializacion',
        'ValorFinalCotizacion',
        'NumeroEstados'
    ];

    protected $casts = [
        'FechaInicio' => 'date',
    ];

    // Relación con Factura
    public function factura()
    {
        return $this->belongsTo(Factura::class, 'CodigoCotizacion', 'CodigoCotizacion');
    }

    // Relación con Cliente
    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'ClienteId');
    }

    // Relación con Usuario
    public function creador()
    {
        return $this->belongsTo(User::class, 'CorreoCreador', 'email');
    }

    // Más adelante: Estados
    public function estados()
    {
        return $this->hasMany(EstadoVenta::class, 'venta_id', 'idComercializacion');
    }
}


