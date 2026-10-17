<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carreras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('division_id')->constrained('divisiones')->restrictOnDelete();
            $table->string('carrera_clave', 10)->unique();            // ABOG, LREI, LHIST...
            $table->string('nombre');
            $table->unsignedSmallInteger('horas_requeridas');          // horas de práctica de la carrera
            $table->decimal('porcentaje_creditos_minimo', 5, 2);       // requisito de entrada (50.00, 60.00...)
            $table->unsignedSmallInteger('creditos_otorgados');        // créditos que se imprimen en el Oficio de Acreditación
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carreras');
    }
};
