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
        Schema::create('estado_ventas', function (Blueprint $table) {
        $table->id(); // por convención será el ID del estado (0 a 7)
        $table->string('nombre'); // Ej: 'En Proceso', 'Terminada'
        $table->text('descripcion')->nullable(); // Descripción adicional si es necesario describir a los trabajadores nuevos que hace cada estado.
        $table->timestamps();
        });

    // Voy a ingresar los valores de los estados en un seeder, (estadoVentaSeeder), en este va a haber comentarios de que significa cada estado, estos
    //los ponemos en una tabla para poder trabajarlos más familiarmente al desplegarlos en FrontEnd.
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
