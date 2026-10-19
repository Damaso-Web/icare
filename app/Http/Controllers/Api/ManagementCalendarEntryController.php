<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\CalendarEntry;
use App\Support\ManagementGuard;
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
        $data = $this->validated($request);
        $this->assertNoDuplicate($data['entry_date'], $data['type'], null);

        $e = CalendarEntry::create($data + ['status' => 'active', 'created_by_user_id' => $request->user()->id]);
        ManagementGuard::audit('holidays and suspensions', 'created', $e->describe(), $e, [], $this->snap($e));
        return response()->json($this->row($e), 201);
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

        $entry->forceFill(['status' => 'archived'])->save();
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

        $entry->forceFill(['status' => 'active'])->save();
        ManagementGuard::audit('holidays and suspensions', 'reactivated', $entry->describe(), $entry, ['status' => 'archived'], ['status' => 'active'], $reason, $note);
        return response()->json($this->row($entry->fresh()));
    }

    public function destroy(Request $request, CalendarEntry $entry)
    {
        $this->authorizeManage($request);
        [$reason, $note] = ManagementGuard::requireDeleteReason($request, 'holidays and suspensions');

        $links = self::links($entry);
        if ($this->isPast($entry) || array_sum($links) > 0) {
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