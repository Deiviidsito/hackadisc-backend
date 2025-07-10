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
        Schema::create('estado_facturas', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();   // 0,1,2,...
            $table->string('nombre');                       // ‘Pendiente’, ‘Pagada’, …
            $table->text('descripcion')->nullable();        // detalle opcional
            $table->timestamps();
        });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('estado_facturas');
    }
};
