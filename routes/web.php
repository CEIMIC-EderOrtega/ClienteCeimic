<?php
// routes/web.php

use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\GetMuestrasController; // Asegúrate de importar el controlador
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController as AdminUserController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\GetResultsAmostrasController;

/*Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});*/
Route::redirect('/', '/login');
// *** CAMBIO AQUÍ: Aceptar GET y POST para el dashboard ***
Route::match(['get', 'post'], '/dashboard', [GetMuestrasController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // --- Ruta para extraer laudos (sin cambios) ---
    Route::post('/muestras/extraer-laudos', [GetMuestrasController::class, 'extraerLaudos'])
        ->name('muestras.extraerLaudos');

    // --- Ruta para resultados del detalle (sin cambios) ---
    Route::post('/get-amostra-results', [GetResultsAmostrasController::class, 'getResults'])
        ->name('muestras.getResults');

    // --- NUEVAS RUTAS PARA MRL ---
    // Ruta para obtener los datos para el modal (mercados, retailers)
    Route::get('/mrl/options', [\App\Http\Controllers\MrlController::class, 'getOptions'])
        ->name('mrl.options');

    // Ruta para generar y descargar el informe MRL
    Route::post('/mrl/generate-report', [\App\Http\Controllers\MrlController::class, 'generateReport'])
        ->name('mrl.generateReport');
});

// --- Rutas del Panel de Administración (sin cambios) ---
Route::prefix('admin')->middleware(['auth'])->name('admin.')->group(function () {
    Route::get('/', function () {
        return Inertia::render('Admin/Dashboard');
    })->name('dashboard');
    Route::resource('countries', CountryController::class);
    Route::resource('roles', RoleController::class);
    Route::resource('companies', CompanyController::class);
    Route::resource('users', AdminUserController::class);
});

// --- Ruta validación email (sin cambios) ---
Route::post('/check-registration-email', [RegisteredUserController::class, 'validateRegistrationEmail'])
    ->name('register.checkEmail')
    ->middleware('guest');

require __DIR__ . '/auth.php';
