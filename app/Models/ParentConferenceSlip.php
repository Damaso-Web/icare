<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// One immutable record per Parent Conference Slip issued for a case -
// mirrors FeedbackSlip. The slip itself is handed to the parent/guardian
// face-to-face, so this is just GCU's record of having issued it.
// See CaseFile::parentConferenceSlips().
class ParentConferenceSlip extends Model
{
    use HasFactory;

    protected $fillable = [
        'case_id',
        'issued_by_user_id',
        'conference_date',
        'conference_time',
        'reason',
        'remarks',
        'notes',
        'notes_recorded_by_user_id',
        'notes_recorded_at',
        'issued_at',
    ];

    protected $casts = [
        'conference_date' => 'date',
        'issued_at'        => 'datetime',
        'notes_recorded_at' => 'datetime',
    ];

    public function caseFile() { return $this->belongsTo(CaseFile::class, 'case_id'); }
    public function issuedBy() { return $this->belongsTo(User::class, 'issued_by_user_id'); }
    public function notesRecordedBy() { return $this->belongsTo(User::class, 'notes_recorded_by_user_id'); }
}