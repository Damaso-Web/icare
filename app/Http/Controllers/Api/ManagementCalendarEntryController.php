<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppointmentSlotHistory;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\CalendarEntry;
use App\Support\ManagementGuard;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Holidays and Suspensions (School Calendar tab). Writes: Admin (GCU Head). */
class ManagementCalendarEntryController extends Controller
{
    private function authorizeManage(Request $request): void
    {
        abort_unless(in_array($request->user()?->role, ['admin'], true), 403, 'Only the GCU Head can manage holidays and suspensions.');
    }

    /** Records that point at the date: appointments booked on it. (Reschedule records and notifications join in later.) */
    public static function links(CalendarEntry $e): array
    {
        return [
            'appointments'  => Appointment::whereDate('appointment_date', $e->entry_date->format('Y-m-d'))->count(),
            'notifications' => 0,
        ];
    }

    private function isPast(CalendarEntry $e): bool
    {
        return $e->entry_date->lt(now()->startOfDay());
    }

    private function row(CalendarEntry $e): array
    {
        $links = self::links($e);
        return $e->toArray() + [
            'is_past'      => $this->isPast($e),
            'linked'       => $links,
            'linked_total' => array_sum($links),
            'can_delete'   => !$this->isPast($e) && array_sum($links) === 0,
        ];
    }

    public function index()
    {
        return CalendarEntry::orderByDesc('entry_date')->get()->map(fn($e) => $this->row($e));
    }

    public function store(Request $request)
    {
        $this->authorizeManage($request);
        $request->validate(['resolution' => ['nullable', Rule::in(['auto_cancel', 'auto_reschedule', 'manual_review'])]]);
        $data = $this->validated($request);
        $this->assertNoDuplicate($data['entry_date'], $data['type'], null);

        // B534 (1): check for appointments already booked on this date before
        // committing the new entry. If there are any and the admin hasn't said
        // how to handle them yet, stop here and let the frontend show the
        // warning modal with the count, instead of silently creating the
        // holiday and leaving those appointments stranded.
        $conflicts = $this->conflictingAppointments($data['entry_date']);
        $resolution = $request->input('resolution'); // auto_cancel | auto_reschedule | manual_review
        if ($conflicts->isNotEmpty() && !$resolution) {
            $label = CalendarEntry::TYPES[$data['type']] ?? 'holiday';
            return response()->json([
                'needs_resolution' => true,
                'conflict_count'   => $conflicts->count(),
                'message'          => "Marking {$this->fmtDate($data['entry_date'])} as a {$label} affects {$conflicts->count()} scheduled appointment" . ($conflicts->count() === 1 ? '' : 's') . '. How would you like to process them?',
            ], 409);
        }

        $e = CalendarEntry::create($data + ['status' => 'active', 'created_by_user_id' => $request->user()->id]);
        ManagementGuard::audit('holidays and suspensions', 'created', $e->describe(), $e, [], $this->snap($e));

        if ($conflicts->isNotEmpty()) {
            $this->resolveConflicts($conflicts, $e, $resolution, $request->user());
        }

        return response()->json($this->row($e) + ['resolved_conflicts' => $conflicts->count()], 201);
    }

    /** Appointments on $date that are still "live" (not already cancelled/done/unscheduled). */
    private function conflictingAppointments(string $date)
    {
        return Appointment::whereDate('appointment_date', $date)
            ->whereNotIn('status', ['cancelled', 'rescheduled', 'completed', 'no_show'])
            ->where('request_status', '!=', 'awaiting_student')
            ->get();
    }

    private function fmtDate(string $date): string
    {
        return Carbon::parse($date)->format('M j, Y');
    }

