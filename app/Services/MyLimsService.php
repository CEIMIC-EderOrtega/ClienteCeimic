<?php
// app/Services/MyLimsService.php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use ZipArchive;
use Illuminate\Support\Carbon;
use Exception;

class MyLimsService
{
    /**
     * Obtiene todos los registros sin paginación (para carga inicial completa).
     */
    public function obtenerTodosRegistros(string $email): array
    {
        $fullResults = DB::connection('mylims')->select(
            'EXEC CLink_obtenerRegistros @email = ?',
            [$email]
        );

        return collect($fullResults)
            ->map(fn($item) => (array) $item)
            ->all();
    }

    /**
     * Extrae los laudos para una lista de IDs de muestra.
     * Si son más de uno, los comprime en un ZIP.
     */
    public function extraerLaudos(array $idsAmostra): array
    {
        if (empty($idsAmostra)) {
            throw new \InvalidArgumentException("IDs de muestra no proporcionados.");
        }

        $idsString = implode(',', $idsAmostra);
        Log::info('Llamando CLink_ExtraerLaudos_Nuevo con: ' . $idsString);

        try {
            $results = DB::connection('mylims')
                ->select('EXEC CLink_ExtraerLaudos_Nuevo @IdsAmostra = ?', [$idsString]);

            $processed = collect($results)
                ->map(function ($item) {
                    $binComp = $item->Laudo;
                    $data = $this->decompressZlib($binComp);
                    return $data ? [
                        'NombreLaudo'   => $item->NombreLaudo,
                        'LaudoBinario'  => $data
                    ] : null;
                })
                ->filter()
                ->values();

            if ($processed->isEmpty()) {
                return [];
            }

            // Si hay más de un laudo, los comprimimos en ZIP
            if ($processed->count() > 1) {
                // Crear archivo temporal en disco
                $tmpFile = tempnam(sys_get_temp_dir(), 'rel_') . '.zip';
                $zip = new ZipArchive;
                if ($zip->open($tmpFile, ZipArchive::CREATE) !== true) {
                    throw new \RuntimeException('No se pudo crear el archivo ZIP en disco.');
                }

                foreach ($processed as $laudo) {
                    $zip->addFromString($laudo['NombreLaudo'], $laudo['LaudoBinario']);
                }
                $zip->close();

                $zipContent = file_get_contents($tmpFile);
                @unlink($tmpFile);

                if ($zipContent === false) {
                    throw new \RuntimeException('No se pudo leer el contenido del archivo ZIP.');
                }

                // Nombre: DD_MM_YY_HH_MI_SS_mmm_relatorio.zip
                $now    = Carbon::now();
                $ms     = intval(substr($now->format('u'), 0, 3));
                $zipName = $now->format('d_m_y_H_i_s') . '_' . sprintf('%03d', $ms) . '_relatorio.zip';

                return [[
                    'NombreLaudo' => $zipName,
                    'Laudo'       => base64_encode($zipContent),
                ]];
            }

            // Solo un laudo: retornarlo individualmente
            $single = $processed->first();
            return [[
                'NombreLaudo' => $single['NombreLaudo'],
                'Laudo'       => base64_encode($single['LaudoBinario']),
            ]];
        } catch (Exception $e) {
            Log::error('Error extraerLaudos:', ['msg' => $e->getMessage()]);
            throw new \RuntimeException("Error al extraer laudos: " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Descomprime datos zlib o gz
     */
    protected function decompressZlib(?string $data): ?string
    {
        if (empty($data)) {
            return null;
        }

        // Intentar gzinflate
        $out = @gzinflate($data);
        if ($out === false) {
            Log::warning('gzinflate falló, intentando gzuncompress');
            $out = @gzuncompress($data);
            if ($out === false) {
                Log::error('gzuncompress también falló.');
                return null;
            }
        }

        return $out;
    }
}
