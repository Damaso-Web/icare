<?php

namespace App\Models;

use App\Support\CloudinaryClient;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
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
        'cloud_public_id',
        'cloud_resource_type',
        'cloud_format',
        'mime_type',
        'file_size',
        'document_type',
        'description',
        'is_confidential',
        'content',
    ];

    // The file bytes (base64, on older rows only) are large and never belong
    // in list/JSON payloads.
    protected $hidden = ['content'];

    protected $casts = [
        'is_confidential' => 'boolean',
    ];

    // Relationships
    public function documentable() { return $this->morphTo(); }
    public function uploadedBy()   { return $this->belongsTo(User::class, 'uploaded_by_user_id'); }

    /**
     * Upload $file to Cloudinary and create the Document row for it in one
     * step. $attributes is everything the caller already knows (uploader,
     * documentable_type/id, document_type, description, is_confidential,
     * original_filename) - this method fills in the storage-related fields.
     */
    public static function storeUploadedFile(UploadedFile $file, array $attributes): self
    {
        $cloud = app(CloudinaryClient::class)->upload($file->getRealPath(), 'documents');

        return static::create($attributes + [
            'stored_filename'     => $cloud['public_id'],
            'disk'                => 'cloudinary',
            'path'                => null,
            'cloud_public_id'     => $cloud['public_id'],
            'cloud_resource_type' => $cloud['resource_type'],
            'cloud_format'        => $cloud['format'],
            'mime_type'           => $file->getMimeType(),
            'file_size'           => $file->getSize(),
        ]);
    }

    /**
     * Build the download response. Tries, in order: Cloudinary (current
     * uploads), the base64 copy in `content` (the old workaround - still
     * served for rows written before this change), then local disk (only
     * ever correct immediately after an upload, before the next Render
     * deploy wipes it). 404s with a friendly message if none of those have
     * it, which can legitimately happen for pre-Cloudinary rows that were
     * already lost to a deploy.
     */
    public function streamResponse(): Response
    {
        $name = str_replace(['"', "\r", "\n"], '', $this->original_filename ?: 'file');
        $headers = [
            'Content-Type'           => $this->mime_type ?: 'application/octet-stream',
            'Content-Disposition'    => 'attachment; filename="' . $name . '"',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($this->disk === 'cloudinary' && $this->cloud_public_id) {
            $bytes = app(CloudinaryClient::class)->fetch(
                $this->cloud_public_id,
                $this->cloud_resource_type ?: 'raw',
                $this->cloud_format
            );
            return response($bytes, 200, $headers);
        }

        if (!empty($this->content)) {
            return response(base64_decode($this->content), 200, $headers);
        }

        if ($this->disk && $this->path && Storage::disk($this->disk)->exists($this->path)) {
            return Storage::disk($this->disk)->download($this->path, $this->original_filename, [
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        abort(404, 'This file is no longer available. Please ask the sender to upload it again.');
    }
}