    /**
     * B534 (3)+(5): flag every affected appointment, then act on the admin's
     * chosen resolution: auto-cancel, auto-reschedule to the next open
     * business day at the same time (falls back to manual review if no slot
     * is free), or leave all of them for manual review.
     */
    private function resolveConflicts($conflicts, CalendarEntry $entry, ?string $resolution, $admin): void
    {
        foreach ($conflicts as $appt) {
            $appt->forceFill([
                'holiday_conflict_entry_id'   => $entry->id,
                'holiday_conflict_flagged_at' => now(),
            ]);

            if ($resolution === 'auto_cancel') {
                $appt->forceFill([
                    'status'                               => 'cancelled',
                    'cancellation_reason'                  => "Cancelled automatically: \"{$entry->name}\" was declared a {$entry->type_label} on this date.",
                    'cancelled_at'                          => now(),
                    'cancelled_by_user_id'                  => $admin->id,
                    'holiday_conflict_resolution'           => 'auto_cancelled',
                    'holiday_conflict_resolved_at'          => now(),
                    'holiday_conflict_resolved_by_user_id'  => $admin->id,
                ])->save();
                AuditLog::record('holiday_conflict_cancelled', "[Holiday Conflict] Cancelled {$appt->appointment_code} - \"{$entry->name}\" was declared a {$entry->type_label} on {$this->fmtDate($entry->entry_date->format('Y-m-d'))}.", $appt, [], [], $admin);
                try {
                    $appt->student?->notify(new \App\Notifications\AppointmentHolidayCancelledNotification($appt, $entry->name));
                } catch (\Throwable $ex) {
                    report($ex);
                }
                continue;
            }

            if ($resolution === 'auto_reschedule') {
                $moved = $this->tryAutoReschedule($appt, $entry, $admin);
                $appt->forceFill([
                    'holiday_conflict_resolution'          => $moved ? 'auto_rescheduled' : null,
                    'holiday_conflict_resolved_at'          => $moved ? now() : null,
                    'holiday_conflict_resolved_by_user_id'  => $moved ? $admin->id : null,
                ])->save();
                AuditLog::record($moved ? 'holiday_conflict_rescheduled' : 'holiday_conflict_flagged',
                    $moved
                        ? "[Holiday Conflict] Moved {$appt->appointment_code} to {$this->fmtDate($appt->appointment_date->format('Y-m-d'))} - \"{$entry->name}\" was declared on its original date."
                        : "[Holiday Conflict] Could not auto-reschedule {$appt->appointment_code} (no open slot found); left for manual review because \"{$entry->name}\" was declared on {$this->fmtDate($entry->entry_date->format('Y-m-d'))}.",
                    $appt, [], [], $admin);
                continue;
            }

            // Manual review (explicit choice, or the default when no resolution
            // is given but the entry is created anyway): just flag it.
            $appt->save();
            AuditLog::record('holiday_conflict_flagged', "[Holiday Conflict] Flagged {$appt->appointment_code} for manual review - \"{$entry->name}\" was declared a {$entry->type_label} on {$this->fmtDate($entry->entry_date->format('Y-m-d'))}.", $appt, [], [], $admin);
        }
    }

    /** Moves $appt to the next open business day at the same time and staff, if one is free within two weeks. */
    private function tryAutoReschedule(Appointment $appt, CalendarEntry $entry, $admin): bool
    {
        $date = Carbon::parse($appt->appointment_date);
        for ($i = 0; $i < 14; $i++) {
            $date = $date->copy()->addDay();
            if ($date->isWeekend()) continue;
            if (CalendarEntry::blocking($date->format('Y-m-d'))) continue;
            if ($appt->staff_user_id && Appointment::hasConflict($appt->staff_user_id, $date->format('Y-m-d'), $appt->start_time, $appt->end_time, $appt->id)) continue;

            AppointmentSlotHistory::create([
                'appointment_id' => $appt->id, 'slot_date' => $appt->appointment_date, 'start_time' => $appt->start_time, 'end_time' => $appt->end_time,
                'staff_user_id' => $appt->staff_user_id, 'reason' => "Holiday: {$entry->name}", 'rescheduled_by_type' => 'admin', 'rescheduled_by_name' => $admin->name ?? 'Admin', 'created_at' => now(),
            ]);
            $appt->forceFill([
                'appointment_date'   => $date->format('Y-m-d'),
                'reschedule_reason'  => "Automatically moved to the next open business day: \"{$entry->name}\" was declared on the original date.",
                'reschedule_count'   => ($appt->reschedule_count ?? 0) + 1,
            ])->save();
            return true;
        }
        return false;
    }

    public function update(Request $request, CalendarEntry $entry)
    {
        $this->authorizeManage($request);
        ManagementGuard::assertEditable($entry);
        $data = $this->validated($request);
        $this->assertNoDuplicate($data['entry_date'], $data['type'], $entry->id);

        $old = $this->snap($entry);
        $entry->update($data);
        ManagementGuard::audit('holidays and suspensions', 'updated', $entry->describe(), $entry, $old, $this->snap($entry));
        return response()->json($this->row($entry->fresh()));
    }

    public function archive(Request $request, CalendarEntry $entry)
    {
        $this->authorizeManage($request);
        abort_if($entry->status === 'archived', 422, 'This entry is already archived.');
        [$reason, $note] = ManagementGuard::requireStatusReason($request, 'holidays and suspensions', 'archive');

        // An upcoming date that already affected appointments needs an explicit acknowledgement.
        $links = self::links($entry);
        if (!$this->isPast($entry) && $links['appointments'] > 0 && !$request->boolean('acknowledged')) {
            throw ValidationException::withMessages(['acknowledged' => ['Please tick "I understand" to archive an upcoming date that affected appointments.']]);
        }

        // B536: keep the reason (and note) on the entry itself, not only in the audit
        // log, so it can be shown back to the admin when they come to reactivate it.
        $entry->forceFill(['status' => 'archived', 'archive_reason' => $reason, 'archive_note' => $note])->save();
        ManagementGuard::audit('holidays and suspensions', 'archived', $entry->describe() . " [{$links['appointments']} linked appointments, {$links['notifications']} notifications]",
            $entry, ['status' => 'active'], ['status' => 'archived'], $reason, $note);
        return response()->json($this->row($entry->fresh()));
    }

