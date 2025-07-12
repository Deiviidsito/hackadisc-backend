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
        Schema::table('facturas', function (Blueprint $table) {
            // Agregar columna idComercializacion para relacionar con ventas
            $table->unsignedBigInteger('idComercializacion')->nullable()->after('NumeroEstadosFactura');
            
            // Crear índice para mejorar rendimiento en consultas
            $table->index('idComercializacion');
            
            // Agregar foreign key constraint (si ya existen datos, será nullable inicialmente)
            $table->foreign('idComercializacion')->references('idComercializacion')->on('ventas')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            // Eliminar foreign key constraint primero
            $table->dropForeign(['idComercializacion']);
            
            // Eliminar índice
            $table->dropIndex(['idComercializacion']);
            
            // Eliminar la columna
            $table->dropColumn('idComercializacion');
        });
    }
};
