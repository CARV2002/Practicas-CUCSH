<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Control de los papeles del trámite híbrido (oficios, dictamen jurídico y juegos firmados)
        Schema::create('documentos_fisicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('convenio_id')->constrained('convenios')->cascadeOnDelete();
            $table->string('tipo', 40);
            $table->unsignedTinyInteger('juego')->nullable();
            $table->string('destino')->nullable();
            $table->string('estado', 40);
            $table->date('fecha_estado');
            $table->date('fecha_compromiso')->nullable();
            $table->text('notas')->nullable();
            $table->foreignId('actualizado_por')->nullable()->constrained('personal')->nullOnDelete();
            $table->timestamps();

            $table->unique(['convenio_id', 'tipo', 'juego']);
            $table->index(['estado', 'fecha_compromiso']);
        });

        Schema::create('documento_fisico_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('documento_fisico_id')->constrained('documentos_fisicos')->cascadeOnDelete();
            $table->string('estado', 40);
            $table->text('nota')->nullable();
            $table->date('fecha');
            $table->foreignId('registrado_por')->nullable()->constrained('personal')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documento_fisico_movimientos');
        Schema::dropIfExists('documentos_fisicos');
    }
};
