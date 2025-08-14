<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class AcademicYearSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $ac_years = [
            ['name' => '2021-22','year_start' => '2021','year_end' => '2022','is_current' => false],
            ['name' => '2022-23','year_start' => '2022','year_end' => '2023','is_current' => false],
            ['name' => '2023-24','year_start' => '2023','year_end' => '2024','is_current' => false],
            ['name' => '2024-25','year_start' => '2024','year_end' => '2025','is_current' => true],
        ];

        foreach($ac_years as $year)
        {
            AcademicYear::firstOrCreate(['name' => $year['name'],'year_start' => $year['year_start'],'year_end' => $year['year_end'],'is_current' => $year['is_current']]);
        }
    }
}
