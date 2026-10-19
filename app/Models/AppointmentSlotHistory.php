<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One date/time slot an appointment gave up when it was rescheduled. */
class AppointmentSlotHistory extends Model
{
    protected $table = 'appointment_slot_history';
    public $timestamps = false;
    protected $fillable = ['appointment_id', 'slot_date', 'start_time', 'end_time', 'staff_user_id', 'reason', 'rescheduled_by_type', 'rescheduled_by_name', 'created_at'];
    protected $casts = ['slot_date' => 'date:Y-m-d', 'created_at' => 'datetime'];
}