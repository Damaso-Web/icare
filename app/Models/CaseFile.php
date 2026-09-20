<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CaseFile extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'cases';

    protected $fillable = [
        'case_number',
        'student_id',
        'primary_counselor_id',
        'current_unit',
        'case_type',
        'status',
        'opened_date',
        'closed_date',
        'target_resolution_date',
        'total_sessions',
        'last_session_at',
        'presenting_concern',
        'interventions_applied',
        'outcomes',
        'recommendations',
        'closure_summary',
        'is_recurring',
        'requires_follow_up',
        'follow_up_notes',
        'follow_up_flagged_at',
        'follow_up_flagged_by',
        'referred_to_tmdu',
        'referred_externally',
        'external_referral_destination',
        'student_unreachable',
        'unreachable_flagged_at',
        'unreachable_flagged_by',
        'unreachable_notes',
        'status_changed_at',
        'follow_up_due_date',
    ];

    protected $casts = [
        'opened_date'             => 'date',
        'closed_date'             => 'date',
        'target_resolution_date'  => 'date',
        'last_session_at'         => 'datetime',
        'is_recurring'            => 'boolean',
        'requires_follow_up'      => 'boolean',
        'follow_up_flagged_at'    => 'datetime',
        'referred_to_tmdu'        => 'boolean',
        'referred_externally'     => 'boolean',
        'student_unreachable'     => 'boolean',
        'unreachable_flagged_at'  => 'datetime',
        'status_changed_at'       => 'datetime',
        'follow_up_due_date'      => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (CaseFile $case) {
            // One case per student for life, so the student's own ID number
            // is already a stable, unique key - no separate counter needed.
            $studentIdNumber = Student::whereKey($case->student_id)->value('student_id');
            $case->case_number = 'CASE-' . ($studentIdNumber ?: $case->student_id);
            $case->status_changed_at = now();
        });

        // Centralized so every status change - wherever it happens - resets
        // the inactivity clock the automatic status engine reads from.
        static::updating(function (CaseFile $case) {
            if ($case->isDirty('status')) {
                $case->status_changed_at = now();
            }
        });
    }

    public function student()       { return $this->belongsTo(Student::class); }
    public function referrals()     { return $this->hasMany(Referral::class, 'case_id')->orderBy('created_at'); }
    public function latestReferral(){ return $this->hasOne(Referral::class, 'case_id')->latestOfMany(); }
    public function counselor()     { return $this->belongsTo(User::class, 'primary_counselor_id'); }
    public function flaggedBy()     { return $this->belongsTo(User::class, 'unreachable_flagged_by'); }
    public function followUpFlaggedBy() { return $this->belongsTo(User::class, 'follow_up_flagged_by'); }
    public function interventions()  { return $this->hasMany(CaseIntervention::class, 'case_id')->latest(); }
    public function sessionNotes()  { return $this->hasMany(SessionNote::class, 'case_id')->orderBy('session_date'); }
    public function appointments()  { return $this->hasMany(Appointment::class, 'case_id')->orderBy('appointment_date'); }
    public function testingRecord() { return $this->hasOne(TestingRecord::class, 'case_id'); }
    public function handoffs()      { return $this->hasMany(CaseHandoff::class, 'case_id'); }
    public function documents()     { return $this->morphMany(Document::class, 'documentable'); }

    public function isOpen(): bool  { return !in_array($this->status, ['resolved', 'closed']); }

    // Anything that would make an inactivity-based auto-transition wrong:
    // a session logged recently, an intervention logged recently, or an
    // upcoming appointment that just hasn't happened yet.
    public function hasRecentActivity(int $days): bool
    {
        $since = now()->subDays($days);

        if ($this->last_session_at && $this->last_session_at->gte($since)) {
            return true;
        }

        if ($this->interventions()->where('created_at', '>=', $since)->exists()) {
            return true;
        }

        if ($this->appointments()
            ->where('appointment_date', '>=', now()->toDateString())
            ->whereNotIn('status', ['cancelled'])
            ->exists()
        ) {
            return true;
        }

        return false;
    }
}