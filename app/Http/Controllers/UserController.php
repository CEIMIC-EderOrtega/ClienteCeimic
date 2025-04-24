<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Models\Country;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class UserController extends Controller
{
    /**
     * Muestra un listado de usuarios.
     */
    public function index()
    {
        // Cargar relaciones para mostrarlas en la lista si es necesario
        // Usamos paginación para no cargar todos los usuarios si hay muchos
        $users = User::with(['roles', 'country', 'company'])->paginate(10);


        $roles = Role::all(['id', 'name']); // Solo ID y nombre, es suficiente
        $countries = Country::all(['id', 'name']); // Solo ID y nombre

        $companies = Company::all(['id', 'razon_social']);


        return Inertia::render('Admin/Users/Index', [
            'users' => $users, // Pasa los usuarios paginados
            'roles' => $roles, // Pasa la lista de roles
            'countries' => $countries, // Pasa la lista de países
            'companies' => $companies, // Pasa la lista de empresas
        ]);
    }


    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'], // confirmed busca 'password_confirmation'
            'direccion' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'country_id' => ['nullable', 'exists:countries,id'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'roles' => ['required', 'array'], // Espera un array de IDs de roles
            'roles.*' => ['exists:roles,id'], // Valida que cada ID en el array de roles exista
        ]);

        // Crea el usuario
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'direccion' => $request->direccion,
            'telefono' => $request->telefono,
            'country_id' => $request->country_id,
            'company_id' => $request->company_id,
        ]);

        // Asignar roles (usa sync para manejar la relación muchos a muchos)
        $user->roles()->sync($request->roles);

        // Lógica para asegurar que un usuario con rol 'admin' NO tenga 'company_id'
        if ($user->fresh()->hasRole('admin')) {
            $user->company_id = null;
            $user->save();
        }

        return redirect()->route('admin.users.index')
            ->with('success', 'Usuario creado con éxito.');
    }


    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            // Contraseña es opcional al editar, solo valida si se envía
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'country_id' => ['nullable', 'exists:countries,id'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'roles' => ['required', 'array'],
            'roles.*' => ['exists:roles,id'],
        ]);

        $userData = [
            'name' => $request->name,
            'email' => $request->email,
            'direccion' => $request->direccion,
            'telefono' => $request->telefono,
            'country_id' => $request->country_id,
            'company_id' => $request->company_id,
        ];

        // Si se proporcionó una nueva contraseña, la hasheamos y la añadimos
        if ($request->filled('password')) {
            $userData['password'] = Hash::make($request->password);
        }

        $user->update($userData);

        // Sincronizar roles
        $user->roles()->sync($request->roles);

        // Lógica para asegurar que un usuario con rol 'admin' NO tenga 'company_id'
        if ($user->fresh()->hasRole('admin')) {
            $user->company_id = null;
            $user->save();
        }

        return redirect()->route('admin.users.index')
            ->with('success', 'Usuario actualizado con éxito.');
    }

    /**
     * Elimina un usuario. (Este método ya lo tienes bien)
     */
    public function destroy(User $user)
    {
        // Auth::user()->id !== $user->id // Opcional: chequeo
        try {
            $user->delete();
            $message = 'Usuario eliminado con éxito.';
            $type = 'success';
        } catch (\Exception $e) {
            $message = 'Ocurrió un error al eliminar el usuario.';
            $type = 'error';
            // Log::error("Error deleting user {$user->id}: " . $e->getMessage()); // Opcional: log el error
        }

        return redirect()->route('admin.users.index')
            ->with($type, $message);
    }
}
