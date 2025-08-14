<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Enrollment extends Model
{
    protected $fillable = ['enrollment_id','student_id','course_id','academic_year_id','semester','enrollment_date'];

    public function student()
    {
        return $this->belongsTo(Student::class,'student_id');
    }

    public function course()
    {
        return $this->belongsTo(Course::class,'course_id');
    }

    public function academic_year()
    {
        return $this->belongsTo(AcademicYear::class,'academic_year_id');
    }
}
