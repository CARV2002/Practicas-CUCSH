<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Entidad asociativa: el Dictamen Técnico de Pertinencia Académica de cada carrera.
        // Las carreras del contrato final son las que quedan con dictamen "aprobatorio".
        Schema::create('convenio_carrera', function (Blueprint $table) {
            $table->id();
            $table->foreignId('convenio_id')->constrained('convenios')->cascadeOnDelete();
            $table->foreignId('carrera_id')->constrained('carreras')->restrictOnDelete();
            $table->foreignId('dictaminado_por')->nullable()->constrained('personal')->nullOnDelete();
            $table->string('dictamen', 15)->default('pendiente');   // App\Enums\Dictamen
            $table->text('observaciones')->nullable();
            $table->date('fecha_dictamen')->nullable();
            $table->timestamps();

            $table->unique(['convenio_id', 'carrera_id']);
            $table->index(['carrera_id', 'dictamen']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('convenio_carrera');
    }
};
