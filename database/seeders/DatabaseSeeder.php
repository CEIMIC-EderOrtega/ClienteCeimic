<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Puedes descomentar o dejar comentada la creación de usuarios según necesites
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Agrega este bloque para llamar a otros seeders
        $this->call([
            CountrySeeder::class, // <-- Agrega esta línea aquí
            // Si tienes otros seeders en el futuro, los añades en este array
        ]);
    }
}