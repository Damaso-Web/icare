<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A Holiday, Class Suspension or Office Closed date that blocks appointment booking. */
class CalendarEntry extends Model
{
    public const TYPES = [
        'holiday'          => 'Holiday',
        'class_suspension' => 'Class Suspension',
        'office_closed'    => 'Office Closed',
    ];

    protected $fillable = ['entry_date', 'name', 'type', 'status', 'created_by_user_id', 'archive_reason', 'archive_note'];

    protected $casts = ['entry_date' => 'date:Y-m-d'];

    protected $appends = ['type_label'];

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /** "Christmas Day" (Holiday · Dec 25, 2026) */
    public function describe(): string
    {
        return "{$this->name} ({$this->type_label} · {$this->entry_date->format('M j, Y')})";
    }

    /** The active entry that blocks the given date, if any. */
    public static function blocking(string $date): ?self
    {
        return self::where('status', 'active')->whereDate('entry_date', $date)->first();
    }
}