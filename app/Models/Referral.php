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
    public function admissionIssuedBy() { return $this->belongsTo(User::class, 'admission_issued_by_user_id'); }
    // The TMDU testing workflow record this referral spawned (only present
    // for referral_type = 'psychological_testing' referrals created via
    // CaseController::referToTmdu()).
    public function testingRecord()  { return $this->hasOne(TestingRecord::class); }

    // Immutable history of every Feedback Slip (QF-OSS-01 companion
    // document) sent for this referral - each send creates a new row
    // rather than overwriting a single set of columns, mirroring how
    // session notes are never overwritten either. Newest first.
    public function feedbackSlips()
    {
        return $this->hasMany(FeedbackSlip::class)->latest('sent_at');
    }

    // Helpers
    public function isUrgent(): bool  { return in_array($this->urgency_level, ['high', 'critical']); }
    public function isPending(): bool { return $this->status === 'submitted'; }
}