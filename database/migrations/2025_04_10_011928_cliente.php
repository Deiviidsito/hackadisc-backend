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
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();  // PK autoincremental
            $table->unsignedBigInteger('InsecapClienteId')->unique()
                ->comment('ID que viene del JSON como ClienteId');
        $table->string('NombreCliente')
                ->comment('Nombre de la empresa, no nulo');
            $table->timestamps();
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('clientes');
    }

};
