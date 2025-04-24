<?php

namespace App\Http\Controllers;

use App\Models\Country;
use Illuminate\Http\Request;
use Inertia\Inertia; // Asegúrate de importar Inertia

class CountryController extends Controller
{
    /**
     * Muestra un listado de países.
     */
    public function index()
    {
        // Obtiene todos los países. Puedes añadir paginación si la lista es larga:
        // $countries = Country::paginate(10);
        $countries = Country::all();

        // Renderiza la vista Vue con Inertia y pasa los datos
        return Inertia::render('Admin/Countries/Index', [
            'countries' => $countries,
        ]);
    }

    /**
     * Muestra el formulario para crear un nuevo país.
     */
    public function create()
    {
         return Inertia::render('Admin/Countries/Create');
    }

    /**
     * Guarda un nuevo país en la base de datos.
     */
    public function store(Request $request)
    {
        // Valida los datos de la request
        $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:countries,name'], // 'unique' valida que el nombre sea único
            'code' => ['nullable', 'string', 'max:10', 'unique:countries,code'], // 'nullable' permite que sea nulo, 'unique' valida si no es nulo
        ]);

        // Crea el nuevo país
        Country::create($request->all());

        // Redirige a la lista de países con un mensaje de éxito (para mostrar en la UI)
        return redirect()->route('admin.countries.index')
                         ->with('success', 'País creado con éxito.');
    }

    /**
     * Muestra los detalles de un país específico.
     * (Opcional: No siempre necesario para un CRUD básico)
     */
    public function show(Country $country)
    {
        // return Inertia::render('Admin/Countries/Show', ['country' => $country]);
        // Si no necesitas una vista de detalles, puedes eliminar este método
    }

    /**
     * Muestra el formulario para editar un país existente.
     */
    public function edit(Country $country)
    {
         return Inertia::render('Admin/Countries/Edit', [
            'country' => $country, // Pasa el país a la vista para pre-llenar el formulario
        ]);
    }

    /**
     * Actualiza un país existente en la base de datos.
     */
    public function update(Request $request, Country $country)
    {
        // Valida los datos. 'unique' ignora el ID actual al verificar unicidad.
        $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:countries,name,' . $country->id],
            'code' => ['nullable', 'string', 'max:10', 'unique:countries,code,' . $country->id],
        ]);

        // Actualiza el país
        $country->update($request->all());

        // Redirige a la lista de países con mensaje de éxito
        return redirect()->route('admin.countries.index')
                         ->with('success', 'País actualizado con éxito.');
    }

    /**
     * Elimina un país de la base de datos.
     */
    public function destroy(Country $country)
    {
        // Elimina el país
        $country->delete();

        // Redirige a la lista de países con mensaje de éxito
        return redirect()->route('admin.countries.index')
                         ->with('success', 'País eliminado con éxito.');
    }
}
