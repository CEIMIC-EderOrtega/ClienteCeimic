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

    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            /** @var \App\Models\User $user */
            if (!$user) {
                return redirect()->route('login');
            }

            $isAdmin = $user->hasRole('Administrador');

            // Validar y establecer el rango de fechas
            $filters = $request->validate([
                'desde' => 'nullable|date_format:Y-m-d',
                'hasta' => 'nullable|date_format:Y-m-d',
            ]);
            
            // ====================================================================
            // === CAMBIO CLAVE: El rango por defecto ahora es de 15 días atrás ===
            // ====================================================================
            $desde = Carbon::parse($filters['desde'] ?? Carbon::now()->subDays(15))->startOfDay();
            $hasta = Carbon::parse($filters['hasta'] ?? Carbon::now())->endOfDay();
            
            $registros = [];

            if ($isAdmin) {
                Log::info('Usuario es Administrador. Obteniendo datos de dashboard para admin.');
                $registros = $this->myLimsService->getDashboardDataForAdmin($desde, $hasta);
            } else {
                Log::info('Usuario no es Administrador. Validando procesos.');
                $procesosIniciales = $this->myLimsService->checkProcesosUserEmpresa($user->email);
                $listaDeIds = collect($procesosIniciales)->pluck('CDPROCESSO')->all();
                $procesosActivosString = $this->myLimsService->ValidarProcesosActivosMax($listaDeIds);

                if (!empty($procesosActivosString)) {
                    $registros = $this->myLimsService->getDashboardData($procesosActivosString, $desde, $hasta);
                }
            }

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

            $user = Auth::user();
            /** @var \App\Models\User|null $user */
            $isAdmin = $user ? $user->hasRole('Administrador') : false;

            return Inertia::render('Admin/Principal/Dashboard', [
                'masterData' => [],
                'currentFilters' => $request->all(),
                'isAdmin' => $isAdmin,
                'error' => 'No se pudo cargar la información del dashboard: ' . $e->getMessage()
            ]);
        }
    }
}