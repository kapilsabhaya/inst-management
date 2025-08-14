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
use App\Models\CourseSubject;
use Illuminate\Support\Facades\DB;
use App\Models\StudentAcademicHistory;

class TestController extends Controller
{
    public function query1()
    {
        ## Query 1: Department Performance Analysis
        /* Definition: Write a query to find all departments that have more than 5 active students enrolled in the current academic year, 
        along with their average SGPA. Only include departments where the average SGPA is above 7.0. Display the department name, total number of students, 
        average SGPA, and total number of courses offered by the department.  */

        $departments = Enrollment::from('enrollments as e')
            ->leftJoin('courses as c', 'e.course_id', '=', 'c.id')
            ->leftJoin('departments as d', 'c.department_id', '=', 'd.id')
            ->leftJoin('students as s', 'e.student_id', '=', 's.id')
            ->leftJoin('academic_years as ay', 'e.academic_year_id', '=', 'ay.id')
            ->leftJoin('student_academic_histories as sah', 'e.student_id', '=', 'sah.student_id')
            ->select(
                'd.department_name',
                DB::raw('count(distinct e.student_id) as total_students'),
                DB::raw('round(avg(sah.sgpa),2) as avg_sgpa'),
                DB::raw('COUNT(DISTINCT c.id) as total_courses')
            )
            ->where('s.status','active')
            ->where('ay.is_current',1)
            ->groupBy('d.id','d.department_name')
            ->having('total_students','>',5)
            ->having('avg_sgpa','>',7.0)
            ->get();

        return $departments;
    }

    public function query2()
    {
        ## Query 2: Faculty Teaching High Performers
        /* Definition: Find all faculty members who are currently teaching subjects to students who have achieved 'distinction' class in their academic performance.
        Display the faculty's first name, last name, email, and department name. Ensure no duplicate faculty records appear. */

        $faculties = Faculty::from('faculties as f')
            ->leftJoin('departments as d', 'f.department_id', '=', 'd.id')
            ->leftJoin('faculty_assignments as fa', 'f.id', '=', 'fa.faculty_id')
            ->leftJoin('academic_years as ay', 'fa.academic_year_id', '=', 'ay.id')
            ->leftJoin('course_subjects as cs', 'fa.course_subject_id', '=', 'cs.id')
            ->leftJoin('courses as c', 'cs.course_id', '=', 'c.id')
            ->leftJoin('enrollments as e', 'c.id', '=', 'e.course_id')
            ->leftJoin('student_academic_histories as sah', 'e.student_id', '=', 'sah.student_id')
            ->select(
                DB::raw('distinct f.first_name'),
                'f.last_name',
                'f.email',
                'd.department_name'
            )
            ->where('sah.class','Distinction')
            ->where('ay.is_current',1)
            ->get();

        return $faculties;
    }
    
    public function query3()
    {
        ## Query 3: Course Enrollment Summary with Student Names
        /* Definition: Create a comprehensive report showing course details along with all enrolled students and subjects. For each course in the current academic year, 
        display the course name, department, academic year, total enrolled students, a concatenated list of all student names (semicolon separated), and all subjects for that course (comma separated). 
        Only include courses with at least 2 enrolled students. */

        $courses = Course::from('courses as c')
            ->leftJoin('departments as d', 'c.department_id', '=', 'd.id')
            ->leftJoin('enrollments as e', 'c.id', '=', 'e.course_id')
            ->leftJoin('academic_years as ay', 'e.academic_year_id', '=', 'ay.id')
            ->leftJoin('students as s', 'e.student_id', '=', 's.id')
            ->leftJoin('course_subjects as cs', 'c.id', '=', 'cs.course_id')
            ->leftJoin('subjects as sub', 'cs.subject_id', '=', 'sub.id')
            ->select(
                'c.course_name',
                'd.department_name',
                'ay.name as academic_year',
                DB::raw('count(distinct e.student_id) as total_enrolled'),
                DB::raw("GROUP_CONCAT(DISTINCT CONCAT(s.first_name, ' ', s.last_name) SEPARATOR '; ') AS enrolled_students"),
                DB::raw("GROUP_CONCAT(DISTINCT CONCAT(sub.subject_name) SEPARATOR ', ') AS course_subjects")
            )
            ->where('ay.is_current',1)
            ->groupBy('c.course_name','d.department_name','ay.name')
            ->having('total_enrolled','>',2)
            ->get();

        return $courses;
    }

    public function query4()
    {
        ## Query 4: Students Above Department Average
        /* Definition: Find all students whose SGPA is higher than the average SGPA of their respective department. 
        Show student details, their SGPA, class grade, department name, and the department's average SGPA for comparison. */

        $students = Student::from('students as s')
            ->leftJoin('enrollments as e', 's.id', '=', 'e.student_id')
            ->leftJoin('courses as c', 'e.course_id', '=', 'c.id')
            ->leftJoin('departments as d', 'c.department_id', '=', 'd.id')
            ->leftJoin('student_academic_histories as sah', 's.id', '=', 'sah.student_id')
            ->select(
                's.id',
                DB::raw('MAX(s.first_name) as first_name'),
                DB::raw('MAX(s.last_name) as last_name'),
                DB::raw('MAX(s.email) as email'),
                DB::raw('MAX(d.department_name) as department'),
                DB::raw('ROUND(AVG(sah.sgpa), 2) as sgpa'),
                DB::raw('MAX(sah.class) as class'),
                DB::raw('(SELECT ROUND(AVG(sah2.sgpa), 2)
                        FROM student_academic_histories sah2
                        LEFT JOIN students s2 ON sah2.student_id = s2.id
                        LEFT JOIN enrollments e2 ON s2.id = e2.student_id
                        LEFT JOIN courses c2 ON e2.course_id = c2.id
                        LEFT JOIN departments d2 ON c2.department_id = d2.id
                        WHERE d2.department_name = MAX(d.department_name)
                        ) as department_average')
            )
            ->groupBy('s.id','d.department_name')
            ->havingRaw('sgpa > department_average')
            ->get();

        return $students;
    }

