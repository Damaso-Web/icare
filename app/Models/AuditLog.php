<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;
    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'user_name',
        'user_role',
        'ip_address',
        'user_agent',
        'action',
        'model_type',
        'model_id',
        'description',
        'old_values',
        'new_values',
        'url',
        'method',
        'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    // Relationships
    public function user() { return $this->belongsTo(User::class); }

    // Static logger
    public static function record(string $action, string $description, $model = null, array $old = [], array $new = []): void
    {
        $user = auth()->user();
        static::create([
            'user_id'     => $user?->id,
            'user_name'   => $user?->name,
            'user_role'   => $user?->role,
            'ip_address'  => request()->ip(),
            'user_agent'  => request()->userAgent(),
            'action'      => $action,
            'model_type'  => $model ? get_class($model) : null,
            'model_id'    => $model?->id,
            'description' => $description,
            'old_values'  => $old,
            'new_values'  => $new,
            'url'         => request()->fullUrl(),
            'method'      => request()->method(),
            'created_at'  => now(),
        ]);
    }
}