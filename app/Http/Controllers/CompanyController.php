<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;
use Inertia\Inertia;

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
     * Muestra el formulario para crear una nueva empresa. (Esta vista 'Create' ya no la usaríamos con el enfoque unificado)
     */
    // public function create()
    // {
    //     return Inertia::render('Admin/Companies/Create');
    // }

    /**
     * Guarda una nueva empresa.
     */
    public function store(Request $request)
    {
        $request->validate([
            'razon_social' => ['required', 'string', 'max:255', 'unique:companies,razon_social'],
            'nombre_comercial' => ['nullable', 'string', 'max:255'], // Era nombre_fantasia
            'tipo_identificacion' => ['nullable', 'string', 'max:50'], // Ajusta max según necesites
            'numero_identificacion' => ['nullable', 'string', 'max:255'],
            'domicilio_legal' => ['nullable', 'string'], // Textarea, no necesita max
            'telefono' => ['nullable', 'string', 'max:50'],
            'correo_electronico' => ['nullable', 'email', 'max:255'], // Agrega validación email
            'sitio_web' => ['nullable', 'url', 'max:255'], // Agrega validación url
        ]);

        Company::create($request->all());

        return redirect()->route('admin.companies.index')
            ->with('success', 'Empresa creada con éxito.'); // Puedes usar flash messages si tu layout los maneja
    }

    /**
     * Muestra los detalles de una empresa. (Opcional)
     */
    // public function show(Company $company)
    // {
    //     // Si necesitaras mostrar detalles en una vista separada
    // }

    /**
     * Muestra el formulario para editar una empresa. (Esta vista 'Edit' ya no la usaríamos con el enfoque unificado)
     */
    // public function edit(Company $company)
    // {
    //      // Inertia ya pasará los datos de la empresa a la vista Index
    // }

    /**
     * Actualiza una empresa.
     */
    public function update(Request $request, Company $company)
    {
        $request->validate([
            'razon_social' => ['required', 'string', 'max:255', 'unique:companies,razon_social,' . $company->id],
            'nombre_comercial' => ['nullable', 'string', 'max:255'], // Era nombre_fantasia
            'tipo_identificacion' => ['nullable', 'string', 'max:50'],
            'numero_identificacion' => ['nullable', 'string', 'max:255'],
            'domicilio_legal' => ['nullable', 'string'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'correo_electronico' => ['nullable', 'email', 'max:255'],
            'sitio_web' => ['nullable', 'url', 'max:255'],
        ]);

        $company->update($request->all());

        return redirect()->route('admin.companies.index')
            ->with('success', 'Empresa actualizada con éxito.'); // Puedes usar flash messages
    }

    /**
     * Elimina una empresa.
     */
    public function destroy(Company $company)
    {
         try {
             // Intenta eliminar la empresa
             $company->delete();
             $message = 'Empresa eliminada con éxito.';
             $type = 'success';
         } catch (\Illuminate\Database\QueryException $e) {
             // Captura excepciones de base de datos (ej. si hay FK constraints)
             // Puedes examinar $e->getCode() o $e->getMessage() para ser más específico
             $message = 'No se pudo eliminar la empresa. Asegúrate de que no esté asociada a usuarios u otros registros.';
             $type = 'error';
         } catch (\Exception $e) {
             // Captura otras posibles excepciones
             $message = 'Ocurrió un error al eliminar la empresa.';
             $type = 'error';
         }


         return redirect()->route('admin.companies.index')
                         ->with($type, $message); // Usa el tipo de mensaje para flash
    }
}