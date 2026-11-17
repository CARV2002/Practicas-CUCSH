<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Datos que se imprimen en la Carta de Presentación
        Schema::table('alumnos', function (Blueprint $table) {
            $table->unsignedTinyInteger('grado')->nullable()->after('num_control');   // semestre que cursa
            $table->string('turno', 15)->nullable()->after('grado');                  // App\Enums\Turno
        });
    }

    public function down(): void
    {
        Schema::table('alumnos', function (Blueprint $table) {
            $table->dropColumn(['grado', 'turno']);
        });
    }
};
