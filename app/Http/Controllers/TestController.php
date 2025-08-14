<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Faculty;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Department;
use App\Models\Enrollment;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\StudentAcademicHistory;

class TestController extends Controller
{
    public function query1()
    {
        $student_sub = DB::table('students as s')
            ->leftJoin('enrollments as e', 's.id', '=','e.student_id')
            ->leftJoin('courses as c', 'e.course_id', '=', 'c.id')
            ->leftJoin('course_subjects as cs', 'c.id', '=', 'cs.course_id')
            ->leftJoin('subjects as sub', 'cs.subject_id', '=', 'sub.id')
            ->leftJoin('student_academic_histories as sah',function($join){
                $join->on('e.student_id', '=', 'sah.student_id')
                    ->on('cs.semester', '=', 'sah.semester');
            })
            ->select(
                DB::raw("concat(s.first_name,' ',s.last_name) as student_name"),
                'c.course_name',
                'sub.subject_name',
                'sah.sgpa',
                'sah.semester'
            );

        $semester_order =  DB::query()
            ->fromSub($student_sub,'')
            ->select(
                '*',
                DB::raw('row_number() over (PARTITION by student_name order by semester) as semester_order')
            );
            
        $semester_gap =  DB::query()
            ->fromSub($semester_order,'')
            ->select(
                'student_name',
                'course_name',
                'subject_name',
                'sgpa',
                'semester',
                'semester_order',
                DB::raw('semester - lag(semester) over (partition by student_name order by semester) as semester_gap'),
            );

        $subject_sequence =  DB::query()
            ->fromSub($semester_gap,'')
            ->select(
                'student_name',
                'course_name',
                DB::raw("GROUP_CONCAT(subject_name order by semester SEPARATOR '->') as subject_sequence"),
                DB::raw("GROUP_CONCAT(sgpa order by semester SEPARATOR '->') as performance_sequence"),
                DB::raw("COALESCE(GROUP_CONCAT(DISTINCT case when sgpa < 6.0 then subject_name end SEPARATOR ', '),'none') as struggle_subjects"),
                DB::raw("Substring_index(GROUP_CONCAT(sgpa order by semester),',',1) as first_sgpa"),
                DB::raw("Substring_index(GROUP_CONCAT(sgpa order by semester),',',-1) as last_sgpa"),
                DB::raw("round(avg(sgpa),2) avg_performance"),
                DB::raw("MAX(case when semester_gap = 0 then 'Yes' else NULL end) as time_gap")
            )
            ->groupBy('student_name','course_name');

        $result = DB::query()
            ->fromSub($subject_sequence,'')
            ->select(
                'student_name',
                'course_name',
                'subject_sequence',
                'performance_sequence',
                'struggle_subjects',
                'avg_performance',
                DB::raw("case when time_gap = 'Yes' then '1 Semester' else 'None' end as time_gaps"),
                DB::raw("case when last_sgpa >= first_sgpa - 0.5 then 'Consistent'
                        when last_sgpa > first_sgpa + 0.5 then 'Recovering'
                        else 'Declining' end as leaning_trend"),
                DB::raw("case when avg_performance > 8.0 then 'Advance Courses'
                        when avg_performance < 6.5 and struggle_subjects != 'none' then 'Extra Tutorial'
                        else 'Subject Review' end as recommended_action"),
            )
            ->get();

        return $result;
    }

    public function query2()
    {
        $faculties = Faculty::with([
            'department',
            'faculty_assignments.course_subject.course.enrollments.student.academic_history'
        ])->get();

        $result = $faculties->filter(function ($faculty) {
            foreach ($faculty->faculty_assignments as $assignment) {
                if (!$assignment->academic_year || !$assignment->academic_year->is_current) {
                    continue;
                }

                $courseSubject = $assignment->course_subject;

                foreach ($courseSubject->course->enrollments as $enrollment) {
                    $student = $enrollment->student;
                    if (!$student) {
                        continue;
                    }

                    foreach ($student->academic_history as $history) {
                        if ($history->class === 'Distinction') {
                            return true; 
                        }
                    }
                }
            }

            return false; 
        })->map(function ($faculty) {
            return [
                'first_name' => $faculty->first_name,
                'last_name' => $faculty->last_name,
                'email' => $faculty->email,
                'department_name' => $faculty->department->department_name ?? null
            ];
        })->values();

        return $result;
    }
    
    public function query3()
    {
        $courses = Course::with([
            'department',
            'enrollments.student',
            'enrollments.academic_year',
            'course_subjects.subject'
        ])->get();

        $result = $courses->map(function($course) {
            $currentEnrollments = $course->enrollments->filter(function($enrollment) {
                return $enrollment->academic_year && $enrollment->academic_year->is_current;
            });

            $academicYear = $currentEnrollments->first()->academic_year->name;

            $enrolledStudents = $currentEnrollments->map(function($enrollment) {
                $student = $enrollment->student;
                return $student->first_name . ' ' . $student->last_name;
            })->filter()->implode('; ');

            $subjects = $course->course_subjects->map(function($cs) {
                return $cs->subject->subject_name;
            })->filter()->implode(', ');

            if($currentEnrollments->count() >= 2){
                return [
                    'course_name' => $course->course_name,
                    'department_name' => $course->department->department_name,
                    'academic_year' => $academicYear,
                    'total_enrolled' => $currentEnrollments->count(),
                    'enrolled_students' => $enrolledStudents,
                    'course_subjects' => $subjects
                ];
            }
        })->filter()->values();

        return $result;

    }

    public function query4()
    {
        $students = Student::with([
            'enrollment.course.department',
            'academic_history'
        ])->get();

        $department_averages=[];

        $students->each(function ($student) use (&$department_averages) {
            $department = $student->enrollment->course->department;

            if ($department && $student->academic_history->isNotEmpty()) {
                $deptName = $department->department_name;

                if (!isset($department_averages[$deptName])) {
                    $department_averages[$deptName] = collect();
                }

                $avg_sgpa = $student->academic_history->avg('sgpa');
                $department_averages[$deptName]->push($avg_sgpa);
            }
        });

        $department_averages = collect($department_averages)->map(function ($sgpaList) {
            return round($sgpaList->avg(), 2);
        });

        $result = $students->map(function($student) use ($department_averages){            
            $department = $student->enrollment->course->department;

            $academic_history = $student->academic_history;

            $avg_sgpa = $academic_history->avg('sgpa');
            $avg_sgpa = round($avg_sgpa,2);

            $department_avg = $department_averages[$department->department_name];

            if($avg_sgpa > $department_avg)
            {
                return [
                    'first_name' => $student->first_name,
                    'last_name' => $student->last_name,
                    'email' => $student->email,
                    'department_name' => $department->department_name,
                    'sgpa' => $avg_sgpa,
                    'class' => $academic_history->first()->class,
                    'department_avg' => $department_avg,
                ];
            }            

        })->filter()->values();

        return $result;

    }

    public function query5()
    {
        $students = Student::with('enrollment.course.department')->where('status', 'active')->get();
        $result1 = $students->map(function ($student) {
            return [
                'person_type' => 'Student',
                'full_name' => $student->first_name . ' ' . $student->last_name,
                'email' => $student->email,
                'phone' => $student->phone,
                'department_name' => $student->enrollment->course->department->department_name,
                'joining_date' => 'N/A',
                'status' => $student->status,
            ];
        });

        $faculties = Faculty::with('department')->where('status', 'active')->get();
        $result2 = $faculties->map(function ($faculty) {
            return [
                'person_type' => 'Faculty',
                'full_name' => $faculty->first_name . ' ' . $faculty->last_name,
                'email' => $faculty->email,
                'phone' => $faculty->phone,
                'department_name' => $faculty->department->department_name,
                'joining_date' => $faculty->joining_date,
                'status' => $faculty->status,
            ];
        });

        $result = $result1->merge($result2)->sortBy('department_name')->values();

        return $result;

    }
        public function query6()
    {
        $courses = Course::with([
            'department',
            'enrollments.student.academic_history'
        ])->get();

        $result = $courses->map(function($course) {

            $semesterSgpas = [];

            foreach ($course->enrollments as $enrollment) {
                $student = $enrollment->student;
                if ($student && $student->academic_history) {
                    foreach ($student->academic_history as $history) {
                        if ($history->semester) {
                            $semesterSgpas[$history->semester][] = $history->sgpa;
                        }
                    }
                }
            }

            if (empty($semesterSgpas)) {
                return null;
            }

            $semesterAverages = collect($semesterSgpas)->map(function ($sgpas) {
                return round(collect($sgpas)->avg(), 2);
            });

            $overallAvg = round($semesterAverages->avg(), 2);
            $highest = $semesterAverages->max();
            $lowest = $semesterAverages->min();
            $variance = round($highest - $lowest, 2);

            if ($variance <= 1.0) {
                return null;
            }

            return [
                'course_name' => $course->course_name,
                'department_name' => $course->department->department_name,
                'semesters_offered' => $course->semesters,
                'overall_avg_sgpa' => $overallAvg,
                'highest_semester_avg' => $highest,
                'lowest_semester_avg' => $lowest,
                'sgpa_variance' => $variance,
            ];

        })->filter()->values();

        return $result;
    }

    public function query7()
    {
        $faculties = Faculty::with(['department','faculty_assignments'])->get();

        $result = $faculties->map(function($faculty){
            $currentYearAssignments = $faculty->faculty_assignments->filter(function ($assignment) {
                return $assignment->academic_year && $assignment->academic_year->is_current;
            });

            if ($currentYearAssignments->isNotEmpty()) {
                return [
                    'first_name' => $faculty->first_name,
                    'last_name' => $faculty->last_name,
                    'department_name' => $faculty->department->department_name,
                    'email' => $faculty->email,
                    'total_subject' => $currentYearAssignments->count(),
                ];
            }   

        })->filter()->values();

        return $result;

    }
        public function query8()
    {
        $students = Student::with(['enrollment.course', 'academic_history'])
            ->where('status', 'active')
            ->get();

        $allRecords = collect();

        foreach ($students as $student) {
            $course = $student->enrollment->course;

            foreach ($student->academic_history as $history) {
                if ($course && $history) {
                    $allRecords->push([
                        'first_name' => $student->first_name,
                        'last_name' => $student->last_name,
                        'course_name' => $course->course_name,
                        'semester' => $history->semester,
                        'sgpa' => $history->sgpa,
                        'class' => $history->class,
                    ]);
                }
            }
        }

        $grouped = $allRecords->groupBy(function ($item) {
            return $item['course_name'] . '||' . $item['semester'];
        });

        $ranked = collect();

        foreach ($grouped as $group) {
            $sorted = $group->sortByDesc('sgpa')->values();

            $prevSgpa = null;
            $rank = 0;
            $denseRank = 0;

            foreach ($sorted as $index => $item) {
                $rank = $index + 1;

                if ($prevSgpa === null || $item['sgpa'] != $prevSgpa) {
                    $denseRank++;
                }

                $item['sgpa_rank'] = $rank;
                $item['dense_rank'] = $denseRank;

                $prevSgpa = $item['sgpa'];

                $ranked->push($item);
            }
        }

        return $ranked->sortBy([
            ['semester', 'asc'],
            ['course_name', 'asc'],
            ['sgpa', 'desc']
        ])->values();

    }


    public function query9()
    {
        $departments = Department::with('faculties','courses.enrollments')->get();

        $result = $departments->map(function($dept){

            $enrollments = collect();
            $students = collect();
            foreach($dept->courses as $course)
            {
                foreach($course->enrollments as $enrollment)
                {
                    $enrollments->push($enrollment);
                    $students->push($enrollment->student);
                }
            }

            $avg_sgpa = $students->map(function($student){
                return $student->academic_history->avg('sgpa');
            })->avg();

            $avg_sgpa = round($avg_sgpa, 2);

            $class_count = function($students,$class){
                return $students->sum(fn($student) => 
                    $student->academic_history->where('class', $class)->count()
                );
            };

            $distinction_count = $class_count($students,'Distinction');
            $first_class_count = $class_count($students,'First class');
            $second_class_count = $class_count($students,'Second class');

            $total = $distinction_count + $first_class_count + $second_class_count;
            $distinction_percentage = $total > 0 ? round(($distinction_count * 100) / $total, 2) : 0;

            $department_grade = null;

            if($avg_sgpa >= 8) {
                $department_grade = 'Excellent';
            }
            elseif($avg_sgpa >=7 and $avg_sgpa < 8) {
                $department_grade = 'Very Good';
            }
            elseif($avg_sgpa < 7) {
                $department_grade = 'Good';
            }

            return [
                'department_name' => $dept->department_name,
                'total_students' => $enrollments->count(),
                'total_faculty' => $dept->faculties->count(),
                'avg_sgpa' => $avg_sgpa,
                'distinction_count' => $distinction_count,
                'first_class_count' => $first_class_count,
                'second_class_count' => $second_class_count,
                'distinction_percentage' => $distinction_percentage,
                'department_grade' => $department_grade,
            ];
        });

        return $result;

    }

    public function query10()
    {
        $courses = Course::with([
            'department',
            'enrollments.student',
            'course_subjects.faculty_assignments',
        ])->get();

        $courseCounts = $courses->map(function ($course) {
            $studentIds = $course->enrollments->pluck('student.id')->unique();
            
            $facultyIds = $course->course_subjects->flatMap(function ($cs) {
                    return $cs->faculty_assignments;
                })->pluck('faculty_id')->unique();

            $facultyCount = $facultyIds->count();
            $studentCount = $studentIds->count();
            
            $ratio = $facultyCount > 0 ? round($studentCount / $facultyCount, 2) : 0;

            return [
                'course_id' => $course->id,
                'course_name' => $course->course_name,
                'department_name' => $course->department->department_name ?? null,
                'faculty_count' => $facultyCount,
                'student_count' => $studentCount,
                'student_faculty_ratio' => $ratio,
                'students' => $course->enrollments->pluck('student')->unique('id')->values(),
            ];
        });

        $institutionalAvg = $courseCounts->avg('student_faculty_ratio');

        $filteredCourses = $courseCounts->filter(function ($course) use ($institutionalAvg) {
            return $course['student_faculty_ratio'] < $institutionalAvg;
        });

        $result = $filteredCourses->flatMap(function ($course) {
            return $course['students']->map(function ($student) use ($course) {
                return [
                    'first_name' => $student->first_name,
                    'last_name' => $student->last_name,
                    'course_name' => $course['course_name'],
                    'department_name' => $course['department_name'],
                    'faculty_count' => $course['faculty_count'],
                    'student_count' => $course['student_count'],
                    'student_faculty_ratio' => $course['student_faculty_ratio'],
                ];
            });
        })->values();

        return $result;        

    }    
    
    public function query11()
    {
        $subjects = Subject::with([
            'course_subjects.course.department',
            'course_subjects.course.enrollments.student.academic_history'
        ])->get();

        $result = $subjects->map(function($subject) {
            $courses = $subject->course_subjects->pluck('course')->unique()->values();
            $enrollments = $courses->flatMap(function($course){
                return $course->enrollments->unique();
            });

            $avg_sgpa = $enrollments->map(function($enrollment) {
                return $enrollment->student->academic_history
                    ->where('academic_year_id', $enrollment->academic_year_id)
                    ->where('semester', $enrollment->semester)
                    ->avg('sgpa');
            })->avg();

            $engineering_students = $enrollments->filter(function($enrollment) {  
                return Str::contains(strtolower($enrollment->course->department->department_name),'engineering');
            })->count();

            $science_students = $enrollments->filter(function($enrollment) {  
                return Str::contains(strtolower($enrollment->course->department->department_name),'science');
            })->count();

            $arts_students = $enrollments->filter(function($enrollment) {  
                return Str::contains(strtolower($enrollment->course->department->department_name),'arts');
            })->count();

            $course_distribution = $subject->course_subjects->map(function($cs) {
                $student_count = $cs->course->enrollments
                    ->where('semester', $cs->semester)
                    ->unique('student_id')
                    ->count();

                return $cs->course->course_name . ' (' . $student_count . ')';
            })->unique()->implode(', ');

            return [
                'subject_name' => $subject->subject_name,
                'total_students' => $enrollments->count(),
                'avg_sgpa' => round($avg_sgpa, 2),
                'engineering_students' => $engineering_students,
                'science_students' => $science_students,
                'arts_students' => $arts_students,
                'course_distribution' => $course_distribution
            ];
        })->sortBy('subject_name')->values();

        return $result;

    }

        
    public function query12()
    {
        $students = Student::with([
            'enrollment.course.department',
            'enrollment.course.course_subjects.subject',
            'enrollment.course.course_subjects.faculty_assignments.faculty',
            'academic_history'
        ])->get();


        $result = $students->map(function($student){

            $course = $student->enrollment->course;

            $faculties = $course->course_subjects->flatMap(function ($cs) {
                return $cs->faculty_assignments->map(function ($fa) use ($cs) {
                    return $fa->faculty->first_name . ' ' . $fa->faculty->last_name . ' (' . $cs->subject->subject_name . ')';
                });
            })->unique()->implode('; ');

            $overall_sgpa = $student->academic_history->avg('sgpa');
            $best_sgpa = $student->academic_history->max('sgpa');
            $worst_sgpa = $student->academic_history->min('sgpa');

            $semester_performance = $student->academic_history->map(function($history){
                return 'Sem '. $history->semester . ': ' . $history->sgpa . ' (' . $history->class . ')';
            })->implode(' | ');

            return [
                'student_name' => $student->first_name.' '.$student->last_name,
                'email' => $student->email,
                'course_name' => $course->course_name,
                'department_name' => $course->department->department_name,
                'overall_sgpa' => round($overall_sgpa,2),
                'best_sgpa' => $best_sgpa,
                'worst_sgpa' => $worst_sgpa,
                'semester_performance' => $semester_performance,
                'taught_by_faculty' => $faculties,
            ];
        });

        $result = $result->filter(function($row){
            return $row['overall_sgpa'] > 8.0;
        });

        return $result;
    }

    public function query13()
    {
        $students = Student::with([
            'enrollment.course.course_subjects',
            'enrollment.course.department',
            'academic_history.academic_year'
        ])->where('status','active')->get();

        $result = $students->map(function($student){

            $course = $student->enrollment->course;

            $current_academics = $student->academic_history
            ->where('academic_year.is_current', true)
            ->where('semester',$student->enrollment->semester)
            ->first();

            $current_semester = optional($current_academics)->semester;
            $current_sgpa = optional($current_academics)->sgpa;

            $previous_semester = $current_semester > 0 ? $current_semester - 1 : null;
            $previous = $student->academic_history->where('semester',$previous_semester)->first();
            $previous_sgpa = optional($previous)->sgpa;

            $sgpa_drop = ($previous_sgpa != null && $current_sgpa != null) ? $previous_sgpa - $current_sgpa : null;

            $total_subjects = $course->course_subjects->where('semester', $current_semester)->unique('id');

            return [
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'email' => $student->email,
                'course' => $course->course_name,
                'department' => $course->department->department_name,
                'previous_semester' => $current_semester > 0 ? $current_semester - 1 : null,
                'previous_sgpa' => $previous_sgpa,
                'current_semester' => $current_semester,
                'current_sgpa' => $current_sgpa,
                'sgpa_drop' => round($sgpa_drop,2),
                'current_subjects' => $total_subjects->count()
            ];
        })->filter(function($row){
            return $row['sgpa_drop'] > 1.0;
        })->values();

        return $result;

    }
        
    public function query14()
    {
        $faculties = Faculty::with([
                'faculty_assignments',
                'department',
            ])->get();

            $faculty_hrs = $faculties->map(function($faculty){
                return [
                    'faculty_id' => $faculty->id,
                    'department' => $faculty->department->department_name,
                    'faculty_hrs' => $faculty->faculty_assignments->count() * 4,
                ];
            });

            $avg_dept_hrs = $faculty_hrs->groupBy('department')->map(function ($group) {
                return $group->pluck('faculty_hrs')->avg();
            });

            $result = $faculties->map(function($faculty) use ($avg_dept_hrs){

                $course_subjects = $faculty->faculty_assignments->pluck('course_subject');

                $courses = $course_subjects->pluck('course')->unique('id');

                $departments_teaching = $courses->map(function($course){
                    return $course->department->department_name;
                })->unique()->implode(', ');
                
                $students_count = $courses->map(function($course){
                    return $course->enrollments->count();
                })->sum();

                $estimated_hours = $course_subjects->count() * 4;

                $dept_avg_hours = $avg_dept_hrs[$faculty->department->department_name] ?? 0;

                return [
                    'faculty_name' => $faculty->first_name . ' ' . $faculty->last_name,
                    'primary_department' => $faculty->department->department_name,
                    'departments_teaching' => $departments_teaching,
                    'total_subjects' => $course_subjects->count(),
                    'total_courses' => $courses->count(),
                    'total_students' => $students_count,
                    'estimated_hours' => $estimated_hours,
                    'workload_status' => $estimated_hours > $dept_avg_hours ? 'Above Average' : ($estimated_hours < $dept_avg_hours ? 'Below Average' : 'Average'),
                    'avg_dept_hours' => $dept_avg_hours,
                ];
            });

            return $result;
    }
        
    public function query15()
    {
        $courses = Course::with([
            'department',
            'enrollments.academic_year',
        ])->get();

        $result = $courses->map(function ($course) {

            $academic_years = $course->enrollments
                ->pluck('academic_year')
                ->filter()
                ->unique('id') 
                ->sortBy('year_start') 
                ->values();

            $prevRate = null;

            return $academic_years->map(function ($ac) use ($course, &$prevRate) {
                $enrollments = $course->enrollments->filter(function ($enroll) use ($ac) {
                    return $enroll->academic_year && $enroll->academic_year->id === $ac->id;
                });

                $students = $enrollments->pluck('student');

                $class_count = function ($students, $class) {
                    return $students->filter(function ($student) use ($class) {
                        return $student->academic_history->contains('class', $class);
                    })->count();
                };

                $distinction_count = $class_count($students, 'Distinction');
                $distinction_rate = ($distinction_count * 100) / max(1, $students->count());

                $first_class_count = $class_count($students, 'First class');
                $first_class_rate = ($first_class_count * 100) / max(1, $students->count());

                $second_class_count = $class_count($students, 'Second class');
                $second_class_rate = ($second_class_count * 100) / max(1, $students->count());

                $completed = $students->where('status', '!=', 'dropped')->count();

                $overall_pass_rate = ($completed * 100) / max(1, $students->count());

                $trend = 'Stable';
                if (!is_null($prevRate)) {
                    if ($overall_pass_rate > $prevRate) {
                        $trend = 'Improving';
                    } elseif ($overall_pass_rate < $prevRate) {
                        $trend = 'Declining';
                    }
                }
                $prevRate = $overall_pass_rate;

                return [
                    'course_name'       => $course->course_name,
                    'department_name'   => $course->department->department_name,
                    'academic_year'     => $ac->name,
                    'total_enrolled'    => $students->count(),
                    'completed'         => $completed,
                    'dropped'           => $students->where('status', 'dropped')->count(),
                    'distinction_rate'  => round($distinction_rate, 2),
                    'first_class_rate'  => round($first_class_rate, 2),
                    'second_class_rate' => round($second_class_rate, 2),
                    'overall_pass_rate' => round($overall_pass_rate, 2),
                    'completion_trend'  => $trend,
                ];
            });
        })->flatten(1)->values();

        return $result;

    }
       
    public function query16()
    {
        $pre_subjects = Subject::with([
            'course_subjects.course.enrollments.student.academic_history',
        ])->whereIn('subject_name', ['Programming', 'Math', 'Signals', 'Database'])->get();

        $mapping = [
            'Programming' => 'Advance Programming',
            'Math' => 'Math II',
            'Signals' => 'Analog and Digital Signals',
            'Database' => 'Relational Database',
        ];

        $records = collect();
            
        foreach ($pre_subjects as $pre) {
            $advanced_name = $mapping[$pre->subject_name] ?? null;

            foreach ($pre->course_subjects as $cs) {
                $course = $cs->course;
                
                $advanced_cs_list = $course->course_subjects
                    ->where('semester', '>', $cs->semester)
                    ->filter(fn($acs) => $acs->subject->subject_name === $advanced_name);

                foreach ($advanced_cs_list as $acs) {
                    foreach ($cs->course->enrollments as $pre_enroll) {
                        $student_id = $pre_enroll->student_id;

                        $prereq_history = $pre_enroll->student->academic_history
                            ->where(fn($history) =>
                                $history->academic_year_id == $pre_enroll->academic_year_id && $history->semester == $cs->semester
                            )->first();

                        $adv_enroll = $acs->course->enrollments
                            ->where(fn($enroll) =>
                                $enroll->student_id == $student_id && $enroll->semester == $acs->semester
                            )->first();

                        $adv_history = null;
                        if ($adv_enroll && $adv_enroll->student && $adv_enroll->student->academic_history) {
                            $adv_history = $adv_enroll->student->academic_history
                                ->where(fn($history) =>
                                    $history->academic_year_id == $adv_enroll->academic_year_id && $history->semester == $acs->semester
                                )->first();
                        }

                        if ($prereq_history && $adv_history) {
                            $records->push([
                                'prerequisite_subject' => $pre->subject_name,
                                'advanced_subject' => $advanced_name,
                                'student_id' => $student_id,
                                'prereq_class' => $prereq_history->class,
                                'advanced_sgpa' => $adv_history->sgpa,
                            ]);
                        }
                    }
                }
            }
        }

        $result = $records->groupBy(fn($item) => $item['prerequisite_subject'] . '|' . $item['advanced_subject'])
            ->map(function ($group) {
                $prereq_subject = $group->first()['prerequisite_subject'];
                $adv_subject = $group->first()['advanced_subject'];

                $distinction = $group->where('prereq_class', 'Distinction');
                $first_class = $group->where('prereq_class', 'First class');
                $second_class = $group->where('prereq_class', 'Second class');

                $avg_dist = round($distinction->avg('advanced_sgpa'), 2);
                $avg_fc = round($first_class->avg('advanced_sgpa'), 2);

                if ($avg_dist >= 9 && ($avg_dist - $avg_fc) > 0.8) {
                    $correlation = 'Strong Positive';
                } elseif ($avg_dist >= 8 && ($avg_dist - $avg_fc) > 0.4) {
                    $correlation = 'Positive';
                } else {
                    $correlation = 'Moderate Positive';
                }

                return [
                    'prerequisite_subject' => $prereq_subject,
                    'advanced_subject' => $adv_subject,
                    'students_with_distinction_prereq' => $distinction->count(),
                    'avg_advanced_sgpa' => $avg_dist,
                    'students_with_firstclass_prereq' => $first_class->count(),
                    'avg_advanced_sgpa_fc' => $avg_fc,
                    'students_with_secondclass_prereq' => $second_class->count(),
                    'avg_advanced_sgpa_sc' => round($second_class->avg('advanced_sgpa'), 2),
                    'performance_correlation' => $correlation,
                ];
            })
            ->values();

        return $result;
    }

    public function query17()
    {
        $enrollments = Enrollment::with(['student.academic_history','course.department','academic_year'])->get();
        $performance_data = $enrollments->groupBy(fn($e) => $e->course->department->department_name)->map(function($data){
            $student_count = $data->flatMap(function($e){
                return $e->student->academic_history
                    ->where('academic_year_id', $e->academic_year_id)
                    ->where('semester', $e->semester)
                    ->pluck('student_id');
            })->unique()->count();

            $avg_sgpa = $data->flatMap(function($e) {
                return $e->student->academic_history
                    ->where('academic_year_id', $e->academic_year_id)
                    ->where('semester', $e->semester)
                    ->unique('student_id')
                    ->pluck('sgpa');
            });

            $distinction_count = $data->flatMap(function($e){
                return $e->student->academic_history
                    ->where('academic_year_id', $e->academic_year_id)
                    ->where('semester', $e->semester)
                    ->where('class','Distinction');
            })->count();

            $pass_count = $avg_sgpa->filter(fn($sgpa) => $sgpa >= 4.0)->count();

            return [
                'department_name' => $data->first()->course->department->department_name,
                'course_name' => $data->first()->course->course_name,
                'semester' => $data->first()->semester,
                'academic_year' => $data->first()->academic_year->name,
                'gender' =>$data->first()->student->gender,
                'student_count' => $student_count,
                'avg_sgpa' => round($avg_sgpa->avg(),2),
                'distinction_pct' => $student_count ? ($distinction_count * 100) / $student_count : 0,
                'pass_rate' => $student_count ? ($pass_count * 100) / $student_count : 0
            ];
        });

        $aggregated_data = $performance_data->flatMap(function($data) {
            $base = [
                'department_name' => $data['department_name'],
                'course_name' => $data['course_name'],
                'semester' => $data['semester'],
                'academic_year' => $data['academic_year'],
                'gender' => $data['gender'],
                'student_count' => $data['student_count'],
                'avg_sgpa' => $data['avg_sgpa'],
                'distinction_pct' => $data['distinction_pct'],
                'pass_rate' => $data['pass_rate'],
            ];

            $detail = array_merge(['dimension_type' => 'Detail', 'total_metric' => 'DETAIL'], $base);
            $gender = array_merge(['dimension_type' => 'Gender', 'total_metric' => 'GENDER_TOTAL'], $base);
            $semester = array_merge(['dimension_type' => 'Semester', 'total_metric' => 'SEM_TOTAL'], $base);
            $course = array_merge(['dimension_type' => 'Course', 'total_metric' => 'COURSE_TOTAL'], $base);
            $department = array_merge(['dimension_type' => 'Department', 'total_metric' => 'DEPT_TOTAL'], $base);

            return [$detail, $gender, $semester, $course, $department];
        });

        return $aggregated_data->sortBy([
            ['dimension_type', 'desc'],
            ['department_name', 'desc'],
            ['course_name', 'desc'],
            ['semester', 'desc'],
            ['academic_year', 'desc'],
            ['gender', 'desc'],
        ])->values();

    }

    public function query18()
    {
        $students = Student::with(['enrollment.course.course_subjects','academic_history'])->get();

        $allRecords = collect();

        $student_sub = $students->map(function($student) use(&$allRecords){
            $course = $student->enrollment->course;
            $course_subjects = $course->course_subjects;

            foreach ($student->academic_history as $history) {
                if ($course && $history) {
                    foreach($course_subjects as $cs)
                    {
                        if($history->semester == $cs->semester)
                        {
                            $allRecords->push([
                                'student_name' => $student->first_name. ' ' .$student->last_name,
                                'course_name' => $course->course_name,
                                'subject' => $cs->subject->subject_name,
                                'semester' => $history->semester,
                                'sgpa' => $history->sgpa,
                            ]);
                        }
                    }
                }
            }
        });

        $semester_order = $allRecords->groupBy('student_name')->flatMap(function ($records) {
            return $records->sortBy('semester')->values()->map(function ($record, $index) {
                $record['sem_order'] = $index + 1;
                return $record;
            });
        })->values();

        $semester_gap = $semester_order->groupBy('student_name')->flatMap(function ($records) {
            $prev_sem = null;
            return $records->map(function ($record) use (&$prev_sem) {
                $gap = $prev_sem !== null ? $record['semester'] - $prev_sem : null;
                $prev_sem = $record['semester'];
                $record['semester_gap'] = $gap;
                return $record;
            });
        })->values();

        $subject_sequence = $semester_gap->groupBy(fn($r) => $r['student_name'].'|'.$r['course_name'])
        ->map(function($records){
            $subjects = $records->pluck('subject_name')->implode('->');
            $sgpa = $records->pluck('sgpa')->implode('->');
            $struggle_subjects = $records->where('sgpa', '<', 6.0)->pluck('subject_name')->unique()->implode(', ') ?: 'none';
            $avg_sgpa = round($records->avg('sgpa'),2);
            $first_sgpa = $records->pluck('sgpa')->first();
            $last_sgpa = $records->pluck('sgpa')->last();
            $time_gap = $records->contains(fn($r) => $r['semester_gap'] === 0) ? '1 Semester' : 'None';

            $leaning_trend = ($last_sgpa >= $first_sgpa - 0.5) ? 'Consistent' : (($last_sgpa > $first_sgpa + 0.5) ? 'Recovering' : 'Declining') ;
            
            $recommended_action = $avg_sgpa >= 8.0 ? 'Advance Courses' : (($avg_sgpa < 6.5 && $struggle_subjects != 'none') ? 'Extra Tutorial' : 'Subject Review');

            return[
                'student_name' => $records->first()['student_name'],
                'course_name' => $records->first()['course_name'],
                'subject_sequence' => $subjects,
                'performance_sequence' => $sgpa,    
                'struggle_subjects' => $struggle_subjects,
                'avg_performance' => $avg_sgpa,
                'time_gap' => $time_gap,
                'leaning_trend' => $leaning_trend,
                'recommended_action' => $recommended_action,
            ];
        })->values();

        return $subject_sequence;

    }

    public function query19()
    {
        $faculties = Faculty::with(['department','faculty_assignments.course_subject.course.enrollments'])->get();

        $faculty_utilization = $faculties->map(function($faculty){
            $faculty_assignments = $faculty->faculty_assignments;
            $courses = $faculty_assignments->pluck('course_subject.course');
            $student_count = $courses->map(function($course){
                return $course->enrollments->count();
            })->sum();

            if($student_count >= 10 && $student_count <= 20){
                $recommendation = 'optimal';
                $priority_level = 'Low';
                $efficiency_score = '80%';
            }elseif($student_count < 10){
                $recommendation = 'Underutilized';
                $priority_level = 'Medium';
                $efficiency_score = '60%';
            }else{
                $recommendation = 'Overloaded';
                $priority_level = 'High';
                $efficiency_score = '50%';
            }

            return [
                'resource_type' => 'Faculty',
                'resource_name' => $faculty->first_name.' '.$faculty->last_name,
                'department_name' => $faculty->department->department_name,
                'utilization_metric' => '1 : '.$student_count.' ratio',
                'efficiency_score' => $efficiency_score,
                'recommendation' => $recommendation,
                'priority_level' => $priority_level,
            ];
        });

        $courses = Course::with(['department','enrollments'])->get();

        $course_utilization = $courses->map(function($course){
            $student_count = $course->enrollments->count();

            if($student_count >= 30){
                $recommendation = 'Optimal';
                $priority_level = 'Low';
                $efficiency_score = '80%';
            }elseif($student_count >= 15 && $student_count <= 29){
                $recommendation = 'Increase Enrollment';
                $priority_level = 'Medium';
                $efficiency_score = '60%';
            }elseif($student_count < 15){
                $recommendation = 'Low Enrollment';
                $priority_level = 'High';
                $efficiency_score = '40%';
            }

            return [
                'resource_type' => 'Course',
                'resource_name' => $course->course_name,
                'department_name' => $course->department->department_name,
                'utilization_metric' => $student_count.' student',
                'efficiency_score' => $efficiency_score,
                'recommendation' => $recommendation,
                'priority_level' => $priority_level,
            ];
        });

        $subjects = Subject::with(['course_subjects.course.department','course_subjects.course.enrollments.student.academic_history'])->get();

        $subject_utilization = $subjects->map(function($subject){
            $courses = $subject->course_subjects->pluck('course')->filter();
            $academicHistories = $courses->pluck('enrollments')->flatten()->pluck('student.academic_history')->flatten();

            $total = $academicHistories->count();
            $passCount = $academicHistories->where('sgpa', '>=', 4.0)->count();

            $utilization_metric = $total > 0 ? round(($passCount * 100) / $total, 2) : 0;

            if($utilization_metric > 80){
                $recommendation = 'Optimal';
                $priority_level = 'Low';
                $efficiency_score = '80%';
            }elseif($utilization_metric >= 60 && $utilization_metric <= 80){
                $recommendation = 'Moderate failure';
                $priority_level = 'Medium';
                $efficiency_score = '60%';
            }else{
                $recommendation = 'High failure';
                $priority_level = 'High';
                $efficiency_score = '45%';
            }
            
            return [
                'resource_type' => 'Subject',
                'resource_name' => $subject->subject_name,
                'department_name' => $courses->first()?->department?->department_name,
                'utilization_metric' => $total > 0 ? $utilization_metric . '% pass rate' : '0% pass rate',
                'efficiency_score' => $efficiency_score,
                'recommendation' => $recommendation,
                'priority_level' => $priority_level,
            ];
        });

        $departments = Department::with(['faculties','courses.enrollments'])->get();

        $department_utilization = $departments->map(function($dept){
            $courses = $dept->courses;
            $enrollments = $courses->pluck('enrollments')->flatten()->count();
            $faculties = $dept->faculties->count();

            $utilization_metric = round($faculties / ($enrollments / 100),2);

            if($utilization_metric >= 2 && $utilization_metric <= 3){
                $recommendation = 'Optimal';
                $priority_level = 'Low';
                $efficiency_score = '80%';
            }elseif($utilization_metric < 1){
                $recommendation = 'Need More Faculty';
                $priority_level = 'Medium';
                $efficiency_score = '60%';
            }else{
                $recommendation = 'Reduce faculty';
                $priority_level = 'High';
                $efficiency_score = '40%';
            }

            return [
                'resource_type' => 'Department',
                'resource_name' => $dept->department_name,
                'department_name' => $dept->department_name,
                'utilization_metric' => $utilization_metric. ' : 1 ratio',
                'efficiency_score' => $efficiency_score,
                'recommendation' => $recommendation,
                'priority_level' => $priority_level,
            ];
        });

        $result = $faculty_utilization->merge($course_utilization)->merge($subject_utilization)->merge($department_utilization)->sortBy(['resource_type','resource_name'])->values();

        return $result;
    }

}