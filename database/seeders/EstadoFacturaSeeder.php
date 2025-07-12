<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EstadoFacturaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $estados = [
            ['id'=>0, 'nombre'=>'No Facturado'],
            ['id'=>1, 'nombre'=>'Facturado'],
            ['id'=>2, 'nombre'=>'Refacturado'],
            ['id'=>3, 'nombre'=>'Pagado'],
            ['id'=>4, 'nombre'=>'Abonado'],
            ['id'=>5, 'nombre'=>'No Aplica'],
            ['id'=>6, 'nombre'=>'Perdida'],
            ['id'=>7, 'nombre'=>'En Juicio'],
            
        ];
    foreach($estados as $e) {
        \App\Models\EstadoFactura::create($e);
    }
}

}
