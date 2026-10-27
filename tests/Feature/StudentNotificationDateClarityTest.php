<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Student;
use App\Notifications\AppointmentConfirmedNotification;
use App\Notifications\DocumentsRequiredNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// B109: student-facing appointment notifications dropped in the raw
// appointment_date datetime string (misleadingly showing midnight) and
// never mentioned the time at all - fixed to a plain date and a 12-hour
// time with AM/PM.
class StudentNotificationDateClarityTest extends TestCase
{
    use RefreshDatabase;

    private function appointment(): Appointment
    {
        $student = Student::factory()->create();
        return Appointment::create([
            'student_id' => $student->id, 'unit' => 'GCU', 'appointment_type' => 'counseling',
            'appointment_date' => '2026-11-03', 'start_time' => '14:30', 'end_time' => '15:30',
            'status' => 'confirmed', 'appointment_code' => 'APT-TEST-1',
        ]);
    }

    public function test_appointment_confirmed_message_has_readable_date_and_time(): void
    {
        $appointment = $this->appointment();
        $message = (new AppointmentConfirmedNotification($appointment))->toArray($appointment->student)['message'];

        $this->assertStringContainsString('November 3, 2026', $message);
        $this->assertStringContainsString('2:30 PM', $message);
        $this->assertStringNotContainsString('00:00:00', $message);
    }

    public function test_documents_required_message_has_readable_date_and_time(): void
    {
        $appointment = $this->appointment();
        $appointment->required_documents = 'Valid ID, Parent consent form';
        $message = (new DocumentsRequiredNotification($appointment))->toArray($appointment->student)['message'];

        $this->assertStringContainsString('November 3, 2026', $message);
        $this->assertStringContainsString('2:30 PM', $message);
        $this->assertStringNotContainsString('00:00:00', $message);
    }
}