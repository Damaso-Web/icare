<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// One immutable record per Feedback Slip send for a referral - mirrors how
// SessionNote rows are never overwritten. See Referral::feedbackSlips().
class FeedbackSlip extends Model
{
    use HasFactory;

    protected $fillable = [
        'referral_id',
        'sent_by_user_id',
        'sent_to_name',
        'sent_to_role',
        'feedback_checklist',
        'feedback_referred_other_text',
        'feedback_others_text',
        'feedback_notes',
        'feedback_ctrl_no',
        'sent_at',
    ];

    protected $casts = [
        'feedback_checklist' => 'array',
        'sent_at'             => 'datetime',
    ];

    public function referral() { return $this->belongsTo(Referral::class); }
    // "Recorded By" on the slip.
    public function sentBy()   { return $this->belongsTo(User::class, 'sent_by_user_id'); }
}