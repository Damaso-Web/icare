<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Shared rules for the Management module: the reason a record is deleted,
 * the check that nothing still uses it, the "someone else changed it" check,
 * and the Audit Trail entries for add / edit / delete.
 */
class ManagementGuard
{
    private const CREATED = ['created_error' => 'Created in Error'];
    private const DUP     = ['duplicate' => 'Duplicate Entry'];

    /** Delete is only for mistaken or duplicate entries, whatever the tab. */
    public static function reasons(string $tab): array
    {
        return self::CREATED + self::DUP + ['other' => 'Other'];
    }

    /**
     * Reasons for Deactivate / Archive / Reactivate, per Management tab.
     * Keep in sync with MANAGEMENT_STATUS_REASONS in resources/js/constants/accountReasons.js.
     */
    public static function statusReasons(string $tab, string $action): array
    {
        $tab = $tab === 'referral sources' ? 'sources' : $tab;
        $table = [
            'college' => [
                'deactivate' => ['temporarily_not_offered' => 'Temporarily Not Offered', 'under_reorganization' => 'Under Reorganization', 'pending_merger' => 'Pending Merger'],
                'archive'    => ['no_longer_offered' => 'No Longer Offered', 'merged' => 'Merged', 'renamed_replaced' => 'Renamed or Replaced'],
            ],
            'program' => [
                'deactivate' => ['temporarily_not_offered' => 'Temporarily Not Offered', 'under_curriculum_review' => 'Under Curriculum Review'],
                'archive'    => ['phased_out' => 'Phased Out', 'replaced' => 'Replaced by New Program or Curriculum', 'merged' => 'Merged'],
            ],
            'department' => [
                'deactivate' => ['under_reorganization' => 'Under Reorganization', 'pending_merger' => 'Pending Merger'],
                'archive'    => ['merged' => 'Merged', 'dissolved' => 'Dissolved'],
            ],
            'wellness' => [
                'deactivate' => ['temporarily_unavailable' => 'Temporarily Unavailable', 'under_review' => 'Under Review'],
                'archive'    => ['service_discontinued' => 'Service Discontinued', 'replaced_merged' => 'Replaced or Merged with Another Service'],
            ],
            'disciplinary' => [
                'deactivate' => ['temporarily_unavailable' => 'Temporarily Unavailable', 'pending_policy_update' => 'Pending Policy Update'],
                'archive'    => ['discontinued' => 'Discontinued', 'replaced_policy' => 'Replaced by Updated Policy or Procedure'],
            ],
            'document header' => [
                'deactivate' => [],
                'archive'    => ['outdated_format' => 'Outdated Format', 'replaced_version' => 'Replaced by New Version', 'office_logo_changed' => 'Office Name or Logo Changed'],
            ],
            'holidays and suspensions' => [
                'deactivate' => [],
                'archive'    => ['date_changed' => 'Date Changed by the University', 'working_day' => 'Declared a Working Day', 'replaced_announcement' => 'Replaced by an Updated Announcement', 'event_cancelled' => 'Event Cancelled or Moved'],
            ],
            'calendar' => [
                'deactivate' => ['entered_ahead' => 'Entered Ahead of Time', 'schedule_under_review' => 'Schedule Under Review'],
                'archive'    => ['term_ended' => 'Term Ended', 'replaced_calendar' => 'Replaced by Updated Calendar'],
            ],
            'school calendar' => [
                'deactivate' => ['entered_ahead' => 'Entered Ahead of Time', 'schedule_under_review' => 'Schedule Under Review'],
                'archive'    => ['term_ended' => 'Term Ended', 'replaced_calendar' => 'Replaced by Updated Calendar'],
            ],
            'sources' => [
                'deactivate' => ['not_currently_used' => 'Not Currently Used', 'under_review' => 'Under Review'],
                'archive'    => ['no_longer_used' => 'No Longer Used', 'merged' => 'Merged with Another Source'],
            ],
        ];
        $reactivate = ['offered_again' => 'Offered or Used Again', 'review_completed' => 'Review or Reorganization Completed', 'deactivated_in_error' => 'Deactivated in Error'];
        // B535: "Deactivated in Error" talks about deactivation, which doesn't apply to an
        // archived holiday/suspension. This tab gets its own, archiving-appropriate set.
        $holidayReactivate = [
            'needed_again'                     => 'Needed for Current Operations',
            'archived_by_mistake'              => 'Archived by Mistake',
            'review_completed'                 => 'Review or Reorganization Completed',
            'previously_archived_now_relevant' => 'Previously Archived/Now Relevant',
        ];

        $list = $action === 'reactivate'
            ? ($tab === 'holidays and suspensions' ? $holidayReactivate : $reactivate)
            : ($table[$tab][$action] ?? []);
        return $list + ['other' => 'Other'];
    }

