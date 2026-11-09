<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class PublicSchedulingController extends Controller
{
    public function show($token)
    {
        $appointment = Appointment::where('scheduling_token', $token)
            ->where('token_expires_at', '>=', now())
            ->with(['student', 'case.latestReferral', 'referral', 'slotHistory'])
            ->first();

        if (!$appointment) {
            return response()->json(['message' => 'This scheduling link is invalid or has expired. Please ask GCU to issue a new one.'], 404);
        }

        // Bug (11/5/2026): same case-vs-appointment mismatch as submit()
        // below - show the referral this scheduling link's appointment is
        // actually for, not whichever referral happens to be newest on the
        // case.
        $referral = $appointment->referral ?: $appointment->case?->latestReferral;

        // This link is opened by the student directly - the Feedback Slip
        // (feedback_notes/feedback_checklist/etc.) is GCU/TMDU's internal
        // note about the case and must never reach them here either. Same
        // fields hidden the same way as every student-facing referral in
        // StudentAuthController.
        $referral?->makeHidden([
            'feedback_notes', 'feedback_checklist', 'feedback_referred_other_text',
            'feedback_others_text', 'feedback_ctrl_no', 'feedback_sent_at', 'feedback_sent_by_user_id',
        ]);

        return response()->json([
            'appointment' => $appointment,
            'previous_slots' => $this->previousSlots($appointment),
            'student'     => $appointment->student,
            'referral'    => $referral,
        ]);
    }

    public function checkAvailability(Request $request, $token)
    {
        // B642 (10/30/2026): show() and submit() below both require
        // token_expires_at >= now() - this endpoint (and monthAvailability()
        // right after it) only checked the token string itself, so an
        // expired scheduling link kept returning real availability data
        // (200) forever instead of 404 once expired. Same predicate as the
        // other two token-gated handlers.
        $appointment = Appointment::where('scheduling_token', $token)
            ->where('token_expires_at', '>=', now())
            ->first();

        if (!$appointment) {
            return response()->json(['available' => false, 'message' => 'This scheduling link is invalid or has expired. Please ask GCU to issue a new one.'], 404);
        }

        $request->validate([
            'appointment_date' => 'required|date',
            'start_time'       => 'required',
            'end_time'         => 'required',
        ]);

        if ($request->end_time <= $request->start_time) {
            return response()->json(['available' => false, 'message' => 'End time must be later than start time.']);
        }

        $date = new \DateTime($request->appointment_date);
        $dayOfWeek = (int) $date->format('N');

        if ($dayOfWeek > 5) {
            return response()->json(['available' => false, 'message' => 'Appointments can only be scheduled Monday to Friday.']);
        }

        if ($violation = $this->dateTimeRuleViolation($request->appointment_date, $request->start_time, $request->end_time)) {
            return response()->json(['available' => false, 'message' => $violation]);
        }

        // B628-equivalent: bounded on both ends (see AppointmentController)
        // so a too-late start (e.g. 20:51) or too-early end (e.g. 04:41)
        // can't slip through.
        if ($request->start_time < '08:00' || $request->start_time >= '16:00'
            || $request->end_time <= '08:00' || $request->end_time > '16:00') {
            return response()->json(['available' => false, 'message' => 'Appointments can only be scheduled between 8:00 AM and 4:00 PM.']);
        }

        if ($blocked = \App\Support\BlockedDates::message($request->appointment_date)) {
            return response()->json(['available' => false, 'blocked' => true, 'message' => $blocked]);
        }

        // Panel fix (11/8/2026, item #8): up to Appointment::STUDENT_SAME_SLOT_CAP
        // students can share one exact date+start+end before this is "taken" -
        // see Appointment::hasUnitConflict() for the exact-vs-partial-overlap rule.
        $conflict = Appointment::hasUnitConflict(
            $appointment->unit,
            $request->appointment_date,
            $request->start_time,
            $request->end_time,
            $appointment->id,
            Appointment::STUDENT_SAME_SLOT_CAP
        );

        if (!$conflict && $this->overlapsReplacedSlot($appointment, $request->appointment_date, $request->start_time, $request->end_time)) {
            return response()->json(['available' => false, 'replaced_slot' => true, 'message' => self::REPLACED_SLOT_MESSAGE]);
        }

        // Panel fix (11/10/2026, item #8): same live slot count as the
        // authenticated self-booking check, so this reschedule screen can
        // show how many students already share this exact date and time.
        $slotCount = Appointment::exactSlotCount($appointment->unit, $request->appointment_date, $request->start_time, $request->end_time, $appointment->id);

        return response()->json([
            'available'  => !$conflict,
            'slot_count' => $slotCount,
            'slot_cap'   => Appointment::STUDENT_SAME_SLOT_CAP,
        ]);
    }

    private const REPLACED_SLOT_MESSAGE = 'This time was already used and rescheduled, so it is no longer available. Please choose another date or time.';

    // Panel fix (11/8/2026): two gaps in the public scheduling form.
    //   1) The month calendar only greyed out days strictly before today
    //      (monthAvailability()'s $isPast check), so today itself always
    //      looked bookable even after its own 8:00-16:00 window had
    //      already passed, and a student could still submit an early-
    //      morning slot (e.g. 8:00 AM) well after that time had gone by.
    //   2) Nothing bounded how long a slot could be - a student could
    //      submit a 15-minute or an 8-hour "appointment".
    // Both checkAvailability() and submit() need the same two checks, so
    // they're pulled out here rather than duplicated across the two
    // response-shape differences (array message vs 422).
    private function dateTimeRuleViolation(string $date, string $start, string $end): ?string
    {
        $startsAt = \Carbon\Carbon::createFromFormat('Y-m-d H:i', $date . ' ' . $start);
        if ($startsAt->isPast()) {
            return 'This time has already passed. Please choose another date or time.';
        }

        $minutes = \Carbon\Carbon::createFromFormat('H:i', $start)->diffInMinutes(\Carbon\Carbon::createFromFormat('H:i', $end));
        if ($minutes < 60 || $minutes > 120) {
            return 'Appointments must be between 1 and 2 hours long.';
        }

        return null;
    }

    // B471: every slot this appointment gave up stays unavailable to it - not just the latest one -
    // so repeated reschedules cannot land back on a slot that was already rescheduled.
    private function previousSlots(Appointment $appointment): array
    {
        $slots = $appointment->slotHistory()->get()->map(fn($h) => [
            'date' => $h->slot_date->format('Y-m-d'), 'start' => substr($h->start_time, 0, 5), 'end' => substr($h->end_time, 0, 5),
        ])->all();

        // Older reschedules were stored as a chain of separate rows; include those slots too.
        $prev = $appointment->rescheduled_from_id ? Appointment::find($appointment->rescheduled_from_id) : null;
        $guard = 0;
        while ($prev && $guard++ < 20) {
            if ($prev->appointment_date && $prev->start_time && $prev->end_time) {
                $slots[] = ['date' => $prev->appointment_date->format('Y-m-d'), 'start' => substr($prev->start_time, 0, 5), 'end' => substr($prev->end_time, 0, 5)];
            }
            $prev = $prev->rescheduled_from_id ? Appointment::find($prev->rescheduled_from_id) : null;
        }
        return $slots;
    }

    private function overlapsReplacedSlot(Appointment $appointment, string $date, string $start, string $end): bool
    {
        foreach ($this->previousSlots($appointment) as $s) {
            if ($s['date'] === substr($date, 0, 10) && $s['start'] < substr($end, 0, 5) && $s['end'] > substr($start, 0, 5)) {
                return true;
            }
        }
        return false;
    }

    public function monthAvailability(Request $request, $token)
    {
        // B642: same expired-token gap as checkAvailability() above.
        $appointment = Appointment::where('scheduling_token', $token)
            ->where('token_expires_at', '>=', now())
            ->first();

        if (!$appointment) {
            return response()->json(['message' => 'This scheduling link is invalid or has expired. Please ask GCU to issue a new one.'], 404);
        } $appointment = Appointment::where('scheduling_token', $token)
            ->where('token_expires_at', '>=', now())
            ->firstOrFail();

        $request->validate([
            'month' => 'required|date_format:Y-m',
        ]);

        $start = \Carbon\Carbon::createFromFormat('Y-m-d', $request->month . '-01')->startOfMonth();
        $end   = $start->copy()->endOfMonth();

        $booked = Appointment::where('unit', $appointment->unit)
            ->where('id', '!=', $appointment->id)
            ->whereBetween('appointment_date', [$start->toDateString(), $end->toDateString()])
            ->whereNotIn('status', ['cancelled', 'rescheduled'])
            ->where('request_status', '!=', 'awaiting_student')
            ->get(['appointment_date', 'start_time', 'end_time']);

        // 8:00-16:00 is the whole bookable window for a unit in one day -
        // once booked minutes reach that, there's no room left to fit
        // another slot no matter how it's arranged.
        $dailyCapacityMinutes = 8 * 60;

        $bookedMinutesByDate = [];
        foreach ($booked as $a) {
            $date = $a->appointment_date instanceof \DateTimeInterface
                ? $a->appointment_date->format('Y-m-d')
                : substr($a->appointment_date, 0, 10);
            $minutes = (strtotime($a->end_time) - strtotime($a->start_time)) / 60;
            $bookedMinutesByDate[$date] = ($bookedMinutesByDate[$date] ?? 0) + max(0, $minutes);
        }

        $blockedDays = \App\Support\BlockedDates::between($start->toDateString(), $end->toDateString());

        $days = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $dateStr = $d->toDateString();
            $isWeekend = $d->dayOfWeekIso > 5;
            $isPast = $d->lt(now()->startOfDay());
            $bookedMinutes = $bookedMinutesByDate[$dateStr] ?? 0;

            $status = 'available';
            if ($isWeekend) {
                $status = 'closed';
            } elseif ($isPast) {
                $status = 'past';
            } elseif (isset($blockedDays[$dateStr])) {
                $status = 'blocked';
            } elseif ($bookedMinutes >= $dailyCapacityMinutes) {
                $status = 'full';
            }

            $days[] = ['date' => $dateStr, 'status' => $status] + (isset($blockedDays[$dateStr]) && $status === 'blocked' ? ['label' => $blockedDays[$dateStr]['label']] : []);
        }

        return response()->json(['days' => $days, 'previous_slots' => $this->previousSlots($appointment)]);
    }

    public function submit(Request $request, $token)
    {
        $appointment = Appointment::where('scheduling_token', $token)
            ->where('token_expires_at', '>=', now())
            ->first();

        if (!$appointment) {
            return response()->json(['message' => 'This scheduling link is invalid or has expired. Please ask GCU to issue a new one.'], 404);
        }

        $request->validate([
            'appointment_date' => 'required|date',
            'start_time'       => 'required',
            'end_time'         => 'required',
        ]);

        if ($request->end_time <= $request->start_time) {
            return response()->json(['message' => 'End time must be later than start time.'], 422);
        }

        $date = new \DateTime($request->appointment_date);
        $dayOfWeek = (int) $date->format('N');

        if ($dayOfWeek > 5) {
            return response()->json(['message' => 'Appointments can only be scheduled Monday to Friday.'], 422);
        }

        if ($violation = $this->dateTimeRuleViolation($request->appointment_date, $request->start_time, $request->end_time)) {
            return response()->json(['message' => $violation], 422);
        }

        // B628-equivalent: bounded on both ends (see AppointmentController).
        if ($request->start_time < '08:00' || $request->start_time >= '16:00'
            || $request->end_time <= '08:00' || $request->end_time > '16:00') {
            return response()->json(['message' => 'Appointments can only be scheduled between 8:00 AM and 4:00 PM.'], 422);
        }

        if ($blocked = \App\Support\BlockedDates::message($request->appointment_date)) {
            return response()->json(['message' => $blocked, 'blocked' => true], 422);
        }

        // Panel fix (11/8/2026, item #8): same exact-match cap as checkAvailability() above.
        $conflict = Appointment::hasUnitConflict(
            $appointment->unit,
            $request->appointment_date,
            $request->start_time,
            $request->end_time,
            $appointment->id,
            Appointment::STUDENT_SAME_SLOT_CAP
        );

        if ($conflict) {
            return response()->json(['message' => 'This time slot is no longer available. Please pick another.'], 422);
        }

        if ($this->overlapsReplacedSlot($appointment, $request->appointment_date, $request->start_time, $request->end_time)) {
            return response()->json(['message' => self::REPLACED_SLOT_MESSAGE], 422);
        }

        $appointment->update([
            'appointment_date' => $request->appointment_date,
            'start_time'       => $request->start_time,
            'end_time'         => $request->end_time,
            'request_status'   => 'pending_confirmation',
            'status'           => 'pending',
        ]);

        // Bug (11/5/2026): this used $appointment->case->latestReferral -
        // the case's most recently created referral, not necessarily the
        // one this scheduling link/appointment actually belongs to. A case
        // can carry more than one referral (e.g. an older Class Attendance
        // referral plus a newer, unrelated referral); a student using THIS
        // appointment's own link was wrongly advancing whatever referral
        // happened to be newest on the case - sometimes one nobody had
        // acknowledged yet - straight to "Scheduled" with no staff action
        // on it. Target the appointment's own linked referral instead,
        // falling back to the case's latest only for legacy appointments
        // with no referral_id, and don't downgrade/overwrite a referral
        // that's already moved further along.
        $referralToSchedule = $appointment->referral ?: $appointment->case?->latestReferral;
        if ($referralToSchedule && $referralToSchedule->canMoveStatusTo('scheduled')) {
            $referralToSchedule->update(['status' => 'scheduled']);
        }

        AuditLog::record('scheduled', "Student self-scheduled appointment {$appointment->appointment_code}.", $appointment);
        $appointment->notifyOffice('scheduled');

        return response()->json(['message' => 'Appointment request submitted. The office will confirm shortly.', 'appointment' => $appointment]);
    }
}