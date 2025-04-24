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
        Schema::table('companies', function (Blueprint $table) {
            // Renombrar nombre_fantasia a nombre_comercial
            $table->renameColumn('nombre_fantasia', 'nombre_comercial');

            // Eliminar la columna otros_datos
            $table->dropColumn('otros_datos');

            // Agregar las nuevas columnas
            $table->string('tipo_identificacion')->nullable()->after('nombre_comercial'); // Ej: RUC, EIN, NIT
            $table->string('numero_identificacion')->nullable()->after('tipo_identificacion'); // El número de RUC/EIN, etc.
            $table->text('domicilio_legal')->nullable()->after('numero_identificacion');
            $table->string('telefono')->nullable()->after('domicilio_legal');
            $table->string('correo_electronico')->nullable()->after('telefono');
            $table->string('sitio_web')->nullable()->after('correo_electronico');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // Revertir el cambio de nombre
            $table->renameColumn('nombre_comercial', 'nombre_fantasia');

            // Reagregar la columna otros_datos (asumiendo que era TEXT o JSON)
            $table->json('otros_datos')->nullable(); // O ->text()->nullable(); si no era JSON

            // Eliminar las columnas que agregamos
            $table->dropColumn('tipo_identificacion');
            $table->dropColumn('numero_identificacion');
            $table->dropColumn('domicilio_legal');
            $table->dropColumn('telefono');
            $table->dropColumn('correo_electronico');
            $table->dropColumn('sitio_web');
        });
    }
};