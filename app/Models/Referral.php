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
        'referred_by_user_id',
        'referrer_name',
        'referrer_role',
        'referrer_source',
        'referrer_college',
        'referral_type',
        'nature_of_concern',
        'urgency_level',
        'is_self_referred',
        'assigned_to_user_id',
        'assigned_at',
        'status',
        'acknowledged_at',
        'acknowledged_by_user_id',
        'violation_type',
        'incident_description',
        'incident_date',
        'has_attachments',
        'intake_notes',
    ];

    protected $casts = [
        'is_self_referred' => 'boolean',
        'has_attachments'  => 'boolean',
        'assigned_at'      => 'datetime',
        'acknowledged_at'  => 'datetime',
        'incident_date'    => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (Referral $referral) {
            $year = now()->year;
            $count = static::whereYear('created_at', $year)->count() + 1;
            $referral->referral_code = 'REF-' . $year . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
        });
    }

    // Relationships
    public function student()        { return $this->belongsTo(Student::class); }
    public function referredBy()     { return $this->belongsTo(User::class, 'referred_by_user_id'); }
    public function assignedTo()     { return $this->belongsTo(User::class, 'assigned_to_user_id'); }
    public function acknowledgedBy() { return $this->belongsTo(User::class, 'acknowledged_by_user_id'); }
    public function case()           { return $this->hasOne(CaseFile::class, 'referral_id'); }
    public function documents()      { return $this->morphMany(Document::class, 'documentable'); }

    // Helpers
    public function isUrgent(): bool  { return in_array($this->urgency_level, ['high', 'critical']); }
    public function isPending(): bool { return $this->status === 'submitted'; }
}