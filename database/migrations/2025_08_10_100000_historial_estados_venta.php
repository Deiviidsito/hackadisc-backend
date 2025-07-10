<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('historial_estados_venta', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('venta_id'); // FK a ventas.idVenta (clave primaria)
            $table->unsignedBigInteger('idComercializacion'); // ID de comercialización del JSON
            $table->unsignedTinyInteger('estado_venta_id'); // FK a estado_ventas.id
            $table->date('fecha');
            $table->unsignedInteger('numero_estado')->nullable(); // Número de estado en el historial
            $table->timestamps();

            $table->foreign('venta_id')->references('idVenta')->on('ventas')->cascadeOnDelete();
            $table->foreign('estado_venta_id')->references('id')->on('estado_ventas')->cascadeOnDelete();
            $table->index('idComercializacion'); // Índice para búsquedas rápidas
        });
    }

    public function down()
    {
        Schema::dropIfExists('historial_estados_venta');
    }
};
