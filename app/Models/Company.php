<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory;

    protected $fillable = ['razon_social', 'nombre_fantasia', 'otros_datos'];

    protected $casts = [
        'otros_datos' => 'json',
    ];

    /**
     * Los usuarios que pertenecen a esta empresa.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
