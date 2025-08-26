<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Faculty;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Department;
use App\Models\AcademicYear;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Test2Controller extends Controller
{
    public function query1()
    {
        /* 1. Department-wise Pass Rate in Current Academic Year
        Definition: For the current academic year, show each department’s pass rate (percentage of students whose SGPA ≥ 4.0 in any semester). */

        $result = Department::from('departments as d')
            ->leftJoin('courses as c','d.id', '=', 'c.department_id')
            ->leftJoin('enrollments as e','c.id', '=', 'e.course_id')
            ->leftJoin('student_academic_histories as sah','e.student_id', '=', 'sah.student_id')
            ->select(
                'd.department_name',
                DB::raw('count(distinct e.student_id) as total_students'),
                DB::raw('count(distinct (case when sah.sgpa >= 4.0 then sah.student_id end)) as passed_students'),
                DB::raw("concat(round(count(distinct (case when sah.sgpa >= 4.0 then sah.student_id end)) * 100 / count(distinct e.student_id),2),'%') as passed_rate")
            )
            ->groupBy('d.department_name')
            ->get();
            
        return $result;
    }

    public function query2()
    {
        /* 2. Top 3 Students by SGPA per Department (Latest Semester)
        Definition: Get the top 3 students in each department based on SGPA from the latest semester recorded in student_academic_history. */

        $departments = Department::from('departments as d')
            ->leftJoin('courses as c','d.id', '=', 'c.department_id')
            ->leftJoin('enrollments as e','c.id', '=', 'e.course_id')
            ->leftJoin('students as s','e.student_id','=','s.id')
            ->leftJoin('student_academic_histories as sah','s.id', '=', 'sah.student_id')
            ->select(
                'd.department_name',
                DB::raw("concat(s.first_name,' ',s.last_name) as student_name"),
                DB::raw("substring_index(group_concat(sah.semester order by sah.semester),',',-1) as semester"),
                DB::raw("substring_index(group_concat(sah.sgpa order by sah.semester),',',-1) as sgpa"),
                DB::raw("row_number() over(partition by d.department_name order by sgpa desc) as rn")
            )
            ->groupBy('d.id','d.department_name','student_name')
            ->orderByDesc('d.department_name')
            ->orderByDesc('sgpa')
            ->orderByDesc('rn');
        
        $result = DB::query()
            ->fromSub($departments,'')
            ->select(
                'department_name',
                'student_name',
                'semester',
                'sgpa',
                'rn as rank'
            )
            ->where('rn','<=',3)
            ->get();

        return $result;
    }

    public function query3()
    {
        /* 3. Faculty Subject Load
        Definition: Show each faculty member, the total number of subjects they are assigned to teach in the current academic year, and their department name. */

        $result = Faculty::from('faculties as f')
            ->leftJoin('departments as d','f.department_id','=','d.id')
            ->leftJoin('faculty_assignments as fa','f.id','=','fa.faculty_id')
            ->leftJoin('academic_years as ay','fa.academic_year_id','=','ay.id')
            ->select(
                DB::raw("concat(f.first_name,' ',f.last_name) as faculty_name"),
                'd.department_name',
                DB::raw('count(fa.course_subject_id) as subjects_assigned')
            )
            ->where('ay.is_current',true)
            ->groupBy('faculty_name','d.department_name')
            ->get();

        return $result;
    }

    public function query4()
    {
        /* 4. Faculty Utilization Report
        Definition: For each department, show the total faculty, faculty teaching at least 1 subject, and faculty without assignments in the current academic year. */

        $result = Department::from('departments as d')
            ->leftJoin('faculties as f', 'd.id', '=', 'f.department_id')
            ->leftJoin('faculty_assignments as fa', 'f.id', '=', 'fa.faculty_id')
            ->leftJoin('course_subjects as cs', 'fa.course_subject_id', '=', 'cs.id')
            ->leftJoin('academic_years as ay', 'fa.academic_year_id', '=', 'ay.id')
            ->select(
                'd.department_name',
                DB::raw('COUNT(f.id) AS total_faculty'),
                DB::raw('SUM(CASE WHEN fa.id IS NOT NULL THEN 1 ELSE 0 END) as active_faculty'),
                DB::raw('SUM(CASE WHEN fa.id IS NULL THEN 1 ELSE 0 END) as unassigned_faculty')
            )
            ->where('ay.is_current', true)
            ->groupBy('d.department_name')
            ->get();

        return $result;
    }

    public function query5()
    {
        /* 5. Student Backlog Analysis
        Definition: Show students who have failed (SGPA < 4.0) in at least 2 different semesters, along with the list of those semesters. */

        $result = Student::from('students as s') 
            ->leftJoin('student_academic_histories as sah','s.id','=','sah.student_id')
            ->select(
                DB::raw("concat(s.first_name,' ',s.last_name) as student_name"),
                DB::raw("group_concat(case when sah.sgpa < 4.0 then sah.semester end separator ',') as failed_semesters")
            )
            ->groupBy('student_name')
            ->havingRaw('COUNT(case when sah.sgpa < 4.0 then 1 end) >= 1')
            ->get();

        return $result;
    }

    public function query6()
    {
        /* 6. Semester-wise SGPA Ranking in Each Course
        Definition: For each course in the current academic year, list students ranked by SGPA for each semester.
        If two students have the same SGPA, rank them equally. */

        $result = Course::from('courses as c')
            ->leftJoin('enrollments as e','c.id','=','e.course_id')
            ->leftJoin('students as s','e.student_id','=','s.id')
            ->leftJoin('student_academic_histories as sah',function($join){
                $join->on('e.student_id','=','sah.student_id')
                    ->on('e.semester','=','sah.semester');
            })
            ->leftJoin('academic_years as ay','e.academic_year_id','=','ay.id')
            ->select(
                'c.course_name',
                'e.semester',
                DB::raw("concat(s.first_name,' ',s.last_name) as student_name"),
                'sah.sgpa',
                DB::raw("rank() over (partition by c.course_name,e.semester order by sah.sgpa desc) as rank")
            )
            ->where('ay.is_current',true)
            ->where('sah.sgpa','!=',null)
            ->get();

        return $result;
    }

    public function query7()
    {
        /* 10. Department-wise Subject-Faculty Mapping
        Definition: For each department, list all subjects, the faculty teaching them, and the semester.
        If a subject has no assigned faculty, mark as “Unassigned”. */

        $result = Department::from('departments as d')
            ->leftJoin('courses as c','d.id','=','c.department_id')
            ->leftJoin('course_subjects as cs','c.id','=','cs.course_id')
            ->leftJoin('subjects as sub','cs.subject_id','=','sub.id')
            ->leftJoin('faculty_assignments as fa','cs.id','=','fa.course_subject_id')
            ->leftJoin('faculties as f','fa.faculty_id','=','f.id')
            ->select(
                'd.department_name',
                'c.course_name',
                'sub.subject_name',
                'cs.semester',
                DB::raw("COALESCE(CONCAT(f.first_name, ' ', f.last_name), 'Unassigned') as faculty_name"),
            )
            ->get();

        return $result;
    }

    public function query8()
    {
        /* 8. Course-Wise Subject Distribution
        Definition: List each course, its department, and the count of subjects assigned for the current academic year. */

        $result = Course::from('courses as c')
            ->leftJoin('departments as d','c.department_id','=','d.id')
            ->leftjoin('course_subjects as cs','c.id','=','cs.course_id')
            ->leftJoin('faculty_assignments as fa','cs.id','=','fa.course_subject_id')
            ->leftJoin('academic_years as ay','fa.academic_year_id','=','ay.id')
            ->select(
                'c.course_name',
                'd.department_name',
                DB::raw('count(cs.id) as subjects_count')
            )
            ->groupBy('c.course_name','d.department_name')
            ->where('ay.is_current',true)
            ->get();

        return $result;
    }

    public function query9()
    {
        /* 9. Outstanding Students by Department
        Definition: List students who have distinction in at least 3 different semesters. */

        $result = Student::from('students as s')
            ->leftJoin('enrollments as e','s.id','=','e.student_id')
            ->leftJoin('courses as c','e.course_id','=','c.id')
            ->leftJoin('departments as d','c.department_id','=','d.id')
            ->leftJoin('student_academic_histories as sah','s.id','=','sah.student_id')
            ->select(
                DB::raw("concat(s.first_name,' ',s.last_name) as student_name"),
                'd.department_name',
                DB::raw("group_concat(case when sah.class = 'Distinction' then sah.semester end) as distinction_semesters")
            )
            ->groupBy('student_name','d.department_name')
            ->havingRaw("COUNT(case when sah.class = 'Distinction' then 1 end) > 3")
            ->get();

        return $result;
    }

    public function query10()
    {
        /* 10. Student Retention Analysis
        Definition: Find how many students dropped out after each semester (status = dropped). Group by course + semester. */

        $result = Course::from('courses as c')
            ->leftJoin('enrollments as e','c.id','=','e.course_id')
            ->leftJoin('students as s','e.student_id','=','s.id')
            ->select(
                'c.course_name',
                'e.semester',
                DB::raw('count(s.id) as dropped_students')
            )
            ->groupBy('c.course_name','e.semester')
            ->where('s.status','dropped')
            ->get();

        return $result;
    }

    public function query11()
    {
        /* 11. Faculty Workload Balance
        Definition: For each department, calculate the average number of subjects assigned per faculty. */

        $result = Department::from('departments as d')
            ->leftJoin('faculties as f','d.id','=','f.department_id')
            ->leftJoin('faculty_assignments as fa','f.id','=','fa.faculty_id')
            ->select(
                'd.department_name',
                'f.first_name',
                DB::raw('count(fa.course_subject_id) as subject_count')
            )
            ->groupBy('d.department_name','f.first_name');

        $final = DB::query()
            ->fromSub($result,'')
            ->select(
                'department_name',
                DB::raw('round(avg(subject_count),2) as avg_subjects_per_faculty')
            )
            ->groupBy('department_name')
            ->get();
        
        return $final;
    }

    public function query12()
    {
        /* 12. Multi-Year SGPA Performance Trend
        Definition: For each academic year, calculate average SGPA per department. */

        $result = AcademicYear::from('academic_years as ay')
            ->leftJoin('student_academic_histories as sah','ay.id','=','sah.academic_year_id')
            ->leftJoin('enrollments as e','sah.student_id','=','e.student_id')
            ->leftJoin('courses as c','e.course_id','=','c.id')
            ->leftJoin('departments as d','c.department_id','=','d.id')
            ->select(
                'ay.name as academic_year',
                'd.department_name',
                DB::raw('round(avg(sah.sgpa),2) as avg_sgpa')
            )
            ->groupBy('academic_year','d.department_name')
            ->get();
        
        return $result;
    }

    public function query13()
    {
        /* 13. Student Subject Load per Semester
        Definition: List students along with the number of subjects they enrolled in each semester. */

        $result = Student::from('students as s')
            ->leftJoin('enrollments as e','s.id','=','e.student_id')
            ->leftJoin('courses as c','e.course_id','=','c.id')
            ->leftJoin('course_subjects as cs','c.id','=','cs.course_id')
            ->select(
                DB::raw("concat(s.first_name,' ',s.last_name) as student_name"),
                'cs.semester',
                DB::raw('count(cs.id) as subjects_count')
            )
            ->groupBy('student_name','cs.semester')
            ->get();
        
        return $result;
    }

    public function query14()
    {
        /* 14. Top Department by Consistent Performance
        Definition: Find the department whose students have the highest average SGPA for the last 3 consecutive academic years. */

        $last3years = AcademicYear::orderByDesc('id')
            ->limit(3)
            ->pluck('id');

        $result = Department::from('departments as d')
            ->leftJoin('courses as c','d.id','=','c.department_id')
            ->leftJoin('enrollments as e','c.id','=','e.course_id')
            ->leftJoin('student_academic_histories as sah','e.student_id','=','sah.student_id')
            ->leftJoin('academic_years as ay','sah.academic_year_id','=','ay.id')
            ->select(
                'd.department_name',
                'ay.name as academic_year',
                DB::raw('avg(sah.sgpa) as dept_avg')
            )
            ->WhereIn('ay.id',$last3years)
            ->groupBy('d.department_name','academic_year');

        $final = DB::query()
            ->fromSub($result,'')
            ->select(
                'department_name',
                DB::raw('round(avg(dept_avg),2) as avg_sgpa_last_3_years')
            )
            ->groupBy('department_name')
            ->orderByDesc('avg_sgpa_last_3_years')
            ->first();
        
        return $final;
    }

    public function query15()
    {
        /* 15. Faculty Assignment Gaps
        Definition: List subjects in the current academic year that do not have any faculty assigned. */

        $result = Subject::from('subjects as sub')
            ->leftJoin('course_subjects as cs','sub.id','=','cs.subject_id')
            ->leftJoin('courses as c','cs.course_id','=','c.id')
            ->leftJoin('faculty_assignments as fa','cs.id','=','fa.course_subject_id')
            ->leftJoin('academic_years as ay','fa.academic_year_id','=','ay.id')
            ->leftJoin('faculties as f','fa.faculty_id','=','f.id')
            ->select(
                'c.course_name',
                'sub.subject_name',
                'cs.semester',
                DB::raw("Coalesce(concat(f.first_name,' ',f.last_name),'Unassigned') as assigned_faculty")
            )
            ->where('ay.is_current',true)
            ->whereNull('f.id')
            ->get();
        
        return $result;
    }

    public function query16()
    {
        /* Query 16: Gender Distribution in Departments
        Definition: Find the number of male and female students in each department. Show department name, total students, male count, female count, and percentage of females. */

        $result = Department::from('departments as d')
            ->leftJoin('courses as c','d.id','c.department_id')
            ->leftJoin('enrollments as e','c.id','e.course_id')
            ->leftJoin('students as s','e.student_id','s.id')
            ->select(
                'd.department_name',
                DB::raw('count(e.student_id) as total_students'),
                DB::raw("count(case when s.gender = 'male' then s.id end) as male_count"),
                DB::raw("count(case when s.gender = 'female' then s.id end) as female_count"),
                DB::raw("round(count(case when s.gender = 'female' then s.id end) * 100 / count(e.student_id),2) as female_percentage")
            )
            ->groupBy('department_name')
            ->get();
        
        return $result;
    }

    public function query17()
    {
        /* Query 17: Faculty Experience Report
        Definition: List all faculty with their total years of experience (current date - joining_date). 
        Show faculty name, department, joining_date, and years_of_experience. Order by experience (highest first). */

        $result = Faculty::from('faculties as f')
            ->leftJoin('departments as d','f.department_id','d.id')
            ->select(
                DB::raw("concat(f.first_name,' ',f.last_name) as faculty_name"),
                'd.department_name',
                'f.joining_date',
                DB::raw("TIMESTAMPDIFF(year, f.joining_date, current_date()) as years_of_experience")
            )
            ->orderByDesc('years_of_experience')
            ->get();

        return $result;
    }

    public function query18()
    {
        /* Query 18: Course Enrollment Growth
        Definition: Compare enrollment growth between the last two academic years. Show course name, department, enrollment count in last year, enrollment count in current year, and percentage growth. */

        $result = Course::from('courses as c')
            ->leftJoin('departments as d','c.department_id','d.id')
            ->leftJoin('enrollments as e','c.id','e.course_id')
            ->leftJoin('academic_years as ay','e.academic_year_id','ay.id')
            ->select(
                'c.course_name',
                'd.department_name',
                DB::raw("count(case when ay.id = (select max(id) from academic_years where is_current = 0) then e.student_id end) as last_year_count"),
                DB::raw("count(case when ay.is_current = true then e.student_id end) as current_year_count")
            )
            ->groupBy('course_name','department_name');

        $final = DB::query()
            ->fromSub($result,'')
            ->select(
                'course_name',
                'department_name',
                'last_year_count',
                'current_year_count',
                DB::raw("round(( (current_year_count - last_year_count) * 100 / nullif(last_year_count,0)),2) as growth_percentage")
            )
            ->get();

        return $final;
    }

    public function query19()
    {
        /* Query 19: Faculty-Student Interaction Matrix
        Definition: Find out which faculty taught which students in the current academic year. Display faculty name, subject name, and a concatenated list of student names. */

        $result = Faculty::from('faculties as f')
            ->leftJoin('faculty_assignments as fa','f.id','fa.faculty_id')
            ->leftJoin('course_subjects as cs','fa.course_subject_id','cs.id')
            ->leftJoin('subjects as sub','cs.subject_id','sub.id')
            ->leftJoin('courses as c','cs.course_id','c.id')
            ->leftJoin('enrollments as e','c.id','e.course_id')
            ->leftJoin('students as s','e.student_id','s.id')
            ->select(
                DB::raw("concat(f.first_name,' ',f.last_name) as faculty_name"),
                'sub.subject_name',
                DB::raw("group_concat(concat(s.first_name,' ',s.last_name) separator ';') as students_taught")
            )
            ->groupBy('faculty_name','subject_name')
            ->get();

        return $result;
    }

    public function query20()
    {
        /* Query 20: Student Without Enrollments
        Definition: Find students who are marked "active" but are not enrolled in any course in the current academic year */

        $result = Student::from('students as s')
            ->leftJoin('enrollments as e','s.id','e.student_id')
            ->leftJoin('academic_years as ay','e.academic_year_id','ay.id')
            ->select(
                DB::raw("concat(s.first_name,' ',s.last_name) as student_name"),
                's.email',
            )
            ->where('s.status','active')
            ->where('ay.is_current',true)
            ->whereNull('e.course_id')
            ->get();

        return $result;
    }

    public function query21()
    {
        /* Query 21: SGPA Trend Analysis
        Definition: For each student, show their SGPA progression over semesters. Display student name, course, semester, SGPA, and whether the trend is "Improving", "Declining", or "Stable". */

        $result = Student::from('students as s')
            ->leftJoin('enrollments as e','s.id','e.student_id')
            ->leftJoin('courses as c','e.course_id','c.id')
            ->leftJoin('student_academic_histories as sah','s.id','sah.student_id')
            ->select(
                DB::raw("concat(s.first_name,' ',s.last_name) as student_name"),
                'c.course_name',
                'sah.semester',
                'sah.sgpa',
                DB::raw("lag(sgpa) over (partition by student_name order by semester) as prev"),
                DB::raw("case 
                        when sgpa = (lag(sgpa) over (partition by student_name order by semester)) then 'Stable'
                        when sgpa < (lag(sgpa) over (partition by student_name order by semester)) then 'Declining'
                        when sgpa > (lag(sgpa) over (partition by student_name order by semester)) then 'Improving' end as trend")
            )
            ->groupBy('student_name','course_name','semester','sgpa')
            ->get();

        return $result;
    }

    public function query22()
    {
        /* Query 22: Alumni Tracking
        Definition: Find all students with status = "graduated". Show their name, department, graduation year (last academic_year_id), and email. */

        $result = Student::from('students as s')
            ->leftJoin('enrollments as e','s.id','e.student_id')
            ->leftJoin('courses as c','e.course_id','c.id')
            ->leftJoin('departments as d','c.department_id','d.id')
            ->leftJoin('student_academic_histories as sah','s.id','sah.student_id')
            ->leftJoin('academic_years as ay','sah.academic_year_id','ay.id')
            ->select(
                DB::raw("concat(s.first_name,' ',s.last_name) as student_name"),
                'd.department_name',
                DB::raw("(substring_index(group_concat(ay.name order by sah.semester),',',-1)) as graduation_year"),
                's.email',
            )
            ->groupBy('student_name','department_name','email')
            ->where('s.status','graduated')
            ->get();

        return $result;
    }

    public function query23()
    {
        /* Query 23: Course Semester Difficulty Index
        Definition: For each course, compute a difficulty index = (1 − pass_rate) × 100 for each semester in the last academic year. 
        Show course, semester, avg_sgpa, pass_rate, difficulty_index, and rank semesters within each course by difficulty. */

        $result = Course::from('courses as c')
            ->leftJoin('enrollments as e','c.id','e.course_id')
            ->leftJoin('student_academic_histories as sah','e.student_id','sah.student_id')
            ->select(
                'c.course_name',
                'sah.semester',
                DB::raw("round(avg(sah.sgpa),2) as avg_sgpa"),
                DB::raw("round(count(distinct (case when sah.sgpa >= 4.0 then sah.student_id end)) * 100 / count(sah.student_id),2) as pass_rate"),
                DB::raw("100 - round(count(distinct (case when sah.sgpa >= 4.0 then sah.student_id end)) * 100 / count(sah.student_id),2) as difficulty_index"),
            )
            ->groupBy('course_name','semester');

        $final = DB::query()
            ->fromSub($result,'r')
            ->select(
                'course_name',
                'semester',
                'avg_sgpa',
                'pass_rate',
                'difficulty_index',
                DB::raw("case when difficulty_index = 0 then 'none' else rank() over(partition by course_name order by difficulty_index desc )end as difficulty_rank")
            )
            ->get();

        return $final;        
    }

    public function query24()
    {
        /* Query 24: Student Peer Similarity
        Definition: Identify pairs of students who have enrolled in at least 3 common subjects in the same semesters. Show student A, student B, number of common subjects, and common subject list. */

        $result = Student::from('students as s1')
            ->leftJoin('enrollments as e1','s1.id','e1.student_id')
            ->leftJoin('course_subjects as cs',function($join){
                $join->on('e1.course_id','cs.course_id')
                    ->on('e1.semester','cs.semester');
            })
            ->leftJoin('subjects as sub','cs.subject_id','sub.id')
            ->leftJoin('enrollments as e2',function($join){
                $join->on('e1.course_id','e2.course_id')
                    ->on('e1.semester','e2.semester');
            })
            ->leftJoin('students as s2',function($join){
                $join->on('e2.student_id','s2.id')
                    ->on('e2.student_id','!=','e1.student_id');
            })
            ->select(
                DB::raw("case when s1.id < s2.id 
                            then concat(s1.first_name,' ',s1.last_name) 
                            else concat(s2.first_name,' ',s2.last_name) end as student_a"),
                DB::raw("case when s1.id < s2.id 
                            then concat(s2.first_name,' ',s2.last_name) 
                            else concat(s1.first_name,' ',s1.last_name) end as student_b"),
                DB::raw("count(distinct cs.id) as common_subjects_count"),
                DB::raw("group_concat(distinct sub.subject_name) as common_subjects")
            )
            ->groupByRaw("
                case when s1.id < s2.id then s1.id else s2.id end,
                case when s1.id < s2.id then s2.id else s1.id end
            ")
            ->groupBy('student_a','student_b')
            ->havingRaw('student_a is not null')
            ->having('common_subjects_count','>',2)
            ->get();

        return $result;
    }

    public function query25()
    {
        /* Query 25: Department Stability Index
        Definition: For each department, calculate a stability index:
        (graduated_students − dropped_students) ÷ total_students × 100
        Show department, counts, stability_index, and grade it as Stable (>70), Moderate (50–70), Unstable (<50). */

        $result = Department::from('departments as d')
            ->leftJoin('courses as c','d.id','c.department_id')
            ->leftJoin('enrollments as e','c.id','e.course_id')
            ->leftJoin('students as s','e.student_id','s.id')
            ->select(
                'd.department_name',
                DB::raw("count(case when s.status = 'graduated' then s.id end) as graduated"),
                DB::raw("count(case when s.status = 'dropped' then s.id end) as dropped"),
                DB::raw("count(s.id) as total_students"),
            )
            ->groupBy('department_name');
        
        $final = DB::query()
            ->fromSub($result,'r')
            ->select(
                'department_name',
                'graduated',
                'dropped',
                'total_students',
                DB::raw("(graduated - dropped) / total_students * 100 as stability_index"),
                DB::raw("case
                        when ((graduated - dropped) / total_students * 100) > 70 then 'Stable'
                        when ((graduated - dropped) / total_students * 100) between 50 and 70 then 'Moderate'
                        when ((graduated - dropped) / total_students * 100) < 50 then 'Unstable'
                        end as grade")
            )
            ->get();

        return $final;
    }
}