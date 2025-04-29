<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CountrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $countries = [
            ['id' => 9, 'name' => 'Argentina', 'code' => 'AR'],
            ['id' => 18, 'name' => 'Bolívia', 'code' => 'BO'], // Nota: El nombre correcto es Bolivia
            ['id' => 20, 'name' => 'Brasil', 'code' => 'BR'],
            ['id' => 26, 'name' => 'Chile', 'code' => 'CL'],
            ['id' => 28, 'name' => 'Colômbia', 'code' => 'CO'], // Nota: El nombre correcto es Colombia
            ['id' => 32, 'name' => 'Costa Rica', 'code' => 'CR'],
            ['id' => 38, 'name' => 'Ecuador', 'code' => 'EC'],
            ['id' => 42, 'name' => 'Estados Unidos', 'code' => 'US'],
            ['id' => 55, 'name' => 'Honduras', 'code' => 'HN'],
            ['id' => 79, 'name' => 'México', 'code' => 'MX'],
            ['id' => 85, 'name' => 'Nicarágua', 'code' => 'NI'],
            ['id' => 89, 'name' => 'Panamá', 'code' => 'PA'],
            ['id' => 91, 'name' => 'Paraguai', 'code' => 'PY'], // Nota: El nombre correcto es Paraguay
            ['id' => 92, 'name' => 'Peru', 'code' => 'PE'],
            ['id' => 94, 'name' => 'Porto Rico', 'code' => 'PR'], // Nota: Puerto Rico es un territorio no independiente
            ['id' => 98, 'name' => 'República Dominicana', 'code' => 'DO'],
            ['id' => 115, 'name' => 'Uruguai', 'code' => 'UY'], // Nota: El nombre correcto es Uruguay
            ['id' => 118, 'name' => 'Venezuela', 'code' => 'VE'],
            ['id' => 120, 'name' => 'Reino Unido', 'code' => 'GB'], // O UK, GB es más común para el país.
            ['id' => 121, 'name' => 'França', 'code' => 'FR'], // Nota: El nombre correcto es Francia
            ['id' => 122, 'name' => 'Guatemala', 'code' => 'GT'],
        ];

        // Inserta los datos en la tabla 'countries'
        // Usamos insert directamente para poder especificar el ID
        DB::table('countries')->insert($countries);
    }
}