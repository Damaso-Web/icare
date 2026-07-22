<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Document extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uploaded_by_user_id',
        'documentable_type',
        'documentable_id',
        'original_filename',
        'stored_filename',
        'disk',
        'path',
        'mime_type',
        'file_size',
        'document_type',
        'description',
        'is_confidential',
    ];

    protected $casts = [
        'is_confidential' => 'boolean',
    ];

    // Relationships
    public function documentable() { return $this->morphTo(); }
    public function uploadedBy()   { return $this->belongsTo(User::class, 'uploaded_by_user_id'); }

    // Helpers
    public function url(): string
    {
        return Storage::disk($this->disk)->temporaryUrl($this->path, now()->addMinutes(30));
    }
}