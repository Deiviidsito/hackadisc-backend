<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Venta;
use App\Models\Factura;

class Cliente extends Model
{
    // Si usas el id autoincremental interno como PK, no es necesario sobreescribir $primaryKey

    protected $fillable = [
        'InsecapClienteId',
        'NombreCliente',
    ];

    /**
     * Ventas gestionadas para este cliente.
     * 
     * -> Cliente.id  ← ventas.ClienteId
     */
    public function ventas()
    {
        return $this->hasMany(Venta::class, 'ClienteId', 'id');
    }

    /**
     * Facturas asociadas (a través de ventas).
     * 
     * cliente.id          ← ventas.ClienteId  
     * ventas.CodigoCotizacion ← facturas.numero
     */
    public function facturas()
    {
        return $this->hasManyThrough(
            Factura::class,
            Venta::class,
            'ClienteId',        // FK en ventas → clientes.id
            'numero',           // FK en facturas → ventas.CodigoCotizacion
            'id',               // PK local clientes.id
            'CodigoCotizacion'  // Key en ventas que referencia facturas.numero
        );
    }
}
