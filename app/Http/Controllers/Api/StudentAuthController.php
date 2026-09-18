<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class StudentAuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'student_id' => 'required|string',
            'password'   => 'required|string',
        ]);

        $student = Student::where('student_id', $request->student_id)
            ->where('is_active', true)
            ->first();

        if (!$student || !$student->password || !Hash::check($request->password, $student->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        $student->update(['last_login_at' => now()]);
        $token = $student->createToken('student-token', ['student'])->plainTextToken;

        return response()->json([
            'token'   => $token,
            'student' => $student->only([
                'id', 'student_id', 'first_name', 'middle_name', 'last_name',
                'email', 'college', 'program', 'year_level', 'must_change_password'
            ]),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user('student')->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user('student'));
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password'         => ['required', 'confirmed', 'min:8'],
        ]);

        $student = $request->user('student');

        if (!Hash::check($request->current_password, $student->password)) {
            return response()->json(['message' => 'Current password is incorrect.'], 422);
        }

        $student->update([
            'password'              => Hash::make($request->password),
            'temp_password'         => null,
            'must_change_password'  => false,
        ]);

        return response()->json(['message' => 'Password updated successfully.']);
    }

    public function dashboard(Request $request)
{
    $student = $request->user('student');

    $pendingAppointment = $student->appointments()
        ->where('request_status', 'awaiting_student')
        ->whereNotIn('status', ['cancelled'])
        ->latest()
        ->first();

    return response()->json([
        'appointments'         => $student->appointments()->with(['staff', 'case.latestReferral'])->latest()->get(),
        'referrals'            => $student->referrals()->latest()->get(),
        'pending_appointment'  => $pendingAppointment,
    ]);
}

public function updateProfile(Request $request)
{
    $student = $request->user('student');

    $validated = $request->validate([
        'first_name'     => 'sometimes|string|max:255',
        'last_name'      => 'sometimes|string|max:255',
        'middle_name'    => 'nullable|string|max:255',
        'email'          => 'nullable|email',
        'contact_number' => 'nullable|string|max:11',
    ]);

    $student->update($validated);

    return response()->json($student->only([
        'id', 'student_id', 'first_name', 'middle_name', 'last_name',
        'email', 'contact_number', 'college', 'program', 'year_level', 'must_change_password'
    ]));
}

public function showReferral(Request $request, $id)
{
    $student = $request->user('student');
    $referral = $student->referrals()->with('case')->findOrFail($id);
    return response()->json($referral);
}

public function showAppointment(Request $request, $id)
{
    $student = $request->user('student');
    $appointment = $student->appointments()->with(['staff', 'case.latestReferral'])->findOrFail($id);
    return response()->json($appointment);
}

public function notifications(Request $request)
{
    $student = $request->user('student');
    return response()->json($student->notifications()->latest()->paginate(20));
}

public function markNotificationRead(Request $request, string $id)
{
    $student = $request->user('student');
    $notification = $student->notifications()->findOrFail($id);
    $notification->markAsRead();
    return response()->json(['message' => 'Notification marked as read.']);
}

public function markAllNotificationsRead(Request $request)
{
    $student = $request->user('student');
    $student->unreadNotifications->markAsRead();
    return response()->json(['message' => 'All notifications marked as read.']);
}

// Feature B76: Student-initiated reschedule request
public function requestReschedule(Request $request, $id)
{
    $student = $request->user('student');
    $appointment = $student->appointments()->findOrFail($id);

    if (!in_array($appointment->status, ['pending', 'confirmed'])) {
        return response()->json(['message' => 'This appointment can no longer be rescheduled.'], 422);
    }

    $request->validate(['reason' => 'required|string']);

    $newCount = $appointment->reschedule_count + 1;

    if ($newCount >= \App\Models\Appointment::MAX_RESCHEDULES) {
        $appointment->update([
            'status'               => 'no_show',
            'reschedule_count'     => $newCount,
            'no_show_escalated'    => true,
            'no_show_escalated_at' => now(),
        ]);

        $deanSecretaries = \App\Models\User::where('role', 'dean_secretary')
            ->where('college', $student->college)
            ->where('is_active', true)
            ->get();

        foreach ($deanSecretaries as $secretary) {
            $secretary->notify(new \App\Notifications\NoShowEscalationNotification($appointment));
        }

        \App\Models\AuditLog::record('reschedule_limit_reached', "Student exceeded reschedule limit for appointment {$appointment->appointment_code}. Flagged as no-show.", $appointment);

        return response()->json(['message' => 'You have reached the maximum number of reschedules. This has been flagged for staff follow-up.', 'appointment' => $appointment], 422);
    }

    $newToken = \Illuminate\Support\Str::random(48);

    $appointment->update([
        'request_status'    => 'awaiting_student',
        'status'            => 'pending',
        'scheduling_token'  => $newToken,
        'token_expires_at'  => now()->addDays(7),
        'reschedule_reason' => 'Student requested: ' . $request->reason,
        'reschedule_count'  => $newCount,
    ]);

    \App\Models\AuditLog::record('reschedule_requested', "Student requested reschedule for appointment {$appointment->appointment_code} ({$newCount}/" . \App\Models\Appointment::MAX_RESCHEDULES . ").", $appointment);

    return response()->json(['message' => 'Reschedule request submitted. Please pick a new time.', 'appointment' => $appointment]);
}

// Feature B77: Student-initiated cancellation with reason
public function cancelAppointment(Request $request, $id)
{
    $student = $request->user('student');
    $appointment = $student->appointments()->findOrFail($id);

    if (!in_array($appointment->status, ['pending', 'confirmed'])) {
        return response()->json(['message' => 'This appointment cannot be cancelled.'], 422);
    }

    $request->validate(['reason' => 'required|string']);

    $appointment->update([
        'status'               => 'cancelled',
        'request_status'       => 'confirmed',
        'cancellation_reason'  => $request->reason,
        'cancelled_at'         => now(),
    ]);

    \App\Models\AuditLog::record('cancelled', "Student cancelled appointment {$appointment->appointment_code}. Reason: {$request->reason}", $appointment);

    return response()->json(['message' => 'Appointment cancelled.', 'appointment' => $appointment]);
}

}