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
            $table->timestamps();
        });
    }

    public function down()
    {
        // Solo elimina la tabla facturas, sin intentar soltar FK de ventas
        Schema::dropIfExists('facturas');
    }
};
