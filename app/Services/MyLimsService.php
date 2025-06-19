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
use Illuminate\Support\Facades\Config; // <-- Importa la fachada Config
use Illuminate\Support\Facades\Http; // <-- ¡IMPORTANTE! Añade esta línea al principio del archivo.

class MyLimsService
{

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
    // En app/Services/MyLimsService.php

    public function FilterNewFood(string $email, array $filters): array
    {
        // ==============================================================================
        // === PASO 1: LÓGICA DE VALIDACIÓN DE PROCESOS (Como la pediste) ===
        // ==============================================================================

        // 1. Llamamos a la función que busca los procesos del usuario
        $procesosIniciales = $this->checkProcesosUserEmpresa($email);

        // 2. Transformamos el resultado a un array plano de IDs.
        $listaDeIds = collect($procesosIniciales)->pluck('CDPROCESSO')->all();

        // 3. Validamos cuáles de esos procesos están realmente activos
        $procesosActivosString = $this->ValidarProcesosActivosMax($listaDeIds);

        // ==============================================================================
        // === PASO 2: VERIFICACIÓN Y MANEJO DE ERROR (Lógica de negocio) ===
        // ==============================================================================

        // Si la lista de procesos activos está vacía, detenemos todo y lanzamos un error.
        if (empty($procesosActivosString)) {
            throw new Exception("No hay procesos asignados a su perfil, contactarse con su asesor comercial.");
        }

        // NOTA: He eliminado el dd() para que la función pueda continuar y no se detenga aquí.

        // ==============================================================================
        // === PASO 3: LÓGICA ORIGINAL DE FILTROS (Se mantiene igual) ===
        // ==============================================================================

        $status_id_string = Arr::get($filters, 'status', '4');

        $valid_status_ids = ['2', '10', '3', '4'];
        if (!in_array($status_id_string, $valid_status_ids)) {
            $status_id_string = '4'; // Si no es válido, usar '4' (Publicado) como defecto seguro.
        }

        $status_param_for_sp = (int) $status_id_string;

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

        Log::debug('Ejecutando CLink_obtenerRegistrosFoodFiltrados1 con parámetros:', [
            'Sit_INT_param' => $status_param_for_sp,
            'Solicitante' => $solicitante,
            'Grupo' => $grupo,
            'Tipo' => $tipo,
            'Cdamostra' => $cdamostra,
            'Idamostra' => $idamostra,
            'Processo' => $processo,
            'Numero' => $numero,
            'Desde' => $desde->toDateTimeString(),
            'Hasta' => $hasta->toDateTimeString(),
            'ProcesosCSV' => $procesosActivosString // Logueamos el nuevo parámetro
        ]);

        try {
            // ==============================================================================
            // === PASO 4: LLAMADA AL SP (MODIFICADA PARA AÑADIR EL PARÁMETRO) ===
            // ==============================================================================

            $results = DB::connection('mylims')->select(
                // 4.1: Añadimos @ProcesosCSV = ? al final de la llamada
                'EXEC CLink_obtenerRegistrosFoodFiltrados1 @Sit = ?,@Solicitante=?,@Grupo=?,@Tipo=?,@Cdamostra=?,@Idamostra=?,@Processo=?,@Numero=?,@Desde=?,@Hasta=?, @ProcesosCSV = ?',
                [
                    // 4.2: Añadimos la variable $procesosActivosString al final del array de parámetros
                    $status_param_for_sp,
                    $solicitante,
                    $grupo,
                    $tipo,
                    $cdamostra,
                    $idamostra,
                    $processo,
                    $numero,
                    $desde,
                    $hasta,
                    $procesosActivosString // <--- NUEVO PARÁMETRO
                ]
            );

            // El resto de la función se mantiene igual...
            if (isset($results[0]) && property_exists($results[0], 'GeneratedQueryForDebug')) {
                $generatedSql = $results[0]->GeneratedQueryForDebug;
                Log::debug('SQL Generado por el SP: ' . $generatedSql);
            }

            $mrlReportEnabled = Config::get('features.mrl_report_enabled');

            return collect($results)->map(function ($item) use ($mrlReportEnabled) {
                $itemArray = (array) $item;
                $itemArray['mrl_report_enabled_global'] = $mrlReportEnabled;
                return $itemArray;
            })->all();
        } catch (Exception $e) {
            // La captura de errores se mantiene igual
            Log::error("Error en MyLimsService::CLink_obtenerRegistrosFoodFiltrados1", [
                'input_filters' => $filters,
                'exception_message' => $e->getMessage()
            ]);
            throw new Exception("Error al obtener registros Food filtrados: " . $e->getMessage());
        }
    }
    public function FilterNewFoodAdmin(array $filters): array
    {


        $status_id_string = Arr::get($filters, 'status', '4');

      // ==============================================================================
        // === INICIO DEL CAMBIO: Aceptar los nuevos códigos de estado ===
        // ==============================================================================

        // Se añaden los nuevos códigos a la lista de estados permitidos.
        $valid_status_ids = ['2', '3', '4', '111', '133', '222', '444'];
        if (!in_array($status_id_string, $valid_status_ids)) {
            $status_id_string = '4'; // Si no es válido, usar '4' (Publicado) como defecto seguro.
        }

        // ==============================================================================
        // === FIN DEL CAMBIO ===
        // ==============================================================================


        $status_param_for_sp = (int) $status_id_string;

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

        Log::debug('Ejecutando CLink_obtenerRegistrosFoodFiltrados_Admin con parámetros:', [
            'Sit_INT_param' => $status_param_for_sp,
            'Solicitante' => $solicitante,
            'Grupo' => $grupo,
            'Tipo' => $tipo,
            'Cdamostra' => $cdamostra,
            'Idamostra' => $idamostra,
            'Processo' => $processo,
            'Numero' => $numero,
            'Desde' => $desde->toDateTimeString(),
            'Hasta' => $hasta->toDateTimeString()
        ]);

        try {
            // ==============================================================================
            // === PASO 4: LLAMADA AL SP (MODIFICADA PARA AÑADIR EL PARÁMETRO) ===
            // ==============================================================================

            $results = DB::connection('mylims')->select(
                // 4.1: Añadimos @ProcesosCSV = ? al final de la llamada
                'EXEC CLink_obtenerRegistrosFoodFiltrados1 @Sit = ?,@Solicitante=?,@Grupo=?,@Tipo=?,@Cdamostra=?,@Idamostra=?,@Processo=?,@Numero=?,@Desde=?,@Hasta=?',
                [
                    // 4.2: Añadimos la variable $procesosActivosString al final del array de parámetros
                    $status_param_for_sp,
                    $solicitante,
                    $grupo,
                    $tipo,
                    $cdamostra,
                    $idamostra,
                    $processo,
                    $numero,
                    $desde,
                    $hasta
                ]
            );

            // El resto de la función se mantiene igual...
            if (isset($results[0]) && property_exists($results[0], 'GeneratedQueryForDebug')) {
                $generatedSql = $results[0]->GeneratedQueryForDebug;
                Log::debug('SQL Generado por el SP: ' . $generatedSql);
            }

            $mrlReportEnabled = Config::get('features.mrl_report_enabled');

            return collect($results)->map(function ($item) use ($mrlReportEnabled) {
                $itemArray = (array) $item;
                $itemArray['mrl_report_enabled_global'] = $mrlReportEnabled;
                return $itemArray;
            })->all();
        } catch (Exception $e) {
            // La captura de errores se mantiene igual
            Log::error("Error en MyLimsService::CLink_obtenerRegistrosFoodFiltrados1", [
                'input_filters' => $filters,
                'exception_message' => $e->getMessage()
            ]);
            throw new Exception("Error al obtener registros Food filtrados: " . $e->getMessage());
        }
    }
    /**
     * Obtiene los datos para el nuevo dashboard principal usando el SP simplificado.
     *
     * @param string $procesosActivosString String con los IDs de procesos activos separados por comas.
     * @param Carbon $desde Fecha de inicio.
     * @param Carbon $hasta Fecha de fin.
     * @return array
     */
    public function getDashboardData(string $procesosActivosString, Carbon $desde, Carbon $hasta): array
    {
        // Verificación de seguridad: si no hay procesos, no hay nada que buscar.
        if (empty($procesosActivosString)) {
            return [];
        }

        try {
            $results = DB::connection('mylims')->select(
                'EXEC dbo.CLink_obtenerRegistrosFoodDashboard @ProcesosCSV = ?, @Desde = ?, @Hasta = ?',
                [$procesosActivosString, $desde, $hasta]
            );

            return collect($results)->map(fn($item) => (array) $item)->all();
        } catch (Exception $e) {
            Log::error("Error en MyLimsService::getDashboardData", [
                'exception_message' => $e->getMessage()
            ]);
            throw new Exception("Error al obtener datos para el dashboard: " . $e->getMessage());
        }
    }
    /**
     * Obtiene los datos para el dashboard principal para un Administrador.
     * Llama a un SP que no requiere validación de procesos.
     *
     * @param Carbon $desde Fecha de inicio.
     * @param Carbon $hasta Fecha de fin.
     * @return array
     */
    public function getDashboardDataForAdmin(Carbon $desde, Carbon $hasta): array
    {
        Log::info('Ejecutando getDashboardDataForAdmin para el rango de fechas.', [
            'desde' => $desde->toDateTimeString(),
            'hasta' => $hasta->toDateTimeString(),
        ]);

        try {
            $results = DB::connection('mylims')->select(
                // Llamada al nuevo SP de Admin
                'EXEC dbo.CLink_obtenerRegistrosFoodDashboard_Admin @Desde = ?, @Hasta = ?',
                [$desde, $hasta]
            );

            return collect($results)->map(fn($item) => (array) $item)->all();
        } catch (Exception $e) {
            Log::error("Error en MyLimsService::getDashboardDataForAdmin", [
                'exception_message' => $e->getMessage()
            ]);
            throw new Exception("Error al obtener datos de admin para el dashboard: " . $e->getMessage());
        }
    }

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

