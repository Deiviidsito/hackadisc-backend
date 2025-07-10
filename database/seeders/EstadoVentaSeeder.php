<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EstadoVentaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
        public function run()
    {
        // Limpia la tabla antes de insertar para evitar duplicados
        DB::table('estado_ventas')->delete();

        $estados = [
            ['id' => 0, 'nombre' => 'En Proceso'],
            ['id' => 1, 'nombre' => 'Terminada', 'descripcion' => 'Cuando el servicio ya está listo para ser facturado'],
            ['id' => 2, 'nombre' => 'Cancelada'],
            ['id' => 3, 'nombre' => 'Terminada SENCE', 'descripcion' => 'Estado para facturación parcial del servicio en la mayoría de los casos.'],
            ['id' => 4, 'nombre' => 'Deshabilitada'],
            ['id' => 5, 'nombre' => 'Borrador'],
            ['id' => 6, 'nombre' => 'Reprogramada'],
            ['id' => 7, 'nombre' => 'Perdida'],
        ];

    //Se crea un estado por cada estado que esta aquí arriba
    foreach ($estados as $estado) {
        \App\Models\EstadoVenta::create($estado);
    }
    }

}