    public function reactivate(Request $request, CalendarEntry $entry)
    {
        $this->authorizeManage($request);
        abort_if($entry->status !== 'archived', 422, 'Only an archived entry can be reactivated.');
        abort_if($this->isPast($entry), 422, 'This date has already passed, so the entry cannot be reactivated.');
        $this->assertNoDuplicate($entry->entry_date->format('Y-m-d'), $entry->type, $entry->id);
        [$reason, $note] = ManagementGuard::requireStatusReason($request, 'holidays and suspensions', 'reactivate');

        // The archive reason/note are left in place as a historical record of why
        // it was archived; the reactivation reason is recorded separately below.
        $entry->forceFill(['status' => 'active'])->save();
        ManagementGuard::audit('holidays and suspensions', 'reactivated', $entry->describe(), $entry, ['status' => 'archived'], ['status' => 'active'], $reason, $note);
        return response()->json($this->row($entry->fresh()));
    }

    public function destroy(Request $request, CalendarEntry $entry)
    {
        $this->authorizeManage($request);
        $rawReason = $request->input('reason');
        [$reason, $note] = ManagementGuard::requireDeleteReason($request, 'holidays and suspensions');

        $links = self::links($entry);
        if ($this->isPast($entry) || array_sum($links) > 0) {
            // B550: before enforcing the usual "Archive Instead" block, check whether
            // this is a genuine duplicate - another entry for the exact same date and
            // type. links() above counts every appointment that falls on the date,
            // which, when two entries share a date, blocks deleting EITHER of them
            // even though only one is actually tied to any of those appointments.
            // ownLinks checks that directly via holiday_conflict_entry_id, so a true
            // "nothing of its own" duplicate can be told apart from a record that
            // really does carry history.
            $duplicate = CalendarEntry::where('id', '!=', $entry->id)
                ->whereDate('entry_date', $entry->entry_date->format('Y-m-d'))
                ->where('type', $entry->type)
                ->first();
            $ownLinks = Appointment::where('holiday_conflict_entry_id', $entry->id)->count();

            if ($duplicate && $ownLinks === 0) {
                if ($rawReason === 'duplicate') {
                    // Confirmed duplicate, nothing specifically tied to this record:
                    // safe to hard-delete without leaving anything orphaned.
                    $name = $entry->describe();
                    $old = $this->snap($entry);
                    $entry->delete();
                    ManagementGuard::audit('holidays and suspensions', 'deleted',
                        $name . " [confirmed duplicate of \"{$duplicate->name}\" ({$duplicate->status}); nothing specifically linked to this record]",
                        $entry, $old, [], $reason, $note);
                    return response()->json(['message' => 'Duplicate entry deleted.']);
                }
                $otherStatus = $duplicate->status === 'active' ? 'active' : 'archived';
                abort(422, "\"{$entry->name}\" shares its date with another {$entry->type_label} entry (\"{$duplicate->name}\", {$otherStatus}), and nothing is specifically tied to this record. If this is a mistaken duplicate, choose \"Duplicate Entry\" as the delete reason to remove it directly. Otherwise, archive it instead.");
            }

            abort(422, "\"{$entry->name}\" is linked to {$links['appointments']} appointments and {$links['notifications']} notifications, or its date has already passed. Deleting it would remove the reason shown in those records. Archive it instead.");
        }

        $name = $entry->describe();
        $old = $this->snap($entry);
        $entry->delete();
        ManagementGuard::audit('holidays and suspensions', 'deleted', $name . ' [0 linked records]', $entry, $old, [], $reason, $note);
        return response()->json(['message' => 'Entry deleted.']);
    }

    private function snap(CalendarEntry $e): array
    {
        return ['date' => $e->entry_date->format('Y-m-d'), 'name' => $e->name, 'type' => $e->type_label];
    }

    private function validated(Request $request): array
    {
        $d = $request->validate([
            'entry_date' => ['required', 'date_format:Y-m-d'],
            'name'       => ['required', 'string', 'max:120', 'regex:/\p{L}/u'],
            'type'       => ['required', Rule::in(array_keys(CalendarEntry::TYPES))],
        ], [
            'name.regex' => 'The name must contain letters.',
            'type.in'    => 'Choose Holiday, Class Suspension or Office Closed.',
        ]);
        $d['name'] = trim($d['name']);
        return $d;
    }

    private function assertNoDuplicate(string $date, string $type, ?int $ignoreId): void
    {
        $clash = CalendarEntry::where('status', 'active')->whereDate('entry_date', $date)->where('type', $type)
            ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))->first();
        if ($clash) {
            throw ValidationException::withMessages(['entry_date' => ["There is already an active {$clash->type_label} on that date (\"{$clash->name}\")."]]);
        }
    }
}