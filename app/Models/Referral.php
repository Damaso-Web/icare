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
        'admission_excused',
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
        'admission_excused' => 'boolean',
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

    // Gate for Case Action: sending a Feedback Slip, referring to TMDU, or
    // completing this referral should only be possible once an appointment
    // has actually happened for the case as a whole - "set" (any row
    // exists) is not enough, the student has to have shown up (status
    // 'completed', set by AppointmentController::checkIn()). Checked at the
    // case level (CaseFile::hasAttendedAppointment()) since this is a gate
    // on the SIF as a whole, not a single referral's own appointment list;
    // falls back to this referral's own appointments if it has no case yet.
    public function hasAttendedAppointment(): bool
    {
        // B301: linked by referral, not by case - an attended appointment
        // for a different referral on the same case no longer unlocks this
        // one. Older rows that predate referral linking (referral_id null)
        // on the same case still count so existing SIFs are not locked.
        return Appointment::where('status', 'completed')
            ->where(function ($q) {
                $q->where('referral_id', $this->id);
                if ($this->case_id) {
                    $q->orWhere(fn($w) => $w->whereNull('referral_id')->where('case_id', $this->case_id));
                }
            })
            ->exists();
    }

    // Broader SIF edit gate: nothing on the referral's Student Information
    // File - Previous Interventions, Session Notes, Follow-up Session,
    // Admission Slip, Feedback Slip, or Case Action (Refer to TMDU/Resolve)
    // - should be editable until the referral has actually been
    // acknowledged (status moved past 'submitted') AND an appointment for
    // the case has been attended. Mirrors referrals/Show.vue's canEditSif
    // computed so the frontend gate and this backend gate can't drift.
    public function canEditSif(): bool
    {
        return $this->status !== 'submitted' && $this->hasAttendedAppointment();
    }

    // A SIF whose referral is Resolved ('completed') or 'closed' is final -
    // none of its contents (session notes, follow-ups, interventions,
    // feedback/admission slips, parent conference, referral info) may be
    // changed any more. Mirrors referrals/Show.vue's isResolved computed so
    // the frontend lock and this backend lock can't drift; the frontend one
    // is UX only, this is what actually refuses a direct API call.
    // A referral created through "Refer to TMDU" (the Case Referral Slip)
    // carries its own TestingRecord and belongs to TMDU alone - it is kept
    // out of GCU's Referrals / SIF and TMDU's own queue is limited to these.
    public function scopeTmduOwned($q)    { return $q->whereHas('testingRecord'); }
    public function scopeNotTmduOwned($q) { return $q->whereDoesntHave('testingRecord'); }
    public function isTmduOwned(): bool   { return $this->testingRecord()->exists(); }

    public function isLocked(): bool
    {
        return in_array($this->status, ['completed', 'closed'], true);
    }

    public function abortIfLocked(): void
    {
        if ($this->isLocked()) {
            abort(422, 'This Student Information File is closed and can no longer be edited.');
        }
    }

    // The referral's main pipeline, in order. 'in_review' is retired (B275 -
    // a referral used to jump straight to "In Review" on acknowledge, before
    // any appointment even existed, which was confusing and redundant with
    // "In Progress"). referred_tmdu/referred_external are branch exits, not
    // on this line, so they're deliberately left out here.
    private const STATUS_ORDER = ['submitted', 'acknowledged', 'scheduled', 'in_progress', 'completed', 'closed'];

    /**
     * Whether moving this referral's status to $status is a legal forward
     * move. A status cannot go backward once set. Branch destinations
     * (referred_tmdu, referred_external) aren't on the linear path above -
     * they're allowed as long as the referral hasn't already reached a
     * terminal state (completed/closed) or another branch.
     */
    public function canMoveStatusTo(string $status): bool
    {
        $currentIndex = array_search($this->status, self::STATUS_ORDER);
        $targetIndex  = array_search($status, self::STATUS_ORDER);

        if ($targetIndex === false) {
            return $this->status !== 'completed' && $this->status !== 'closed';
        }

        return $currentIndex === false || $targetIndex > $currentIndex;
    }

    /**
     * Moves the status forward to $status, but only if canMoveStatusTo()
     * allows it - a no-op (returns false) otherwise, so callers along the
     * main pipeline (acknowledge -> confirm -> check-in) can't accidentally
     * move a referral backward just by re-running in the wrong order.
     */
    public function advanceStatusTo(string $status): bool
    {
        if (!$this->canMoveStatusTo($status)) {
            return false;
        }
        $this->update(['status' => $status]);
        return true;
    }

    /**
     * The documents a student must bring/prepare for a given referral type,
     * shown to them before they confirm an appointment (OSS requirement).
     * Returns null when nothing additional needs to be brought (e.g. a
     * counseling/psychological-testing referral - the Referral Slip is
     * already on the system, so there's nothing extra to prepare).
     */
    public static function requiredDocumentsFor(?string $referralType): ?string
    {
        return match ($referralType) {
            'class_attendance' => "1. Original letter of explanation duly signed by the parent, guardian, or concerned teacher in relation to absences and tardiness, if applicable - 1 original copy\n"
                . "2. Valid ID of the Guardian or Parent, if applicable - 1 photocopy\n"
                . "3. One photocopy of ANY of the following supporting documents (original copy for verification), whichever is applicable: Verified Medical Certificate; Approved Travel Order; Death Certificate / Obituary / Barangay Certification of Death of Deceased Relative; Invitation letters or programs with the name of the student indicated; Marriage Certificate; Baptismal Certificate; or Wedding Invitation/Baptism indicating the student's name as sponsor.",

            'academic_deficiency' => "1. Validated BSU ID or Enrollment Form with another valid ID.",

            'leave_of_absence', 'withdrawal', 'readmission', 'shifting' => "1. Client Intake Form (QF-OSS-GCU-01)\n"
                . "2. Forms from the Registrar.",

            // Counseling / psychological testing: the Referral Slip is
            // already on the system, so there's nothing additional to bring.
            'counseling', 'psychological_testing' => null,

            default => null,
        };
    }

    /**
     * Which Appointment::appointment_type the first GCU/SDU appointment for
     * this referral should actually be created as - previously this was
     * hardcoded to 'initial_counseling' for every referral type, so a
     * class-attendance or academic-deficiency referral's appointment showed
     * "Initial Counseling" as its Service even though that's not the
     * service being given. Mapped onto the existing appointment_type enum
     * (widened in 2026_09_28_193000_widen_appointment_type_enum_on_appointments_table)
     * rather than adding new values, so no further migration is needed.
     */
    public static function appointmentTypeFor(?string $referralType): string
    {
        return match ($referralType) {
            'counseling'          => 'initial_counseling',
            'academic_deficiency' => 'academic_coaching',
            'disciplinary'        => 'disciplinary_conference',
            // Class attendance and LOA/withdrawal/readmission/shifting are
            // all a sit-down with OSS to go over documents/forms, not a
            // counseling session - closest existing fit is 'consultation'.
            'class_attendance', 'leave_of_absence', 'withdrawal', 'readmission', 'shifting' => 'consultation',
            default => 'initial_counseling',
        };
    }
}