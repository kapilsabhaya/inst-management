<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    protected $fillable = ['course_name','department_id'];

    public function department()
    {
        return $this->belongsTo(Department::class,'department_id');
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function course_subjects()
    {
        return $this->hasMany(CourseSubject::class);
    }

}
