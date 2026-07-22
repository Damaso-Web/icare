<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\StaffAvailability;
use Illuminate\Http\Request;

class StaffAvailabilityController extends Controller
{
    public function index(Request $request)
    {
        $query = StaffAvailability::with('user')
            ->when($request->user_id, fn($q) => $q->where('user_id', $request->user_id))
            ->when($request->unit,    fn($q) => $q->where('unit', $request->unit))
            ->when($request->day,     fn($q) => $q->where('day_of_week', $request->day));

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id'      => 'required|exists:users,id',
            'day_of_week'  => 'required|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday',
            'start_time'   => 'required|date_format:H:i',
            'end_time'     => 'required|date_format:H:i|after:start_time',
            'is_available' => 'boolean',
            'unit'         => 'required|string',
        ]);

        $availability = StaffAvailability::updateOrCreate(
            [
                'user_id'     => $validated['user_id'],
                'day_of_week' => $validated['day_of_week'],
                'start_time'  => $validated['start_time'],
            ],
            $validated
        );

        AuditLog::record('created', "Set availability for user #{$validated['user_id']} on {$validated['day_of_week']}.", $availability);
        return response()->json($availability, 201);
    }

    public function show(StaffAvailability $staffAvailability)
    {
        return response()->json($staffAvailability->load('user'));
    }

    public function update(Request $request, StaffAvailability $staffAvailability)
    {
        $validated = $request->validate([
            'start_time'   => 'sometimes|date_format:H:i',
            'end_time'     => 'sometimes|date_format:H:i|after:start_time',
            'is_available' => 'boolean',
        ]);

        $old = $staffAvailability->toArray();
        $staffAvailability->update($validated);
        AuditLog::record('updated', "Updated availability #{$staffAvailability->id}.", $staffAvailability, $old, $staffAvailability->toArray());
        return response()->json($staffAvailability);
    }

    public function destroy(StaffAvailability $staffAvailability)
    {
        AuditLog::record('deleted', "Deleted availability #{$staffAvailability->id}.", $staffAvailability);
        $staffAvailability->delete();
        return response()->json(['message' => 'Availability deleted.']);
    }
}