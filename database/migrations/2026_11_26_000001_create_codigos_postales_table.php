<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo local de códigos postales (SEPOMEX): una fila por asentamiento (colonia).
 * Sustituye la consulta a un servicio externo; se llena con `php artisan practicas:importar-cp`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('codigos_postales', function (Blueprint $table) {
            $table->id();
            $table->char('cp', 5)->index();
            $table->string('colonia');
            $table->string('tipo_asentamiento', 60)->nullable();
            $table->string('municipio');
            $table->string('estado', 60);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('codigos_postales');
    }
};
