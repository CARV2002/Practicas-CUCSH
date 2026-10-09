<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Program extends Model
{
    use HasFactory;

    protected $fillable = [
        'agreement_id',
        'name',
        'type',
        'description',
        'required_hours',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function agreement()
    {
        return $this->belongsTo(Agreement::class);
    }

    public function vacancies()
    {
        return $this->hasMany(Vacancy::class);
    }
}
