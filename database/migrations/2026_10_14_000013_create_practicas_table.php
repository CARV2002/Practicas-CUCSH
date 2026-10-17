<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('practicas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('postulacion_id')->unique()->constrained('postulaciones')->restrictOnDelete();
            $table->foreignId('autorizado_por')->nullable()->constrained('personal')->nullOnDelete();
            $table->foreignId('profesor_supervisor_id')->nullable()->constrained('profesores')->nullOnDelete();

            $table->string('modalidad', 10);                        // copia del programa
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->unsignedSmallInteger('horas_requeridas');       // copia congelada de carreras.horas_requeridas
            $table->string('estado', 15)->default('en_curso');      // App\Enums\PracticaEstado
            $table->timestamps();
            $table->softDeletes();

            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('practicas');
    }
};
