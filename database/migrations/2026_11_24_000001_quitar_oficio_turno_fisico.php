<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El turno a las coordinaciones es 100 % digital: el «Oficio de turno académico» ya no se imprime ni se rastrea.
 * Se quitan sus registros del control de documentos físicos (sus movimientos se borran en cascada).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('documentos_fisicos')->where('tipo', 'oficio_turno')->delete();
    }

    public function down(): void
    {
        // No se restauran: eran papeles que ya no existen en el proceso
    }
};
