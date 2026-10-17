<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('convenios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            // Quién lleva el trámite (Vinculación); null mientras nadie lo toma
            $table->foreignId('gestionado_por')->nullable()->constrained('personal')->nullOnDelete();

            $table->string('folio', 30)->nullable()->unique();      // se asigna al formalizar
            $table->string('estado', 30)->default('solicitado');    // App\Enums\ConvenioEstado
            $table->date('fecha_recepcion');

            // Propuesta (lo que antes era el Google Form)
            $table->text('objetivos');
            $table->text('actividades_propuestas');
            $table->unsignedSmallInteger('plazas_estimadas');       // informativo: lo declarado al proponer
            $table->string('medio_conocimiento')->nullable();
            $table->text('experiencia_previa')->nullable();

            // Vigencia (null hasta formalizar; suelen ser de 2 a 5 años)
            $table->date('fecha_inicio_vigencia')->nullable();
            $table->date('fecha_fin_vigencia')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['estado', 'fecha_fin_vigencia']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('convenios');
    }
};
