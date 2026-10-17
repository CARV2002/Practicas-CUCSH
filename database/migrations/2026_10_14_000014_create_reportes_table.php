<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reportes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practica_id')->constrained('practicas')->cascadeOnDelete();
            $table->string('tipo', 15);                            // App\Enums\ReporteTipo
            $table->date('periodo_inicio');
            $table->date('periodo_fin');
            $table->unsignedSmallInteger('horas_reportadas');
            $table->text('retroalimentacion_empresa')->nullable();
            $table->text('evaluacion_profesor')->nullable();
            $table->string('estado', 15)->default('pendiente');   // App\Enums\ReporteEstado
            $table->timestamps();

            $table->index(['practica_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reportes');
    }
};
