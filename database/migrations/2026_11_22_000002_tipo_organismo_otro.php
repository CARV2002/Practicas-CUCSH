<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Formulario oficial: «Tipo de organismo: Público, Privado, Sociedad civil u Otros: ___» (cuál, si es «otro»). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('tipo_organismo_otro', 80)->nullable()->after('tipo_organismo');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn('tipo_organismo_otro');
        });
    }
};
