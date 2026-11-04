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

    // Static logger. $actor is for requests where nobody is authenticated yet
    // (login attempts) - everywhere else the logged-in staff user or student
    // is picked up automatically.
    public static function record(string $action, string $description, $model = null, array $old = [], array $new = [], $actor = null): void
{
    $user = $actor ?: (auth()->user() ?: auth('student')->user());
    $isStaffUser = $user instanceof \App\Models\User;
    // Students have no single "name" column.
    $name = $user ? ($user->name ?: trim("{$user->first_name} {$user->last_name}")) : null;

    $row = [
        'user_id'     => $isStaffUser ? $user->id : null,
        'user_name'   => $name,
        'user_role'   => $isStaffUser ? $user->role : ($user ? 'student' : null),
        'ip_address'  => request()->ip(),
        'user_agent'  => mb_substr((string) request()->userAgent(), 0, 255),
        'action'      => $action,
        'model_type'  => $model ? get_class($model) : null,
        'model_id'    => $model?->id,
        'description' => mb_substr($description, 0, 4000),
        'old_values'  => $old,
        'new_values'  => $new,
        'url'         => mb_substr(request()->fullUrl(), 0, 255),
        'method'      => request()->method(),
        'created_at'  => now(),
    ];

    // B#, 11/4/2026: every audit entry used to be an INSERT sitting on
    // the critical path of whatever action triggered it - called on
    // nearly every request app-wide, including plain "viewed" GETs. With
    // the database on a separate host (Aiven) from the app (Render),
    // that's a full extra network round trip tacked onto nearly every
    // click. The values above are captured now, while request()/auth()
    // still reflect the current request, but the write itself is
    // deferred until after the response has already gone back to the
    // browser (Laravel's afterResponse() - no queue worker needed, it
    // just runs once the response is sent instead of before), so logging
    // never adds to what the user is waiting on. Outside an HTTP request
    // (console commands, tests not going through the HTTP kernel) there
    // is no "after the response" to wait for, so it writes immediately.
    if (app()->runningInConsole()) {
        static::create($row);
        return;
    }
    dispatch(function () use ($row) {
        static::create($row);
    })->afterResponse();
}
}