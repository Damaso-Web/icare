<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Complaint extends Model
{
    use HasFactory;

    protected $fillable = [
        'complaint_code',
        'complainant_name',
        'complainant_address',
        'complainee_student_id',
        'complainee_position',
        'complainee_college',
        'complainee_department',
        'complainee_office',
        'complainee_address',
        'violation_type',
        'description', // used as "Narration of Facts" (what/when/where/how)
        'incident_date',
        'filed_by_user_id',
        'status',
        'certification_agreed',
    ];

    protected $casts = [
        'incident_date'         => 'date',
        'certification_agreed'  => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (Complaint $complaint) {
            $year = now()->year;
            $count = static::whereYear('created_at', $year)->count() + 1;
            $complaint->complaint_code = sprintf('CMP-%d-%05d', $year, $count);
        });
    }

    public function complainee()
    {
        return $this->belongsTo(Student::class, 'complainee_student_id');
    }

    public function filedBy()
    {
        return $this->belongsTo(User::class, 'filed_by_user_id');
    }

    public function attachments()
    {
        return $this->hasMany(ComplaintAttachment::class);
    }
}