<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Application extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'vacancy_id',
        'application_status_id',
        'application_date',
        'observations',
    ];

    protected $casts = [
        'application_date' => 'date',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function vacancy()
    {
        return $this->belongsTo(Vacancy::class);
    }

    public function status()
    {
        return $this->belongsTo(
            ApplicationStatus::class,
            'application_status_id'
        );
    }

    public function assignment()
{
    return $this->hasOne(Assignment::class);
}
}
