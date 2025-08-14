<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FacultyAssignment extends Model
{
    protected $fillable = ['faculty_id','course_subject_id','academic_year_id'];

    public $timestamps = false;

    public function faculty()
    {
        return $this->belongsTo(Faculty::class,'faculty_id');
    }

    public function course_subject()
    {
        return $this->belongsTo(CourseSubject::class,'course_subject_id');
    }

    public function academic_year()
    {
        return $this->belongsTo(AcademicYear::class,'academic_year_id');
    }
}
