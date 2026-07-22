<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'student_id',
        'first_name',
        'last_name',
        'middle_name',
        'email',
        'contact_number',
        'sex',
        'birthdate',
        'year_level',
        'college',
        'program',
        'section',
        'address',
        'guardian_name',
        'guardian_contact',
        'guardian_relationship',
        'medical_notes',
        'is_active',
    ];

    protected $casts = [
        'birthdate' => 'date',
        'is_active' => 'boolean',
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