    public function checkCeimicEmailStatus(string $email): array
    {
        $email = strtolower($email);

        if (str_contains($email, 'ceimic')) {
            Log::info("Email con dominio 'ceimic' detectado para registro: " . $email);
            return ['status' => 'ni', 'country_code' => null];
        }

        try {
            // Preparamos el término de búsqueda en PHP para que incluya los comodines
            $searchTerm = "%{$email}%";

            // Usamos el parámetro preparado en la consulta LIKE
            $results = DB::connection('mylims')->select(
                "SELECT distinct
                    E.CDPAIS
                FROM PROCESSO P
                INNER JOIN EMPRESA e ON e.CDEMPRESA = p.CDEMPRESASOL
                INNER JOIN CONTATOSEMP CE ON CE.IDAUXEMPRESA=E.IDAUXEMPRESA
                WHERE
                    P.NMUDPROCESSO02 LIKE ?
                    AND P.NMUDPROCESSO02 IS NOT NULL
                    AND e.FLATIVO = 'S'
                    AND e.VEREMPRESA = (SELECT MAX(EE.VEREMPRESA) FROM EMPRESA EE WHERE EE.IDAUXEMPRESA = e.IDAUXEMPRESA)
                    AND P.VERPROCESSO= (SELECT MAX (PP.VERPROCESSO) FROM PROCESSO PP WHERE PP.NRCONTROLE1=P.NRCONTROLE1 AND PP.NRCONTROLE2=P.NRCONTROLE2)",
                [$searchTerm] // Pasamos la variable ya preparada con los '%'
            );

            if (!empty($results) && is_object($results[0])) {
                $countryCode = trim($results[0]->CDPAIS);
                Log::info("Email encontrado en DB externa (activo, no moroso): " . $email . " País: " . $countryCode);
                return ['status' => 'ok', 'country_code' => $countryCode];
            } else {
                Log::info("Email no encontrado en DB externa (o no activo/moroso): " . $email);
                return ['status' => 'no', 'country_code' => null];
            }
        } catch (\Exception $e) {
            Log::error('Error al consultar DB externa para validación de email: ' . $e->getMessage(), [
                'email' => $email,
                'exception' => $e
            ]);
            return ['status' => 'error', 'message' => 'Error al validar con sistema externo.', 'country_code' => null];
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

    // En app/Services/MyLimsService.php
    public function checkProcesosUserEmpresa(string $email): array
    {
        Log::info("MyLimsService: Verificando procesos para el usuario: " . $email);
        try {
            // CORRECCIÓN 1: Preparar el parámetro de búsqueda para LIKE de forma segura
            $searchTerm = "%{$email}%";

            $results = DB::connection('mylims')->select("
            SELECT DISTINCT
                P.CDPROCESSO
            FROM
                PROCESSO P WITH (NOLOCK)
            INNER JOIN
                EMPRESA e WITH (NOLOCK) ON e.CDEMPRESA = p.CDEMPRESASOL
            INNER JOIN
                CONTATOSEMP CE WITH (NOLOCK) ON CE.IDAUXEMPRESA = E.IDAUXEMPRESA
            WHERE
                P.NMUDPROCESSO02 LIKE ? -- Se usa el parámetro seguro
                AND P.NMUDPROCESSO02 IS NOT NULL
                AND e.FLATIVO = 'S'
                AND e.VEREMPRESA = (
                    SELECT MAX(EE.VEREMPRESA)
                    FROM EMPRESA EE WITH (NOLOCK)
                    WHERE EE.IDAUXEMPRESA = e.IDAUXEMPRESA
                )
                AND P.VERPROCESSO = (
                    SELECT MAX(PP.VERPROCESSO)
                    FROM PROCESSO PP WITH (NOLOCK)
                    WHERE PP.NRCONTROLE1 = P.NRCONTROLE1 AND PP.NRCONTROLE2 = P.NRCONTROLE2
                )
               -- AND CE.EMAIL LIKE ? -- Se usa el parámetro seguro
            -- CORRECCIÓN 2: Se elimina la cláusula ORDER BY que causaba el error
        ", [$searchTerm]); // CORRECCIÓN 3: Se pasan los parámetros de forma segura

            return collect($results)->map(function ($item) {
                return (array) $item;
            })->all();
        } catch (Exception $e) {
            Log::error("MyLimsService: Error al verificar procesos para el usuario {$email}: " . $e->getMessage(), ['exception' => $e]);
            throw new Exception("Error al verificar procesos del usuario: " . $e->getMessage());
        }
    }
    public function checkProcesosUser(string $email): array
    {
        Log::info("MyLimsService: Verificando procesos para el usuario: " . $email);
        try {
            // CORRECCIÓN 1: Preparar el parámetro de búsqueda para LIKE de forma segura
            $searchTerm = "%{$email}%";

            $results = DB::connection('mylims')->select("
            SELECT DISTINCT
                P.CDPROCESSO
            FROM
                PROCESSO P WITH (NOLOCK)
            INNER JOIN
                EMPRESA e WITH (NOLOCK) ON e.CDEMPRESA = p.CDEMPRESASOL
            INNER JOIN
                CONTATOSEMP CE WITH (NOLOCK) ON CE.IDAUXEMPRESA = E.IDAUXEMPRESA
            WHERE
                P.NMUDPROCESSO02 LIKE ? -- Se usa el parámetro seguro
                AND P.NMUDPROCESSO02 IS NOT NULL
               -- AND e.FLATIVO = 'S'
               -- AND e.VEREMPRESA = (
                  --  SELECT MAX(EE.VEREMPRESA)
                   -- FROM EMPRESA EE WITH (NOLOCK)
                  --  WHERE EE.IDAUXEMPRESA = e.IDAUXEMPRESA
                --)
                AND P.VERPROCESSO = (
                    SELECT MAX(PP.VERPROCESSO)
                    FROM PROCESSO PP WITH (NOLOCK)
                    WHERE PP.NRCONTROLE1 = P.NRCONTROLE1 AND PP.NRCONTROLE2 = P.NRCONTROLE2
                )
               -- AND CE.EMAIL LIKE ? -- Se usa el parámetro seguro
            -- CORRECCIÓN 2: Se elimina la cláusula ORDER BY que causaba el error
        ", [$searchTerm]); // CORRECCIÓN 3: Se pasan los parámetros de forma segura

            return collect($results)->map(function ($item) {
                return (array) $item;
            })->all();
        } catch (Exception $e) {
            Log::error("MyLimsService: Error al verificar procesos para el usuario {$email}: " . $e->getMessage(), ['exception' => $e]);
            throw new Exception("Error al verificar procesos del usuario: " . $e->getMessage());
        }
    }
    // En app/Services/MyLimsService.php

    public function ValidarProcesosActivosMax(array $listaProcesos): string
    {
        // Si la lista de procesos está vacía, no hacemos nada y devolvemos un string vacío.
        if (empty($listaProcesos)) {
            Log::info("MyLimsService: No se proporcionaron procesos para validar.");
            return '';
        }

        Log::info("MyLimsService: Validando procesos activos para la lista: " . implode(',', $listaProcesos));

        try {

            $placeholders = implode(',', array_fill(0, count($listaProcesos), '?'));

            // 2. Construimos la consulta usando los placeholders seguros.
            $results = DB::connection('mylims')->select(
                "SELECT P.CDPROCESSO
             FROM PROCESSO P WITH (NOLOCK)
             INNER JOIN HISTSITPROCESSO HSTP WITH (NOLOCK) ON HSTP.CDPROCESSO = P.CDPROCESSO
             WHERE P.CDPROCESSO IN ({$placeholders})
             AND HSTP.CDHISTORICO IN (2) -- Aprobado",
                $listaProcesos // 3. Pasamos el array de IDs de forma segura como segundo argumento.
            );
            return collect($results)->pluck('CDPROCESSO')->implode(',');
        } catch (Exception $e) {
            Log::error("MyLimsService: Error al validar procesos activos: " . $e->getMessage(), ['exception' => $e]);
            throw new Exception("Error al validar procesos activos: " . $e->getMessage());
        }
    }
    public function extraerLaudosEnIngles(array $codigosMuestra): array
    {
        // Configuración de la conexión y rutas
        $baseUrl = 'https://clink.ceimic.com/mylims'; // URL base del sistema LIMS
        $basePath = '\\\\cmccldlims01\\c$\\web\\mylims'; // Ruta de red (UNC)

        $generatedPdfs = [];

        // Hacemos el proceso uno por uno, como solicitaste
        foreach ($codigosMuestra as $cdamostra) {
            $sesionId = 'clink_' . uniqid() . '_' . $cdamostra;

            try {
                // Etapa 1: Registrar muestra en onlinedata.dbo.codigos
                DB::connection('mylims')->transaction(function () use ($cdamostra, $sesionId) {
                    DB::connection('mylims')->table('onlinedata.dbo.codigos')->where('sesion', $sesionId)->delete();
                    DB::connection('mylims')->table('onlinedata.dbo.codigos')->insert([
                        'CDAMOSTRA' => $cdamostra,
                        'SESION' => $sesionId
                    ]);
                });

                // Etapa 2: Llamar a la URL para generar el PDF
                $urlGenera = "{$baseUrl}/gera_per_html.php?sesion={$sesionId}&lng=1&directo=1";
                $nombreArchivo = Http::timeout(180)->get($urlGenera)->body();

                if (empty(trim($nombreArchivo)) || stripos($nombreArchivo, 'Error') !== false) {
                    Log::warning("Fallo al generar informe en inglés para cdamostra {$cdamostra}. Respuesta: {$nombreArchivo}");
                    continue; // Saltar a la siguiente muestra
                }

                // Etapa 3: Leer el PDF desde la ruta de red
                $rutaCompleta = "{$basePath}\\laudos\\" . trim($nombreArchivo);
                if (!file_exists($rutaCompleta)) {
                    $rutaCompleta = "{$basePath}\\Relatorios\\" . trim($nombreArchivo);
                }

                if (file_exists($rutaCompleta)) {
                    $generatedPdfs[] = [
                        'NombreLaudo' => trim($nombreArchivo),
                        'LaudoBinario' => file_get_contents($rutaCompleta)
                    ];
                } else {
                    Log::warning("Informe en inglés generado pero no encontrado en ruta de red: {$rutaCompleta}");
                }
            } catch (\Exception $e) {
                Log::error("Error procesando informe en inglés para cdamostra {$cdamostra}: " . $e->getMessage());
                continue; // Si uno falla, continuamos con el siguiente
            }
        }

        if (empty($generatedPdfs)) {
            return []; // No se generó ningún informe con éxito
        }

        // Si se generó más de un PDF, los comprimimos en un ZIP
        if (count($generatedPdfs) > 1) {
            $zip = new ZipArchive();
            $zipName = 'Informes_Ingles_' . date('Y-m-d_H-i-s') . '.zip';
            $tmpFile = tempnam(sys_get_temp_dir(), 'zip');

            if ($zip->open($tmpFile, ZipArchive::CREATE) === TRUE) {
                foreach ($generatedPdfs as $pdf) {
                    $zip->addFromString($pdf['NombreLaudo'], $pdf['LaudoBinario']);
                }
                $zip->close();

                $zipContent = file_get_contents($tmpFile);
                @unlink($tmpFile);

                return [['NombreLaudo' => $zipName, 'Laudo' => base64_encode($zipContent)]];
            }
        }

        // Si solo hay un PDF, lo devolvemos directamente
        $singlePdf = $generatedPdfs[0];
        return [['NombreLaudo' => $singlePdf['NombreLaudo'], 'Laudo' => base64_encode($singlePdf['LaudoBinario'])]];
    }
}
