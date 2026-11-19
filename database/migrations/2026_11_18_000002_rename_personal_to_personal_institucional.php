<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * «personal» era ambiguo: la tabla guarda el perfil institucional de las cuentas del personal
 * (administrador, Vinculación y coordinaciones) con su alcance de carrera o división.
 * Las llaves foráneas que apuntan a ella se actualizan solas al renombrar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('personal', 'personal_institucional');
    }

    public function down(): void
    {
        Schema::rename('personal_institucional', 'personal');
    }
};
