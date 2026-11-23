<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de profesores supervisores que administra cada coordinación (sin acceso al sistema).
 * No se borran: se desactivan, para conservar a quién supervisó cada práctica.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profesores', function (Blueprint $table) {
            $table->string('telefono', 40)->nullable()->after('email');
            $table->boolean('activo')->default(true)->after('telefono');
        });
    }

    public function down(): void
    {
        Schema::table('profesores', function (Blueprint $table) {
            $table->dropColumn(['telefono', 'activo']);
        });
    }
};
