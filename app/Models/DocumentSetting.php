<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentSetting extends Model
{
    protected $fillable = [
        'document_code',
        'revision_no',
        'effectivity_date',
        'ctrl_no_year',
        'ctrl_no_term',
        'version_no',
        'status',
        'is_active',
        'activated_at',
        'deactivated_at',
        'created_by_user_id',
    ];

    protected $casts = [
        'effectivity_date' => 'date:Y-m-d',
        'is_active'        => 'boolean',
        'activated_at'     => 'datetime',
        'deactivated_at'   => 'datetime',
    ];

    public function getCtrlNoAttribute(): ?string
    {
        if (!$this->ctrl_no_year || !$this->ctrl_no_term) {
            return null;
        }
        return "{$this->ctrl_no_year}-{$this->ctrl_no_term}";
    }

    protected $appends = ['ctrl_no'];
}