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
            $email = Auth::user()->email;

            /** @var \App\Models\User $user */


            if (!$user) {
                return redirect()->route('login');
            }

            $isAdmin = $user->hasRole('Administrador');

            // 1. VALIDAR Y ESTABLECER FILTROS
            $filters = $request->validate([
                'status' => 'nullable|string',
                'desde' => 'nullable|date_format:Y-m-d',
                'hasta' => 'nullable|date_format:Y-m-d',
                'search_solicitante' => 'nullable|string',
                'search_tipo' => 'nullable|string',
            ]);

            $filters['status'] = $filters['status'] ?? '4';
            $filters['desde'] = $filters['desde'] ?? Carbon::now()->subMonth()->toDateString();
            $filters['hasta'] = $filters['hasta'] ?? Carbon::now()->toDateString();

            // 2. LÓGICA DE ROLES PARA EL FILTRO 'SOLICITANTE'
            $solicitanteParaSP = null;
            if ($isAdmin) {
                $solicitanteParaSP = $filters['search_solicitante'] ?? null;
            } else {

                $solicitanteParaSP = $user->company->name ?? null;
                $filters['search_solicitante'] = $solicitanteParaSP;
            }

            // 3. OBTENER DATOS CRUDOS
            $filtersParaSP = [
                'status' => $filters['status'],
                'desde' => $filters['desde'],
                'hasta' => $filters['hasta'],
                'search_solicitante' => $solicitanteParaSP,
                'search_tipo' => $filters['search_tipo'] ?? null,
            ];
            $registros = $this->myLimsService->getRawDataForDashboard($filtersParaSP,$email );
            $collection = collect($registros);

            // 4. GENERAR OPCIONES PARA LOS FILTROS
            $solicitantesOptions = [];
            if ($isAdmin) {
                // La lista de empresas solo se genera para el Admin.
                $solicitantesOptions = $collection->pluck('Solicitante')->filter()->unique()->sort()->values()->all();
            }
            $tiposAmostraOptions = $collection->pluck('Tipo Amostra')->filter()->unique()->sort()->values()->all();

            // 5. PROCESAR DATOS PARA GRÁFICOS
            $barChartData = $collection->groupBy('Situacao')->map(fn($g) => $g->count());
            $pieChartData = $collection->groupBy('Tipo Amostra')->map(fn($g) => $g->count());

            // 6. RENDERIZAR VISTA
            return Inertia::render('Admin/Principal/Dashboard', [
                'chartData' => ['bar' => $barChartData, 'pie' => $pieChartData],
                'filtersOptions' => [
                    'solicitantes' => $solicitantesOptions,
                    'tiposAmostra' => $tiposAmostraOptions,
                ],
                'currentFilters' => $filters,
                'isAdmin' => $isAdmin,
            ]);
        } catch (\Exception $e) {
            Log::error('Error en PrincipalDashboardController: ' . $e->getMessage(), ['exception' => $e]);
            return back()->with('error', 'No se pudo cargar la información del dashboard.');
        }
    }
}
