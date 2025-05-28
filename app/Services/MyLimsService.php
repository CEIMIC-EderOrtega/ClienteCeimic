<?php
// app/Services/MyLimsService.php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use ZipArchive;
use Illuminate\Support\Carbon;
use Exception;
use Illuminate\Validation\Rules\In;
use Illuminate\Support\Arr; // Para facilitar el manejo de arrays

class MyLimsService
{
    /**
     * Obtiene todos los registros sin paginación (para carga inicial completa).
     */
    /* public function obtenerTodosRegistros(string $email): array
    {
        $fullResults = DB::connection('mylims')->select(
            'EXEC CLink_obtenerRegistros @email = ?',
            [$email]
        );

        return collect($fullResults)
            ->map(fn($item) => (array) $item)
            ->all();
    }*/
    public function obtenerRegistrosEnviro(string $email): array
    {
        try {
            $results = DB::connection('mylims')->select(
                'EXEC CLink_obtenerRegistros @email = ?', // SP original
                [$email]
            );
            return collect($results)
                ->map(fn($item) => (array) $item)
                ->all();
        } catch (Exception $e) {
            Log::error("Error en MyLimsService::obtenerRegistrosEnviro para {$email}", ['exception' => $e]);
            throw new Exception("Error al obtener registros Enviro: " . $e->getMessage());
        }
    }
   public function FilterNewFood(string $email, array $filters): array
    {
        //dd("FilterNewFood: " . $email . " - " . json_encode($filters));
        // Obtener el ID de estado como string desde los filtros (ej: "2", "3", "4", "10")
        // Si no viene $filters['status'], por defecto será "4".
        $status_id_string = Arr::get($filters, 'status', '4');

        // Validar que el ID de estado sea uno de los conocidos para evitar errores.
        // Estos son los values que usa tu <select> en MuestrasFilters.vue
        $valid_status_ids = ['2', '10', '3', '4'];
        if (!in_array($status_id_string, $valid_status_ids)) {
            $status_id_string = '4'; // Si no es válido, usar '4' (Publicado) como defecto seguro.
        }

        // *** LA CORRECCIÓN MÁS IMPORTANTE ESTÁ AQUÍ ***
        // Convertir el ID de estado (que es un string como "4") a un entero (int).
        // Esto es lo que el Stored Procedure espera para @Sit.
        $status_param_for_sp = (int) $status_id_string;

        // El resto de la lógica para obtener otros filtros y fechas:
        $today = Carbon::today();
        $defaultDesde = $today->copy()->subMonth()->format('Y-m-d');
        $defaultHasta = $today->format('Y-m-d');
        $desde = Carbon::parse(Arr::get($filters, 'desde', $defaultDesde))->startOfDay();
        $hasta = Carbon::parse(Arr::get($filters, 'hasta', $defaultHasta))->endOfDay();
        $grupo = Arr::get($filters, 'search_grupo');
        $processo = Arr::get($filters, 'search_processo');
        $numero = Arr::get($filters, 'search_numero'); // Se envía como string o null, SP lo recibe como VARCHAR
        $idamostra = Arr::get($filters, 'search_idamostra');
        $solicitante = Arr::get($filters, 'search_solicitante');
        $tipo = Arr::get($filters, 'search_tipo');
        $cdamostra = Arr::get($filters, 'search_cdamostra');

        Log::debug('Ejecutando CLink_obtenerRegistrosFoodFiltrados1 con parámetros:', [
            'Sit_INT_param' => $status_param_for_sp, // Se loguea el entero que se enviará
            'Solicitante' => $solicitante,
            'Grupo' => $grupo,
            'Tipo' => $tipo,
            'Cdamostra' => $cdamostra,
            'Idamostra' => $idamostra,
            'Processo' => $processo,
            'Numero' => $numero, // $numero sigue siendo string o null aquí, SP lo maneja
            'Desde' => $desde->toDateTimeString(),
            'Hasta' => $hasta->toDateTimeString(),
        ]);

        try {
            $results = DB::connection('mylims')->select(
                'EXEC CLink_obtenerRegistrosFoodFiltrados1 @Sit = ?,@Solicitante=?,@Grupo=?,@Tipo=?,@Cdamostra=?,@Idamostra=?,@Processo=?,@Numero=?,@Desde=?,@Hasta=?',
                [
                    $status_param_for_sp, // *** AQUÍ SE PASA EL ENTERO ***
                    $solicitante,
                    $grupo,
                    $tipo,
                    $cdamostra,
                    $idamostra,
                    $processo,
                    $numero,              // Se pasa el string o null que el SP espera como VARCHAR
                    $desde,
                    $hasta
                ]
            );

            return collect($results)->map(function ($item) {
                return (array) $item;
            })->all();

        } catch (Exception $e) {
            // Tu log de error
            Log::error("Error en MyLimsService::CLink_obtenerRegistrosFoodFiltrados1", [
                'input_filters' => $filters,
                'params_sent_to_sp' => [
                    'Sit' => $status_param_for_sp,
                    'Solicitante' => $solicitante,
                    'Grupo' => $grupo,
                    'Tipo' => $tipo,
                    'Cdamostra' => $cdamostra,
                    'Idamostra' => $idamostra,
                    'Processo' => $processo,
                    'Numero' => $numero,
                    'Desde' => $desde->toDateTimeString(),
                    'Hasta' => $hasta->toDateTimeString(),
                ],
                'exception_message' => $e->getMessage()
            ]);
            throw new Exception("Error al obtener registros Food filtrados: " . $e->getMessage());
        }
    }

