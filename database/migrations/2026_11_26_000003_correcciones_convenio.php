<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Datos de la solicitud que Vinculación devuelve a la empresa para corregir (además de los documentos):
 * { campo: { seccion, motivo, pedido_at, corregido_at|null } }. Solo esos campos quedan editables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('convenios', function (Blueprint $table) {
            $table->json('correcciones')->nullable()->after('plazas_estimadas');
        });
    }

    public function down(): void
    {
        Schema::table('convenios', function (Blueprint $table) {
            $table->dropColumn('correcciones');
        });
    }
};
