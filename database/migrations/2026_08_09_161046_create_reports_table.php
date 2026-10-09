<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
             $table->foreignId('assignment_id')
                ->constrained('assignments')
                ->cascadeOnDelete();

            $table->unsignedInteger('report_number');

            $table->date('start_date');

            $table->date('end_date');

            $table->unsignedInteger('reported_hours')->default(0);

            $table->string('pdf_file')->nullable();

            $table->string('status')->default('pending');

            $table->timestamps();

            $table->unique([
                'assignment_id',
                'report_number'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
