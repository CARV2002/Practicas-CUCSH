<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El domicilio fiscal se arma con calle, números, colonia, CP, municipio y estado (hasta ~480 caracteres):
 * en varchar(255) la base de datos lo rechazaba y guardar «Datos generales» terminaba en error 500.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->text('domicilio_fiscal')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('domicilio_fiscal', 255)->nullable()->change();
        });
    }
};
