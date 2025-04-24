<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name', // Mantenemos este para el nombre completo
        'email',
        'password',
        'direccion', // Nuevo campo
        'telefono', // Nuevo campo
        'country_id', // Nueva FK
        'company_id', // Nueva FK (nullable)
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // --- Relaciones ---

    /**
     * El país al que pertenece el usuario.
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * La empresa a la que pertenece el usuario.
     * Puede ser null para administradores.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Los roles que tiene el usuario. (Relación muchos a muchos)
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    // --- Helpers (Opcional pero útil) ---

    /**
     * Comprueba si el usuario tiene un rol específico.
     */
    public function hasRole(string $role): bool
    {
        // Carga los roles si no están cargados para evitar N+1 en algunos casos
        // aunque contains en una colección ya cargada es eficiente.
        return $this->roles->contains('name', $role);
    }

    /**
     * Comprueba si el usuario tiene alguno de los roles dados.
     */
    public function hasAnyRole(array $roles): bool
    {
        // Carga los roles si no están cargados
        return $this->roles->pluck('name')->intersect($roles)->isNotEmpty();
    }
}
