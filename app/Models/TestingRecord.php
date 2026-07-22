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
        'student_id',
        'referred_by_user_id',
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
    ];

    protected $casts = [
        'tests_administered' => 'array',
        'testing_date'       => 'date',
        'report_date'        => 'date',
        'report_sent_to_gcu' => 'boolean',
        'report_sent_at'     => 'datetime',
    ];

    // Relationships
    public function case()       { return $this->belongsTo(CaseFile::class, 'case_id'); }
    public function student()    { return $this->belongsTo(Student::class); }
    public function referredBy() { return $this->belongsTo(User::class, 'referred_by_user_id'); }
    public function tester()     { return $this->belongsTo(User::class, 'assigned_tester_user_id'); }
    public function documents()  { return $this->morphMany(Document::class, 'documentable'); }
}