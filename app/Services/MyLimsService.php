<?php
// app/Services/MyLimsService.php
namespace App\Services;

use Illuminate\Support\Facades\DB;

class MyLimsService
{
    /**
     * Obtiene todos los registros sin paginación (para carga inicial completa).
     *
     * @param int $parametro1
     * @param int $parametro2
     * @return array
     */
    public function obtenerTodosRegistros( string $email): array
    {
        $fullResults = DB::connection('mylims')->select(
            'EXEC CLink_obtenerRegistros  @email= ?',
            [ $email]
        );

        // Convertir cada objeto STDClass a array para Vue
        return collect($fullResults)
            ->map(fn($item) => (array) $item)
            ->all();
    }
}
