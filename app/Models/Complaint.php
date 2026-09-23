<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Complaint extends Model
{
    protected $fillable = [
        'complainee_student_id',
        'violation_type',
        'description',
        'incident_date',
        'filed_by_user_id',
        'status',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (Complaint $complaint) {
            if (!$complaint->complaint_code) {
                $next = (self::max('id') ?? 0) + 1;
                $complaint->complaint_code = 'CMP-' . date('Y') . '-' . str_pad($next, 5, '0', STR_PAD_LEFT);
            }
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
}