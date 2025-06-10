<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
// Exeption está duplicado y mal escrito, se elimina

class getResultadosService
{
    /**
     * Obtiene los resultados de una muestra específica.
     *
     * @param string $cdamostra El código de la muestra.
     * @return array Un array con el estado y los resultados o un mensaje de error.
     * @throws \InvalidArgumentException Si el código de muestra está vacío.
     */
    public function obtenerResultados(string $cdamostra): array
    {

        if (empty($cdamostra)) {
            Log::warning('Intento de obtener resultados con cdamostra vacío.');
            throw new \InvalidArgumentException("No se seleccionó Muestra. El código de muestra está vacío.");
        }


        Log::info("Iniciando consulta para obtener resultados de la muestra: {$cdamostra}");

        try {

            $results = DB::connection('mylims')
                ->select("SELECT DISTINCT
                            dbo.numero(a.cdamostra, 0) AS NUMERO,
                            a.idamostra AS IDAMOSTRA,
                            m.descmetodo AS METODO,
                            VS.NMVS AS PARAMETRO,
                            VSA.VLVS AS RES,
                            VSA.UNIDADE AS UNID,
                            --T.NMTIPOAMOSTRA as MATRIZ
                            case when dbo.infos(A.CDAMOSTRA,677) is null then 'N/A' else dbo.infos(A.CDAMOSTRA,677) end as MATRIZ
                        FROM
                            AMOSTRA A
                            INNER JOIN TIPOAMOSTRA T ON T.CDTIPOAMOSTRA = A.CDTIPOAMOSTRA
                            INNER JOIN VSAMOSTRA VSA ON VSA.CDAMOSTRA = A.CDAMOSTRA
                            INNER JOIN VARSAIDA VS ON VS.CDVS = VSA.CDVS
                            INNER JOIN METODOSAM MA ON MA.CDAMOSTRA = VSA.CDAMOSTRA AND VSA.CDMETODO = MA.CDMETODO
                            INNER JOIN METODOANALISE M ON M.CDMETODO = VSA.CDMETODO
                            INNER JOIN LIMITE L ON L.CDLIMITE = A.CDLIMITE
                            LEFT JOIN VSLIMITE VSL ON VSL.CDLIMITE = L.CDLIMITE AND VSL.CDVS = VSA.CDVS
                        WHERE
                            A.FLATIVO = 'S'
                            AND a.DTPUBLICACAO IS NOT NULL
                            AND a.CDCLASSEAMOSTRA = 1
                            AND vsa.flimprime = 'S'
                            AND a.cdamostra = ?
                        ORDER BY
                            1, 3, 4
                    ", [$cdamostra]);


            if (!empty($results)) {
                Log::info("Consulta exitosa. Se encontraron " . count($results) . " resultados para la muestra: {$cdamostra}");
                // --- CORRECCIÓN CLAVE AQUÍ: Convertir cada objeto stdClass a array ---
                $resultsAsArrays = collect($results)->map(function ($item) {
                    return (array) $item; // Convertir cada objeto a array
                })->all();

                return ['status' => 'ok', 'results' => $resultsAsArrays]; // Devolver el array de arrays
            } else {
                Log::info("No se encontraron resultados para la muestra: {$cdamostra}");
                return ['status' => 'empty', 'message' => 'No existe información de resultados para la muestra seleccionada.'];
            }
        } catch (Exception $e) {
            // Manejo de excepciones y logs detallados
            Log::error("Error al consultar la base de datos 'mylims' para la muestra {$cdamostra}: " . $e->getMessage(), [
                'exception' => $e,
                'muestra' => $cdamostra
            ]);

            // Retorno estandarizado para errores
            return [
                'status' => 'error',
                'message' => 'Ocurrió un error al obtener los resultados de la muestra.',
                'error_details' => $e->getMessage() // Opcional: incluir detalle del error solo en ambientes de desarrollo/debug
            ];
        }
    }
}
