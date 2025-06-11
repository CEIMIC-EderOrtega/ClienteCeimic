<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\MyLimsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class PrincipalDashboardController extends Controller
{
    protected $myLimsService;

    public function __construct(MyLimsService $myLimsService)
    {
        $this->myLimsService = $myLimsService;
    }

    // En app/Http/Controllers/PrincipalDashboardController.php

    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            $email = $user->email; // Asumo que necesitas el email

            // ================== SOLUCIÓN AQUÍ ==================
            // Este comentario le dice al editor que $user es de tipo App\Models\User
            // y así encontrará el método hasRole() y el error desaparecerá.
            /** @var \App\Models\User $user */
            // ================================================

            if (!$user) {
                return redirect()->route('login');
            }

            // Ahora el editor ya no marcará error en la siguiente línea
            $isAdmin = $user->hasRole('Administrador');

            // 1. Validar permisos de procesos
            $procesosIniciales = $this->myLimsService->checkProcesosUserEmpresa($email);
            $listaDeIds = collect($procesosIniciales)->pluck('CDPROCESSO')->all();
            $procesosActivosString = $this->myLimsService->ValidarProcesosActivosMax($listaDeIds);

            if (empty($procesosActivosString)) {
                return Inertia::render('Admin/Principal/Dashboard', [
                    'masterData' => [],
                    'currentFilters' => [
                        'desde' => Carbon::now()->subMonth()->format('Y-m-d'),
                        'hasta' => Carbon::now()->format('Y-m-d')
                    ],
                    'isAdmin' => $isAdmin
                ]);
            }

            // 2. Validar y establecer el rango de fechas
            $filters = $request->validate([
                'desde' => 'nullable|date_format:Y-m-d',
                'hasta' => 'nullable|date_format:Y-m-d',
            ]);

            $desde = Carbon::parse($filters['desde'] ?? Carbon::now()->subMonth())->startOfDay();
            $hasta = Carbon::parse($filters['hasta'] ?? Carbon::now())->endOfDay();

            // 3. Obtener los datos usando el nuevo método del servicio
            $registros = $this->myLimsService->getDashboardData($procesosActivosString, $desde, $hasta);

            // 4. Renderizar la vista, pasando los datos crudos
            return Inertia::render('Admin/Principal/Dashboard', [
                'masterData' => $registros,
                'currentFilters' => [
                    'desde' => $desde->format('Y-m-d'),
                    'hasta' => $hasta->format('Y-m-d')
                ],
                'isAdmin' => $isAdmin
            ]);
        } catch (\Exception $e) {
            Log::error('Error en PrincipalDashboardController: ' . $e->getMessage(), ['exception' => $e]);
            return back()->with('error', 'No se pudo cargar la información del dashboard.');
        }
    }
}
