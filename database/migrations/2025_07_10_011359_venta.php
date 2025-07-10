<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
    Schema::create('ventas', function (Blueprint $table) {
        $table->id('idComercializacion'); // primary key

        $table->string('CodigoCotizacion'); // FK hacia Factura
        $table->date('FechaInicio'); // formato YYYY-MM-DD
        $table->unsignedBigInteger('ClienteId'); // FK hacia Cliente
        $table->string('NombreCliente'); // redundante pero solicitado
        $table->string('CorreoCreador'); // FK hacia Usuario (por correo)
        $table->decimal('ValorFinalComercializacion', 15, 2);
        $table->decimal('ValorFinalCotizacion', 15, 2);
        $table->unsignedInteger('NumeroEstados')->default(0); // contador

        $table->timestamps();

        // Claves foráneas (reales y lógicas)
        $table->foreign('CodigoCotizacion')->references('CodigoCotizacion')->on('facturas');
        $table->foreign('ClienteId')->references('id')->on('clientes');
        $table->foreign('CorreoCreador')->references('email')->on('users');
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
