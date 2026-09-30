<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Referral extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'referral_code',
        'student_id',
        'case_id',
        'complaint_id',
        'referred_by_user_id',
        'referrer_name',
        'referrer_role',
        'referrer_source',
        'referrer_college',
        'referral_type',
        'nature_of_concern',
        'urgency_level',
        'is_self_referred',
        'is_archived',
        'assigned_to_user_id',
        'assigned_at',
        'status',
        'acknowledged_at',
        'acknowledged_by_user_id',
        'violation_type',
        'incident_description',
        'incident_date',
        'sanction',
        'sanction_notes',
        'has_attachments',
        'intake_notes',
        'feedback_notes',
        'feedback_checklist',
        'feedback_referred_other_text',
        'feedback_others_text',
        'feedback_ctrl_no',
        'feedback_sent_at',
        'feedback_sent_by_user_id',
        'admission_date',
        'admission_time_in',
        'admission_time_out',
        'admission_remarks',
        'admission_issued_at',
        'admission_issued_by_user_id',
    ];

    protected $casts = [
        'is_self_referred' => 'boolean',
        'is_archived'      => 'boolean',
        'has_attachments'  => 'boolean',
        'assigned_at'      => 'datetime',
        'acknowledged_at'  => 'datetime',
        'incident_date'    => 'date',
        'feedback_sent_at'  => 'datetime',
        'feedback_checklist' => 'array',
        'admission_date'    => 'date',
        'admission_issued_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Referral $referral) {
            $year = now()->year;
            $lastCode = static::withTrashed()
                ->where('referral_code', 'like', "REF-{$year}-%")
                ->orderByRaw('CAST(SUBSTRING(referral_code, -4) AS UNSIGNED) DESC')
                ->value('referral_code');

            $nextNumber = $lastCode ? ((int) substr($lastCode, -4)) + 1 : 1;
            $referral->referral_code = 'REF-' . $year . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
        });
    }

    // Relationships
    public function student()        { return $this->belongsTo(Student::class); }
    public function referredBy()     { return $this->belongsTo(User::class, 'referred_by_user_id'); }
    public function assignedTo()     { return $this->belongsTo(User::class, 'assigned_to_user_id'); }
    public function acknowledgedBy() { return $this->belongsTo(User::class, 'acknowledged_by_user_id'); }
    public function case()           { return $this->belongsTo(CaseFile::class, 'case_id'); }
    // The Complaint ("Incident Report") this referral was filed from, if any
    // (ComplaintController::store() creates both together). Null for an
    // ordinary disciplinary referral submitted directly, without a Complaint.
    public function complaint()      { return $this->belongsTo(Complaint::class); }
    public function documents()      { return $this->morphMany(Document::class, 'documentable'); }
    public function sessionNotes()   { return $this->hasMany(SessionNote::class, 'referral_id')->orderBy('session_date'); }
    public function appointments()   { return $this->hasMany(Appointment::class, 'referral_id')->orderBy('appointment_date'); }
    public function feedbackSentBy() { return $this->belongsTo(User::class, 'feedback_sent_by_user_id'); }
    // Every Feedback Slip (QF-OSS-03) copy ever sent for this referral - an
    // append-only log, newest first. See FeedbackSlip: nothing in that
    // table is ever edited or deleted, so each entry here is a locked,
    // permanent copy of what was actually sent to the referrer.
    public function feedbackSlips()  { return $this->hasMany(FeedbackSlip::class, 'referral_id')->orderByDesc('sent_at'); }
    public function admissionIssuedBy() { return $this->belongsTo(User::class, 'admission_issued_by_user_id'); }
    // The TMDU testing workflow record this referral spawned (only present
    // for referral_type = 'psychological_testing' referrals created via
    // CaseController::referToTmdu()).
    public function testingRecord()  { return $this->hasOne(TestingRecord::class); }

    // Helpers
    public function isUrgent(): bool  { return in_array($this->urgency_level, ['high', 'critical']); }
    public function isPending(): bool { return $this->status === 'submitted'; }

    // Staff shouldn't be able to write up outcomes (send a feedback slip,
    // change status, issue an admission slip, refer to TMDU) for a session
    // that hasn't actually happened yet. This looks at whichever appointment
    // is currently "live" for this referral - the most recent non-cancelled
    // one tied to it by referral_id (initial_counseling from acknowledge(),
    // or whatever follow_up_session was scheduled most recently from the SIF
    // page) - and returns null once it's been checked in as attended, or a
    // reason otherwise so callers can explain the block instead of just
    // failing silently. A real TMDU testing referral (no appointment at all
    // until scheduleTesting()) correctly reads as 'not_set' right after
    // acknowledgment.
    public function attendanceGateReason(): ?string
    {
        $current = Appointment::where('referral_id', $this->id)
            ->where('status', '!=', 'cancelled')
            ->orderByDesc('appointment_date')
            ->orderByDesc('start_time')
            ->first();

        if (!$current) {
            return 'not_set';
        }

        return $current->checked_in ? null : 'not_attended';
    }
}