<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cuando la Coordinación cierra una vacante externa por alguna irregularidad deja sus observaciones:
 * la empresa las ve y, si la reabre, la vacante vuelve a revisión en lugar de publicarse directo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programas', function (Blueprint $table) {
            $table->text('observaciones_cierre')->nullable()->after('motivo_rechazo');
        });
    }

    public function down(): void
    {
        Schema::table('programas', function (Blueprint $table) {
            $table->dropColumn('observaciones_cierre');
        });
    }
};
