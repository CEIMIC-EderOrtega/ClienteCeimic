<?php

namespace App\Http\Controllers;


use App\Models\User;
use App\Models\Role;
use App\Models\Country;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia; // Asegúrate de importar Inertia

class UserController extends Controller
{
    /**
     * Muestra un listado de usuarios.
     */
    public function index()
    {
        // Cargar relaciones para mostrarlas en la lista si es necesario
        $users = User::with(['roles', 'country', 'company'])->paginate(10); // Usamos paginación

        return Inertia::render('Admin/Users/Index', [
            'users' => $users, // Pasa los usuarios paginados
        ]);
    }

    /**
     * Muestra el formulario para crear un nuevo usuario.
     */
    public function create()
    {
        // Necesitas pasar las listas de roles, países y empresas para los selects en el formulario
        $roles = Role::all();
        $countries = Country::all();
        $companies = Company::all();

        return Inertia::render('Admin/Users/Create', [
            'roles' => $roles,
            'countries' => $countries,
            'companies' => $companies,
        ]);
    }

    /**
     * Guarda un nuevo usuario.
     */
    public function store(Request $request)
    {
        // Validar los datos de entrada
        $request->validate([
            'name' => ['required', 'string', 'max:255'], // Nombre completo
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'], // confirmed busca 'password_confirmation'
            'direccion' => ['nullable', 'string', 'max:255'], // Nuevos campos
            'telefono' => ['nullable', 'string', 'max:20'],
            'country_id' => ['nullable', 'exists:countries,id'], // Valida que el ID de país exista
            'company_id' => ['nullable', 'exists:companies,id'], // Valida que el ID de empresa exista (puede ser null)
            'roles' => ['required', 'array'], // Espera un array de IDs de roles
            'roles.*' => ['exists:roles,id'], // Valida que cada ID en el array de roles exista
        ]);

        // Crea el usuario
        $user = User::create([
            'name' => $request->name, // Usamos directamente el campo 'name'
            'email' => $request->email,
            'password' => Hash::make($request->password), // Hashear la contraseña
            'direccion' => $request->direccion, // Guardamos los nuevos campos
            'telefono' => $request->telefono,
            'country_id' => $request->country_id,
            'company_id' => $request->company_id,
        ]);

        // Asignar roles (usa sync para manejar la relación muchos a muchos)
        $user->roles()->sync($request->roles); // sync(array de IDs de roles)

        // Lógica para asegurar que un usuario con rol 'admin' NO tenga 'company_id'
        // Esto es una capa extra de seguridad por si la UI permite seleccionarlo por error.
        // user->fresh() recarga el modelo para asegurarse de tener los roles recién asignados.
        if ($user->fresh()->hasRole('admin')) {
             $user->company_id = null;
             $user->save(); // Guarda el cambio
        }

        // Redirige a la lista de usuarios con un mensaje de éxito
        return redirect()->route('admin.users.index')
                         ->with('success', 'Usuario creado con éxito.');
    }

    /**
     * Muestra los detalles de un usuario. (Opcional)
     */
    public function show(User $user)
    {
         // return Inertia::render('Admin/Users/Show', ['user' => $user->load(['roles', 'country', 'company'])]);
    }

    /**
     * Muestra el formulario para editar un usuario existente.
     */
    public function edit(User $user)
    {
        // Cargar las relaciones para mostrar en el formulario de edición
        $user->load(['roles', 'country', 'company']);

        // Necesitas pasar las listas de roles, países y empresas también para los selects
        $roles = Role::all();
        $countries = Country::all();
        $companies = Company::all();

        return Inertia::render('Admin/Users/Edit', [
            'user' => $user, // Pasa el usuario con sus relaciones cargadas
            'roles' => $roles,
            'countries' => $countries,
            'companies' => $companies,
        ]);
    }

    /**
     * Actualiza un usuario existente.
     */
    public function update(Request $request, User $user)
    {
         // Validar los datos
         $request->validate([
            'name' => ['required', 'string', 'max:255'],
            // Ignorar el email único para el usuario actual al actualizar
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'], // Contraseña es opcional al editar
            'direccion' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'country_id' => ['nullable', 'exists:countries,id'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'roles' => ['required', 'array'],
            'roles.*' => ['exists:roles,id'],
        ]);

        // Datos a actualizar
        $userData = [
            'name' => $request->name,
            'email' => $request->email,
            'direccion' => $request->direccion,
            'telefono' => $request->telefono,
            'country_id' => $request->country_id,
            'company_id' => $request->company_id,
        ];

        // Si se proporcionó una nueva contraseña, la hasheamos y la añadimos a los datos a actualizar
        if ($request->filled('password')) {
            $userData['password'] = Hash::make($request->password);
        }

        // Actualiza el usuario
        $user->update($userData);

        // Sincronizar roles
        $user->roles()->sync($request->roles);

        // Lógica para asegurar que un usuario con rol 'admin' NO tenga 'company_id'
        // Lo hacemos de nuevo después de la actualización y sincronización de roles.
        if ($user->fresh()->hasRole('admin')) {
             $user->company_id = null;
             $user->save();
        }

        // Redirige a la lista de usuarios con mensaje de éxito
        return redirect()->route('admin.users.index')
                         ->with('success', 'Usuario actualizado con éxito.');
    }

    /**
     * Elimina un usuario.
     */
    public function destroy(User $user)
    {
         // Opcional: Añade una política o chequeo para evitar borrar al usuario logueado
         // Auth::user()->id !== $user->id

         $user->delete();
         return redirect()->route('admin.users.index')
                          ->with('success', 'Usuario eliminado con éxito.');
    }
}
