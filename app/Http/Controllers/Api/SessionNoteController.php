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
     *
     * B562: a counselor the case's $case was just reassigned away from
     * keeps read-only visibility to the case and its notes, but can't log
     * a new one for it - same block as CaseController::authorizeCaseWriter().
     */
    private function authorizeCounselor(?CaseFile $case = null): void
    {
        $user = request()->user();
        if (!$user?->canCounsel()) {
            abort(403, 'Access denied. Only counselors may modify session notes.');
        }
        if ($case
            && $case->previous_counselor_id
            && (int) $case->previous_counselor_id === (int) $user->id
            && (int) $case->primary_counselor_id !== (int) $user->id) {
            abort(403, 'This case has been reassigned to another counselor. You have read-only access.');
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

        $referral->abortIfLocked();

        $validated = $request->validate([
            'session_date'       => 'required|date',
            'session_start_time' => 'nullable|date_format:H:i',
            'session_end_time'   => 'nullable|date_format:H:i|after:session_start_time',
            'session_type'       => 'required|in:initial,follow_up,assessment,conference,final',
            'observations'       => 'required|string|max:5000',
            'interventions'      => ['nullable', 'string', 'max:5000', 'regex:' . \App\Support\PlainText::REGEX],
            'student_response'   => 'nullable|string|max:5000',
            'next_steps'         => ['nullable', 'string', 'max:2000', 'regex:' . \App\Support\PlainText::REGEX],
            'student_showed_up'  => 'boolean',
            'follow_up_needed'   => 'boolean',
        ]);

        // Interventions only make sense if the student was actually there -
        // enforced here too, in case something other than this form reaches
        // this endpoint (the "Add Session Notes" modal already guards this
        // client-side).
        if (($validated['student_showed_up'] ?? true) && empty($validated['interventions'])) {
            return response()->json(['message' => 'Interventions applied is required when the student attended.'], 422);
        }
        if (!($validated['student_showed_up'] ?? true)) {
            $validated['interventions'] = null;
        }

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
        $this->authorizeCounselor($case);

        $validated = $request->validate([
            'session_date'       => 'required|date',
            'session_start_time' => 'nullable|date_format:H:i',
            'session_end_time'   => 'nullable|date_format:H:i|after:session_start_time',
            'session_type'       => 'required|in:initial,follow_up,assessment,conference,final',
            'observations'       => 'required|string|max:5000',
            'interventions'      => ['nullable', 'string', 'max:5000', 'regex:' . \App\Support\PlainText::REGEX],
            'student_response'   => 'nullable|string|max:5000',
            'next_steps'         => ['nullable', 'string', 'max:2000', 'regex:' . \App\Support\PlainText::REGEX],
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

    // A session note belongs to a referral's SIF; once that SIF is
    // Resolved/closed none of its notes may be edited or removed.
    private function abortIfNoteLocked(SessionNote $sessionNote): void
    {
        $sessionNote->loadMissing('referral');
        $sessionNote->referral?->abortIfLocked();
    }

    public function update(Request $request, SessionNote $sessionNote)
    {
        $this->authorizeCounselor();
        $this->abortIfNoteLocked($sessionNote);

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
        $this->abortIfNoteLocked($sessionNote);

        AuditLog::record('deleted', "Deleted session note #{$sessionNote->session_number}.", $sessionNote);
        $sessionNote->delete();
        return response()->json(['message' => 'Session note deleted.']);
    }
}