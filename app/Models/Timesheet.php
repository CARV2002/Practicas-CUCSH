<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Timesheet extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_id',
        'work_date',
        'start_time',
        'end_time',
        'activity',
        'hours',
    ];

    protected $casts = [
        'work_date' => 'date',
        'hours' => 'decimal:2',
    ];

    public function report()
    {
        return $this->belongsTo(Report::class);
    }
}
