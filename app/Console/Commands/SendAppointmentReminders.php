<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Notifications\AppointmentReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// Reminds students of their confirmed appointments: the day before, and again
// a few hours before. Meant to run every hour. Each reminder is sent once per
// appointment slot, and a reminder still unread is withdrawn if its
// appointment has since been cancelled or moved.
class SendAppointmentReminders extends Command
{
    protected $signature = 'reminders:appointments';
    protected $description = 'Notify students of confirmed appointments tomorrow and in the next few hours.';

    /** "A few hours before": the same-day reminder goes out this many hours ahead. */
    private const HOURS_BEFORE = 3;

    public function handle(): int
    {
        $sent = 0;

        // Day before.
        foreach ($this->confirmed()->whereDate('appointment_date', now()->addDay()->toDateString())->get() as $a) {
            $sent += $this->remind($a, 'tomorrow');
        }

        // Same day, starting within the next few hours.
        $nowTime   = now()->format('H:i:s');
        $untilTime = now()->addHours(self::HOURS_BEFORE)->format('H:i:s');
        $sameDay   = $this->confirmed()->whereDate('appointment_date', now()->toDateString())->where('start_time', '>', $nowTime);
        // Late in the evening "a few hours ahead" runs past midnight; then everything left today qualifies.
        if ($untilTime > $nowTime) {
            $sameDay->where('start_time', '<=', $untilTime);
        }
        foreach ($sameDay->get() as $a) {
            $sent += $this->remind($a, 'today');
        }

        $withdrawn = $this->withdrawStale();

        $this->info("Appointment reminders sent: {$sent}. Withdrawn: {$withdrawn}.");

        return self::SUCCESS;
    }

    private function confirmed()
    {
        return Appointment::with(['student', 'staff'])->where('status', 'confirmed')->whereNotNull('student_id');
    }

    /** Sends one reminder unless this exact one was already sent. Returns 1 if sent. */
    private function remind(Appointment $a, string $kind): int
    {
        if (!$a->student) {
            return 0;
        }

        $already = $a->student->notifications()
            ->where('type', AppointmentReminderNotification::class)
            ->where('data->appointment_id', $a->id)
            ->where('data->kind', $kind)
            ->where('data->slot', AppointmentReminderNotification::slot($a))
            ->exists();
        if ($already) {
            return 0;
        }

        $a->student->notify(new AppointmentReminderNotification($a, $kind));
        $a->forceFill(['reminder_sent' => true, 'reminder_sent_at' => now()])->saveQuietly();

        return 1;
    }

    /** Removes unread reminders whose appointment is no longer confirmed, or is now at another time. */
    private function withdrawStale(): int
    {
        $removed = 0;
        $recent = DB::table('notifications')
            ->where('type', AppointmentReminderNotification::class)
            ->whereNull('read_at')
            ->where('created_at', '>=', now()->subDays(3))
            ->get(['id', 'data']);

        foreach ($recent as $row) {
            $data = json_decode($row->data, true) ?: [];
            $a = Appointment::find($data['appointment_id'] ?? 0);
            if (!$a || $a->status !== 'confirmed' || AppointmentReminderNotification::slot($a) !== ($data['slot'] ?? '')) {
                DB::table('notifications')->where('id', $row->id)->delete();
                $removed++;
            }
        }

        return $removed;
    }
}
