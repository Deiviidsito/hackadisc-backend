<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Usuario de prueba principal
        User::create([
            'name' => 'David',
            'email' => 'test@test.com',
            'password' => bcrypt('123456'),
            'email_verified_at' => now(),
        ]);

        // Usuarios adicionales para probar Capi
        User::create([
            'name' => 'María García',
            'email' => 'maria@example.com',
            'password' => bcrypt('123456'),
            'email_verified_at' => now(),
        ]);

        User::create([
            'name' => 'Carlos López',
            'email' => 'carlos@example.com',
            'password' => bcrypt('123456'),
            'email_verified_at' => now(),
        ]);

        User::create([
            'name' => 'Ana Martínez',
            'email' => 'ana@example.com',
            'password' => bcrypt('123456'),
        ]);

        User::create([
            'name' => 'Pedro Rodríguez',
            'email' => 'pedro@example.com',
            'password' => bcrypt('123456'),
            'email_verified_at' => now(),
        ]);
    }
}
