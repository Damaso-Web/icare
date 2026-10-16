<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    protected $fillable = [
        'notifiable_type', 'notifiable_id', 'recipient_email', 'notification', 'subject',
        'status', 'attempts', 'error_message', 'payload', 'last_attempt_at', 'sent_at',
    ];

    protected $casts = [
        'last_attempt_at' => 'datetime',
        'sent_at'         => 'datetime',
    ];

    protected $hidden = ['payload'];
}