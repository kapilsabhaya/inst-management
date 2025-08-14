<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departments = [
        ['department_name'=>'Computer Science'],
        ['department_name'=>'Electronics'],
        ['department_name'=>'Mechanical']
        ];

        foreach($departments as $dept)
        {
            Department::firstOrCreate(['department_name'=>$dept['department_name']]);
        }
    }
}
