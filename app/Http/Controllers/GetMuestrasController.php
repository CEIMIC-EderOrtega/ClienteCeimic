<?php
// app/Http/Controllers/GetMuestrasController.php
namespace App\Http\Controllers;

use App\Services\MyLimsService;
use Illuminate\Http\Request;
use Inertia\Inertia; // Si usas Inertia para la vista que hace la llamada
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth; // <--- Importa la fachada Auth
use Illuminate\Http\JsonResponse; // Para el tipo de retorno JSON
use Exception; // Importar Exception

class GetMuestrasController extends Controller
{
    protected $myLimsService;

    public function __construct(MyLimsService $myLimsService)
    {
        $this->myLimsService = $myLimsService;
    }

  /**
     * Renderiza Dashboard con registros basados en la unidad y filtros.
     */
   /**
     * Renderiza Dashboard con registros basados en la unidad y filtros.
     * Maneja peticiones GET (carga inicial) y POST (filtrado).
     */
    public function index(Request $request)
    {
        $email = Auth::user()->email;

        // *** CAMBIO AQUÍ: Obtener filtros y unidad según el método ***
        if ($request->isMethod('post')) {
            // Para peticiones POST (filtrado desde Vue), tomar datos del body
            $unit = $request->input('unit', 'Enviro');
            $filters = $request->except('unit'); // Tomar todo excepto 'unit' como filtros
        } else {
            // Para peticiones GET (carga inicial), tomar datos del query string
            $unit = $request->query('unit', 'Enviro');
            $filters = $request->query();
            unset($filters['unit']); // Quitar unit si viene en query string
        }

        Log::info("Cargando dashboard", [
            'method' => $request->method(),
            'email' => $email,
            'unit' => $unit,
            'filters' => $filters
        ]);

        try {
            if ($unit === 'Food') {
                $registros = $this->myLimsService->obtenerRegistrosFoodFiltrados($email, $filters);
                Log::info("Registros Food obtenidos:", ['count' => count($registros)]);
            } else { // 'Enviro'
                 // Asegúrate que obtenerRegistrosEnviro acepte filtros si es necesario
                 // o modifícalo para aceptar $filters
                 // $registros = $this->myLimsService->obtenerRegistrosEnviro($email, $filters); // Ejemplo si aceptara filtros
                 $registros = $this->myLimsService->obtenerRegistrosEnviro($email); // Manteniendo versión original por ahora
                 Log::info("Registros Enviro obtenidos:", ['count' => count($registros)]);
            }

            // Pasar los registros Y los filtros actuales a la vista
            // Los filtros pasados a Inertia::render determinarán el estado inicial de MuestrasFilters
            return Inertia::render('Dashboard', [
                'registros' => $registros,
                // Pasamos los filtros que se usaron (sean de query o de post)
                'filters' => $filters + ['unit' => $unit], // Añadimos 'unit' de vuelta para la prop `initialFilters`
                'selectedUnit' => $unit, // Pasar la unidad explícitamente
                'error' => null // Asegurarse de pasar null si no hay error
            ]);

        } catch (Exception $e) {
             Log::error("Error al cargar muestras en GetMuestrasController ({$unit}): " . $e->getMessage(), ['exception' => $e]);
             return Inertia::render('Dashboard', [
                 'registros' => [],
                 'filters' => $filters + ['unit' => $unit], // Pasar filtros aunque haya error
                 'selectedUnit' => $unit,
                 'error' => 'Error al cargar muestras. Intente de nuevo o contacte soporte.' // Mensaje genérico
                 // 'error' => 'Error al cargar muestras: ' . $e->getMessage() // Opcional: Mensaje detallado (cuidado con exponer info sensible)
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