    /**
     * Obtiene registros para la unidad 'Food' aplicando filtros complejos.
     * Llama al SP CLink_obtenerRegistrosFoodFiltrados.
     */
    public function obtenerRegistrosFoodFiltrados(string $email, array $filters): array
    {
        $statusLogicMap = [

            '4' => 'publicada_no_enviada',
            '111' => 'enviada_portal',
            'finalizada' => 'finalizada',
            'publicada' => 'publicada_no_enviada',
            'enviada' => 'enviada_portal',
        ];
        $status_logic = $statusLogicMap[$filters['status'] ?? '3'] ?? 'finalizada';

        $today = Carbon::today();
        $defaultDesde = $today->copy()->subMonth()->format('Y-m-d');
        $defaultHasta = $today->format('Y-m-d');

        $desde = Carbon::parse(Arr::get($filters, 'desde', $defaultDesde))->startOfDay();
        $hasta = Carbon::parse(Arr::get($filters, 'hasta', $defaultHasta))->endOfDay();
        $grupo = Arr::get($filters, 'search_grupo');
        $processo = Arr::get($filters, 'search_processo');
        $numero = Arr::get($filters, 'search_numero');
        $idamostra = Arr::get($filters, 'search_idamostra');
        $solicitante = Arr::get($filters, 'search_solicitante');
        $tipo = Arr::get($filters, 'search_tipo');
        $cdamostra = Arr::get($filters, 'search_cdamostra');

        Log::debug('Ejecutando CLink_obtenerRegistrosFoodFiltrados', [
            'email' => $email,
            'status_logic' => $status_logic,
            'desde' => $desde->toDateTimeString(),
            'hasta' => $hasta->toDateTimeString(),
            'grupo' => $grupo,
            'processo' => $processo,
            'numero' => $numero,
            'idamostra' => $idamostra,
            'solicitante' => $solicitante,
            'tipo' => $tipo,
            'cdamostra' => $cdamostra,
        ]);

        try {
            $results = DB::connection('mylims')->select(
                'EXEC CLink_obtenerRegistrosFoodFiltrados @email = ?, @status_logic = ?, @desde = ?, @hasta = ?, @grupo = ?, @processo = ?, @numero = ?, @idamostra = ?, @solicitante = ?, @tipo = ?, @cdamostra = ?',
                [
                    $email,
                    $status_logic,
                    $desde,
                    $hasta,
                    $grupo,
                    $processo,
                    $numero,
                    $idamostra,
                    $solicitante,
                    $tipo,
                    $cdamostra
                ]
            );

            // Convertir resultados a array (como estaba antes)
            return collect($results)->map(function ($item) {
                $itemArray = (array) $item;
                // Opcional: Formatear fechas si es necesario para Vue
                // foreach ($itemArray as $key => $value) {
                //     if ($value instanceof Carbon || $value instanceof \DateTime) {
                //          $itemArray[$key] = Carbon::parse($value)->format('d/m/Y'); // Ejemplo formato
                //     }
                // }
                return $itemArray;
            })->all();
        } catch (Exception $e) { // <-- La excepción se captura en $e

            // *** CORRECCIÓN AQUÍ ***
            // Cambiamos compact('filters', 'exception') por un array explícito
            // que incluye la variable correcta $e
            Log::error("Error en MyLimsService::obtenerRegistrosFoodFiltrados para {$email}", [
                'filters' => $filters,
                'exception' => $e // Pasamos el objeto Exception directamente
            ]);
            // *** FIN CORRECCIÓN ***

            // Lanzamos la excepción para que el controlador la capture y muestre el error genérico
            throw new Exception("Error al obtener registros Food filtrados: " . $e->getMessage());
        }
    }



