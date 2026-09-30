<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeedbackSlip extends Model
{
    use HasFactory;

    // Append-only: rows here are never edited after creation
    // (ReferralController::sendFeedback() only ever creates, never updates
    // or deletes a FeedbackSlip). There is deliberately no update()/destroy()
    // endpoint for this model - that IS the lock the Feedback Slip needed.
    protected $fillable = [
        'referral_id',
        'case_id',
        'student_id',
        'ctrl_no',
        'checklist',
        'referred_other_text',
        'others_text',
        'notes',
        'recorded_by_user_id',
        'sent_to_user_id',
        'sent_to_name',
        'sent_to_role',
        'sent_at',
    ];

    protected $casts = [
        'checklist' => 'array',
        'sent_at'   => 'datetime',
    ];

    public function referral()   { return $this->belongsTo(Referral::class, 'referral_id'); }
    public function case()       { return $this->belongsTo(CaseFile::class, 'case_id'); }
    public function student()    { return $this->belongsTo(Student::class); }
    // Attending OSS Personnel who filled out and sent this copy.
    public function recordedBy() { return $this->belongsTo(User::class, 'recorded_by_user_id'); }
    // The referrer this copy was sent to, when they have a system account.
    public function sentTo()     { return $this->belongsTo(User::class, 'sent_to_user_id'); }
}