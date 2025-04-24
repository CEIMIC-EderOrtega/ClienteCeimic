<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Campos nuevos a añadir SOLAMENTE
            $table->string('direccion')->nullable()->after('password'); // Puedes ponerlo después de password o email
            $table->string('telefono')->nullable()->after('direccion');

            // Relaciones (Foreign Keys)
            $table->foreignId('country_id')
                  ->nullable()
                  ->after('telefono')
                  ->constrained()
                  ->onDelete('set null');

            $table->foreignId('company_id')
                  ->nullable() // Nullable para administradores
                  ->after('country_id')
                  ->constrained()
                  ->onDelete('set null');

            // *** IMPORTANTE ***
            // No añadimos 'nombres' ni 'apellidos' porque usaremos el campo 'name' existente para el nombre completo.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Eliminar FKs primero
            $table->dropConstrainedForeignId('country_id');
            $table->dropConstrainedForeignId('company_id');

            // Eliminar columnas añadidas
            $table->dropColumn(['direccion', 'telefono']);

            // No hay que remover 'name', 'email', 'password' porque eran originales
        });
    }
};
