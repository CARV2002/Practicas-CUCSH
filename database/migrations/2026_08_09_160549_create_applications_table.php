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
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')
                ->constrained('students')
                ->cascadeOnDelete();

            $table->foreignId('vacancy_id')
                ->constrained('vacancies')
                ->cascadeOnDelete();

            $table->foreignId('application_status_id')
                ->constrained('application_statuses');

            $table->date('application_date');

            $table->text('observations')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'vacancy_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
