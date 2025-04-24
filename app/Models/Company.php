<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory;

    // Actualiza los campos permitidos para asignación masiva
    protected $fillable = [
        'razon_social',
        'nombre_comercial', // Renombrado
        'tipo_identificacion', // Nuevo
        'numero_identificacion', // Nuevo
        'domicilio_legal', // Nuevo
        'telefono', // Nuevo
        'correo_electronico', // Nuevo
        'sitio_web', // Nuevo
        // 'otros_datos' eliminado
    ];

    // Elimina el cast para 'otros_datos'
    protected $casts = [
        // Ya no necesitamos el cast para otros_datos
    ];

    /**
     * Los usuarios que pertenecen a esta empresa.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}