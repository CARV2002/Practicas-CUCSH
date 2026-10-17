<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oficios_acreditacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practica_id')->unique()->constrained('practicas')->cascadeOnDelete();
            $table->foreignId('emitido_por')->nullable()->constrained('personal')->nullOnDelete();
            $table->string('estado_validacion', 15)->default('pendiente');   // App\Enums\ValidacionEstado
            // Sin API de SIIAU: dos hitos manuales
            $table->timestamp('enviado_control_escolar_at')->nullable();
            $table->timestamp('capturado_siiau_at')->nullable();             // lo marca Control Escolar
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oficios_acreditacion');
    }
};
