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

    public function validateRegistrationEmail(Request $request)
    {
        try {
            // 1. Validamos que el email venga en la petición
            $validated = $request->validate([
                'email' => 'required|string|lowercase|email|max:255',
            ]);

            $email = $validated['email'];

            // 2. Llamamos a la función que busca los procesos del usuario
            $procesos = $this->myLimsService->checkProcesosUser($email);

            // 3. Para depurar, guardamos el resultado en el log en lugar de usar dd()
            // Puedes ver esto en el archivo: storage/logs/laravel.log
            Log::info('Procesos encontrados para ' . $email, $procesos);

            // 4. Lógica de decisión: ¿Se encontraron procesos?
            if (!empty($procesos)) {
                // ¡Sí se encontraron! La validación es un éxito.
                return response()->json([
                    'status' => 'ok',
                    'message' => 'El usuario tiene procesos activos y puede registrarse.'
                    // Podrías incluso devolver los procesos si el frontend los necesita
                    // 'procesos' => $procesos
                ]);
            } else {
                // No se encontraron procesos para este email. La validación falla.
                return response()->json([
                    'status' => 'no',
                    'message' => 'Este correo no tiene procesos asociados en el sistema.'
                ]);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Si la validación del email falla (ej. no es un email válido)
            throw $e;
        } catch (\Exception $e) {
            // Para cualquier otro error inesperado
            Log::error('Error en RegisteredUserController::validateRegistrationEmail: ' . $e->getMessage(), [
                'email' => $request->email,
                'exception' => $e
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error de servidor al validar el correo.'
            ], 500);
        }
    }
    /*  public function validateRegistrationEmail(Request $request)
    {

        $request->validate([
            'email' => 'required|string|lowercase|email|max:255',
        ]);



         $cddprocesos= $this->myLimsService->checkProcesosUser($request->email);
         dd($cddprocesos);
        try {
            $result = $this->myLimsService->checkCeimicEmailStatus($request->email);

            if ($result['status'] === 'no') {
                $localUserExists = User::where('email', $request->email)->exists();
                if ($localUserExists) {
                    Log::warning("Email no encontrado en DB externa pero sí localmente: " . $request->email);
                    return response()->json(['status' => 'ok', 'message' => 'Este correo ya está registrado en nuestro sistema.']);
                }
            }

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Error en RegisteredUserController::validateRegistrationEmail: ' . $e->getMessage(), [
                'email' => $request->email,
                'exception' => $e
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al validar el correo. Intenta de nuevo.'
            ], 500);
        }
    }*/

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        // ... (Validación inicial de name, email, password, direccion, telefono, country_id) ...
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:' . User::class, // Valida unicidad local
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'direccion' => 'nullable|string|max:255',
            'telefono' => 'nullable|string|max:255',
            'country_id' => 'required|exists:countries,id', // Asegura que el ID exista
        ]);

        // *** Lógica de re-validación con el servicio externo ***
        try {
            $externalStatus = $this->myLimsService->checkCeimicEmailStatus($request->email);

            // !!! CAMBIO CLAVE AQUÍ !!!
            // Solo permitir el registro si el estado externo es 'ok'
            if ($externalStatus['status'] !== 'ok') {
                // Si el estado NO es 'ok' (es 'ni' o 'no'), NO se puede registrar.
                // Lanzamos una excepción de validación manual con el mensaje apropiado.
                $errorMessage = 'No se puede registrar con este correo electrónico.'; // Mensaje por defecto

                if ($externalStatus['status'] === 'ni') {
                    $errorMessage = 'Los usuarios internos no pueden registrarse a través de este formulario.';
                } elseif ($externalStatus['status'] === 'no') {
                    $errorMessage = 'Este correo electrónico no fue encontrado en nuestro sistema externo.';
                }
                // Si status es 'error', el catch() del servicio ya loggeó. Puedes dar un mensaje genérico aquí también si lo necesitas.
                // elseif ($externalStatus['status'] === 'error') { ... }


                throw \Illuminate\Validation\ValidationException::withMessages([
                    'email' => [$errorMessage],
                ]);
            }
            // Si el status ES 'ok', la ejecución continúa normalmente.

        } catch (\Exception $e) {
            // Manejar error si falla el servicio externo en este punto crucial
            Log::error('Fallo la re-validación de email en store: ' . $e->getMessage(), ['email' => $request->email]);
            throw \Illuminate\Validation\ValidationException::withMessages([
                'email' => ['Ocurrió un error al verificar tu correo. Intenta de nuevo más tarde.'],
            ]);
        }
        // *** Fin lógica de re-validación ***


        // Crear el usuario (solo si pasó la validación local y la re-validación externa === 'ok')
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'direccion' => $request->direccion,
            'telefono' => $request->telefono,
            'country_id' => $request->country_id,
        ]);

        // Asignar el rol "Cliente" (ID 2) - Esta lógica permanece igual
        $clienteRole = Role::find(2);
        if ($clienteRole) {
            $user->roles()->sync([$clienteRole->id]);
        } else {
            Log::error("El rol con ID 2 (Cliente) no fue encontrado al registrar un usuario: " . $user->email);
        }

        event(new Registered($user));
        Auth::login($user);

        // Redirigir al dashboard
        return redirect(route('dashboard', absolute: false)); // O RouteServiceProvider::HOME si ya lo solucionaste
    }
}
