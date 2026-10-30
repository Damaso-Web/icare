<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\CaseFile;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    public function definition(): array
    {
        $date = now()->startOfDay()->next(Carbon::MONDAY)->toDateString();

        return [
            'student_id'           => Student::factory(),
            'case_id'              => CaseFile::factory(),
            'staff_user_id'        => User::factory(),
            'created_by_user_id'   => User::factory(),
            'unit'                 => 'GCU',
            'appointment_type'     => 'initial_counseling',
            'appointment_date'     => $date,
            'start_time'           => '09:00',
            'end_time'             => '10:00',
            'duration_minutes'     => 60,
            'status'               => 'confirmed',
            'request_status'       => 'confirmed',
            'confirmation_sent'    => true,
            'confirmation_sent_at' => now(),
        ];
    }
}