<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'employee_id',
        'password',
        'role',
        'unit',
        'college',
        'department',
        'contact_number',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at'     => 'datetime',
        'is_active'         => 'boolean',
    ];

    // Role helpers
    public function isAdmin(): bool        { return $this->role === 'admin'; }
    public function isGCUStaff(): bool     { return $this->role === 'gcu_staff'; }
    public function isSDUHead(): bool      { return $this->role === 'sdu_head'; }
    public function isTMDUStaff(): bool    { return $this->role === 'tmdu_staff'; }
    public function isFaculty(): bool      { return $this->role === 'faculty'; }
    public function isDeanSecretary(): bool{ return $this->role === 'dean_secretary'; }
    public function canCounsel(): bool     { return in_array($this->role, ['admin', 'gcu_staff']); }

    // Relationships
    public function submittedReferrals()  { return $this->hasMany(Referral::class, 'referred_by_user_id'); }
    public function assignedReferrals()   { return $this->hasMany(Referral::class, 'assigned_to_user_id'); }
    public function cases()               { return $this->hasMany(CaseFile::class, 'primary_counselor_id'); }
    public function appointments()        { return $this->hasMany(Appointment::class, 'staff_user_id'); }
    public function availability()        { return $this->hasMany(StaffAvailability::class); }
    public function sessionNotes()        { return $this->hasMany(SessionNote::class, 'recorded_by_user_id'); }
    public function auditLogs()           { return $this->hasMany(AuditLog::class); }
}