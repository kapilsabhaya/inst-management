<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentAcademicHistory extends Model
{
    protected $fillable = ['student_id','academic_year_id','semester','sgpa','class'];

    public $timestamps = false;

    public function student()
    {
        return $this->belongsTo(Student::class,'student_id');
    }

    public function academic_year()
    {
        return $this->belongsTo(AcademicYear::class,'academic_year_id');
    }
}