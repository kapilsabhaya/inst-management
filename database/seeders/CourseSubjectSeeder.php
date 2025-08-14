<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Subject;
use App\Models\CourseSubject;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class CourseSubjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $courses = [
            ['course_name' => 'Advance Computing', 'department_id' => 1, 'semesters' => 4],
            ['course_name' => 'Computer Engineering', 'department_id' => 1, 'semesters' => 6],
            ['course_name' => 'Electronics Engineering', 'department_id' => 2, 'semesters' => 6],
            ['course_name' => 'Digital Electronics', 'department_id' => 2, 'semesters' => 4],
            ['course_name' => 'Mechanical Engineering', 'department_id' => 3, 'semesters' => 4],
            ['course_name' => 'Thermal Engineering', 'department_id' => 3, 'semesters' => 4],
        ];

        $courseMap = [];
        foreach ($courses as $course) {
            $c = Course::firstOrCreate($course);
            $courseMap[$c->course_name] = $c->id;
        }

        $subjects = [
            'Math', 'Programming', 'Database', 'Signals', 'Microelectronics',
            'Circuits', 'Fluid Mechanics', 'Machine Learning', 'Thermodynamics',
            'Solid Mechanics', 'Electromagnetic Theory', 'Web Designing',
            'Advance Programming', 'Math II', 'Advance Physics', 'Relational Database',
            'Analog and Digital Signals', 'Android', 'Logics',
        ];

        $subjectMap = []; 
        foreach ($subjects as $subject) {
            $s = Subject::firstOrCreate(['subject_name' => $subject]);
            $subjectMap[$s->subject_name] = $s->id;
        }

        $course_subjects = [
            ['course_id' => $courseMap['Advance Computing'], 'subject_id' => $subjectMap['Math'], 'semester' => 1],
            ['course_id' => $courseMap['Advance Computing'], 'subject_id' => $subjectMap['Programming'], 'semester' => 2],
            ['course_id' => $courseMap['Computer Engineering'], 'subject_id' => $subjectMap['Math'], 'semester' => 1],
            ['course_id' => $courseMap['Computer Engineering'], 'subject_id' => $subjectMap['Database'], 'semester' => 2],
            ['course_id' => $courseMap['Advance Computing'], 'subject_id' => $subjectMap['Database'], 'semester' => 3],
            ['course_id' => $courseMap['Advance Computing'], 'subject_id' => $subjectMap['Advance Programming'], 'semester' => 4],
            ['course_id' => $courseMap['Computer Engineering'], 'subject_id' => $subjectMap['Math II'], 'semester' => 3],
            ['course_id' => $courseMap['Computer Engineering'], 'subject_id' => $subjectMap['Advance Programming'], 'semester' => 4],
            ['course_id' => $courseMap['Computer Engineering'], 'subject_id' => $subjectMap['Web Designing'], 'semester' => 5],
            ['course_id' => $courseMap['Computer Engineering'], 'subject_id' => $subjectMap['Android'], 'semester' => 6],
            ['course_id' => $courseMap['Electronics Engineering'], 'subject_id' => $subjectMap['Math'], 'semester' => 1],
            ['course_id' => $courseMap['Electronics Engineering'], 'subject_id' => $subjectMap['Signals'], 'semester' => 2],
            ['course_id' => $courseMap['Electronics Engineering'], 'subject_id' => $subjectMap['Electromagnetic Theory'], 'semester' => 3],
            ['course_id' => $courseMap['Electronics Engineering'], 'subject_id' => $subjectMap['Machine Learning'], 'semester' => 4],
            ['course_id' => $courseMap['Electronics Engineering'], 'subject_id' => $subjectMap['Advance Physics'], 'semester' => 5],
            ['course_id' => $courseMap['Electronics Engineering'], 'subject_id' => $subjectMap['Microelectronics'], 'semester' => 6],
            ['course_id' => $courseMap['Digital Electronics'], 'subject_id' => $subjectMap['Math'], 'semester' => 1],
            ['course_id' => $courseMap['Digital Electronics'], 'subject_id' => $subjectMap['Analog and Digital Signals'], 'semester' => 2],
            ['course_id' => $courseMap['Digital Electronics'], 'subject_id' => $subjectMap['Advance Physics'], 'semester' => 3],
            ['course_id' => $courseMap['Digital Electronics'], 'subject_id' => $subjectMap['Circuits'], 'semester' => 4],
            ['course_id' => $courseMap['Mechanical Engineering'], 'subject_id' => $subjectMap['Math'], 'semester' => 1],
            ['course_id' => $courseMap['Mechanical Engineering'], 'subject_id' => $subjectMap['Fluid Mechanics'], 'semester' => 2],
            ['course_id' => $courseMap['Mechanical Engineering'], 'subject_id' => $subjectMap['Solid Mechanics'], 'semester' => 3],
            ['course_id' => $courseMap['Mechanical Engineering'], 'subject_id' => $subjectMap['Math II'], 'semester' => 4],
            ['course_id' => $courseMap['Thermal Engineering'], 'subject_id' => $subjectMap['Thermodynamics'], 'semester' => 1],
            ['course_id' => $courseMap['Thermal Engineering'], 'subject_id' => $subjectMap['Logics'], 'semester' => 2],
            ['course_id' => $courseMap['Thermal Engineering'], 'subject_id' => $subjectMap['Fluid Mechanics'], 'semester' => 3],
            ['course_id' => $courseMap['Thermal Engineering'], 'subject_id' => $subjectMap['Solid Mechanics'], 'semester' => 4],
        ];

        foreach ($course_subjects as $cs) {
            CourseSubject::firstOrCreate([
                'course_id' => $cs['course_id'],
                'subject_id' => $cs['subject_id'],
                'semester' => $cs['semester'],
            ]);
        }
    }
}
