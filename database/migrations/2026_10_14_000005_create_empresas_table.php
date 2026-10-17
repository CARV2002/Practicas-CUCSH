<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // Identidad legal: datos que el contrato necesita (se verifican contra los PDFs)
            $table->string('rfc', 13)->unique();
            $table->string('razon_social');
            $table->string('tipo_organismo', 20);          // App\Enums\TipoOrganismo
            $table->string('domicilio_fiscal')->nullable();
            $table->string('representante_legal')->nullable();

            // Contacto operativo (quien propone / recibe practicantes)
            $table->string('contacto_nombre');
            $table->string('contacto_cargo')->nullable();  // "filiación institucional"
            $table->string('contacto_telefono', 20)->nullable();

            // Presentación de la instancia
            $table->text('descripcion')->nullable();
            $table->text('proyectos_principales')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresas');
    }
};
