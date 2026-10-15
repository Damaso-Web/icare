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

    /** tab => [reason key => label]. "other" always needs a note. */
    public static function reasons(string $tab): array
    {
        $specific = match ($tab) {
            'college'      => ['no_longer_offered' => 'No Longer Offered', 'merged' => 'Merged with Another College'],
            'program'      => ['discontinued' => 'Program Discontinued', 'replaced' => 'Renamed or Replaced'],
            'department'   => ['dissolved' => 'Department Dissolved', 'merged' => 'Merged with Another Department'],
            'wellness'     => ['no_longer_offered' => 'Service No Longer Offered', 'replaced' => 'Replaced by Another Service'],
            'disciplinary' => ['no_longer_offered' => 'Service No Longer Offered', 'policy_change' => 'Replaced Following a Policy Change'],
            default        => ['no_longer_used' => 'No Longer Used'],
        };

        return self::CREATED + self::DUP + $specific + ['other' => 'Other'];
    }

    /** Validates the reason (and note for "Other"); returns [label, note]. */
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
    public static function blockIfLinked(string $noun, array $links): void
    {
        $parts = [];
        foreach ($links as $what => $n) {
            if ($n > 0) $parts[] = $n . ' ' . ($n === 1 ? rtrim($what, 's') : $what);
        }
        if (!$parts) return;

        $list = count($parts) > 1 ? implode(', ', array_slice($parts, 0, -1)) . ' and ' . end($parts) : $parts[0];
        throw ValidationException::withMessages([
            'linked' => ["This {$noun} is used by {$list}. Reassign or deactivate them first."],
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