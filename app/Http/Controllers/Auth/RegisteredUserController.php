<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role; // Importa el modelo Role
// Ya NO necesitamos importar RouteServiceProvider si no lo usamos en la redirección
// use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use App\Services\MyLimsService; // Importa tu servicio
use App\Models\Country; // Importa el modelo Country (usado en create)

// Asegúrate de tener estas importaciones al principio del archivo
use App\Mail\WelcomeUserMail;
use Illuminate\Support\Facades\Mail;

class RegisteredUserController extends Controller
{
    protected $myLimsService;

    public function __construct(MyLimsService $myLimsService)
    {
        $this->myLimsService = $myLimsService;
    }

    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        $countries = Country::all(['id', 'name']);

        return Inertia::render('Auth/Register', [
            'canLogin' => Route::has('login'),
            'countries' => $countries,
        ]);
    }

    /**
     * Validate email status using external system (for frontend).
     * Returns JSON: { status: 'ok'|'no'|'ni'|'error', message?: string, country_code?: string }
     */
    // En RegisteredUserController.php

    /**
     * Valida el email contra el sistema local Y el externo.
     */
    public function validateRegistrationEmail(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|string|lowercase|email|max:255',
        ]);
        $email = $validated['email'];

        try {
            // --- INICIO DE LA CORRECCIÓN ---

            // 1. PRIMERO, verificar si el email ya existe en nuestra base de datos local.
            // Esta es la validación más importante para evitar el error de duplicado.
            $localUserExists = User::where('email', $email)->exists();
            if ($localUserExists) {
                return response()->json([
                    'status' => 'no',
                    'message' => 'Este correo electrónico ya se encuentra registrado en nuestro sistema.'
                ]);
            }

            // 2. SI NO EXISTE LOCALMENTE, entonces validamos contra el sistema externo.
            // (Esta es la lógica que ya tenías y funcionaba bien)
            $procesos = $this->myLimsService->checkProcesosUser($email);
            if (empty($procesos)) {
                return response()->json([
                    'status' => 'no',
                    'message' => 'Este correo electrónico no tiene procesos asociados y no puede registrarse.'
                ]);
            }

            // Si pasa ambas validaciones, entonces el estado es 'ok'.
            $countryData = $this->myLimsService->checkCeimicEmailStatus($email);

            return response()->json([
                'status' => 'ok',
                'message' => 'Correo validado exitosamente.',
                'country_code' => $countryData['country_code'] ?? null
            ]);

            // --- FIN DE LA CORRECCIÓN ---

        } catch (\Exception $e) {
            Log::error('Error en RegisteredUserController::validateRegistrationEmail: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Ocurrió un error de servidor al validar.'], 500);
        }
    }


    /**
     * Maneja el registro final del usuario.
     */
    public function store(Request $request): RedirectResponse
    {
        // La validación del email aquí ya no necesita la re-validación externa,
        // porque ya la hicimos en el paso anterior. Solo necesita la validación de unicidad de Laravel.
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:' . User::class, // Esta regla es CLAVE
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'direccion' => 'nullable|string|max:255',
            'telefono' => 'nullable|string|max:255',
            'country_id' => 'required|exists:countries,id',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'direccion' => $request->direccion,
            'telefono' => $request->telefono,
            'country_id' => $request->country_id,
        ]);

        // --- ASIGNACIÓN DE ROL ---
        $clienteRole = Role::find(2); // Buscamos el rol "Cliente"
        if ($clienteRole) {
            $user->roles()->sync([$clienteRole->id]);
        } else {
            Log::error("Rol 'Cliente' con ID 2 no encontrado al registrar: " . $user->email);
        }

        // --- ENVÍO DE CORREO DE BIENVENIDA ---
        try {
            Mail::to($user->email)->send(new WelcomeUserMail($user));
        } catch (\Exception $e) {
            Log::error('Fallo al enviar correo de bienvenida a: ' . $user->email . ' - Error: ' . $e->getMessage());
        }

        event(new Registered($user));
        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
