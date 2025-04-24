<?php
// app/Http/Controllers/GetMuestrasController.php
namespace App\Http\Controllers;

use App\Services\MyLimsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth; // <--- Importa la fachada Auth

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
            // console.log('Registros obtenidos:', registros); // ¡No puedes usar console.log aquí! Es PHP.
            // Puedes loggear en el servidor:
            // \Log::info('Registros obtenidos del servicio:', ['cantidad' => count($registros ?? [])]);

            return Inertia::render('Dashboard', [
                'registros' => $registros
            ]);
        } catch (\Exception $e) {
            // Loggea el error aquí
            Log::error('Error al cargar muestras desde MyLimsService: ' . $e->getMessage(), ['exception' => $e]);

            // Considera no usar back() en Inertia si causa problemas.
            // Podrías redirigir a una página de error específica o renderizar el dashboard
            // con un mensaje de error en lugar de redirigir.
            // Por ahora, mantengamos el back() para ver si el log nos dice algo.
            return back()->withErrors([
                'error' => 'Error al cargar muestras: ' . $e->getMessage()
            ]);
        }
    }
}
