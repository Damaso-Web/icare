<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CaseIntervention extends Model
{
    use HasFactory;

    protected $table = 'case_interventions';

    protected $fillable = [
        'case_id',
        'referral_id',
        'description',
        'type',
        'person_in_charge_id',
        'excused',
        'recorded_by_user_id',
        'is_completed',
        'completed_at',
        'completed_by_user_id',
    ];

    protected $casts = [
        'excused'      => 'boolean',
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
    ];

    public function caseFile()       { return $this->belongsTo(CaseFile::class, 'case_id'); }
    public function referral()       { return $this->belongsTo(Referral::class, 'referral_id'); }
    public function personInCharge() { return $this->belongsTo(User::class, 'person_in_charge_id'); }
    public function recordedBy()     { return $this->belongsTo(User::class, 'recorded_by_user_id'); }
    public function completedBy()    { return $this->belongsTo(User::class, 'completed_by_user_id'); }
}