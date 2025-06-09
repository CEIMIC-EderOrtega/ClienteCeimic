<?php

namespace App\Http\Controllers;

use App\Services\MyLimsService; // Importamos MyLimsService
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Exception;

class GetSampleDetailsController extends Controller
{
    protected $myLimsService;

    public function __construct(MyLimsService $myLimsService)
    {
        $this->myLimsService = $myLimsService;
    }

    /**
     * Obtiene solo los detalles extendidos de una muestra para los paneles de la UI.
     * NO afecta la lógica de resultados de análisis.
     *
     * @param Request $request Debe contener 'cdamostra'.
     * @return JsonResponse
     */
    public function getExtendedDetails(Request $request): JsonResponse
    {
        $request->validate([
            'cdamostra' => 'required|string',
        ]);

        $cdamostra = $request->input('cdamostra');
        Log::info("GetSampleDetailsController: Solicitud de detalles extendidos para cdamostra: " . $cdamostra);

        try {
            $extendedDetails = $this->myLimsService->getSampleExtendedDetailsForDisplay($cdamostra);

            if ($extendedDetails) {
                Log::info("GetSampleDetailsController: Detalles extendidos encontrados para {$cdamostra}.");
                return response()->json([
                    'success' => true,
                    'message' => 'Detalles extendidos obtenidos exitosamente.',
                    'extended_details' => $extendedDetails,
                ]);
            } else {
                Log::info("GetSampleDetailsController: No se encontraron detalles extendidos para {$cdamostra}.");
                return response()->json([
                    'success' => true,
                    'message' => 'No se encontraron detalles extendidos para esta muestra.',
                    'extended_details' => null, // Devuelve null si no hay detalles
                ]);
            }
        } catch (Exception $e) {
            Log::error("GetSampleDetailsController: Error al obtener detalles extendidos para {$cdamostra}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar detalles extendidos: ' . $e->getMessage(),
            ], 500);
        }
    }
}
