<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bitácora: una fila por etapa abierta. Nunca se sobrescribe un rechazo/observación:
        // si Jurídica observa y luego aprueba, quedan las dos filas.
        Schema::create('convenio_seguimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('convenio_id')->constrained('convenios')->cascadeOnDelete();
            $table->foreignId('registrado_por')->nullable()->constrained('personal')->nullOnDelete();
            $table->string('etapa', 30);                             // App\Enums\EtapaConvenio
            $table->string('estado', 15)->default('pendiente');     // App\Enums\SeguimientoEstado
            $table->text('comentario')->nullable();
            $table->date('fecha_registro');                          // se abre la etapa
            $table->date('fecha_resolucion')->nullable();            // se cierra: mide cuánto tardó
            $table->timestamps();

            $table->index(['convenio_id', 'etapa', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('convenio_seguimientos');
    }
};
