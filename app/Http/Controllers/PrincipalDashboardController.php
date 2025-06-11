<?php

namespace App\Http\Controllers;

use App\Services\MyLimsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Response; // Importa la clase Response
use Illuminate\Http\JsonResponse; // Importa la clase JsonResponse

class PrincipalDashboardController extends Controller
{
    protected $myLimsService;

    public function __construct(MyLimsService $myLimsService)
    {
        $this->myLimsService = $myLimsService;
    }

    /**
     * Muestra el "cascarón" o la estructura inicial del dashboard.
     * Es muy rápido porque no carga datos pesados.
     */
    public function index(): Response
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        // Devuelve la vista con datos iniciales mínimos
        return Inertia::render('Admin/Principal/Dashboard', [
            'initialMasterData' => [], // Se envía un array vacío inicialmente
            'currentFilters' => [
                'desde' => Carbon::now()->subDays(15)->format('Y-m-d'),
                'hasta' => Carbon::now()->format('Y-m-d')
            ],
            'isAdmin' => $user ? $user->hasRole('Administrador') : false
        ]);
    }

    /**
     * Obtiene los datos del dashboard de forma asíncrona.
     * Esta es la petición que hará el componente de Vue una vez montado.
     */
    public function fetchData(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            /** @var \App\Models\User $user */
            $isAdmin = $user->hasRole('Administrador');

            $filters = $request->validate([
                'desde' => 'required|date_format:Y-m-d',
                'hasta' => 'required|date_format:Y-m-d',
            ]);
            $desde = Carbon::parse($filters['desde'])->startOfDay();
            $hasta = Carbon::parse($filters['hasta'])->endOfDay();

            $registros = [];

            if ($isAdmin) {
                $registros = $this->myLimsService->getDashboardDataForAdmin($desde, $hasta);
            } else {
                $procesosIniciales = $this->myLimsService->checkProcesosUserEmpresa($user->email);
                $listaDeIds = collect($procesosIniciales)->pluck('CDPROCESSO')->all();
                $procesosActivosString = $this->myLimsService->ValidarProcesosActivosMax($listaDeIds);

                if (!empty($procesosActivosString)) {
                    $registros = $this->myLimsService->getDashboardData($procesosActivosString, $desde, $hasta);
                }
            }

            return response()->json(['masterData' => $registros]);

        } catch (\Exception $e) {
            Log::error('Error en PrincipalDashboardController::fetchData: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['error' => 'No se pudo cargar la información del dashboard.'], 500);
        }
    }
}