    // ... (Asegúrate de tener el método extraerMultiplesLaudos aquí también si lo usas)
    public function extraerMultiplesLaudos(string $email, array $selectedIds): array
    {
        // Implementación para extraer laudos...
        // (Asegúrate que la lógica aquí sea correcta y maneje errores)
        Log::info("Extrayendo laudos para", ['email' => $email, 'ids' => $selectedIds]);
        // Ejemplo de datos simulados
        $laudos = [];
        foreach ($selectedIds as $id) {
            // Aquí iría la lógica real para buscar el Laudo Base64 y el nombre
            // Ejemplo: $fileData = $this->findLaudoById($id);
            // if ($fileData) { $laudos[] = $fileData; }

            // Datos simulados para prueba:
            $laudos[] = [
                'NombreLaudo' => 'Laudo_' . $id . '.pdf',
                'Laudo' => base64_encode('Esto es un PDF simulado para ' . $id) // Simula Base64
            ];
        }
        if (empty($laudos)) {
            // throw new Exception("No se encontraron laudos para los IDs seleccionados.");
            // O devolver un array vacío si eso es aceptable
            return [];
        }
        return $laudos;
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
    /**
     * Verifica el estado de un email en la base de datos externa (CEIMIC/NPU).
     * Retorna un array indicando el estado y el código de país (si aplica):
     * ['status' => 'ni', 'country_code' => null] si el email contiene 'ceimic'.
     * ['status' => 'ok', 'country_code' => '...'] si el email está registrado y activo.
     * ['status' => 'no', 'country_code' => null] si el email no está registrado o no está activo/no moroso.
     */
    public function checkCeimicEmailStatus(string $email): array
    {
        $email = strtolower($email);

        // 1. Verificar si contiene 'ceimic'
        if (str_contains($email, 'ceimic')) {
            Log::info("Email con dominio 'ceimic' detectado para registro: " . $email);
            return ['status' => 'ni', 'country_code' => null]; // Estado 'ni', código de país null
        }

        // 2. Si no contiene 'ceimic', consultar la DB externa
        try {
            // Usamos la conexión 'mylims' que ya tienes configurada
            $results = DB::connection('mylims')->select(
                "SELECT top 1 E.CDPAIS
                 FROM dbo.CONTATOSEMP CE
                 INNER JOIN EMPRESA E ON CE.IDAUXEMPRESA = E.IDAUXEMPRESA
                 WHERE CE.EMAIL = ? AND E.FLATIVO = 'S' AND E.FLINADIMPLENTE = 'N'",
                [$email] // Usamos prepared statements (?) para evitar inyección SQL
            );

            if (!empty($results)) {
                // Si se encuentra un registro activo y no moroso
                $countryCode = trim($results[0]->CDPAIS); // Capturamos el CDPAIS y eliminamos espacios
                Log::info("Email encontrado en DB externa (activo, no moroso): " . $email . " País: " . $countryCode);
                return ['status' => 'ok', 'country_code' => $countryCode]; // Estado 'ok', devolvemos el código
            } else {
                // Si no se encuentra un registro activo y no moroso (este es el caso 'no')
                Log::info("Email no encontrado en DB externa (o no activo/moroso): " . $email);
                return ['status' => 'no', 'country_code' => null]; // Estado 'no', código de país null
            }
        } catch (\Exception $e) {
            // Capturar errores de conexión o consulta a la DB externa
            Log::error('Error al consultar DB externa para validación de email: ' . $e->getMessage(), [
                'email' => $email,
                'exception' => $e
            ]);
            // En caso de error, podrías retornar un estado de error diferente
            return ['status' => 'error', 'message' => 'Error al validar con sistema externo.', 'country_code' => null]; // Estado de error, código de país null
        }
    }
}
