<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Faculty extends Model
{
    use HasFactory, Notifiable;

    protected $fillable = ['first_name','last_name','gender','email','phone','address','joining_date','department_id','status'];

    public function department()
    {
        return $this->belongsTo(Department::class,'department_id');
    }

    public function faculty_assignments()
    {
        return $this->hasMany(FacultyAssignment::class);
    }
}
