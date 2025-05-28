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
    public function index(Request $request)
    {
        $email = Auth::user()->email;

        // FORZAR UNIDAD A 'Food' Y QUITAR LA LÓGICA DE SELECCIÓN DE UNIDAD
        $unit = 'Food'; // Unidad fija
        $filters = $request->isMethod('post') ? $request->except('unit') : $request->query();
        if (isset($filters['unit'])) {
            unset($filters['unit']); // Nos aseguramos que 'unit' de los filtros no interfiera
        }


        Log::info("Cargando dashboard (Unidad Forzada: Food)", [ // Mensaje de log actualizado
            'method' => $request->method(),
            'email' => $email,
            'unit_forced' => $unit, // Indicar que la unidad es forzada
            'filters_received' => $filters // Mostrar los filtros que realmente se usan
        ]);
        //dd($request);
        try {
            // LA LÓGICA AHORA SIEMPRE ES PARA 'Food'
            //dd($filters); // Para depurar los filtros recibidosdd($filters); // Para depurar los filtros recibidos
            $registros = $this->myLimsService->FilterNewFood($email, $filters);
            Log::info("Registros Food obtenidos:", ['count' => count($registros)]);

            /* --- SECCIÓN ENVIRO COMENTADA ---
            if ($unit === 'Food') { // Esta condición ya no es necesaria, se mantiene por si se revierte
                 $registros = $this->myLimsService->obtenerRegistrosFoodFiltrados($email, $filters);
                 Log::info("Registros Food obtenidos:", ['count' => count($registros)]);
            } else { // 'Enviro'
                 // Asegúrate que obtenerRegistrosEnviro acepte filtros si es necesario
                 // o modifícalo para aceptar $filters
                 // $registros = $this->myLimsService->obtenerRegistrosEnviro($email, $filters); // Ejemplo si aceptara filtros
                 $registros = $this->myLimsService->obtenerRegistrosEnviro($email); // Manteniendo versión original por ahora
                 Log::info("Registros Enviro obtenidos:", ['count' => count($registros)]);
            }
            */

            return Inertia::render('Dashboard', [
                'registros' => $registros,
                // Pasamos los filtros que se usaron (sean de query o de post)
                // y añadimos 'unit' de vuelta para la prop `initialFilters` para que Dashboard.vue sepa que es 'Food'
                'filters' => $filters + ['unit' => $unit],
                'selectedUnit' => $unit, // Pasar la unidad explícitamente (siempre 'Food')
                'error' => null
            ]);
        } catch (Exception $e) {
            Log::error("Error al cargar muestras en GetMuestrasController (Forzado Food): " . $e->getMessage(), ['exception' => $e]);
            return Inertia::render('Dashboard', [
                'registros' => [],
                'filters' => $filters + ['unit' => $unit],
                'selectedUnit' => $unit,
                'error' => 'Error al cargar muestras. Intente de nuevo o contacte soporte.'
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
            $laudosData = $this->myLimsService->extraerLaudos($selectedIds);

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
