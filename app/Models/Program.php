<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Program extends Model
{
    protected $fillable = ['college_id', 'department_id', 'name'];

    public function college()    { return $this->belongsTo(College::class); }
    public function department() { return $this->belongsTo(Department::class); }
}