<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BackupRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'trigger',
        'status',
        'verified',
        'file_path',
        'row_counts',
        'error_message',
        'initiated_by_user_id',
    ];

    protected $casts = [
        'verified'   => 'boolean',
        'row_counts' => 'array',
    ];

    public function initiatedBy() { return $this->belongsTo(User::class, 'initiated_by_user_id'); }
}
