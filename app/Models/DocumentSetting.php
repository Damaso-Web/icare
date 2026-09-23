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
    ];

    protected $casts = [
        'effectivity_date' => 'date',
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