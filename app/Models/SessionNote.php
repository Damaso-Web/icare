<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SessionNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'case_id',
        'student_id',
        'recorded_by_user_id',
        'session_number',
        'session_date',
        'session_start_time',
        'session_end_time',
        'duration_minutes',
        'session_type',
        'observations',
        'interventions',
        'student_response',
        'next_steps',
        'student_showed_up',
        'mood_rating',
        'follow_up_needed',
    ];

    protected $casts = [
        'session_date'      => 'date',
        'student_showed_up' => 'boolean',
        'follow_up_needed'  => 'boolean',
    ];

    public function case()       { return $this->belongsTo(CaseFile::class, 'case_id'); }
    public function student()    { return $this->belongsTo(Student::class); }
    public function recordedBy() { return $this->belongsTo(User::class, 'recorded_by_user_id'); }
}