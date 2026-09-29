<?php

namespace Database\Factories;

use App\Models\CaseFile;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

class CaseFileFactory extends Factory
{
    protected $model = CaseFile::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'current_unit' => 'GCU',
            'case_type' => 'counseling',
            'status' => 'open',
            'opened_date' => now()->toDateString(),
            'total_sessions' => 0,
            'is_recurring' => false,
            'requires_follow_up' => false,
            'referred_to_tmdu' => false,
            'referred_externally' => false,
            'student_unreachable' => false,
        ];
    }
}