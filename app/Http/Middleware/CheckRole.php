<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\User; // <-- IMPORTA TU MODELO USER

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if (!Auth::check()) {
            return redirect()->route('login'); // Redirige a login si no está autenticado
        }

        /** @var \App\Models\User $user */ // <-- AÑADE ESTA LÍNEA PARA EL TIPADO EXPLÍCITO
        $user = Auth::user();

        if (!$user->relationLoaded('roles')) {
            $user->load('roles');
        }

        // Si el usuario es "Administrador", siempre permite el acceso.
        if ($user->hasRole('Administrador')) {
            return $next($request);
        }

        // Si se especificó un rol y el usuario no tiene ese rol, redirige o aborta.
        if (!$user->hasRole($role)) {
            return redirect()->route('dashboard')->with('error', 'No tienes permisos para acceder a esta sección.');
        }

        return $next($request);
    }
}
