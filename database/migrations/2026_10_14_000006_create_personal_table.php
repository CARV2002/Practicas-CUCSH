<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Personal institucional. El ROL vive en spatie/laravel-permission; aquí solo el ALCANCE:
        //   division_id  -> directores de división
        //   carrera_id   -> coordinadores de carrera
        //   ambos null   -> alcance global (vinculación, secretaría, jurídico, rectoría, control escolar)
        Schema::create('personal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('division_id')->nullable()->constrained('divisiones')->restrictOnDelete();
            $table->foreignId('carrera_id')->nullable()->constrained('carreras')->restrictOnDelete();
            $table->string('puesto')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal');
    }
};
