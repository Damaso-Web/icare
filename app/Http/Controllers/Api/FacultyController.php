<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * The Faculty module.
 *   Dean       - adds / uploads faculty for the whole college, and assigns
 *                (or removes) the Department Chair of each department.
 *   Dept Chair - adds / uploads faculty for their own department only.
 *   Admin      - sees everything (also manageable from User Management).
 * Every query and every write is scoped here on the server, not just hidden
 * in the UI.
 */
class FacultyController extends UserController
{
    private const NAME_REGEX  = '/^[a-zA-Z\x{00C0}-\x{024F}\'\-\.\s]+$/u';
    // A Philippine mobile number: 09 followed by 9 digits.
    private const PHONE_REGEX = '/^09\d{9}$/';
    private const ID_REGEX    = '/^[A-Za-z0-9\-]+$/';
    // B612/B613: the Add/Edit Faculty form's own Employee ID field is
    // numbers-only, with the same 15-character limit as the other Employee
    // ID field in the system (User Management's Add Employee).
    private const FACULTY_ID_REGEX = '/^[0-9]+$/';
    private const FACULTY_ID_MESSAGES = [
        'employee_id.regex' => 'Employee ID must contain numbers only.',
        'employee_id.max'   => 'Employee ID must not be longer than 15 digits.',
    ];

    // Roles that live on the Faculty page.
    private const FACULTY_ROLES = ['faculty', 'dept_chair'];

    /** Base query limited to what the signed-in actor may see. */
    private function scoped(Request $request)
    {
        $actor = $request->user();
        $q = User::query()->whereIn('role', self::FACULTY_ROLES);

        if ($actor->isAdmin()) {
            return $q;
        }

        abort_if(empty($actor->college), 403, 'Your account has no college assigned. Please contact the OSS administrator.');
        $q->where('college', $actor->college);

        if ($actor->isDeptChair()) {
            abort_if(empty($actor->department), 403, 'Your account has no department assigned. Please contact the OSS administrator.');
            $q->where('department', $actor->department)->where('role', 'faculty');
        }

        return $q;
    }

    private function authorizeTarget(Request $request, User $user): void
    {
        abort_unless($this->scoped($request)->whereKey($user->id)->exists(), 403, 'That person is outside your college/department.');
    }

    /** The college/department every NEW faculty record must carry. */
    private function forcedPlacement(Request $request, ?string $requestedDepartment): array
    {
        $actor = $request->user();

        if ($actor->isDeptChair()) {
            return [$actor->college, $actor->department];
        }
        if ($actor->isDean()) {
            return [$actor->college, $requestedDepartment];
        }
        return [$request->input('college'), $requestedDepartment];
    }

    public function index(Request $request)
    {
        $query = $this->scoped($request)
            ->when($request->search, fn($q) => $q->where(fn($sq) =>
                $sq->where('name', 'like', "%{$request->search}%")
                   ->orWhere('email', 'like', "%{$request->search}%")
                   ->orWhere('employee_id', 'like', "%{$request->search}%")
            ))
            ->when($request->department, fn($q) => $q->where('department', $request->department))
            ->when($request->has('is_active') && $request->is_active !== '', fn($q) => $q->where('is_active', $request->is_active))
            ;

        $sortBy  = in_array($request->sort_by, ['created_at', 'employee_id', 'last_name'], true) ? $request->sort_by : 'last_name';
        $sortDir = $request->sort_dir === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortBy, $sortDir);
        if ($sortBy !== 'last_name') $query->orderBy('last_name');

