<?php

use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\GetMuestrasController; // Asegúrate de importar el controlador
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController as AdminUserController; // Alias para no confundir

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});
/*
Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');*/

Route::get('/dashboard', [GetMuestrasController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');


Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// --- Rutas del Panel de Administración ---

// Agrupamos todas las rutas de administración bajo el prefijo '/admin'
// y las protegemos con el middleware 'auth'.
// El nombre de la ruta empezará por 'admin.'
Route::prefix('admin')->middleware(['auth'])->name('admin.')->group(function () {

    // Dashboard principal del área de administración
    Route::get('/', function () {
        return Inertia::render('Admin/Dashboard'); // Debes crear esta vista Vue
    })->name('dashboard'); // Ruta: admin.dashboard

    // Rutas resource para CRUD de Países
    Route::resource('countries', CountryController::class); // Rutas: admin.countries.index, .create, .store, .edit, .update, .destroy, .show

    // Rutas resource para CRUD de Roles
    Route::resource('roles', RoleController::class); // Rutas: admin.roles.*

    // Rutas resource para CRUD de Empresas
    Route::resource('companies', CompanyController::class); // Rutas: admin.companies.*

    // Rutas resource para CRUD de Usuarios (gestionados desde admin)
    Route::resource('users', AdminUserController::class); // Rutas: admin.users.*
});
require __DIR__.'/auth.php';
