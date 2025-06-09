<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $request->user() ? array_merge(
                    $request->user()->toArray(),
                    ['roles' => $request->user()->roles->pluck('name')->toArray()] // Añade los roles al objeto user de Inertia
                ) : null,
            ],
            // Puedes añadir otras props compartidas aquí si es necesario
        ]);
    }
}
