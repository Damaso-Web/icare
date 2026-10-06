<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Student extends Authenticatable
{
    use HasApiTokens, HasFactory, SoftDeletes, Notifiable;

    protected $fillable = [
        'student_id',
        'first_name',
        'last_name',
        'middle_name',
        'suffix',
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
        'consent_accepted_at',
        'must_change_password',
        'temp_password',
        'deactivation_reason',
        'deactivation_notes',
        'father_first_name',
        'father_middle_name',
        'father_last_name',
        'father_occupation',
        'father_contact_number',
        'mother_first_name',
        'mother_middle_name',
        'mother_last_name',
        'mother_occupation',
        'mother_contact_number',
        'siblings',
        'elementary_school',
        'elementary_year_graduated',
        'high_school',
        'high_school_year_graduated',
        'college_school',
        'college_year_graduated',
        'civil_status', 'nationality', 'birthplace', 'languages',
        'father_age', 'father_educational_attainment',
        'mother_age', 'mother_educational_attainment',
        'guardian_age', 'guardian_occupation', 'guardian_educational_attainment',
        'senior_high_school', 'senior_high_year_graduated',
        'senior_high_achievements', 'high_school_achievements', 'elementary_achievements',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'temp_password',
    ];

    protected $casts = [
        'birthdate'             => 'date',
        'is_active'             => 'boolean',
        'must_change_password'  => 'boolean',
        'last_login_at'         => 'datetime',
        'consent_accepted_at'   => 'datetime',
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
    public function isRecurring(): bool { return $this->referrals()->count() > 1; }
}