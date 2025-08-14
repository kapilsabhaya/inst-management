<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Student extends Model
{
    use HasFactory, Notifiable;

    protected $fillable = ['first_name','last_name','gender','dob','email','phone','address','status'];

    public function enrollment()
    {
        return $this->hasOne(Enrollment::class);
    }

    public function academic_history()
    {
        return $this->hasMany(StudentAcademicHistory::class);
    }

}
