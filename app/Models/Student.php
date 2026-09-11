<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;

class Student extends Authenticatable
{
    use HasApiTokens, HasFactory, SoftDeletes;

    protected $fillable = [
        'student_id',
        'first_name',
        'last_name',
        'middle_name',
        'email',
        'password',
        'contact_number',
        'sex',
        'birthdate',
        'year_level',
        'college',
        'program',
        'section',
        'address',
        'guardian_first_name',
        'guardian_middle_name',
        'guardian_last_name',
        'guardian_contact',
        'guardian_relationship',
        'medical_notes',
        'is_active',
        'last_login_at',
        'must_change_password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'birthdate'             => 'date',
        'is_active'             => 'boolean',
        'must_change_password'  => 'boolean',
        'last_login_at'         => 'datetime',
        'email_verified_at'     => 'datetime',
    ];

    // Relationships
    public function referrals()      { return $this->hasMany(Referral::class)->latest(); }
    public function cases()          { return $this->hasMany(CaseFile::class)->latest(); }
    public function appointments()   { return $this->hasMany(Appointment::class)->latest(); }
    public function sessionNotes()   { return $this->hasMany(SessionNote::class)->latest(); }
    public function testingRecords() { return $this->hasMany(TestingRecord::class)->latest(); }
    public function documents()      { return $this->morphMany(Document::class, 'documentable'); }

    public function activeCase()     { return $this->hasOne(CaseFile::class)->whereIn('status', ['open', 'in_progress', 'awaiting_testing']); }
    public function isRecurring(): bool { return $this->cases()->count() > 1; }
}