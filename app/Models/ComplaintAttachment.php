<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComplaintAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'complaint_id',
        'category',
        'file_path',
        'original_filename',
    ];

    public function complaint()
    {
        return $this->belongsTo(Complaint::class);
    }

    // Convenience accessor so the frontend gets a ready-to-use download URL
    // without needing to know the storage disk layout.
    public function getUrlAttribute(): string
    {
        return asset('storage/' . $this->file_path);
    }
}