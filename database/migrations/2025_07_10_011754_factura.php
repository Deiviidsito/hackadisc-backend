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
            Schema::create('facturas', function (Blueprint $table) {
                $table->string('numero')->primary(); // EJ: "28459"
                $table->date('FechaFacturacion'); // Formato YYYY-MM-DD
                $table->unsignedInteger('NumeroEstadosFactura')->default(0);
                $table->timestamps();
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
