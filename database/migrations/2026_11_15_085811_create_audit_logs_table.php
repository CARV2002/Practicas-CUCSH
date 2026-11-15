<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();   // sin FK: el historial sobrevive al usuario
            $table->string('actor_nombre')->nullable();
            $table->string('actor_rol', 30)->nullable();
            $table->string('accion', 80);                        // convenio.actualizado, documento.descargado...
            $table->string('entidad_tipo', 60)->nullable();      // Convenio, Empresa, Practica...
            $table->unsignedBigInteger('entidad_id')->nullable();
            $table->string('descripcion', 500);
            $table->json('cambios')->nullable();                 // {campo: {antes, despues}}
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['entidad_tipo', 'entidad_id']);
            $table->index(['user_id', 'created_at']);
            $table->index('accion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};