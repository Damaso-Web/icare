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
        'mime_type',
        'content',
    ];

    // The file bytes (base64) are large and never needed in list payloads.
    protected $hidden = ['content'];

    public function complaint()
    {
        return $this->belongsTo(Complaint::class);
    }

    // The file is served through the authenticated attachment route; the bytes
    // live in the database (the frontend fetches it with the viewer's token).
    public function getUrlAttribute(): string
    {
        return url('api/complaints/' . $this->complaint_id . '/attachments/' . $this->id);
    }
}