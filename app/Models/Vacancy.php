<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vacancy extends Model
{
        use HasFactory;

    protected $fillable = [
        'program_id',
        'title',
        'description',
        'slots',
        'start_date',
        'end_date',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function applications()
    {
        return $this->hasMany(Application::class);
    }
}
