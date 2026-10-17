<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programas', function (Blueprint $table) {
            $table->id();
            // null solo cuando la modalidad es "interna"
            $table->foreignId('convenio_id')->nullable()->constrained('convenios')->restrictOnDelete();
            $table->foreignId('carrera_id')->constrained('carreras')->restrictOnDelete();

            $table->string('modalidad', 10)->default('externa');    // App\Enums\Modalidad
            $table->string('ciclo', 10);                            // ej. 2027A
            $table->string('titulo');
            $table->text('descripcion');                            // resumen corto para la tarjeta
            $table->json('actividades');                            // lista de puntos a realizar
            $table->text('perfil_requerido');
            $table->string('horario')->nullable();
            $table->string('sede')->nullable();
            $table->unsignedSmallInteger('cupos_totales');          // los disponibles se calculan
            $table->string('estado', 15)->default('borrador');     // App\Enums\ProgramaEstado
            $table->timestamps();

            $table->index(['carrera_id', 'ciclo', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programas');
    }
};
