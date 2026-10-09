<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'student_code',
        'career',
        'semester',
        'emergency_phone',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function applications()
{
    return $this->hasMany(Application::class);
}
}
