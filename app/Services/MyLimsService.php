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
        //jalar infformacion aparte de clink_obtenerRegistros
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
        //dd($filters, $status_param_for_sp);
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
            // --- INICIO Bloque de Depuración ---
            if (isset($results[0]) && property_exists($results[0], 'GeneratedQueryForDebug')) {
                $generatedSql = $results[0]->GeneratedQueryForDebug;
                Log::debug('SQL Generado por el SP: ' . $generatedSql);
                // Si quieres verlo en la respuesta (solo para depurar, no en producción):

            }
            // --- FIN Bloque de Depuración ---
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
    /* public function obtenerRegistrosFoodFiltrados(string $email, array $filters): array
    {
        $statusLogicMap = [

            '4' => 'publicada_no_enviada',
            '111' => 'enviada_portal',
            'finalizada' => 'finalizada',
            'publicada' => 'publicada_no_enviada',
            'enviada' => 'enviada_portal',
        ];
        $status_logic = $statusLogicMap[$filters['status'] ?? '3'] ?? 'finalizada';
        dd($statusLogicMap);
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
    }*/



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
    /**
     * Obtiene los detalles extendidos de una muestra por su cdamostra,
     * específicamente para mostrar en los paneles de detalle.
     *
     * @param string $cdamostra El ID interno de la muestra.
     * @return array|null Los detalles extendidos de la muestra o null si no se encuentra.
     */
    public function getSampleExtendedDetailsForDisplay(string $cdamostra): ?array
    {
        Log::info("MyLimsService: Obteniendo detalles extendidos para display para cdamostra: " . $cdamostra);

        try {
            $results = DB::connection('mylims')->select("
                SELECT
                    CASE WHEN ISNULL(dbo.infos(A.CDAMOSTRA,962),'') = '' THEN (COALESCE(CONVERT(VARCHAR,A.DTPUBLICACAO,103),CONVERT(VARCHAR,GETDATE(),103))) ELSE
                    ((CASE WHEN DAY(dbo.infos(A.CDAMOSTRA,962)) < 10 THEN '0' + CAST(DAY(dbo.infos(A.CDAMOSTRA,962)) AS VARCHAR(10)) ELSE CAST(DAY(dbo.infos(A.CDAMOSTRA,962)) AS VARCHAR(10)) END) + '/' +
                    (CASE WHEN MONTH(dbo.infos(A.CDAMOSTRA,962)) < 10 THEN '0' + CAST(MONTH(dbo.infos(A.CDAMOSTRA,960)) AS VARCHAR(10)) ELSE CAST(MONTH(dbo.infos(A.CDAMOSTRA,962)) AS VARCHAR(10)) END) + '/' +
                    CAST(YEAR(dbo.infos(A.CDAMOSTRA,962)) AS VARCHAR(10)))
                    END AS datalaudo, -- Fecha de Emisión
                    ES.RAZAOSOCIAL + (CASE WHEN ISNULL(ES.NMUDEMPRESA04, '') != '' THEN ' (' + ES.NMUDEMPRESA04 + ')' ELSE '' END) as solicitante, -- Cliente
                    COALESCE(OG.OBS,OP.OBS,(
                    CASE WHEN LN.NMLOGRADOURO IS NOT NULL THEN + LN.NMLOGRADOURO + ' ' ELSE '' END +
                    CASE WHEN EN.ENDERECO IS NOT NULL THEN EN.ENDERECO + ' ' ELSE '' END +
                    CASE WHEN EN.NUMERO IS NOT NULL THEN EN.NUMERO ELSE '' END +
                    CASE WHEN EN.COMPLEMENTO IS NOT NULL THEN ' ' + EN.COMPLEMENTO + ' ' ELSE '' END +
                    CASE WHEN EN.OBSENDERECO IS NOT NULL THEN ' ' + EN.OBSENDERECO +' - ' ELSE ' - ' END +
                    CASE WHEN EN.BAIRRO IS NOT NULL THEN EN.BAIRRO + ' - ' ELSE '' END +
                    CASE WHEN CD.NMCIDADE IS NOT NULL THEN CD.NMCIDADE + ' - ' ELSE '' END +
                    CASE WHEN ISNULL(ESM.NMESTADO, '') = '' THEN (CASE WHEN ED.NMESTADO IS NOT NULL THEN ED.NMESTADO ELSE '' END) ELSE ESM.NMESTADO END )) AS direccion, -- Dirección
                    dbo.infos(A.CDAMOSTRA,610) AS muestreado_por, -- Muestreado por
                    case when dbo.infos(A.CDAMOSTRA,677) is null then 'N/A' else dbo.infos(A.CDAMOSTRA,677) end AS descripcion_muestra, -- Descripción de la muestra
                    CASE WHEN ISNULL(UD.NMUDAMOSTRA09,'') != '' THEN LEFT(UD.NMUDAMOSTRA09,10) ELSE LEFT(dbo.fechahora(dbo.infos(A.CDAMOSTRA,CASE WHEN A.CDUNIDADE IN (123) THEN 957 ELSE 611 END)),10) END AS fecha_recepcion, -- Fecha de recepción
                    CONVERT(VARCHAR,
                        (
                            SELECT dbo.fecha_amd(
                            (CASE WHEN DAY(VEA.VLVE) < 10 THEN '0' + CAST(DAY(VEA.VLVE) AS VARCHAR(10)) ELSE CAST(DAY(VEA.VLVE) AS VARCHAR(10)) END) + '/' +
                            (CASE WHEN MONTH(VEA.VLVE) < 10 THEN '0' + CAST(MONTH(VEA.VLVE) AS VARCHAR(10)) ELSE CAST(MONTH(VEA.VLVE) AS VARCHAR(10)) END) + '/' +
                            CAST(YEAR(VEA.VLVE) AS VARCHAR(10)))
                            FROM VEAMOSTRA VEA
                            INNER JOIN VEMETODO VEM ON VEM.CDVEMETODO = VEA.CDVEMETODO
                            INNER JOIN METODOSAM MA ON MA.CDAMOSTRA = VEA.CDAMOSTRA AND MA.CDMETODO = VEM.CDMETODO
                            WHERE VEA.CDAMOSTRA = A.CDAMOSTRA AND VEM.CDVE = (CASE WHEN A.CDUNIDADE IN (123) THEN 4 ELSE 234 END)
                            AND VEA.DTEDICAO =
                            (
                                SELECT DTEDICAO = MAX(VEA.DTEDICAO)
                                FROM VEAMOSTRA VEA
                                INNER JOIN VEMETODO VEM ON VEM.CDVEMETODO = VEA.CDVEMETODO
                                INNER JOIN METODOSAM MA ON MA.CDAMOSTRA = VEA.CDAMOSTRA AND MA.CDMETODO = VEM.CDMETODO
                                WHERE VEA.CDAMOSTRA = A.CDAMOSTRA AND VEM.CDVE = (CASE WHEN A.CDUNIDADE IN (123) THEN 4 ELSE 234 END)
                                GROUP BY VEA.CDAMOSTRA, VEM.CDVE
                            )
                        )
                    ,103) AS fecha_inicio_analisis, -- Fecha de Inicio Análisis
                    CASE WHEN ISNULL(dbo.infos(A.CDAMOSTRA,961),'') = '' THEN CONVERT(VARCHAR,HF.DTSITAMOSTRA,103) ELSE
                    ((CASE WHEN DAY(dbo.infos(A.CDAMOSTRA,961)) < 10 THEN '0' + CAST(DAY(dbo.infos(A.CDAMOSTRA,961)) AS VARCHAR(10)) ELSE CAST(DAY(dbo.infos(A.CDAMOSTRA,961)) AS VARCHAR(10)) END) + '/' +
                    (CASE WHEN MONTH(dbo.infos(A.CDAMOSTRA,961)) < 10 THEN '0' + CAST(MONTH(dbo.infos(A.CDAMOSTRA,961)) AS VARCHAR(10)) ELSE CAST(MONTH(dbo.infos(A.CDAMOSTRA,961)) AS VARCHAR(10)) END) + '/' +
                    CAST(YEAR(dbo.infos(A.CDAMOSTRA,961)) AS VARCHAR(10)))
                    END AS fecha_termino_analisis, -- Fecha de Término Análisis
                    CASE WHEN (ES.FLPESSOAFISICA = 'S' AND ISNULL(ES.CNPJCPF, '') = '') THEN ES.NMUDEMPRESA03 ELSE ES.CNPJCPF END AS numero_identificador, -- Número Identificador (RUC)
                    case when A.cdmatriz in (8) then LEFT(dbo.fechahora(A.DTCOLETA),10)
                    else dbo.fechahora(A.DTCOLETA) end AS fecha_muestreo, -- Fecha de muestreo

                    case when dbo.infos(A.CDAMOSTRA,678) is null then 'N/A' else dbo.infos(A.CDAMOSTRA,678) end AS codigo_muestra_cliente, -- Código Muestra Cliente
                    case when dbo.infos(A.CDAMOSTRA,679) is null then 'N/A' else dbo.infos(A.CDAMOSTRA,679) end AS variedad, -- Variedad
                    case when dbo.infos(A.CDAMOSTRA,680) is null then 'N/A' else dbo.infos(A.CDAMOSTRA,680) end AS muestreador_persona, -- Muestreador (persona)
                    CASE WHEN ISNULL(UD.NMUDAMOSTRA08,'') != '' THEN NMUDAMOSTRA08 ELSE dbo.infos(A.CDAMOSTRA,615) END AS lugar_muestreo_detail,
                    case when dbo.infos(A.CDAMOSTRA,681) is null then 'N/A' else dbo.infos(A.CDAMOSTRA,681) end AS nombre_productor, -- Nombre Productor
                    case when dbo.infos(A.CDAMOSTRA,682) is null then 'N/A' else dbo.infos(A.CDAMOSTRA,682) end AS codigo_productor, -- Código de Productor
                    case when dbo.infos(A.CDAMOSTRA,773) is null then 'N/A' else dbo.infos(A.CDAMOSTRA,773) end AS predio, -- Predio
                    case when dbo.infos(A.CDAMOSTRA,684) is null then 'N/A' else dbo.infos(A.CDAMOSTRA,684) end AS n_registro_agricola, -- N° Registro Agricola
                    CASE WHEN ISNULL(dbo.infos(A.CDAMOSTRA,685),'') = '' THEN (SELECT OBS FROM OBSAMOSTRA WHERE CDAMOSTRA = A.CDAMOSTRA AND CDTIPOOBSERVACAO = 145 AND FLATIVO = 'S') ELSE dbo.infos(A.CDAMOSTRA,685) END AS informacion_adicional, -- Información Adicional
                    a.CDAMOSTRA

                FROM AMOSTRA A
                LEFT JOIN AMOSTRASGRPAMOSTRA AGA ON AGA.CDAMOSTRA = A.CDAMOSTRA
                LEFT JOIN GRPAMOSTRA G ON G.CDGRPAMOSTRA = AGA.CDGRPAMOSTRA
                LEFT JOIN OBSGRPAMOSTRA OG ON OG.CDGRPAMOSTRA = G.CDGRPAMOSTRA AND OG.CDTIPOOBSERVACAO = 108 AND OG.FLATIVO = 'S'
                LEFT JOIN OBSGRPAMOSTRA OGP ON OGP.CDGRPAMOSTRA = G.CDGRPAMOSTRA AND OGP.CDTIPOOBSERVACAO = 146 AND OGP.FLATIVO = 'S'
                INNER JOIN TIPOAMOSTRA T ON T.CDTIPOAMOSTRA = A.CDTIPOAMOSTRA
                INNER JOIN AMOSTRASITENSPRO AIP ON AIP.CDAMOSTRA = A.CDAMOSTRA
                INNER JOIN PROCESSO P ON P.CDPROCESSO = AIP.CDPROCESSO
                LEFT JOIN OBSPROCESSO OP ON OP.CDPROCESSO = P.CDPROCESSO AND OP.CDTIPOOBSERVACAO = 108 AND OP.FLATIVO = 'S'
                LEFT JOIN OBSPROCESSO OPR ON OPR.CDPROCESSO = P.CDPROCESSO AND OPR.CDTIPOOBSERVACAO = 146 AND OPR.FLATIVO = 'S'
                INNER JOIN EMPRESA ES ON ES.CDEMPRESA = A.CDEMPRESASOL
                LEFT JOIN CONTATOSEMP CE ON CE.CDCONTATO = A.CDCONTATOSOL
                LEFT JOIN ENDERECOSEMP ENE ON ENE.CDEMPRESA = ES.CDEMPRESA AND ENE.CDTIPOENDERECO = 1
                LEFT JOIN ENDERECO EN ON EN.CDENDERECO = ENE.CDENDERECO
                LEFT JOIN LOGRADOURO LN ON LN.CDLOGRADOURO = EN.CDLOGRADOURO
                LEFT JOIN CIDADE CD ON CD.CDCIDADE = EN.CDCIDADE
                LEFT JOIN ESTADO ED ON ED.CDESTADO = EN.CDESTADO
                LEFT JOIN onlinedata.dbo.ESTADO ESM ON ESM.CDESTADO = EN.CDESTADO
                LEFT JOIN ENDERECOSEMP ENC ON ENC.CDEMPRESA = ES.CDEMPRESA AND ENC.CDTIPOENDERECO = 2
                LEFT JOIN ENDERECO EC ON EC.CDENDERECO = ENC.CDENDERECO
                LEFT JOIN LOGRADOURO LC ON LC.CDLOGRADOURO = EC.CDLOGRADOURO
                LEFT JOIN CIDADE CC ON CC.CDCIDADE = EC.CDCIDADE
                LEFT JOIN ESTADO ET ON ET.CDESTADO = EC.CDESTADO
                LEFT JOIN LIMITE L ON L.CDLIMITE = A.CDLIMITE
                LEFT JOIN HISTSITAMOSTRA H ON H.CDAMOSTRA = A.CDAMOSTRA AND H.CDSITAMOSTRA = 4
                LEFT JOIN (SELECT CDAMOSTRA, DTSITAMOSTRA = MAX(DTSITAMOSTRA) FROM HISTSITAMOSTRA WHERE CDSITAMOSTRA = 3 GROUP BY CDAMOSTRA) HF ON HF.CDAMOSTRA = A.CDAMOSTRA
                LEFT JOIN USUARIO U ON U.CDUSUARIO = H.CDUSUARIO
                INNER JOIN UDSAMOSTRA UD ON UD.CDAMOSTRA = A.CDAMOSTRA
                INNER JOIN EMPRESA CONT ON CONT.CDEMPRESA = A.CDEMPRESACON
                WHERE
                    A.FLATIVO = 'S'
                    AND A.CDCLASSEAMOSTRA = 1
                    AND P.VERPROCESSO = (SELECT MAX(VERPROCESSO) FROM PROCESSO WHERE IDAUXPROCESSO = P.IDAUXPROCESSO)
                    AND a.CDAMOSTRA = ?
            ", [$cdamostra]);

            // --- CORRECCIÓN AQUÍ: Convertir el objeto stdClass a array ---
            return collect($results)->map(function ($item) {
                return (array) $item; // Convertir cada objeto stdClass a array
            })->first(); // Y luego tomar el primer elemento
        } catch (Exception $e) {
            Log::error("MyLimsService: Error en getSampleExtendedDetailsForDisplay para cdamostra {$cdamostra}: " . $e->getMessage(), ['exception' => $e]);
            throw new Exception("Error al obtener detalles extendidos de la muestra: " . $e->getMessage());
        }
    }
}