    /** Validates a status-change reason (and the note for "Other"); returns [label, note]. */
    public static function requireStatusReason(Request $request, string $tab, string $action): array
    {
        $reasons = self::statusReasons($tab, $action);
        $request->validate([
            'reason' => ['required', \Illuminate\Validation\Rule::in(array_keys($reasons))],
            'note'   => ['nullable', 'string', 'max:500', 'required_if:reason,other', 'regex:/\p{L}/u'],
        ], [
            'reason.required'  => 'Please select a reason.',
            'reason.in'        => 'That reason is not allowed for this action.',
            'note.required_if' => 'Please describe the reason briefly.',
            'note.regex'       => 'Please explain in words, not numbers only.',
        ]);

        $note = trim((string) $request->input('note', ''));
        return [$reasons[$request->input('reason')], $note === '' ? null : $note];
    }

    /** Deactivated and archived records are read-only until they are reactivated. */
    public static function assertEditable(Model $model): void
    {
        if (($model->status ?: 'active') !== 'active') {
            abort(422, 'Only active records can be edited. Reactivate it first.');
        }
    }

    /** Validates the delete reason (and note for "Other"); returns [label, note]. */
    public static function requireDeleteReason(Request $request, string $tab): array
    {
        $reasons = self::reasons($tab);
        $request->validate([
            'reason' => ['required', \Illuminate\Validation\Rule::in(array_keys($reasons))],
            'note'   => ['nullable', 'string', 'max:500', 'required_if:reason,other', 'regex:/\p{L}/u'],
        ], [
            'reason.required'  => 'Please select a reason for deletion.',
            'note.required_if' => 'Please describe the reason for deletion briefly.',
            'note.regex'       => 'Please explain in words, not numbers only.',
        ]);

        $note = trim((string) $request->input('note', ''));
        return [$reasons[$request->input('reason')], $note === '' ? null : $note];
    }

    /** Refuses the delete when the record changed after the admin opened the dialog. */
    public static function assertUnchanged(Request $request, Model $model): void
    {
        $seen = $request->input('version');
        if ($seen && $model->updated_at && abs(strtotime($seen) - $model->updated_at->getTimestamp()) > 1) {
            abort(409, 'This record was changed in another session. Refresh the page and try again.');
        }
    }

    /** Blocks the delete while anything still references the record. $links = ['students' => 14, ...] */
    public static function blockIfLinked(string $noun, array $links, string $hint = 'Reassign or deactivate them first.'): void
    {
        $parts = [];
        foreach ($links as $what => $n) {
            if ($n > 0) $parts[] = $n . ' ' . ($n === 1 ? rtrim($what, 's') : $what);
        }
        if (!$parts) return;

        $list = count($parts) > 1 ? implode(', ', array_slice($parts, 0, -1)) . ' and ' . end($parts) : $parts[0];
        throw ValidationException::withMessages([
            'linked' => ["This {$noun} is used by {$list}. {$hint}"],
        ])->status(422);
    }

    public static function capFirst(?string $v): ?string
    {
        if ($v === null) return null;
        $v = trim($v);
        return $v === '' ? $v : mb_strtoupper(mb_substr($v, 0, 1)) . mb_substr($v, 1);
    }

    public static function audit(string $tab, string $action, string $name, ?Model $model = null, array $old = [], array $new = [], ?string $reason = null, ?string $note = null): void
    {
        $verb = ['created' => 'Added', 'updated' => 'Edited', 'deleted' => 'Deleted'][$action] ?? ucfirst($action);
        $text = "{$verb} {$tab} \"{$name}\"";
        if ($model && $model->getKey()) $text .= " (ID {$model->getKey()})";
        $text .= '.';

        if (in_array($action, ['updated', 'deactivated', 'archived', 'reactivated'], true)) {
            $changes = [];
            foreach ($new as $field => $value) {
                if (array_key_exists($field, $old) && (string) $old[$field] !== (string) $value) {
                    $changes[] = str_replace('_', ' ', $field) . ': ' . $old[$field] . ' → ' . $value;
                }
            }
            if ($changes) $text .= ' ' . implode('; ', $changes) . '.';
        }
        if ($reason) $text .= " Reason: {$reason}." . ($note ? " Note: {$note}." : '');

        AuditLog::record($action, '[Management > ' . ucfirst($tab) . '] ' . $text, $model, $old, $new);
    }
}