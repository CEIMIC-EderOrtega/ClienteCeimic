<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'code'];

    /**
     * Los usuarios que pertenecen a este país.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
