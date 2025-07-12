<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Venta extends Model
{
    protected $primaryKey = 'idVenta';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'idComercializacion',
        'CodigoCotizacion',
        'FechaInicio',
        'ClienteId',
        'NombreCliente',
        'CorreoCreador',
        'ValorFinalComercializacion',
        'ValorFinalCotizacion',
        'NumeroEstados',
        'estado_venta_id',
    ];

    protected $casts = [
        'FechaInicio' => 'date',
    ];

    // Venta → Factura
    public function factura()
    {
        return $this->belongsTo(Factura::class, 'CodigoCotizacion', 'numero');
    }

    // Venta → Cliente (usando InsecapClienteId)
    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'ClienteId', 'InsecapClienteId');
    }

    // Venta → Usuario
    public function creador()
    {
        return $this->belongsTo(User::class, 'CorreoCreador', 'email');
    }

    // Venta → EstadoVenta (maestro)
    public function estadoVenta()
    {
        return $this->belongsTo(EstadoVenta::class, 'estado_venta_id', 'id');
    }

    // Historial de estados de la venta
    public function historialEstados()
    {
        return $this->hasMany(HistorialEstadoVenta::class, 'venta_id', 'idComercializacion');
    }
}
