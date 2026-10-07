<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicYear extends Model
{
    public const DATE_FIELDS = [
        'year_start', 'year_end',
        'first_sem_start', 'first_sem_end',
        'second_sem_start', 'second_sem_end',
        'midyear_start', 'midyear_end',
    ];

    protected $fillable = ['start_year', ...self::DATE_FIELDS];

    protected $casts = [
        'start_year' => 'integer',
        'year_start' => 'date:Y-m-d',
        'year_end' => 'date:Y-m-d',
        'first_sem_start' => 'date:Y-m-d',
        'first_sem_end' => 'date:Y-m-d',
        'second_sem_start' => 'date:Y-m-d',
        'second_sem_end' => 'date:Y-m-d',
        'midyear_start' => 'date:Y-m-d',
        'midyear_end' => 'date:Y-m-d',
    ];
}