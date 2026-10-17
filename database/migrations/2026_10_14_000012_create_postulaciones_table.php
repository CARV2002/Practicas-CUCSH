<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('postulaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alumno_id')->constrained('alumnos')->restrictOnDelete();
            $table->foreignId('programa_id')->constrained('programas')->restrictOnDelete();
            $table->foreignId('validado_por')->nullable()->constrained('personal')->nullOnDelete();

            $table->string('estado', 30)->default('pendiente');     // App\Enums\PostulacionEstado
            // Fotografía de lo que el alumno declaró (el Kardex va adjunto como evidencia)
            $table->decimal('porcentaje_creditos_declarado', 5, 2);
            $table->date('fecha_postulacion');
            $table->timestamps();

            $table->unique(['alumno_id', 'programa_id']);
            $table->index(['programa_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('postulaciones');
    }
};
