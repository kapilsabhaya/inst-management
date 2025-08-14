<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Department extends Model
{
    use HasFactory, Notifiable;

    protected $fillable = ['department_name'];

    public function courses()
    {
        return $this->hasMany(Course::class);
    }

    public function faculties()
    {
        return $this->hasMany(Faculty::class);
    }
}