    public function query5()
    {
        ## Query 5: Combined Personnel Directory
        /* Definition: Create a unified directory of all active personnel (both students and faculty) organized by department. 
        For each person, show their type (Student/Faculty), full name, email, phone, department, joining date (N/A for students), and status. 
        Sort by department name, then by person type, then by full name. */

        $students = Student::from('students as s')
            ->leftJoin('enrollments as e', 's.id', '=', 'e.student_id')
            ->leftJoin('courses as c', 'e.course_id', '=', 'c.id')
            ->leftJoin('departments as d', 'c.department_id', '=', 'd.id')
            ->select(
                DB::raw("'Student' as person_type"),
                DB::raw("CONCAT(first_name,' ',last_name) as full_name"),
                'email',
                'phone',
                'd.department_name',
                DB::raw("'N/A' as joining_date"),
                'status'
            )
            ->where('status', 'active');

        $faculties = Faculty::from('faculties')
            ->leftJoin('departments as d', 'department_id', '=', 'd.id')
            ->select(
                DB::raw("'Faculty' as person_type"),
                DB::raw("CONCAT(first_name,' ',last_name) as full_name"),
                'email',
                'phone',
                'd.department_name',
                'joining_date',
                'status'
            )
            ->where('status', 'active');

        $results = $students->union($faculties)->orderBy('department_name')->get();

        return $results;
    }

    public function query6()
    {
        ## Query 6: Course Performance Variance Analysis
        /* Definition: Identify courses that are offered across multiple semesters and show significant variance in student performance between semesters. 
        Display courses where the difference between highest and lowest semester average SGPA is greater than 1.0. 
        Show course name, department, number of semesters offered, overall average SGPA, highest semester average, lowest semester average, and the variance. */

        $semester_avg = Course::from('courses as c')
            ->leftJoin('enrollments as e', 'c.id', '=', 'e.course_id')
            ->leftJoin('student_academic_histories as sah', 'e.student_id', '=', 'sah.student_id')
            ->select(
                'c.course_name',
                'sah.semester',
                DB::raw('ROUND(AVG(sah.sgpa), 2) as sem_avg')
            )
            ->groupBy('c.course_name', 'sah.semester');

        $courses = Course::from('courses as c')
            ->leftJoin('enrollments as e', 'c.id', '=', 'e.course_id')
            ->leftJoin('departments as d', 'c.department_id', '=', 'd.id')
            ->leftJoin('student_academic_histories as sah', 'e.student_id', '=', 'sah.student_id')
            ->leftJoinSub($semester_avg, 'sa', function ($join) {
                $join->on('c.course_name', '=', 'sa.course_name');
            })
            ->select(
                'c.course_name',
                'd.department_name',
                'c.semesters as semesters_offered',
                DB::raw('round(avg(sah.sgpa),2) as overall_sgpa'),
                DB::raw('max(sa.sem_avg) as highest_semester_avg'),
                DB::raw('min(sa.sem_avg) as lowest_semester_avg'),
                DB::raw('(max(sa.sem_avg) - min(sa.sem_avg)) as sgpa_variance')               
            )
            ->groupBy('c.course_name','d.department_name','c.semesters')
            ->having('sgpa_variance','>','1.0')
            ->get();

        return $courses;
    }

    public function query7()
    {
        ## Query 7: Top Faculty by Subject Load
        /* Definition: Find faculty members who are teaching the maximum number of subjects within their respective departments in the current academic year. 
        Display faculty name, department, email, and total number of subjects they teach. */

        $faculties = Faculty::from('faculties as f')
            ->leftJoin('departments as d', 'f.department_id', '=', 'd.id')
            ->leftJoin('faculty_assignments as fa', 'f.id', '=', 'fa.faculty_id')
            ->leftJoin('academic_years as ay', 'fa.academic_year_id', '=', 'ay.id')
            ->select(
                'f.first_name',
                'f.last_name',
                'd.department_name',
                'f.email',
                DB::raw('count(fa.course_subject_id) as total_subjects')
            )
            ->where('ay.is_current',1)
            ->groupBy('f.first_name','f.last_name','d.department_name','f.email')
            ->get();
                
        return $faculties;
    }
        
    public function query8()
    {
        ## Query 8: Student Performance Ranking
        /* Definition: Rank all active students by their SGPA within their course and semester. 
        Show student name, course name, semester, SGPA, class, their rank, and dense rank within their course-semester group. */

        $students = Student::from('students as s')
            ->leftJoin('enrollments as e', 's.id', '=', 'e.student_id')
            ->leftJoin('courses as c', 'e.course_id', '=', 'c.id')
            ->leftJoin('student_academic_histories as sah', 's.id', '=', 'sah.student_id')
            ->select(
                's.first_name',
                's.last_name',
                'c.course_name',
                'sah.semester',
                'sah.sgpa',
                'sah.class',
                DB::raw('rank() over (PARTITION by sah.semester,c.course_name order by sah.sgpa desc) as sgpa_rank'),
                DB::raw('dense_rank() over (PARTITION by sah.semester,c.course_name order by sah.sgpa desc) as dense_rank')
            )
            ->where('s.status','active')
            ->orderByDesc('sah.semester','c.course_name','sah.sgpa')
            ->get();
                
        return $students;
    }

