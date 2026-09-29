<?php

namespace Database\Factories;

use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReferralFactory extends Factory
{
    protected $model = Referral::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'case_id' => CaseFile::factory(),
            'referred_by_user_id' => User::factory(),
            'referrer_name' => $this->faker->name(),
            'referrer_role' => 'faculty',
            'referrer_source' => 'faculty',
            'referral_type' => 'counseling',
            'nature_of_concern' => $this->faker->sentence(),
            'urgency_level' => 'medium',
            'is_self_referred' => false,
            'is_archived' => false,
            'status' => 'submitted',
        ];
    }
}