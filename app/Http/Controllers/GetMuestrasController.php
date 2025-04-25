<?php
// app/Http/Controllers/GetMuestrasController.php
namespace App\Http\Controllers;

use App\Services\MyLimsService;
use Illuminate\Http\Request;
use Inertia\Inertia; // Si usas Inertia para la vista que hace la llamada
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth; // <--- Importa la fachada Auth
use Illuminate\Http\JsonResponse; // Para el tipo de retorno JSON

class GetMuestrasController extends Controller
{
    protected $myLimsService;

    public function __construct(MyLimsService $myLimsService)
    {
        $this->myLimsService = $myLimsService;
    }

    /**
     * Renderiza Dashboard con TODOS los registros cargados de una vez.
     */
    public function index(Request $request)
    {
        $email = Auth::user()->email;
        try {
            $registros = $this->myLimsService->obtenerTodosRegistros($email);
            // \Log::info('Registros obtenidos del servicio:', ['cantidad' => count($registros ?? [])]);

            return Inertia::render('Dashboard', [
                'registros' => $registros
            ]);
        } catch (\Exception $e) {
            Log::error('Error al cargar muestras desde MyLimsService (index): ' . $e->getMessage(), ['exception' => $e]);

            return back()->withErrors([
                'error' => 'Error al cargar muestras: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Procesa la solicitud para extraer laudos de muestras seleccionadas.
     * Recibe un array de IDs de muestra desde el frontend.
     *
     * @param Request $request Debe contener 'selected_ids' como un array.
     * @return JsonResponse
     */
    public function extraerLaudos(Request $request): JsonResponse
    {
        // Validar la entrada: esperamos un array de IDs.
        $request->validate([
            'selected_ids' => 'required|array',
            'selected_ids.*' => 'required|string|distinct', // Ajusta el tipo ('string', 'integer') si es necesario
        ]);

        $selectedIds = $request->input('selected_ids');

        $email = Auth::user()->email; // Obtener el correo del usuario autenticado


        Log::info('Solicitud de extraerLaudos recibida:', [
            'email' => $email,
            'selected_ids_count' => count($selectedIds)
        ]);

        try {

            // Llamar al nuevo método del servicio
            $laudosData = $this->myLimsService->extraerLaudos( $selectedIds);

            // Devolver los datos de los laudos como respuesta JSON
            return response()->json([
                'success' => true,
                'message' => 'Laudos extraídos exitosamente.',
                'data' => $laudosData
            ]);
        } catch (\InvalidArgumentException $e) {
            // Error de validación de parámetros internos del servicio
            Log::warning('Solicitud extraerLaudos con parámetros inválidos:', [
                'message' => $e->getMessage(),
                'email' => $email,
                'selected_ids' => $selectedIds
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400); // Bad Request

        } catch (\Exception $e) {
            // Capturar cualquier otro error del servicio o base de datos
            Log::error('Error en GetMuestrasController::extraerLaudos:', [
                'message' => $e->getMessage(),
                'email' => $email,
                'selected_ids' => $selectedIds,
                'exception' => $e
            ]);

            // Devolver una respuesta de error JSON
            return response()->json([
                'success' => false,
                'message' => 'Error al extraer laudos: ' . $e->getMessage()
            ], 500); // Internal Server Error
        }
    }
}
