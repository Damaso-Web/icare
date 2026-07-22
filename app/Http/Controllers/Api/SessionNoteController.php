<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Models\SessionNote;
use Illuminate\Http\Request;

class SessionNoteController extends Controller
{
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

    public function store(Request $request, CaseFile $case)
    {
        $validated = $request->validate([
            'session_date'       => 'required|date',
            'session_start_time' => 'nullable|date_format:H:i',
            'session_end_time'   => 'nullable|date_format:H:i|after:session_start_time',
            'session_type'       => 'required|in:initial,follow_up,assessment,conference,final',
            'observations'       => 'required|string',
            'interventions'      => 'nullable|string',
            'student_response'   => 'nullable|string',
            'next_steps'         => 'nullable|string',
            'student_showed_up'  => 'boolean',
            'mood_rating'        => 'nullable|in:1,2,3,4,5',
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
        $old = $sessionNote->toArray();

        $sessionNote->update($request->only([
            'observations',
            'interventions',
            'student_response',
            'next_steps',
            'mood_rating',
            'follow_up_needed',
        ]));

        AuditLog::record('updated', "Updated session note #{$sessionNote->session_number}.", $sessionNote, $old, $sessionNote->toArray());
        return response()->json($sessionNote);
    }

    public function destroy(SessionNote $sessionNote)
    {
        AuditLog::record('deleted', "Deleted session note #{$sessionNote->session_number}.", $sessionNote);
        $sessionNote->delete();
        return response()->json(['message' => 'Session note deleted.']);
    }
}