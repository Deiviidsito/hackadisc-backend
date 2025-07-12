<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('facturas', function (Blueprint $table) {
            $table->string('numero')->primary();
            $table->date('FechaFacturacion');
            $table->unsignedInteger('NumeroEstadosFactura')->default(0);
            $table->decimal('valor', 15, 2)->nullable(); // Valor total de la factura
            
            // RELACIÓN CON COMERCIALIZACIÓN (VENTA)
            $table->unsignedBigInteger('idComercializacion')->index();
            $table->foreign('idComercializacion')->references('idComercializacion')->on('ventas')->onDelete('cascade');
            
            $table->timestamps();
        });
    }

    public function down()
    {
        // Solo elimina la tabla facturas, sin intentar soltar FK de ventas
        Schema::dropIfExists('facturas');
    }
};