    public function query9()
    {
        ## Query 9: Department Performance Grading
        /* Definition: Create a comprehensive department analysis report that categorizes each department's performance. 
        Show department name, total students, total faculty, average SGPA, count of students in each class category (distinction, first class, second class), 
        percentage of distinction students, and assign an overall grade to the department based on average SGPA. */

        $classes = Department::from('departments as d')
            ->leftJoin('courses as c', 'd.id', '=', 'c.department_id')
            ->leftJoin('enrollments as e', 'c.id', '=', 'e.course_id')
            ->leftJoin('student_academic_histories as sah', 'e.student_id', '=', 'sah.student_id')
            ->select(
                'd.department_name',
                DB::raw("count(case when sah.class = 'Distinction' then 1 end) as distinction_count"),
                DB::raw("count(case when sah.class = 'First class' then 1 end) as first_class_count"),
                DB::raw("count(case when sah.class = 'Second class' then 1 end) as second_class_count"),
            )
            ->groupBy('d.department_name');

        $departments = Department::from('departments as d')
            ->leftJoin('faculties as f', 'd.id', '=', 'f.department_id')
            ->leftJoin('courses as c', 'd.id', '=', 'c.department_id')
            ->leftJoin('enrollments as e', 'c.id', '=', 'e.course_id')
            ->leftJoin('students as s', 'e.student_id', '=', 's.id')
            ->leftJoin('student_academic_histories as sah', 's.id', '=', 'sah.student_id')
            ->leftJoinSub($classes, 'cc' , function($join){
                $join->on('d.department_name','=','cc.department_name');
            })
            ->select(
                'd.department_name',
                DB::raw('count(DISTINCT s.id) as total_students'),
                DB::raw('count(DISTINCT f.id) as total_faculty'),
                DB::raw('round(avg(sah.sgpa),2) as avg_sgpa'),
                'cc.distinction_count',
                'cc.first_class_count',
                'cc.second_class_count',
                DB::raw('ROUND((cc.distinction_count * 100) / NULLIF((cc.distinction_count + cc.first_class_count + cc.second_class_count), 0), 2) as distinction_percentage'),
                DB::raw("CASE 
                    WHEN ROUND(AVG(sah.sgpa), 2) >= 8 THEN 'Excellent'
                    WHEN ROUND(AVG(sah.sgpa), 2) >= 7 AND ROUND(AVG(sah.sgpa), 2) < 8 THEN 'Very good'
                    WHEN ROUND(AVG(sah.sgpa), 2) < 7 THEN 'Good'
                    ELSE 'null'
                END as department_grade"),
            )
            ->groupBy('d.department_name','cc.distinction_count','cc.first_class_count','cc.second_class_count')
            ->get();
                
        return $departments;
    }

    public function query10()
    {
        ## Query 10: Students in High Faculty-Ratio Courses
        /* Definition: Find students who are enrolled in courses that have a better-than-average faculty-to-student ratio across the institution. 
        Calculate the faculty-to-student ratio for each course and compare it with the institutional average. 
        Display student details, course information, faculty count, student count, and the ratio. */

        $course_counts = Course::from('courses as c')
            ->leftJoin('enrollments as e', 'c.id', '=', 'e.course_id')
            ->leftJoin('course_subjects as cs', 'c.id', '=', 'cs.course_id')
            ->leftJoin('faculty_assignments as fa', 'cs.id', '=', 'fa.course_subject_id')
            ->select(
                'c.id',
                'c.course_name',
                'c.department_id',
                DB::raw('count(DISTINCT fa.faculty_id) as faculty_count'),
                DB::raw('count(DISTINCT e.student_id) as student_count'),
                DB::raw('round((count(DISTINCT e.student_id)/count(DISTINCT fa.faculty_id)),2) as student_faculty_ratio'),                               
            )
            ->groupBy('c.id','c.course_name', 'c.department_id');

        $institutional_avg = $course_counts->get()->avg('student_faculty_ratio');

        $result = DB::query()
            ->fromSub($course_counts, 'cc')
            ->leftJoin('departments as d', 'cc.department_id', '=', 'd.id')
            ->leftJoin('enrollments as e', 'cc.id', '=', 'e.course_id')
            ->leftJoin('students as s', 'e.student_id', '=', 's.id')
            ->select(
                's.first_name', 
                's.last_name', 
                'cc.course_name', 
                'd.department_name', 
                'cc.faculty_count',
                'cc.student_count',
                'cc.student_faculty_ratio'
            )
            ->where('cc.student_faculty_ratio', '<', $institutional_avg)
            ->groupBy('s.first_name','s.last_name','cc.course_name','d.department_name','cc.faculty_count','cc.student_count','cc.student_faculty_ratio')
            ->get();

        return $result;
    }    
    
    public function query11()
    {
        ## Query 11: Subject Performance Distribution
        /* Definition: Analyze performance across all subjects by creating a distribution report. 
        For each subject, show the subject name, total students enrolled, average SGPA, and count of students from different course types (Engineering, Science, Arts). 
        Also show the course distribution for each subject. */

        $course_student_count = CourseSubject::from('course_subjects as cs')
            ->join('enrollments as e',function($join){
                $join->on('cs.course_id','=','e.course_id')
                    ->on('cs.semester','=','e.semester');
            })
            ->join('courses as c', 'cs.course_id', '=', 'c.id')
            ->select(
                'cs.subject_id',
                'c.course_name',
                DB::raw('COUNT(DISTINCT e.student_id) AS student_count')
            )
            ->groupBy('cs.subject_id','c.course_name');

        $students = Subject::from('subjects as s')
            ->join('course_subjects as cs', 's.id', '=', 'cs.subject_id')
            ->join('enrollments as e',function($join){
                $join->on('cs.course_id','=','e.course_id')
                    ->on('cs.semester','=','e.semester');
            })
            ->join('student_academic_histories as sah',function($join){
                $join->on('e.student_id', '=', 'sah.student_id')
                    ->on('e.academic_year_id', '=', 'sah.academic_year_id')
                    ->on('e.semester', '=', 'sah.semester');
            })
            ->join('courses as c', 'cs.course_id', '=', 'c.id')
            ->join('departments as d', 'c.department_id', '=', 'd.id')
            ->leftJoinSub($course_student_count,'csc',function($join){
                $join->on('s.id', '=', 'csc.subject_id');
            })
            ->select(
                's.subject_name',
                DB::raw('count(DISTINCT e.student_id) AS total_students'),
                DB::raw('round(AVG(sah.sgpa), 2) AS avg_sgpa'),
                DB::raw("count(case WHEN d.department_name LIKE '%Engineering%' THEN 1 END) AS engineering_students"),
                DB::raw("count(case WHEN d.department_name LIKE '%Science%' THEN 1 END) AS science_students"),
                DB::raw("count(case WHEN d.department_name LIKE '%Arts%' THEN 1 END) AS arts_students"),
                DB::raw("GROUP_CONCAT(CONCAT(csc.course_name, ' (', csc.student_count, ')') ORDER BY csc.course_name) AS course_distribution"),
            )
            ->groupBy('s.subject_name')
            ->orderBy('s.subject_name')
            ->get();

        return $students;
    }
        
    public function query12()
    {
        ## Query 12: Top Student Academic Profiles
        /* Definition: Create detailed academic profiles for top-performing students (overall SGPA > 8.0).
        Show complete student information, overall performance statistics, semester-wise performance breakdown, and the faculty members who taught them. */

        $students = Student::from('students as s')
            ->leftJoin('enrollments as e', 's.id', '=', 'e.student_id')
            ->leftJoin('courses as c', 'e.course_id', '=', 'c.id')
            ->leftJoin('departments as d', 'c.department_id', '=', 'd.id')
            ->leftJoin('student_academic_histories as sah', 'e.student_id', '=', 'sah.student_id')
            ->leftJoin('course_subjects as cs', 'c.id', '=', 'cs.course_id')
            ->leftJoin('faculty_assignments as fa', 'cs.id', '=', 'fa.course_subject_id')
            ->leftJoin('faculties as f', 'fa.faculty_id', '=', 'f.id')
            ->leftJoin('subjects as sub', 'cs.subject_id', '=', 'sub.id')
            ->select(
                DB::raw("concat(s.first_name,' ',s.last_name) as full_name"),
                's.email',
                'c.course_name',
                'd.department_name',
                DB::raw('round(avg(sah.sgpa),2) as overall_sgpa'),
                DB::raw('max(sah.sgpa) as best_sgpa'),
                DB::raw('min(sah.sgpa) as worst_sgpa'),
                DB::raw("GROUP_CONCAT(DISTINCT concat('Sem ', sah.semester,': ', sah.sgpa, ' (', sah.class, ')') order by sah.semester SEPARATOR ' | ') as semester_performance"),
                DB::raw("GROUP_CONCAT(DISTINCT concat(f.first_name, ' ', f.last_name, ' (', sub.subject_name, ')') SEPARATOR ';') as taught_by_faculty"),                         
            )
            ->groupBy('full_name','s.email','c.course_name','d.department_name')
            ->having('overall_sgpa','>','8.0')
            ->get();

        return $students;
    }

    public function query13()
    {
        ## Query 13: Student Dropout Risk Analysis
        /* Definition: Identify students who might be at risk of dropping out based on declining academic performance. 
        Find students whose SGPA has decreased by more than 1.0 point between any two consecutive semesters in the current academic year. 
        Show student details, course information, previous semester SGPA, current semester SGPA, SGPA drop, and calculate how many subjects they're currently enrolled in. */

        $sgpa_comparison = StudentAcademicHistory::from('student_academic_histories as sah')
            ->join('academic_years as ay', 'sah.academic_year_id', '=', 'ay.id')
            ->select(
                'sah.student_id',
                'sah.academic_year_id',
                'sah.semester as current_semester',
                'sah.sgpa as current_sgpa',
                DB::raw('sah.semester - 1 as previous_semester'),
                DB::raw('(select sgpa 
                    from student_academic_histories  
                    where semester = current_semester - 1 and student_id = sah.student_id 
                    group by sah.student_id,sgpa) as previous_sgpa')
            )
            ->where('ay.is_current',true);

        $subject_count = Enrollment::from('enrollments as e')
            ->join('course_subjects as cs',function($join){
                $join->on('e.course_id', '=', 'cs.course_id')
                    ->on('e.semester', '=', 'cs.semester');
            })
            ->join('academic_years as ay', 'e.academic_year_id', '=', 'ay.id')
            ->select(
                'e.student_id',
                DB::raw('count(cs.id) as current_subjects')
            )
            ->where('ay.is_current',true)
            ->groupBy('e.student_id');

        $students = DB::query()
            ->fromSub($sgpa_comparison,'sc')
            ->join('students as s', 'sc.student_id', '=', 's.id')
            ->join('enrollments as e',function($join){
                $join->on('s.id','=','e.student_id')
                    ->on('sc.academic_year_id','=','e.academic_year_id')
                    ->on('sc.current_semester','=','e.semester');
            })
            ->join('courses as c', 'e.course_id', '=', 'c.id')
            ->join('departments as d', 'c.department_id', '=', 'd.id')
            ->join('academic_years as ay', 'sc.academic_year_id', '=', 'ay.id')
            ->leftJoinSub($subject_count,'sub',function($join){
                $join->on('s.id', '=', 'sub.student_id');
            })
            ->select(
                's.first_name',
                's.last_name',
                's.email',
                'c.course_name',
                'd.department_name',
                'sc.previous_semester',
                'sc.previous_sgpa',
                'sc.current_semester',
                'sc.current_sgpa',
                DB::raw('round(sc.previous_sgpa - sc.current_sgpa,2) as sgpa_drop'),
                'sub.current_subjects'
            )
            ->where('s.status','active')
            ->where('ay.is_current',true)
            ->get();

        return $students;
    }
        
    public function query14()
    {
        ## Query 14: Faculty Workload Distribution Analysis
        /* Definition: Create a comprehensive faculty workload analysis showing how teaching assignments are distributed across departments and semesters. 
        For each faculty member, calculate their total teaching hours (assume each subject = 4 hours/week), number of different courses they teach, number of students they teach, 
        and their workload status compared to department average. Include faculty who teach across multiple departments. */

        $workload_count = Faculty::from('faculties as f')
            ->join('departments as d', 'f.department_id', '=', 'd.id')
            ->join('faculty_assignments as fa', 'f.id', '=', 'fa.faculty_id')
            ->join('course_subjects as cs','fa.course_subject_id', '=', 'cs.id')
            ->join('courses as c', 'cs.course_id', '=', 'c.id')
            ->join('departments as d2', 'c.department_id', '=', 'd2.id')
            ->join('subjects as s','cs.subject_id', '=', 's.id')
            ->join('enrollments as e','c.id', '=','e.course_id')
            ->select(
                'f.id',
                DB::raw("concat(f.first_name,' ',f.last_name) as faculty_name"),
                'd.department_name as primary_department',
                DB::raw("group_concat(distinct d2.department_name separator ', ') as departments_teaching"),
                DB::raw("count(distinct fa.course_subject_id) as total_subjects"),
                DB::raw("count(distinct c.id) as total_courses"),
                DB::raw("count(distinct e.student_id) as total_students"),
                DB::raw("(count(fa.course_subject_id) * 4) as estimated_hours"),
            )
            ->groupBy('f.id','faculty_name','d.department_name');

        $faculty_hours = Faculty::from('faculties as f')
            ->join('departments as d', 'f.department_id', '=', 'd.id')
            ->join('faculty_assignments as fa', 'f.id', '=', 'fa.faculty_id')
            ->select(
                'f.id',
                'd.department_name',
                DB::raw('count(fa.id) * 4 as faculty_hours')
            )
            ->groupBy('f.id','d.department_name');

        $dept_avg = DB::query()
            ->fromSub($faculty_hours,'fc')
            ->select(
                'department_name',
                DB::raw('round(avg(faculty_hours),2) as avg_dept_hours')
            )
            ->groupBy('department_name');

        $result = DB::query()
            ->fromSub($workload_count,'wc')
            ->leftJoinSub($dept_avg,'da',function($join){
                $join->on('wc.primary_department', '=', 'da.department_name');
            })
            ->select(
                'wc.faculty_name',
                'wc.primary_department',
                'wc.departments_teaching',
                'wc.total_subjects',
                'wc.total_courses',
                'wc.total_students',
                'wc.estimated_hours',
                DB::raw("(case 
                    when wc.estimated_hours > da.avg_dept_hours then 'Above Average'
                    when wc.estimated_hours < da.avg_dept_hours then 'Below Average'
                    else 'Average'
                    end) as workload_status"),
                'da.avg_dept_hours'
            )
            ->get();

        return $result;
    }
        
    public function query15()
    {
        ## Query 15: Course Completion and Success Rate Analysis
        /* Definition: Analyze course completion and success rates by calculating what percentage of enrolled students successfully complete each course with different grade categories. 
        Include courses from the last 3 academic years, and show trends in success rates. Consider students with 'dropped' status as incomplete and calculate success rates for distinction, 
        first class, second class, and overall pass rates. */

        $rate_analysis = Course::from('courses as c')
            ->join('departments as d', 'c.department_id', '=', 'd.id')
            ->join('enrollments as e', 'c.id', '=','e.course_id')
            ->join('academic_years as ay', 'e.academic_year_id', '=', 'ay.id')
            ->leftJoin('students as s','e.student_id', '=', 's.id')
            ->leftJoin('student_academic_histories as sah','s.id', '=', 'sah.student_id')
            ->select(
                'c.id',
                'c.course_name',
                'd.department_name',
                'ay.name as academic_year',
                DB::raw("count(DISTINCT e.student_id) as total_enrolled"),
                DB::raw("count(DISTINCT case when s.status != 'dropped' then s.id end) as completed"),
                DB::raw("count(DISTINCT case when s.status = 'dropped' then s.id end) as dropped"),
                DB::raw("(count(DISTINCT case when sah.class = 'Distinction' then sah.student_id end) * 100 / 
                        count(DISTINCT e.student_id)) as distinction_rate"),
                DB::raw("(count(DISTINCT case when sah.class = 'First class' then sah.student_id end) * 100 / 
                        count(DISTINCT e.student_id)) as first_class_rate"),
                DB::raw("(count(DISTINCT case when sah.class = 'Second class' then sah.student_id end) * 100 / 
                        count(DISTINCT e.student_id)) as second_class_rate"),
                DB::raw("(count(DISTINCT case when s.status != 'dropped' then s.id end) * 100 / count(DISTINCT e.student_id)) as overall_pass_rate"),
                DB::raw("LAG(count(DISTINCT case when s.status != 'dropped' then s.id end) * 100 / count(DISTINCT e.student_id)) over 
                        (PARTITION by c.id order by academic_year) as prev_rate"),
            )
            ->where('ay.year_start','>=',"DB::raw('select max(ay.year_start) - 3 from academic_years')")
            ->groupBy('c.id','c.course_name','d.department_name','ay.name');

        $result = DB::query()
            ->fromSub($rate_analysis,'ra')
            ->select(
                'course_name',
                'department_name',
                'academic_year',
                'total_enrolled',
                'completed',
                'dropped',
                'distinction_rate',
                'first_class_rate',
                'second_class_rate',
                'overall_pass_rate',
                DB::raw("(case when prev_rate < overall_pass_rate then 'Improving'
                    when prev_rate > overall_pass_rate then 'Declining'
                    else 'stable' end) as completion_trend")
            )
            ->get();

        return $result;
    }
       
    public function query16()
    {
        ## Query 16: Subject Prerequisites and Performance Correlation
        /* Definition: Create a query that analyzes the relationship between prerequisite subjects and advanced subject performance. 
        For subjects that have logical prerequisites (like Programming before Advanced Programming), compare student performance in advanced subjects based on their performance in prerequisite subjects. 
        Show how students who scored distinction in prerequisites perform in advanced subjects. */

        $student_performance = Subject::from('subjects as s1')
            ->join('course_subjects as cs1', 's1.id', '=', 'cs1.subject_id')
            ->join('enrollments as e1', function($join){
                $join->on('cs1.course_id', '=','e1.course_id')
                    ->on('cs1.semester', '=', 'e1.semester');
            })
            ->leftJoin('student_academic_histories as sah1',function($join){
                $join->on('e1.student_id', '=', 'sah1.student_id')
                    ->on('e1.academic_year_id', '=', 'sah1.academic_year_id')
                    ->on('e1.semester', '=', 'sah1.semester');
            })
            ->join('course_subjects as cs2', function($join){
                $join->on('cs1.id', '=', 'cs2.subject_id')
                    ->on('cs2.semester', '>', 'cs1.semester');
            })
            ->join('subjects as s2', 'cs2.subject_id', '=', 's2.id')
            ->join('enrollments as e2', function($join){
                $join->on('cs2.course_id', '=','e2.course_id')
                    ->on('cs2.semester', '=', 'e2.semester')
                    ->on('e2.student_id', '=', 'e1.student_id');
            })
            ->leftJoin('student_academic_histories as sah2',function($join){
                $join->on('e2.student_id', '=', 'sah2.student_id')
                    ->on('e2.academic_year_id', '=', 'sah2.academic_year_id')
                    ->on('e2.semester', '=', 'sah2.semester');
            })           
            ->select(
                's1.subject_name as prerequisite_subject',
                's2.subject_name as advanced_subject',
                'sah1.student_id',
                'sah1.sgpa as prereq_sgpa',
                'sah1.class as prereq_class',
                'sah2.sgpa as advanced_sgpa'
            )
            ->where(function($q) {
                $q->where(function($q2) {
                    $q2->where('s1.subject_name', 'Programming')
                    ->where('s2.subject_name', 'Advance Programming');
                })
                ->orWhere(function($q2) {
                    $q2->where('s1.subject_name', 'Math')
                    ->where('s2.subject_name', 'Math II');
                });
            });

        $result = DB::query()
            ->fromSub($student_performance,'sp')
            ->select(
                'prerequisite_subject',
                'advanced_subject',
                DB::raw("count(case when prereq_class = 'Distinction' then student_id end) as students_with_distinction_prereq"),
                DB::raw("round(avg(case when prereq_class = 'Distinction' then advanced_sgpa end),2) as avg_advanced_sgpa"),
                DB::raw("count(case when prereq_class = 'First class' then student_id end) as students_with_firstclass_prereq"),
                DB::raw("round(avg(case when prereq_class = 'First class' then advanced_sgpa end),2) as avg_advanced_sgpa_fc"),
                DB::raw("count(case when prereq_class = 'Second class' then student_id end) as students_with_secondclass_prereq"),
                DB::raw("round(avg(case when prereq_class = 'Second class' then advanced_sgpa end),2) as avg_advanced_sgpa_sc"),
                DB::raw("(case when avg(case when prereq_class = 'Distinction' then advanced_sgpa end) >= 9 and 
                    avg(case when prereq_class = 'Distinction' then advanced_sgpa end) - avg(case when prereq_class = 'First class' then advanced_sgpa end) > 0.8
                    then 'Strong Positive' 
                    when avg(case when prereq_class = 'Distinction' then advanced_sgpa end) >= 8 and 
                    avg(case when prereq_class = 'Distinction' then advanced_sgpa end) - avg(case when prereq_class = 'First class' then advanced_sgpa end) > 0.4
                    then 'Positive'
                    else 'Moderate Positive'
                    end) as performance_correlation"),
            )
            ->groupBy('prerequisite_subject','advanced_subject')
            ->orderBy('prerequisite_subject')
            ->get();

        return $result;
    }

    public function query17()
    {
        ## Query 17: Multi-Dimensional Academic Performance Cube
       /*  Definition: Create a comprehensive analytical query that provides a multi-dimensional view of academic performance across different dimensions: department, course, semester, academic year, and gender. 
        This should work like a data cube showing performance metrics across all these dimensions with subtotals and grand totals. */

        $performance_data = Enrollment::from('enrollments as e')
            ->join('students as s', 'e.student_id', '=', 's.id')
            ->join('courses as c', 'e.course_id', '=', 'c.id')
            ->join('departments as d', 'c.department_id', '=', 'd.id')
            ->join('academic_years as ay', 'e.academic_year_id', '=', 'ay.id')
            ->join('student_academic_histories as sah', function ($join) {
                $join->on('sah.student_id', '=', 's.id')
                    ->on('sah.academic_year_id', '=', 'e.academic_year_id')
                    ->on('sah.semester', '=', 'e.semester');
            })
            ->select(
                'd.department_name',
                'c.course_name',
                'e.semester',
                'ay.name as academic_year',
                's.gender',
                DB::raw('COUNT(DISTINCT s.id) as student_count'),
                DB::raw('ROUND(AVG(sah.sgpa), 2) as avg_sgpa'),
                DB::raw('ROUND(SUM(CASE WHEN sah.class = "Distinction" THEN 1 ELSE 0 END) * 100 / COUNT(DISTINCT s.id), 2) as distinction_pct'),
                DB::raw('ROUND(SUM(CASE WHEN sah.sgpa >= 4.0 THEN 1 ELSE 0 END) * 100 / COUNT(DISTINCT s.id), 2) as pass_rate')
            )
            ->groupBy(
                'd.department_name',
                'c.course_name',
                'e.semester',
                'ay.name',
                's.gender'
            )
            ->orderByDesc('d.department_name')
            ->orderByDesc('c.course_name')
            ->orderByDesc('e.semester')
            ->orderByDesc('ay.name')
            ->orderByDesc('s.gender')
            ->get();

        $aggregated_data = $performance_data->flatMap(function($data) {
            $base = [
                'department_name' => $data->department_name,
                'course_name' => $data->course_name,
                'semester' => $data->semester,
                'academic_year' => $data->academic_year,
                'gender' => $data->gender,
                'student_count' => $data->student_count,
                'avg_sgpa' => $data->avg_sgpa,
                'distinction_pct' => $data->distinction_pct,
                'pass_rate' => $data->pass_rate,
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
        ## Query 18: Student Learning Path Analysis
        /* Definition: Track and analyze student learning paths by showing the sequence of subjects taken by students and their performance progression. 
        For each student, show their subject sequence, performance in each subject, identify subjects where they struggled (SGPA < 6.0), and suggest remedial actions. 
        Include time gaps between subjects that might indicate academic delays. */

        $student_sub = Student::from('students as s')
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
            ->fromSub($student_sub,'sb')
            ->select(
                '*',
                DB::raw('row_number() over (PARTITION by student_name order by semester) as semester_order')
            );
            
        $semester_gap =  DB::query()
            ->fromSub($semester_order,'so')
            ->select(
                'student_name',
                'course_name',
                'subject_name',
                'sgpa',
                'semester',
                'semester_order',
                DB::raw('semester - lag(semester) over (partition by student_name order by semester) as semester_gap')
            );

        $subject_seq =  DB::query()
            ->fromSub($semester_gap,'sg')
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
            ->fromSub($subject_seq,'ss')
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

    public function query19()
    {
        ## Query 19: Resource Utilization and Optimization Analysis
        /* Definition: Analyze how effectively college resources (faculty, courses, subjects) are being utilized. 
        Calculate faculty-to-student ratios, identify underutilized courses (low enrollment), overloaded faculty, subjects with high failure rates, and provide optimization recommendations. 
        Include cost analysis assuming average cost per student per subject. */

        $faculty_utilization = Faculty::from('faculties as f')
            ->leftJoin('departments as d', 'f.department_id', '=','d.id')
            ->leftJoin('faculty_assignments as fa', 'f.id', '=', 'fa.faculty_id')
            ->leftJoin('course_subjects as cs', 'fa.course_subject_id', '=', 'cs.id')
            ->leftJoin('enrollments as e', 'cs.course_id', '=', 'e.course_id')
            ->select(
                DB::raw("'faculty' as resource_type"),
                DB::raw("concat(f.first_name,' ',f.last_name) as resource_name"),
                'd.department_name',
                DB::raw("concat('1 : ',count(e.student_id),' ratio') as utilization_metric"),
                DB::raw("concat(case when count(DISTINCT e.student_id) BETWEEN 10 and 20 then 80
                        when count(DISTINCT e.student_id) < 10 then 60
                        else 50 end,'%') as efficiency_score"),
                DB::raw("case when count(DISTINCT e.student_id) BETWEEN 10 and 20 then 'optimal'
                        when count(DISTINCT e.student_id) < 10 then 'Underutilized'
                        else 'Overloaded' end as recommendation"),
                DB::raw("case when count(DISTINCT e.student_id) BETWEEN 10 and 20 then 'Low'
                        when count(DISTINCT e.student_id) < 10 then 'Medium'
                        else 'High' end as priority_level")
            )
            ->groupBy('f.id','f.first_name','f.last_name','d.department_name');

        $course_utilization = Course::from('courses as c')
            ->leftJoin('departments as d', 'c.department_id', '=','d.id')
            ->leftJoin('enrollments as e', 'c.id', '=', 'e.course_id')
            ->select(
                DB::raw("'course' as resource_type"),
                'c.course_name as resource_name',
                'd.department_name',
                DB::raw("concat(count(e.student_id),' ','student') as utilization_metric"),
                DB::raw("concat(case when count(DISTINCT e.student_id) >= 30 then 80
                        when count(DISTINCT e.student_id) BETWEEN 15 and 29 then 60
                        else 40 end,'%') as efficiency_score"),
                DB::raw("case when count(DISTINCT e.student_id) >= 30 then 'Optimal'
                        when count(DISTINCT e.student_id) between 15 and 29 then 'Increase Enrollment'
                        when count(DISTINCT e.student_id) < 15 then 'Low Enrollment' end as recommendation"),
                DB::raw("case when count(DISTINCT e.student_id) >= 30 then 'Low'
                        when count(DISTINCT e.student_id) between 15 and 29 then 'Medium'
                        when count(DISTINCT e.student_id) < 15 then 'High' end as priority_level")
            )
            ->groupBy('c.id','c.course_name','d.department_name');


        $subject_utilization = Subject::from('subjects as s')
            ->leftJoin('course_subjects as cs', 's.id', '=', 'cs.subject_id')
            ->leftJoin('courses as c', 'cs.course_id', '=', 'c.id')
            ->leftJoin('departments as d', 'c.department_id', '=','d.id')
            ->leftJoin('enrollments as e', 'c.id', '=', 'e.course_id')
            ->leftJoin('student_academic_histories as sah', 'e.student_id', '=', 'sah.student_id')
            ->select(
                DB::raw("'subject' as resource_type"),
                's.subject_name as resource_name',
                'd.department_name',
                DB::raw("concat(round(sum(case when sah.sgpa >= 4.0 then 1 else 0 end) * 100/count(*),2),'% pass rate') as utilization_metric"),
                DB::raw("concat(case when sum(case when sah.sgpa >= 4.0 then 1 else 0 end) * 100/count(*) > 80 then 80
                        when sum(case when sah.sgpa >= 4.0 then 1 else 0 end) * 100/count(*) BETWEEN 60 and 80 then 60
                        else 45 end,'%') as efficiency_score"),
                DB::raw("case when sum(case when sah.sgpa >= 4.0 then 1 else 0 end) * 100/count(*) > 80 then 'optimal'
                        when sum(case when sah.sgpa >= 4.0 then 1 else 0 end) * 100/count(*) BETWEEN 60 and 80 then 'Moderate Failure'
                        else 'High Failure' end as recommendation"),
                DB::raw("case when sum(case when sah.sgpa >= 4.0 then 1 else 0 end) * 100/count(*) > 80 then 'Low'
                        when sum(case when sah.sgpa >= 4.0 then 1 else 0 end) * 100/count(*) BETWEEN 60 and 80 then 'Medium'
                        else 'High' end as priority_level")
            )
            ->groupBy('s.id','s.subject_name','d.department_name');

        $department_utilization = Department::from('departments as d')
            ->leftJoin('faculties as f', 'd.id', '=', 'f.department_id')
            ->leftJoin('courses as c', 'd.id', '=', 'c.department_id')
            ->leftJoin('enrollments as e', 'c.id', '=', 'e.course_id')
            ->select(
                DB::raw("'department' as resource_type"),
                'd.department_name as resource_name',
                'd.department_name',
                DB::raw("concat(round(count(DISTINCT f.id) / (count(DISTINCT e.student_id) / 100),2),' : 1 ratio') as utilization_metric"),
                DB::raw("concat(case when count(DISTINCT f.id) / (count(DISTINCT e.student_id) / 100) between 2 and 3 then 80
                        when count(DISTINCT f.id) / (count(DISTINCT e.student_id) / 100) < 1 then 60
                        else 40 end,'%') as efficiency_score"),
                DB::raw("case when count(DISTINCT f.id) / (count(DISTINCT e.student_id) / 100) between 2 and 3 then 'Optimal'
                        when count(DISTINCT f.id) / (count(DISTINCT e.student_id) / 100) < 1 then 'Need More Faculty'
                        else 'Reduce faculty' end as recommendation"),
                DB::raw("case when count(DISTINCT f.id) / (count(DISTINCT e.student_id) / 100) between 2 and 3 then 'Low'
                        when count(DISTINCT f.id) / (count(DISTINCT e.student_id) / 100) < 1 then 'Medium'
                        else 'High' end as priority_level")
            )
            ->groupBy('d.id','d.department_name');

        $result = $faculty_utilization
            ->unionAll($course_utilization)
            ->unionAll($subject_utilization)
            ->unionAll($department_utilization);

        $final = DB::query()
            ->fromSub($result, 'combined')
            ->orderBy('resource_type')
            ->orderBy('resource_name')
            ->get();

        return $final;

    }

}