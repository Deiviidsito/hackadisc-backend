<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    // Si tu tabla se llama 'clientes' sigue la convención, no hace falta $table

    protected $fillable = [
        'InsecapClienteId',
        'NombreCliente',
    ];

    /**
     * Ventas gestionadas para este cliente.
     */
    public function ventas()
    {
        return $this->hasMany(Venta::class, 'ClienteId', 'InsecapClienteId');
    }

    /**
     * Facturas asociadas (si las consultas directo desde el cliente).
     */
    public function facturas()
    {
        return $this->hasManyThrough(
            Factura::class,
            Venta::class,
            'ClienteId',           // FK en ventas hacia clientes
            'numero',              // PK en facturas
            'InsecapClienteId',    // PK local
            'CodigoCotizacion'     // FK en ventas hacia facturas
        );
    }
}
