<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'legal_name',
        'tax_id',
        'address',
        'phone',
        'email',
        'website',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];


    public function users()
    {
        return $this->hasMany(CompanyUser::class);
    }

    public function agreements()
    {
        return $this->hasMany(Agreement::class);
    }
}
