<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserStatusEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'action', 'reason_key', 'reason_label', 'note',
        'previous_reason_label', 'previous_at', 'actor_id', 'actor_name', 'created_at',
    ];

    protected $casts = [
        'created_at'  => 'datetime',
        'previous_at' => 'datetime',
    ];
}