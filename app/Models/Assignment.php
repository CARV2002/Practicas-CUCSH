<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Assignment extends Model
{
     use HasFactory;

    protected $fillable = [
        'application_id',
        'tutor_id',
        'company_user_id',
        'start_date',
        'end_date',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    public function tutor()
    {
        return $this->belongsTo(Tutor::class);
    }

    public function companyUser()
    {
        return $this->belongsTo(CompanyUser::class);
    }

    public function reports()
    {
        return $this->hasMany(Report::class);
    }

}
