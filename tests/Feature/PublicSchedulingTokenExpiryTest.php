<?php

namespace Tests\Feature;

use App\Models\Appointment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * B642 regression: every token-gated public scheduling route must reject an
 * expired or unknown token with 404 before returning any availability data,
 * and must keep working for a valid, unexpired token.
 */
class PublicSchedulingTokenExpiryTest extends TestCase
{
    use RefreshDatabase;

    private function appointment(array $overrides = []): Appointment
    {
        // Adjust the factory() call if AppointmentFactory requires a student/case.
        return Appointment::factory()->create(array_merge([
            'scheduling_token' => 'valid-token-abc',
            'token_expires_at' => now()->addDays(7),
            'status'           => 'pending',
            'request_status'   => 'awaiting_student',
        ], $overrides));
    }

    private function expired(): Appointment
    {
        return $this->appointment([
            'scheduling_token' => 'expired-token-abc',
            'token_expires_at' => now()->subDay(),
        ]);
    }

    /** Next weekday, so the valid-path assertions don't trip the Mon–Fri rule. */
    private function nextWeekday(): string
    {
        return now()->startOfDay()->next(\Carbon\Carbon::MONDAY)->toDateString();
    }

    public static function publicTokenRoutes(): array
    {
        return [
            'show'              => ['get',  '/api/schedule/%s'],
            'check-availability'=> ['post', '/api/schedule/%s/check-availability'],
            'month-availability'=> ['get',  '/api/schedule/%s/month-availability'],
            'submit'            => ['post', '/api/schedule/%s/submit'],
        ];
    }

    #[DataProvider('publicTokenRoutes')]
    public function test_expired_token_returns_404(string $method, string $uri): void
    {
        $appointment = $this->expired();

        $response = $method === 'get'
            ? $this->getJson(sprintf($uri, $appointment->scheduling_token))
            : $this->postJson(sprintf($uri, $appointment->scheduling_token), $this->payload($method));

        $response->assertNotFound();
    }

    #[DataProvider('publicTokenRoutes')]
    public function test_unknown_token_returns_404(string $method, string $uri): void
    {
        $response = $method === 'get'
            ? $this->getJson(sprintf($uri, 'no-such-token'))
            : $this->postJson(sprintf($uri, 'no-such-token'), $this->payload($method));

        $response->assertNotFound();
    }

    private function payload(string $method): array
    {
        return match ($method) {
            'check-availability', 'submit' => [
                'appointment_date' => $this->nextWeekday(),
                'start_time'       => '09:00',
                'end_time'         => '10:00',
            ],
            default => [],
        };
    }

    // --- Allowed controls: the fix must not break legitimate flows ---

    public function test_valid_token_show_returns_appointment(): void
    {
        $appointment = $this->appointment();

        $this->getJson("/api/schedule/{$appointment->scheduling_token}")
            ->assertOk()
            ->assertJsonPath('appointment.id', $appointment->id);
    }

    public function test_valid_token_check_availability_returns_availability(): void
    {
        $appointment = $this->appointment();

        $this->postJson("/api/schedule/{$appointment->scheduling_token}/check-availability", [
            'appointment_date' => $this->nextWeekday(),
            'start_time'       => '09:00',
            'end_time'         => '10:00',
        ])->assertOk()->assertJsonStructure(['available']);
    }

    public function test_valid_token_month_availability_returns_days(): void
    {
        $appointment = $this->appointment();

        $this->getJson("/api/schedule/{$appointment->scheduling_token}/month-availability?month=" . now()->format('Y-m'))
            ->assertOk()
            ->assertJsonStructure(['days' => [['date', 'status']], 'previous_slots']);
    }

    public function test_valid_token_submit_schedules_appointment(): void
    {
        $appointment = $this->appointment();

        $this->postJson("/api/schedule/{$appointment->scheduling_token}/submit", [
            'appointment_date' => $this->nextWeekday(),
            'start_time'       => '09:00',
            'end_time'         => '10:00',
        ])->assertOk();

        $this->assertSame('pending_confirmation', $appointment->fresh()->request_status);
    }
}