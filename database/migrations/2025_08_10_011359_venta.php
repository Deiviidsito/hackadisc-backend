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
        $table->bigIncrements('idVenta'); // ID autoincremental interno
        $table->unsignedBigInteger('idComercializacion')->unique(); // ID del JSON, único pero no autoincremental
        $table->string('CodigoCotizacion');
        $table->date('FechaInicio');
        $table->unsignedBigInteger('ClienteId'); // Cambiado para almacenar InsecapClienteId directamente
        $table->string('NombreCliente');
        $table->string('CorreoCreador');
        $table->decimal('ValorFinalComercializacion', 15, 2);
        $table->decimal('ValorFinalCotizacion', 15, 2);
        $table->unsignedInteger('NumeroEstados')->default(0);
        $table->unsignedTinyInteger('estado_venta_id');
        $table->timestamps();

        // Relaciones correctas
        // ClienteId ahora almacena InsecapClienteId directamente (sin FK)
        $table->foreign('ClienteId')
              ->references('InsecapClienteId')->on('clientes')
              ->cascadeOnUpdate()->cascadeOnDelete();
        $table->foreign('CorreoCreador')
              ->references('email')->on('users')
              ->cascadeOnUpdate()->cascadeOnDelete();
        $table->foreign('estado_venta_id')
              ->references('id')->on('estado_ventas')
              ->cascadeOnUpdate()->cascadeOnDelete();
    });
}

    public function down()
  {
    // Verifica si la tabla existe antes de soltar FKs
        if (Schema::hasTable('ventas')) {
            Schema::table('ventas', function (Blueprint $table) {
                // Suelta las FKs solo si existen
                try { $table->dropForeign(['ClienteId']); } catch (\Exception $e) {}
                try { $table->dropForeign(['CorreoCreador']); } catch (\Exception $e) {}
                try { $table->dropForeign(['estado_venta_id']); } catch (\Exception $e) {}
            });
        }
        // Elimina la tabla si existe
        Schema::dropIfExists('ventas');
  }


};
