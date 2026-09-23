<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReferralFormOption extends Model
{
    protected $fillable = ['category', 'value', 'label', 'unit', 'sort_order'];
}