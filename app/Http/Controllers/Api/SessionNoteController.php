<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\Referral;
use App\Models\SessionNote;
use Illuminate\Http\Request;

class SessionNoteController extends Controller
{
    /**
     * Only counselors (admin + gcu_staff) may write or modify session notes.
     * Mirrors User::canCounsel().
     */
    private function authorizeCounselor(): void
    {
        if (!request()->user()?->canCounsel()) {
            abort(403, 'Access denied. Only counselors may modify session notes.');
        }
    }

    public function index(Request $request, CaseFile $case)
    {
        $user = $request->user();

        if ($user->isFaculty() || $user->isDeanSecretary()) {
            abort(403, 'Access denied.');
        }

        return response()->json(
            $case->sessionNotes()->with('recordedBy')->get()
        );
    }

    public function indexByReferral(Request $request, Referral $referral)
    {
        $user = $request->user();

        if ($user->isFaculty() || $user->isDeanSecretary()) {
            abort(403, 'Access denied.');
        }

        return response()->json(
            $referral->sessionNotes()->with('recordedBy')->get()
        );
    }

    public function storeByReferral(Request $request, Referral $referral)
    {
        $user = $request->user();

        if (!$user->canCounsel()) {
            abort(403, 'Access denied.');
        }

        $validated = $request->validate([
            'session_date'       => 'required|date',
            'session_start_time' => 'nullable|date_format:H:i',
            'session_end_time'   => 'nullable|date_format:H:i|after:session_start_time',
            'session_type'       => 'required|in:initial,follow_up,assessment,conference,final',
            'observations'       => 'required|string|max:5000',
            'interventions'      => 'nullable|string|max:5000',
            'student_response'   => 'nullable|string|max:5000',
            'next_steps'         => 'nullable|string|max:2000',
            'student_showed_up'  => 'boolean',
            'follow_up_needed'   => 'boolean',
        ]);

        $sessionNumber = $referral->sessionNotes()->count() + 1;

        $duration = null;
        if (!empty($validated['session_start_time']) && !empty($validated['session_end_time'])) {
            $duration = (int) \Carbon\Carbon::createFromFormat('H:i', $validated['session_start_time'])
                ->diffInMinutes(\Carbon\Carbon::createFromFormat('H:i', $validated['session_end_time']));
        }

        $note = SessionNote::create([
            ...$validated,
            'case_id'             => $referral->case_id,
            'referral_id'         => $referral->id,
            'student_id'          => $referral->student_id,
            'recorded_by_user_id' => $user->id,
            'session_number'      => $sessionNumber,
            'duration_minutes'    => $duration,
        ]);

        if ($referral->case) {
            $referral->case->update([
                'total_sessions'  => $referral->case->total_sessions + 1,
                'last_session_at' => now(),
            ]);
        }

        // Logging the first session is what actually shows GCU is working
        // the referral, so it's the trigger that moves the SIF's status
        // pipeline to "In Progress" - only a forward move, so a referral
        // already past this point (referred out, completed, closed) never
        // gets bumped backward by a later session note.
        $referral->advanceStatusTo('in_progress');

        AuditLog::record('created', "Logged session #{$sessionNumber} for referral {$referral->referral_code}.", $note);
        return response()->json($note->load('recordedBy'), 201);
    }

    public function store(Request $request, CaseFile $case)
    {
        $this->authorizeCounselor();

        $validated = $request->validate([
            'session_date'       => 'required|date',
            'session_start_time' => 'nullable|date_format:H:i',
            'session_end_time'   => 'nullable|date_format:H:i|after:session_start_time',
            'session_type'       => 'required|in:initial,follow_up,assessment,conference,final',
            'observations'       => 'required|string|max:5000',
            'interventions'      => 'nullable|string|max:5000',
            'student_response'   => 'nullable|string|max:5000',
            'next_steps'         => 'nullable|string|max:2000',
            'student_showed_up'  => 'boolean',
            'follow_up_needed'   => 'boolean',
        ]);

        $sessionNumber = $case->sessionNotes()->count() + 1;

        $duration = null;
        if (!empty($validated['session_start_time']) && !empty($validated['session_end_time'])) {
            $duration = (int) \Carbon\Carbon::createFromFormat('H:i', $validated['session_start_time'])
                ->diffInMinutes(\Carbon\Carbon::createFromFormat('H:i', $validated['session_end_time']));
        }

        $note = SessionNote::create([
            ...$validated,
            'case_id'             => $case->id,
            'student_id'          => $case->student_id,
            'recorded_by_user_id' => $request->user()->id,
            'session_number'      => $sessionNumber,
            'duration_minutes'    => $duration,
        ]);

        // Update case session count
        $case->update([
            'total_sessions'  => $sessionNumber,
            'last_session_at' => now(),
        ]);

        AuditLog::record('created', "Logged session #{$sessionNumber} for case {$case->case_number}.", $note);
        return response()->json($note->load('recordedBy'), 201);
    }

    public function show(SessionNote $sessionNote)
    {
        $user = request()->user();

        if ($user->isFaculty() || $user->isDeanSecretary()) {
            abort(403, 'Access denied.');
        }

        return response()->json($sessionNote->load(['recordedBy', 'student']));
    }

    public function update(Request $request, SessionNote $sessionNote)
    {
        $this->authorizeCounselor();

        $old = $sessionNote->toArray();

        $sessionNote->update($request->only([
            'observations',
            'interventions',
            'student_response',
            'next_steps',
            'follow_up_needed',
        ]));

        AuditLog::record('updated', "Updated session note #{$sessionNote->session_number}.", $sessionNote, $old, $sessionNote->toArray());
        return response()->json($sessionNote);
    }

    public function destroy(SessionNote $sessionNote)
    {
        $this->authorizeCounselor();

        AuditLog::record('deleted', "Deleted session note #{$sessionNote->session_number}.", $sessionNote);
        $sessionNote->delete();
        return response()->json(['message' => 'Session note deleted.']);
    }
}