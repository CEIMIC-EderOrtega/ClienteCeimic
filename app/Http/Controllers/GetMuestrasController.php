<?php
// app/Http/Controllers/GetMuestrasController.php
namespace App\Http\Controllers;

use App\Services\MyLimsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use Exception;
use Illuminate\Support\Facades\Config;

class GetMuestrasController extends Controller
{
    protected $myLimsService;

    public function __construct(MyLimsService $myLimsService)
    {
        $this->myLimsService = $myLimsService;
    }

    public function index(Request $request)
    {
        // 1. Obtenemos el usuario autenticado
        $user = Auth::user();
        /** @var \App\Models\User $user */
        // 2. Verificamos si el usuario es Administrador
        $isAdmin = $user->hasRole('Administrador');

        $email = Auth::user()->email;
        $unit = 'Food'; // Unidad fija
        $filters = $request->except('unit'); // Obtener todos los filtros excepto 'unit'

        $registros = []; // Inicializamos registros como un array vacío por defecto
        $error = null;
        $mrlReportEnabled = Config::get('features.mrl_report_enabled');
        Log::info("Feature Flag MRL: " . ($mrlReportEnabled ? 'Habilitado' : 'Deshabilitado'));
        // --- FIN AÑADIDO ---
        // --- CAMBIO CLAVE AQUÍ: Cargar registros solo si es una petición POST ---
        if ($request->isMethod('post')) {
            try {
                // Aquí usamos los filtros recibidos del POST
                // === 3. LÓGICA CONDICIONAL BASADA EN EL ROL ===
                if ($isAdmin) {
                    // Si es Admin, llamamos a la función sin restricciones de email/proceso
                    Log::info('GetMuestrasController: Usuario es Admin. Usando FilterNewFoodAdmin.');
                    $registros = $this->myLimsService->FilterNewFoodAdmin($filters);
                } else {
                    // Si es un usuario normal (Cliente), usamos la función original que valida sus procesos
                    Log::info('GetMuestrasController: Usuario NO es Admin. Usando FilterNewFood.');
                    $registros = $this->myLimsService->FilterNewFood($user->email, $filters);
                }
                // ===============================================

                Log::info("Registros Food obtenidos por POST:", ['count' => count($registros)]);
            } catch (Exception $e) {
                Log::error("Error al cargar muestras en GetMuestrasController (Forzado Food) en POST request: " . $e->getMessage(), ['exception' => $e]);
                $error = 'Error al cargar muestras. Intente de nuevo o contacte soporte.';
            }
        } else {
            // Si es una petición GET (carga inicial de la página), no cargamos registros
            Log::info("Dashboard cargado inicialmente sin registros (petición GET).");
        }
        // --- FIN CAMBIO CLAVE ---

        // Asegurarse de que los filtros iniciales para el frontend incluyan la unidad
        $initialFilters = $filters + ['unit' => $unit];

        return Inertia::render('Dashboard', [
            'registros' => $registros, // Ahora puede ser vacío en la carga inicial
            'filters' => $initialFilters,
            'selectedUnit' => $unit,
            'error' => $error,
            'mrlReportEnabled' => $mrlReportEnabled,
            'isAdmin' => $isAdmin, // <-- PASAR EL FLAG A LA VISTA
        ]);
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
