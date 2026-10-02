<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TestingRecord extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'case_id',
        'referral_id',
        'source_referral_id',
        'student_id',
        'referred_by_user_id',
        'reason',
        'assigned_tester_user_id',
        'status',
        'tests_administered',
        'testing_date',
        'report_date',
        'assessment_summary',
        'findings',
        'recommendations',
        'report_sent_to_gcu',
        'report_sent_at',
        'or_photo_path',
        'or_photo_original_name',
        'or_uploaded_at',
        'or_stamped_at',
        'or_stamped_by_user_id',
    ];

    protected $casts = [
        'tests_administered' => 'array',
        'testing_date'       => 'date',
        'report_date'        => 'date',
        'report_sent_to_gcu' => 'boolean',
        'report_sent_at'     => 'datetime',
        'or_uploaded_at'     => 'datetime',
        'or_stamped_at'      => 'datetime',
    ];

    // Relationships
    public function case()        { return $this->belongsTo(CaseFile::class, 'case_id'); }
    // The shared GCU<->TMDU Referral this testing record was created from.
    // Nullable - records created before this link existed won't have one.
    public function referral()    { return $this->belongsTo(Referral::class); }
    // The GCU-side referral that this specific TMDU escalation was actually
    // started from (CaseController::referToTmdu()) - NOT the same thing as
    // referral() above, which is this record's own psychological_testing
    // sibling referral. This is what scopes "Refer to TMDU" / "Already
    // referred to TMDU" per-referral on referrals/Show.vue's
    // tmduTestingRecord computed, instead of sharing one TMDU escalation
    // across every referral under the case. Nullable - records created
    // before this column existed won't have one.
    public function sourceReferral() { return $this->belongsTo(Referral::class, 'source_referral_id'); }
    public function student()     { return $this->belongsTo(Student::class); }
    public function referredBy()  { return $this->belongsTo(User::class, 'referred_by_user_id'); }
    public function tester()      { return $this->belongsTo(User::class, 'assigned_tester_user_id'); }
    public function orStampedBy() { return $this->belongsTo(User::class, 'or_stamped_by_user_id'); }
    public function documents()   { return $this->morphMany(Document::class, 'documentable'); }

    // Appointments aren't owned by a specific TestingRecord directly (no FK
    // column for it), so this is keyed off the shared case_id - same pattern
    // CaseFile::appointments() already uses. But a case_id is shared with
    // every other referral on that same case too (a psychological_testing
    // referral's case_id is the student's one lifetime case, same as GCU's),
    // so without a type scope this pulled in every appointment on the case -
    // e.g. scheduling a follow-up session from the referral's SIF page
    // (Show.vue::saveFollowUp()) would show up here under "Testing Records >
    // Appointments" even though it has nothing to do with the testing
    // workflow. Note this can't be scoped by unit instead: once the case is
    // referred to TMDU, its current_unit is 'TMDU', and a follow-up session
    // scheduled from the SIF page inherits that same unit (Show.vue sends
    // `unit: referral.case.current_unit`) - so unit alone doesn't
    // distinguish it from a real testing appointment. appointment_type does:
    // only scheduleTesting()/schedulePar() create 'psychological_testing'/
    // 'par_release' appointments, and those are the only ones that actually
    // belong to this record's workflow ('fee_form_pickup' is in the enum for
    // completeness but nothing currently creates one - the fee-form pickup
    // is a walk-in with no appointment at all).
    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'case_id', 'case_id')
            ->whereIn('appointment_type', ['psychological_testing', 'par_release', 'fee_form_pickup']);
    }
}