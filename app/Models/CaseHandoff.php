<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CaseHandoff extends Model
{
    use HasFactory;

    protected $table = 'case_handoffs';

    protected $fillable = [
        'case_id',
        'referral_id',
        'from_user_id',
        'to_user_id',
        'from_unit',
        'to_unit',
        'reason',
        'notes',
        'acknowledged',
        'acknowledged_at',
    ];

    protected $casts = [
        'acknowledged'    => 'boolean',
        'acknowledged_at' => 'datetime',
    ];

    // Relationships
    public function case()     { return $this->belongsTo(CaseFile::class, 'case_id'); }
    // Which of the case's referrals this endorsement was actually made from -
    // nullable since a case can carry more than one referral (e.g. the GCU
    // referral and the sibling psychological_testing referral created by
    // "Refer to TMDU"), and without this a handoff endorsed from one
    // referral's SIF showed up on every other referral sharing the same
    // case too. Null on older rows created before this column existed.
    public function referral() { return $this->belongsTo(Referral::class); }
    public function fromUser() { return $this->belongsTo(User::class, 'from_user_id'); }
    public function toUser()   { return $this->belongsTo(User::class, 'to_user_id'); }
}