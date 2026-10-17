<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'assignment_id',
        'report_number',
        'start_date',
        'end_date',
        'reported_hours',
        'pdf_file',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function assignment()
    {
        return $this->belongsTo(Assignment::class);
    }

    public function timesheets()
    {
        return $this->hasMany(Timesheet::class);
    }
}
