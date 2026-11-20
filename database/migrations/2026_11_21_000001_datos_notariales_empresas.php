<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Datos notariales capturados por la empresa en su formulario (escritura, fecha, notario y folio mercantil),
 * para que Vinculación no los escriba a mano y el borrador del convenio los rellene solo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('escritura_numero', 30)->nullable()->after('constitucion');
            $table->date('escritura_fecha')->nullable()->after('escritura_numero');
            $table->string('notario_nombre')->nullable()->after('escritura_fecha');
            $table->string('notaria_numero', 20)->nullable()->after('notario_nombre');
            $table->string('notaria_ciudad', 120)->nullable()->after('notaria_numero');
            $table->string('folio_mercantil', 60)->nullable()->after('notaria_ciudad');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn(['escritura_numero', 'escritura_fecha', 'notario_nombre', 'notaria_numero', 'notaria_ciudad', 'folio_mercantil']);
        });
    }
};
