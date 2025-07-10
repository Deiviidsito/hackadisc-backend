<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('datos_estadisticas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('idComercializacion'); // FK a ventas
            $table->string('factura_numero'); // FK a facturas
            $table->date('fecha_emision_factura'); // Fecha del primer estado 1
            $table->date('fecha_pago_final')->nullable(); // Fecha del último estado 3 (puede ser null si no está pagado)
            $table->integer('dias_para_pago')->nullable(); // Días transcurridos entre emisión y pago
            $table->decimal('meses_para_pago', 8, 2)->nullable(); // Meses transcurridos (más preciso)
            $table->boolean('factura_pagada')->default(false); // Si la factura está completamente pagada
            $table->decimal('monto_factura', 15, 2)->nullable(); // Monto de la factura para análisis
            $table->string('cliente_nombre')->nullable(); // Nombre del cliente para facilitar consultas
            $table->timestamps();
            
            // Índices para consultas rápidas
            $table->index('idComercializacion');
            $table->index('factura_numero');
            $table->index('factura_pagada');
            $table->index('dias_para_pago');
            
            // Foreign keys
            $table->foreign('factura_numero')->references('numero')->on('facturas')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('datos_estadisticas');
    }
};
