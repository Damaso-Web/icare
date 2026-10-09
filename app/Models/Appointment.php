<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Appointment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'appointment_code',
        'case_id',
        'referral_id',
        'student_id',
        'staff_user_id',
        'created_by_user_id',
        'unit',
        'appointment_type',
        'appointment_date',
        'start_time',
        'end_time',
        'duration_minutes',
        'location',
        'status',
        'confirmation_sent',
        'confirmation_sent_at',
        'reminder_sent',
        'reminder_sent_at',
        'rescheduled_from_id',
        'reschedule_reason',
        'cancellation_reason',
        'cancelled_at',
        'cancelled_by_user_id',
        'checked_in',
        'checked_in_at',
        'checked_in_by_user_id',
        'no_show_escalated',
        'no_show_escalated_at',
        'notes',
        'scheduling_token',
        'token_expires_at',
        'request_status',
        'call_slip_stage',
        'call_slip_initiated_at',
        'call_slip_notes',
        'required_documents',
        'reschedule_count',
        'on_hold_at',
    ];

    protected $casts = [
        'token_expires_at'       => 'datetime',
        'call_slip_initiated_at' => 'datetime',
        'on_hold_at'             => 'datetime',
        'appointment_date'      => 'date:Y-m-d',
        'confirmation_sent'     => 'boolean',
        'confirmation_sent_at'  => 'datetime',
        'reminder_sent'         => 'boolean',
        'reminder_sent_at'      => 'datetime',
        'cancelled_at'          => 'datetime',
        'checked_in'            => 'boolean',
        'checked_in_at'         => 'datetime',
        'no_show_escalated'     => 'boolean',
        'no_show_escalated_at'  => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Appointment $appt) {
            $year = now()->year;
            $lastCode = static::withTrashed()
                ->where('appointment_code', 'like', "APT-{$year}-%")
                ->orderByRaw('CAST(SUBSTRING(appointment_code, -4) AS UNSIGNED) DESC')
                ->value('appointment_code');

            $nextNumber = 1;
            if ($lastCode) {
                $lastNumber = (int) substr($lastCode, -4);
                $nextNumber = $lastNumber + 1;
            }

            $appt->appointment_code = 'APT-' . $year . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
        });
    }

    public function case()              { return $this->belongsTo(CaseFile::class, 'case_id'); }
    public function referral()          { return $this->belongsTo(Referral::class, 'referral_id'); }
    public function student()           { return $this->belongsTo(Student::class); }
    public function staff()             { return $this->belongsTo(User::class, 'staff_user_id'); }
    public function createdBy()         { return $this->belongsTo(User::class, 'created_by_user_id'); }
    public function cancelledBy()       { return $this->belongsTo(User::class, 'cancelled_by_user_id'); }
    public function checkedInBy()       { return $this->belongsTo(User::class, 'checked_in_by_user_id'); }
    public function rescheduledFrom()   { return $this->belongsTo(Appointment::class, 'rescheduled_from_id'); }

    // The first other appointment that overlaps this staff member's slot, or
    // null. Split out from hasConflict() so callers can tell the user WHICH
    // appointment is in the way instead of a bare "conflict".
    public static function findConflict(int $staffId, string $date, string $start, string $end, int|array|null $excludeId = null): ?self
    {
        return static::where('staff_user_id', $staffId)
            ->where('appointment_date', $date)
            // A superseded appointment (B234: replaced by a new linked row on
            // reschedule) no longer holds its old slot, same as a cancelled one.
            ->whereNotIn('status', ['cancelled', 'rescheduled'])
            ->where('request_status', '!=', 'awaiting_student')
            ->where('start_time', '<', $end)
            ->where('end_time', '>', $start)
            ->when($excludeId, fn($q) => $q->whereNotIn('id', (array) $excludeId))
            ->with('student')
            ->first();
    }

    public static function hasConflict(int $staffId, string $date, string $start, string $end, int|array|null $excludeId = null): bool
    {
        return static::findConflict($staffId, $date, $start, $end, $excludeId) !== null;
    }

public static function hasUnitConflict(string $unit, string $date, string $start, string $end, ?int $excludeId = null): bool
{
    return static::where('unit', $unit)
        ->where('appointment_date', $date)
        ->whereNotIn('status', ['cancelled', 'rescheduled'])
        ->where('request_status', '!=', 'awaiting_student')
        ->where('start_time', '<', $end)
        ->where('end_time', '>', $start)
        ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
        ->exists();
}


    /**
     * Tell the people handling this appointment that the student acted on it:
     * the assigned staff, whoever created it, and the unit head/staff.
     * Never throws - the student's action is already saved.
     */
    public function notifyOffice(string $kind = 'scheduled'): void
    {
        try {
            $roles = match ($this->unit) {
                'SDU'   => ['sdu_head'],
                'TMDU'  => ['tmdu_staff'],
                default => ['admin', 'gcu_staff'],
            };
            $ids = \App\Models\User::where('is_active', true)->whereIn('role', $roles)->pluck('id')
                ->merge([$this->staff_user_id, $this->created_by_user_id])->filter()->unique();
            $users = \App\Models\User::whereIn('id', $ids)->where('is_active', true)->get();
            foreach ($users as $u) {
                try {
                    $u->notify(new \App\Notifications\AppointmentRequestedNotification($this, $kind));
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}