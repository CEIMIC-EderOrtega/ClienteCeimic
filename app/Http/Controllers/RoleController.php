<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Role;
use Inertia\Inertia; // Asegúrate de importar Inertia


class RoleController extends Controller
{
    /**
     * Muestra un listado de roles.
     */
    public function index()
    {
        $roles = Role::all(); // O Role::paginate(10);

         return Inertia::render('Admin/Roles/Index', [
            'roles' => $roles,
        ]);
    }

    /**
     * Muestra el formulario para crear un nuevo rol.
     */
    public function create()
    {
         return Inertia::render('Admin/Roles/Create');
    }

    /**
     * Guarda un nuevo rol.
     */
    public function store(Request $request)
    {
         $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
        ]);

        Role::create($request->all());

        return redirect()->route('admin.roles.index')
                         ->with('success', 'Rol creado con éxito.');
    }

    /**
     * Muestra los detalles de un rol. (Opcional)
     */
    public function show(Role $role)
    {
         //
    }

    /**
     * Muestra el formulario para editar un rol.
     */
    public function edit(Role $role)
    {
         return Inertia::render('Admin/Roles/Edit', [
            'role' => $role,
        ]);
    }

    /**
     * Actualiza un rol.
     */
    public function update(Request $request, Role $role)
    {
         $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name,' . $role->id],
        ]);

        $role->update($request->all());

        return redirect()->route('admin.roles.index')
                         ->with('success', 'Rol actualizado con éxito.');
    }

    /**
     * Elimina un rol.
     */
    public function destroy(Role $role)
    {
         // Considera no permitir eliminar roles cruciales como 'admin' si tienen usuarios asociados
         $role->delete();
         return redirect()->route('admin.roles.index')
                          ->with('success', 'Rol eliminado con éxito.');
    }
}
