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
    ];

    protected $casts = [
        'appointment_date'      => 'date',
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
            $year  = now()->year;
            $count = static::whereYear('created_at', $year)->count() + 1;
            $appt->appointment_code = 'APT-' . $year . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
        });
    }

    public function case()              { return $this->belongsTo(CaseFile::class, 'case_id'); }
    public function student()           { return $this->belongsTo(Student::class); }
    public function staff()             { return $this->belongsTo(User::class, 'staff_user_id'); }
    public function createdBy()         { return $this->belongsTo(User::class, 'created_by_user_id'); }
    public function cancelledBy()       { return $this->belongsTo(User::class, 'cancelled_by_user_id'); }
    public function checkedInBy()       { return $this->belongsTo(User::class, 'checked_in_by_user_id'); }
    public function rescheduledFrom()   { return $this->belongsTo(Appointment::class, 'rescheduled_from_id'); }

    public static function hasConflict(int $staffId, string $date, string $start, string $end, ?int $excludeId = null): bool
    {
        return static::where('staff_user_id', $staffId)
            ->where('appointment_date', $date)
            ->whereNotIn('status', ['cancelled'])
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('start_time', [$start, $end])
                  ->orWhereBetween('end_time', [$start, $end])
                  ->orWhere(function ($q2) use ($start, $end) {
                      $q2->where('start_time', '<=', $start)
                         ->where('end_time', '>=', $end);
                  });
            })
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->exists();
    }
}