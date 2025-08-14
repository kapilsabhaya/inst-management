<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseSubject extends Model
{
    protected $fillable = ['course_id','subject_id','semester'];

    public function course()
    {
        return $this->belongsTo(Course::class,'course_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class,'subject_id');
    }

    public function faculty_assignments()
    {
        return $this->hasMany(FacultyAssignment::class);
    }

}
