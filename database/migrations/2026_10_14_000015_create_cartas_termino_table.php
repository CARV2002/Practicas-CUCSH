<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cartas_termino', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practica_id')->unique()->constrained('practicas')->cascadeOnDelete();
            $table->string('estado_validacion', 15)->default('pendiente');   // App\Enums\ValidacionEstado
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cartas_termino');
    }
};
