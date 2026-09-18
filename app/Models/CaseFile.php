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
        'background_info',
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
    ];

    protected static function booted(): void
    {
        static::creating(function (CaseFile $case) {
            $studentId = optional(Student::find($case->student_id))->student_id;

            if ($studentId) {
                $case->case_number = 'CASE-' . $studentId;
            } else {
                // Fallback for the rare case where the student record can't be resolved yet
                $year = now()->year;
                $lastNumber = static::withTrashed()
                    ->where('case_number', 'like', "CASE-{$year}-%")
                    ->orderByRaw('CAST(SUBSTRING(case_number, -4) AS UNSIGNED) DESC')
                    ->value('case_number');

                $nextNumber = 1;
                if ($lastNumber) {
                    $nextNumber = (int) substr($lastNumber, -4) + 1;
                }

                $case->case_number = 'CASE-' . $year . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
            }
        });
    }

    public function student()       { return $this->belongsTo(Student::class); }
    public function referrals()     { return $this->hasMany(Referral::class, 'case_id')->orderBy('created_at'); }
    public function latestReferral(){ return $this->hasOne(Referral::class, 'case_id')->latestOfMany(); }
    public function counselor()     { return $this->belongsTo(User::class, 'primary_counselor_id'); }
    public function flaggedBy()     { return $this->belongsTo(User::class, 'unreachable_flagged_by'); }
    public function followUpFlaggedBy() { return $this->belongsTo(User::class, 'follow_up_flagged_by'); }
    public function sessionNotes()  { return $this->hasMany(SessionNote::class, 'case_id')->orderBy('session_date'); }
    public function appointments()  { return $this->hasMany(Appointment::class, 'case_id')->orderBy('appointment_date'); }
    public function testingRecord() { return $this->hasOne(TestingRecord::class, 'case_id'); }
    public function handoffs()      { return $this->hasMany(CaseHandoff::class, 'case_id'); }
    public function documents()     { return $this->morphMany(Document::class, 'documentable'); }

    public function isOpen(): bool  { return !in_array($this->status, ['resolved', 'closed']); }
}