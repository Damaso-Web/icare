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
    public function fromUser() { return $this->belongsTo(User::class, 'from_user_id'); }
    public function toUser()   { return $this->belongsTo(User::class, 'to_user_id'); }
}