<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    protected $fillable = ['subject_name'];

    public function course_subjects()
    {
        return $this->hasMany(CourseSubject::class);
    }
}
