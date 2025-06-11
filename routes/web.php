<?php
// routes/web.php

use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\GetMuestrasController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController as AdminUserController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\GetResultsAmostrasController;
use App\Http\Controllers\GetSampleDetailsController;
use App\Http\Controllers\ExcelExportController;
use App\Http\Controllers\PrincipalDashboardController;

Route::redirect('/', '/login');

Route::match(['get', 'post'], '/dashboard', [GetMuestrasController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::post('/muestras/extraer-laudos', [GetMuestrasController::class, 'extraerLaudos'])
        ->name('muestras.extraerLaudos');

    Route::post('/get-amostra-results', [GetResultsAmostrasController::class, 'getResults'])
        ->name('muestras.getResults');

    Route::get('/mrl/options', [\App\Http\Controllers\MrlController::class, 'getOptions'])
        ->name('mrl.options');

    Route::post('/mrl/generate-report', [\App\Http\Controllers\MrlController::class, 'generateReport'])
        ->name('mrl.generateReport');

    Route::post('/get-amostra-extended-details', [GetSampleDetailsController::class, 'getExtendedDetails'])
        ->name('muestras.getExtendedDetails');

    Route::post('/export-sample-details-excel', [ExcelExportController::class, 'exportSampleDetails'])
        ->name('muestras.exportExcelBackend');

    // === NUEVA RUTA PARA EL DASHBOARD PRINCIPAL ===
    // CÓDIGO CORREGIDO
    Route::match(['get', 'post'], '/principal-dashboard', [PrincipalDashboardController::class, 'index'])->name('principal.dashboard');
    Route::post('/principal-dashboard/data', [PrincipalDashboardController::class, 'fetchData'])->name('principal.dashboard.data');
});

// --- CAMBIO CLAVE AQUÍ: Aplicar el middleware 'role:Administrador' ---
Route::prefix('admin')->middleware(['auth', 'role:Administrador'])->name('admin.')->group(function () {
    Route::get('/', function () {
        return Inertia::render('Admin/Dashboard');
    })->name('dashboard'); // Esta ruta /admin es el dashboard de admin

    Route::resource('countries', CountryController::class);
    Route::resource('roles', RoleController::class);
    Route::resource('companies', CompanyController::class);
    Route::resource('users', AdminUserController::class);
});
// --- FIN CAMBIO CLAVE ---

Route::post('/check-registration-email', [RegisteredUserController::class, 'validateRegistrationEmail'])
    ->name('register.checkEmail')
    ->middleware('guest');

require __DIR__ . '/auth.php';
