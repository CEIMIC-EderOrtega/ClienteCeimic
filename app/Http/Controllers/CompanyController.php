<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;
use Inertia\Inertia; // Asegúrate de importar Inertia


class CompanyController extends Controller
{
    /**
     * Muestra un listado de empresas.
     */
    public function index()
    {
         $companies = Company::all(); // O Company::paginate(10);
         return Inertia::render('Admin/Companies/Index', [
            'companies' => $companies,
        ]);
    }

    /**
     * Muestra el formulario para crear una nueva empresa.
     */
    public function create()
    {
         return Inertia::render('Admin/Companies/Create');
    }

    /**
     * Guarda una nueva empresa.
     */
    public function store(Request $request)
    {
         $request->validate([
            'razon_social' => ['required', 'string', 'max:255', 'unique:companies,razon_social'],
            'nombre_fantasia' => ['nullable', 'string', 'max:255'],
            'otros_datos' => ['nullable', 'json'], // Valida si es JSON válido si se envía
        ]);

        Company::create($request->all());

        return redirect()->route('admin.companies.index')
                         ->with('success', 'Empresa creada con éxito.');
    }

    /**
     * Muestra los detalles de una empresa. (Opcional)
     */
    public function show(Company $company)
    {
         //
    }

    /**
     * Muestra el formulario para editar una empresa.
     */
    public function edit(Company $company)
    {
         return Inertia::render('Admin/Companies/Edit', [
            'company' => $company,
        ]);
    }

    /**
     * Actualiza una empresa.
     */
    public function update(Request $request, Company $company)
    {
         $request->validate([
            'razon_social' => ['required', 'string', 'max:255', 'unique:companies,razon_social,' . $company->id],
            'nombre_fantasia' => ['nullable', 'string', 'max:255'],
            'otros_datos' => ['nullable', 'json'],
        ]);

        $company->update($request->all());

        return redirect()->route('admin.companies.index')
                         ->with('success', 'Empresa actualizada con éxito.');
    }

    /**
     * Elimina una empresa.
     */
    public function destroy(Company $company)
    {
         // Considera qué pasa con los usuarios de esta empresa al eliminarla (la FK en users es ON DELETE SET NULL)
         $company->delete();
         return redirect()->route('admin.companies.index')
                         ->with('success', 'Empresa eliminada con éxito.');
    }
}
