<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationStatus extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public function applications()
    {
        return $this->hasMany(Application::class);
    }
}
