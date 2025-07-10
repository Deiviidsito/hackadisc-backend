<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('historial_estados_factura', function (Blueprint $table) {
            $table->id();
            $table->string('factura_numero'); // FK a facturas.numero
            $table->unsignedTinyInteger('estado_id'); // FK a estado_facturas.id
            $table->date('fecha');
            $table->decimal('pagado', 15, 2)->nullable();
            $table->text('observacion')->nullable();
            $table->string('usuario_email')->nullable(); // FK a users.email
            $table->timestamps();

            $table->foreign('factura_numero')->references('numero')->on('facturas')->cascadeOnDelete();
            $table->foreign('estado_id')->references('id')->on('estado_facturas')->cascadeOnDelete();
            $table->foreign('usuario_email')->references('email')->on('users')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::dropIfExists('historial_estados_factura');
    }
};