        return response()->json($query->paginate(20));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name'     => ['required', 'string', 'max:255', 'regex:' . self::NAME_REGEX],
            'middle_name'    => ['nullable', 'string', 'max:255', 'regex:' . self::NAME_REGEX],
            'last_name'      => ['required', 'string', 'max:255', 'regex:' . self::NAME_REGEX],
            'suffix'         => ['nullable', 'string', 'max:20', 'regex:' . self::NAME_REGEX],
            'email'          => ['required', 'email', 'regex:/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/', new \App\Rules\UniqueAccountEmail()],
            'employee_id'    => ['nullable', 'string', 'max:15', 'regex:' . self::FACULTY_ID_REGEX, self::employeeIdUnique()],
            'department'     => 'nullable|string|max:255',
            'college'        => 'nullable|string|max:255',
            'contact_number' => ['nullable', 'string', 'max:11', 'regex:' . self::PHONE_REGEX],
            // Added as Faculty (default) or straight away as the Department Chair.
            'role'           => 'nullable|in:faculty,dept_chair',
        ], self::FACULTY_ID_MESSAGES);
        $role = $validated['role'] ?? 'faculty';
        unset($validated['role']);
        abort_if($role === 'dept_chair' && !($request->user()->isDean() || $request->user()->isAdmin()), 403, 'Only the Dean can add a Department Chair.');

        [$college, $department] = $this->forcedPlacement($request, $validated['department'] ?? null);
        abort_if(empty($college) || empty($department), 422, 'College and department are required.');

        // One chair per department: the previous chair goes back to faculty.
        if ($role === 'dept_chair') {
            User::where('role', 'dept_chair')->where('college', $college)->where('department', $department)->update(['role' => 'faculty']);
        }

        $temp = Str::random(10);
        $user = User::create([
            ...$validated,
            'college'              => $college,
            'department'           => $department,
            'role'                 => $role,
            'name'                 => trim($validated['first_name'] . ' ' . $validated['last_name']),
            'password'             => Hash::make($temp),
            'temp_password'        => $temp,
            'must_change_password' => true,
            'is_active'            => true,
        ]);

        AuditLog::record('created', 'Added ' . ($role === 'dept_chair' ? 'Department Chair' : 'faculty') . " {$user->name} ({$college} / {$department}).", $user);

        return response()->json(['user' => $user, 'temp_password' => $temp], 201);
    }

    public function update(Request $request, User $user)
    {
        $this->authorizeTarget($request, $user);

        $validated = $request->validate([
            'first_name'     => ['sometimes', 'string', 'max:255', 'regex:' . self::NAME_REGEX],
            'middle_name'    => ['nullable', 'string', 'max:255', 'regex:' . self::NAME_REGEX],
            'last_name'      => ['sometimes', 'string', 'max:255', 'regex:' . self::NAME_REGEX],
            'suffix'         => ['nullable', 'string', 'max:20', 'regex:' . self::NAME_REGEX],
            'email'          => ['sometimes', 'email', 'regex:/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/', new \App\Rules\UniqueAccountEmail('staff', $user->id)],
            'employee_id'    => ['nullable', 'string', 'max:15', 'regex:' . self::FACULTY_ID_REGEX, self::employeeIdUnique($user->id)],
            'department'     => 'nullable|string|max:255',
            'contact_number' => ['nullable', 'string', 'max:11', 'regex:' . self::PHONE_REGEX],
        ], self::FACULTY_ID_MESSAGES);

        // A Dept Chair can't move people to another department.
        if ($request->user()->isDeptChair()) {
            unset($validated['department']);
        }

        $old = $user->toArray();
        $user->update($validated);
        if (isset($validated['first_name']) || isset($validated['last_name'])) {
            $user->update(['name' => trim($user->first_name . ' ' . $user->last_name)]);
        }

        AuditLog::record('updated', "Updated faculty {$user->name}.", $user, $old, $user->toArray());

        return response()->json($user);
    }

    public function toggle(Request $request, User $user)
    {
        $this->authorizeTarget($request, $user);
        // Same rules as User Management: a reason from the list for this action (checked here,
        // not just in the dropdown), a status event, and a forced password change on reactivation.
        $deactivating = (bool) $user->is_active;
        abort_if($user->id === $request->user()->id && $deactivating, 422, 'You cannot deactivate your own account.');
        $list = $deactivating ? \App\Support\EmployeeAccountReasons::DEACTIVATE : \App\Support\EmployeeAccountReasons::REACTIVATE;
        $validated = $request->validate([
            'reason' => ['required', \Illuminate\Validation\Rule::in(array_keys($list))],
            'note'   => ['nullable', 'string', 'max:500', 'required_if:reason,other', 'regex:/\p{L}/u'],
        ], [
            'reason.required'  => 'Please select a reason.',
            'reason.in'        => 'That reason is not allowed for this action.',
            'note.required_if' => 'Please describe the reason when "Other" is selected.',
            'note.regex'       => 'Please explain in words, not numbers only.',
        ]);
        $label = \App\Support\EmployeeAccountReasons::label($deactivating ? 'deactivate' : 'reactivate', $validated['reason']);
        $note  = trim((string) ($validated['note'] ?? '')) ?: null;

        if ($deactivating) {
            $user->update(['is_active' => false, 'deactivation_reason' => $label, 'deactivation_note' => $note, 'deactivated_at' => now()]);
        } else {
            $user->update(['is_active' => true, 'deactivation_reason' => null, 'deactivation_note' => null, 'deactivated_at' => null, 'must_change_password' => true]);
            $user->tokens()->delete();
        }
        \App\Models\UserStatusEvent::create([
            'user_id' => $user->id, 'action' => $deactivating ? 'deactivated' : 'reactivated', 'reason_key' => $validated['reason'],
            'reason_label' => $label, 'note' => $note, 'actor_id' => $request->user()->id, 'actor_name' => $request->user()->name, 'created_at' => now(),
        ]);
        AuditLog::record($deactivating ? 'deactivated' : 'reactivated',
            ($deactivating ? 'Deactivated' : 'Reactivated') . " {$user->name} (account {$user->email}). Reason: {$label}." . ($note ? " Note: {$note}." : '')
            . ($deactivating ? '' : ' A password change is required at the next login.'),
            $user, ['is_active' => !$deactivating], ['is_active' => $deactivating ? false : true, 'reason' => $label, 'note' => $note]);
        return response()->json($user->fresh());
    }

    public function resetPassword(Request $request, User $user)
    {
        $this->authorizeTarget($request, $user);
        return parent::resetPassword($request, $user);
    }

    public function passwordInfo(Request $request, User $user)
    {
        $this->authorizeTarget($request, $user);
        return response()->json(['name' => $user->name] + \App\Support\PasswordReset::info($user));
    }

    public function tempPassword(Request $request, User $user)
    {
        $this->authorizeTarget($request, $user);
        return parent::viewTempPassword($user);
    }

    /** Dean (or admin): make a faculty member the Dept Chair of a department. */
    public function assignChair(Request $request, User $user)
    {
        abort_unless($request->user()->isDean() || $request->user()->isAdmin(), 403, 'Only the Dean can assign a Department Chair.');
        $this->authorizeTarget($request, $user);

        $validated = $request->validate(['department' => 'required|string|max:255']);

        // One chair per department: the previous chair goes back to faculty.
        User::where('role', 'dept_chair')
            ->where('college', $user->college)
            ->where('department', $validated['department'])
            ->where('id', '!=', $user->id)
            ->update(['role' => 'faculty']);

        $user->update(['role' => 'dept_chair', 'department' => $validated['department']]);

        AuditLog::record('role_changed', "{$user->name} assigned as Department Chair of {$validated['department']}.", $user);

        return response()->json($user);
    }

    public function removeChair(Request $request, User $user)
    {
        abort_unless($request->user()->isDean() || $request->user()->isAdmin(), 403, 'Only the Dean can remove a Department Chair.');
        $this->authorizeTarget($request, $user);

        $user->update(['role' => 'faculty']);
        AuditLog::record('role_changed', "{$user->name} is no longer a Department Chair.", $user);

        return response()->json($user);
    }

    /**
     * Masterlist upload (preview + confirm reuse the shared import in
     * UserController): every row is forced to role=faculty and to the actor's
     * own college / department, and existing accounts outside that scope are
     * refused, so a Dept Chair can't touch another department.
     */
    protected function assignableRoles(): array
    {
        return ['faculty', 'dept_chair'];
    }

    protected function scopeImportRow(array $row, ?User $existing): array
    {
        $actor   = request()->user();
        $reasons = [];

        // New people come in as faculty; an existing Dept Chair keeps the role.
        $row['role'] = $existing && $existing->role === 'dept_chair' ? 'dept_chair' : 'faculty';

        if ($actor->isDeptChair()) {
            $row['college']    = $actor->college;
            $row['department'] = $actor->department;
        } elseif ($actor->isDean()) {
            $row['college'] = $actor->college;
            if (empty($row['department'])) {
                $reasons[] = 'Department is required.';
            }
        } elseif (empty($row['college']) || empty($row['department'])) {
            $reasons[] = 'College and Department are required.';
        }

        if ($existing) {
            $inScope = $this->scoped(request())->whereKey($existing->id)->exists();
            if (!$inScope) {
                $reasons[] = 'This email belongs to an account outside your college/department.';
            }
        }

        return [$row, $reasons];
    }
}