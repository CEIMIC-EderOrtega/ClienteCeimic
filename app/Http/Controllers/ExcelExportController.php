<?php

namespace App\Http\Controllers;

use App\Services\ExcelExportService;
use App\Services\getResultadosService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class ExcelExportController extends Controller
{
    protected $excelExportService;
    protected $resultadosService;

    public function __construct(
        ExcelExportService $excelExportService,
        getResultadosService $resultadosService
    ) {
        $this->excelExportService = $excelExportService;
        $this->resultadosService = $resultadosService;
    }

    public function exportSampleDetails(Request $request)
    {
        $request->validate([
            'cdamostra' => 'required|string',
            'detail_data' => 'required|array',
            'extended_detail_data' => 'nullable|array',
        ]);

        $cdamostra = $request->input('cdamostra');

        // --- CORRECCIÓN CLAVE AQUÍ: Castear a array para asegurar consistencia ---
        // Aunque Laravel valida 'array', a veces los arrays anidados o ciertos datos
        // pueden ser stdClass. Castear a array aquí asegura el tipo.
        $detailRecord = (array) $request->input('detail_data');
        $extendedDetailRecord = (array) ($request->input('extended_detail_data') ?? []);

        Log::info("Solicitud de exportación Excel desde backend para cdamostra: " . $cdamostra);

        try {
            $resultsServiceResponse = $this->resultadosService->obtenerResultados($cdamostra);
            $sampleResults = $resultsServiceResponse['results'] ?? [];

            $resultsColumns = [
                ['field' => "NUMERO", 'header' => "Número"],
                ['field' => "MATRIZ", 'header' => "DESCRIPCION DDE LA MUESTRA"],
                ['field' => "METODO", 'header' => "Método"],
                ['field' => "PARAMETRO", 'header' => "Parámetro"],
                ['field' => "RES", 'header' => "Resultado"],
                ['field' => "UNID", 'header' => "Unidad"],
            ];

             $fileData = $this->excelExportService->generateSampleDetailExcel(
                $detailRecord,
                $extendedDetailRecord,
                $sampleResults,
                $resultsColumns
            );

            $filePath = $fileData['path'];
            $desiredFilename = $fileData['name'];

            // 3. Enviar el archivo como descarga al navegador
            // Asegurarse de que el MIME type y Content-Disposition sean correctos
            $headers = [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $desiredFilename . '"', // <-- Usamos el nombre deseado
                'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
                'Expires' => '0',
                'Pragma' => 'public',
            ];

            return response()->download($filePath, $desiredFilename, $headers)->deleteFileAfterSend(true);


        } catch (Exception $e) {
            Log::error('Error en ExcelExportController::exportSampleDetails:', [
                'cdamostra' => $cdamostra,
                'message' => $e->getMessage(),
                'exception' => $e
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al generar el archivo Excel: ' . $e->getMessage(),
            ], 500);
        }
    }
}
