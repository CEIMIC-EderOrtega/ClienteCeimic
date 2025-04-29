<?php

namespace App\Http\Controllers;

use App\Services\getResultadosService; // Importamos el servicio mejorado


use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse; // Para retornar respuestas JSON
use Illuminate\Support\Facades\Log; // Usar Log


use InvalidArgumentException; // Importar la excepción específica

class GetResultsAmostrasController extends Controller
{
    protected $resultadosService; // Propiedad para inyectar el servicio

    // Inyección del servicio en el constructor
    public function __construct(getResultadosService  $resultadosService)
    {
        $this->resultadosService = $resultadosService;
    }

    /**
     * Procesa la solicitud para obtener los resultados de una muestra específica.
     * Recibe el código de muestra (cdamostra) desde el frontend.
     *
     * @param Request $request Debe contener 'cdamostra'.
     * @return JsonResponse
     */
    public function getResults(Request $request): JsonResponse
    {
        // Validar la entrada: esperamos el cdamostra.
        $request->validate([
            'cdamostra' => 'required|string',
        ]);

        $cdamostra = $request->input('cdamostra');


        Log::info("Solicitud de getResults recibida para muestra: {$cdamostra}");

        try {
            // Llamar al método del servicio
            // El servicio ya maneja la lógica de la consulta y el manejo de 'empty'
            $serviceResponse = $this->resultadosService->obtenerResultados($cdamostra);

            // Procesar la respuesta del servicio y construir la respuesta JSON para el frontend
            if ($serviceResponse['status'] === 'ok') {
                Log::info("getResults exitoso para muestra {$cdamostra}.");
                return response()->json([
                    'success' => true,
                    'message' => 'Resultados obtenidos exitosamente.',
                    'data' => $serviceResponse['results']
                ]); // Código 200 OK

            } elseif ($serviceResponse['status'] === 'empty') {
                Log::info("No se encontraron resultados en el servicio para muestra: {$cdamostra}.");
                return response()->json([
                    'success' => true, // Es un éxito porque la operación se completó, pero no hay datos
                    'message' => $serviceResponse['message'] ?? 'No se encontraron resultados para la muestra seleccionada.',
                    'data' => [] // Devolver un array vacío para 'data'
                ]); // Código 200 OK

            } else { // $serviceResponse['status'] === 'error'
                // Capturar errores internos del servicio que no son InvalidArgumentException
                Log::error("Error reportado por el servicio para muestra {$cdamostra}: " . ($serviceResponse['message'] ?? 'Error desconocido del servicio.'));
                return response()->json([
                    'success' => false,
                    'message' => $serviceResponse['message'] ?? 'Error interno al obtener resultados de la muestra.',
                    // Considera NO enviar 'error_details' en producción
                    // 'error_details' => $serviceResponse['error_details'] ?? null
                ], 500); // Código 500 Internal Server Error
            }
        } catch (InvalidArgumentException $e) {
            // Capturar la excepción si el servicio considera el argumento inválido (aunque la validación previa lo cubra)
            Log::warning("InvalidArgumentException al llamar al servicio para muestra {$cdamostra}: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() // El mensaje de la excepción es descriptivo
            ], 400); // Código 400 Bad Request

        } catch (\Exception $e) {
            // Capturar cualquier otra excepción inesperada durante la ejecución del servicio
            Log::error("Excepción inesperada en GetResultsAmostrasController::getResults para muestra {$cdamostra}: " . $e->getMessage(), [
                'exception' => $e,
                'cdamostra' => $cdamostra
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error inesperado al procesar la solicitud.'
                // 'error_details' => $e->getMessage() // Opcional para debug
            ], 500); // Código 500 Internal Server Error
        }
    }
}
