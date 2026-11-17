<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Paso del oficio de turno por Secretaría Académica y por la División (canales administrativos)
        Schema::table('convenios', function (Blueprint $table) {
            $table->date('fecha_turno_secretaria')->nullable()->after('experiencia_previa');
            $table->date('fecha_turno_division')->nullable()->after('fecha_turno_secretaria');
        });
    }

    public function down(): void
    {
        Schema::table('convenios', function (Blueprint $table) {
            $table->dropColumn(['fecha_turno_secretaria', 'fecha_turno_division']);
        });
    }
};
