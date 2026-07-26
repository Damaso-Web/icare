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
        'referral_id',
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
        'referred_to_tmdu'        => 'boolean',
        'referred_externally'     => 'boolean',
        'student_unreachable'     => 'boolean',
        'unreachable_flagged_at'  => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (CaseFile $case) {
            $year  = now()->year;
            $count = static::whereYear('created_at', $year)->count() + 1;
            $case->case_number = 'CASE-' . $year . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
        });
    }

    public function student()       { return $this->belongsTo(Student::class); }
    public function referral()      { return $this->belongsTo(Referral::class); }
    public function counselor()     { return $this->belongsTo(User::class, 'primary_counselor_id'); }
    public function flaggedBy()     { return $this->belongsTo(User::class, 'unreachable_flagged_by'); }
    public function sessionNotes()  { return $this->hasMany(SessionNote::class, 'case_id')->orderBy('session_date'); }
    public function appointments()  { return $this->hasMany(Appointment::class, 'case_id')->orderBy('appointment_date'); }
    public function testingRecord() { return $this->hasOne(TestingRecord::class, 'case_id'); }
    public function handoffs()      { return $this->hasMany(CaseHandoff::class, 'case_id'); }
    public function documents()     { return $this->morphMany(Document::class, 'documentable'); }

    public function isOpen(): bool  { return !in_array($this->status, ['resolved', 'closed']); }
}