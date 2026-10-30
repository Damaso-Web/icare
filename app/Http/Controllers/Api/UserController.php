<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
    // Plain <input> fields only. Email and password are intentionally exempt.
    private const NAME_REGEX  = '/^[a-zA-Z\x{00C0}-\x{024F}\'\-\.\s]+$/u';
    private const PHONE_REGEX = '/^[0-9\+\-\s]+$/';
    private const ID_REGEX    = '/^[A-Za-z0-9\-]+$/';

    /**
     * Roles the currently authenticated actor may assign.
     * system_admin can assign anything; admin (GCU Head) cannot grant system_admin.
     */
    protected function assignableRoles(): array
    {
        $actor = request()->user();
        // TMDU Head: TMDU Staff only. Dean: the faculty and chairs of their college.
        if ($actor?->isTMDUHead()) return ['tmdu_staff'];
        if ($actor?->isDean())     return ['faculty', 'dept_chair'];

        // dean_secretary is kept so existing accounts can still be edited;
        // new ones are made as Dean (the two are the same role now).
        $all = ['admin', 'gcu_staff', 'sdu_head', 'tmdu_head', 'tmdu_staff', 'dean', 'dept_chair', 'faculty', 'dean_secretary', 'system_admin'];

        return $actor?->isSystemAdmin()
            ? $all
            : array_values(array_diff($all, ['system_admin']));
    }

    /** Employee ID must be unique; the message names the actual problem instead of a generic one. */
    protected static function employeeIdUnique(?int $ignoreId = null): \Closure
    {
        return function ($attribute, $value, $fail) use ($ignoreId) {
            if ($value !== null && $value !== '' && User::where('employee_id', $value)->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))->exists()) {
                $fail('Employee ID already exists. Please enter a unique Employee ID.');
            }
        };
    }

    // The accounts the signed-in user may see and manage in User Management.
    private function scopeManaged($query, User $actor)
    {
        if ($actor->isTMDUHead()) {
            return $query->where('role', 'tmdu_staff');
        }
        if ($actor->isDean()) {
            return $query->whereIn('role', ['faculty', 'dept_chair'])->where('college', $actor->college);
        }
        return $query;
    }

    private function authorizeManaged(User $target): void
    {
        $actor = request()->user();
        if ($actor->isAdmin() || $actor->isSystemAdmin()) {
            return;
        }
        abort_unless($this->scopeManaged(User::whereKey($target->id), $actor)->exists(), 403, 'You can only manage the accounts under you.');
    }

    // The unit a staff account belongs to follows from its role.
    private const UNIT_FOR_ROLE = ['admin' => 'OSS', 'gcu_staff' => 'GCU', 'sdu_head' => 'SDU', 'tmdu_head' => 'TMDU', 'tmdu_staff' => 'TMDU'];

    // Lightweight, read-only staff list for the Reassign dropdowns. Unlike
    // index() this is open to every OSS staff role (the Users route group is
    // admin-only, which is why those dropdowns used to 403 and glitch).
    public function roster(Request $request)
    {
        abort_unless(in_array($request->user()->role, ['admin', 'gcu_staff', 'sdu_head', 'tmdu_staff', 'tmdu_head'], true), 403, 'Unauthorized.');

        return response()->json(
            User::where('is_active', true)
                ->when($request->role, fn($q) => $q->whereIn('role', (array) $request->role))
                ->when($request->unit, fn($q) => $q->where('unit', $request->unit))
                ->whereIn('role', ['admin', 'gcu_staff', 'sdu_head', 'tmdu_staff', 'tmdu_head'])
                ->orderBy('name')
                ->get(['id', 'name', 'role', 'unit'])
        );
    }

    public function index(Request $request)
    {
        $query = $this->scopeManaged(User::query(), $request->user())
            ->when($request->search, fn($q) => $q->where(fn($sq) =>
                $sq->where('name', 'like', "%{$request->search}%")
                   ->orWhere('email', 'like', "%{$request->search}%")
            ))
            ->when($request->role, fn($q) => $q->where('role', $request->role))
            ->when($request->unit, fn($q) => $q->where('unit', $request->unit))
            ->when($request->college, fn($q) => $q->where('college', $request->college))
            // B589: "All Departments" filter on User Management (mainly for a Dean,
            // whose own college filter is hidden since it's already fixed to theirs).
            ->when($request->department, fn($q) => $q->where('department', $request->department))
            ->when($request->has('is_active'), fn($q) => $q->where('is_active', $request->is_active));

        $sortBy  = in_array($request->sort_by, ['created_at', 'employee_id', 'last_name']) ? $request->sort_by : 'created_at';
        $sortDir = $request->sort_dir === 'asc' ? 'asc' : 'desc';
        if ($sortBy === 'last_name') {
            $query->orderBy('last_name', $sortDir)->orderBy('first_name', $sortDir);
        } else {
            $query->orderBy($sortBy, $sortDir);
        }

        return response()->json($query->paginate(20));
    }

        public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name'            => ['required', 'string', 'max:255', 'regex:' . self::NAME_REGEX],
            'middle_name'           => ['nullable', 'string', 'max:255', 'regex:' . self::NAME_REGEX],
            'last_name'             => ['required', 'string', 'max:255', 'regex:' . self::NAME_REGEX],
            'suffix'                => ['nullable', 'string', 'max:20', 'regex:' . self::NAME_REGEX],
            'email'                 => ['required', 'email', 'regex:/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/', new \App\Rules\UniqueAccountEmail()],
            'employee_id'           => ['nullable', 'string', 'max:50', 'regex:' . self::ID_REGEX, self::employeeIdUnique()],
            'role'                  => ['required', \Illuminate\Validation\Rule::in($this->assignableRoles())],
            'college'               => 'nullable|string|max:255',
            'department'            => 'nullable|string|max:255',
            'contact_number'        => ['nullable', 'string', 'max:11', 'regex:' . self::PHONE_REGEX],
            'password'              => ['required', 'confirmed', 'min:8', 'max:64', 'regex:/[A-Z]/', 'regex:/[0-9]/', 'regex:/[!@#$%^&*(),.?":{}|<>]/'],
        ]);

        if ($request->user()->isDean()) {
            $validated['college'] = $request->user()->college;
        }
        if (in_array($validated['role'], ['faculty', 'dept_chair'], true)) {
            abort_if(empty($validated['college']) || empty($validated['department']), 422, 'Choose the college and department.');
        }
        $validated['unit'] = self::UNIT_FOR_ROLE[$validated['role']] ?? null;

        // A Dean manages the faculty of one college, so the college is required.
        if ($validated['role'] === 'dean') {
            abort_if(empty($validated['college']), 422, 'Choose the college this Dean manages.');
            $validated['department'] = null;
        }

        // B643 (10/30/2026): temp_password is a hash now, never plain text -
        // the admin already typed this password in, so nothing is lost by
        // not keeping a reversible copy of it.
        $user = User::create([
            ...$validated,
            'name'                  => trim($validated['first_name'] . ' ' . $validated['last_name']),
            'password'              => Hash::make($validated['password']),
            'temp_password'         => Hash::make($validated['password']),
            'must_change_password'  => true,
            'is_active'             => true,
        ]);

        AuditLog::record('created', "Created employee account for {$user->name} ({$user->role}).", $user);

        return response()->json($user, 201);
    }

    public function show(User $user)
    {
        $this->authorizeManaged($user);
        return response()->json($user);
    }

        public function update(Request $request, User $user)
{
    $this->authorizeManaged($user);
    $validated = $request->validate([
        'first_name'     => ['sometimes', 'string', 'max:255', 'regex:' . self::NAME_REGEX],
        'middle_name'    => ['nullable', 'string', 'max:255', 'regex:' . self::NAME_REGEX],
        'last_name'      => ['sometimes', 'string', 'max:255', 'regex:' . self::NAME_REGEX],
        'suffix'         => ['nullable', 'string', 'max:20', 'regex:' . self::NAME_REGEX],
        'email'          => ['sometimes', 'email', 'regex:/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/', new \App\Rules\UniqueAccountEmail('staff', $user->id)],
        'employee_id'    => ['nullable', 'string', 'max:50', 'regex:' . self::ID_REGEX, self::employeeIdUnique($user->id)],
        'role'           => ['sometimes', \Illuminate\Validation\Rule::in($this->assignableRoles())],
        'college'        => 'nullable|string|max:255',
        'department'     => 'nullable|string|max:255',
        'contact_number' => ['nullable', 'string', 'max:11', 'regex:' . self::PHONE_REGEX],
    ]);

    // A Dean's accounts stay in the Dean's college.
    if ($request->user()->isDean()) {
        unset($validated['college']);
    }
    if (isset($validated['role']) && isset(self::UNIT_FOR_ROLE[$validated['role']])) {
        $validated['unit'] = self::UNIT_FOR_ROLE[$validated['role']];
    }

    // Prevent a user from changing their own role (self-escalation guard).
    if (isset($validated['role']) && $request->user()->id === $user->id) {
        unset($validated['role']);
    }

    if (($validated['role'] ?? $user->role) === 'dean' && array_key_exists('college', $validated) && empty($validated['college'])) {
        abort(422, 'Choose the college this Dean manages.');
    }

    $old = $user->toArray();
    $user->update($validated);

    AuditLog::record('updated', "Updated employee account for {$user->name}.", $user, $old, $user->toArray());

    return response()->json($user);
}

    public function destroy(User $user)
    {
        $this->authorizeManaged($user);
        $user->delete();
        AuditLog::record('deleted', "Deleted employee account for {$user->name}.");
        return response()->json(['message' => 'User deleted.']);
    }

    private function openWorkQuery(User $user): array
    {
        $referrals = \App\Models\Referral::where('assigned_to_user_id', $user->id)
            ->where('is_archived', false)
            ->whereNotIn('status', ['completed', 'closed']);
        $cases = \App\Models\CaseFile::where('primary_counselor_id', $user->id)
            ->whereNotIn('status', ['closed', 'resolved']);
        $appointments = \App\Models\Appointment::where('staff_user_id', $user->id)
            ->whereIn('status', ['pending', 'confirmed'])
            ->whereDate('appointment_date', '>=', today());

        return [$referrals, $cases, $appointments];
    }

    /** Open cases / upcoming appointments that would be left on a deactivated account, and who can take them over. */
    public function openWork(User $user)
    {
        $this->authorizeManaged($user);
        [$referrals, $cases, $appointments] = $this->openWorkQuery($user);

        return response()->json([
            'open_referrals'        => $referrals->count(),
            'open_cases'            => $cases->count(),
            'upcoming_appointments' => $appointments->count(),
            'staff'                 => User::where('is_active', true)
                ->where('id', '!=', $user->id)
                ->whereIn('role', ['admin', 'gcu_staff', 'sdu_head', 'tmdu_staff', 'tmdu_head'])
                ->when($user->unit, fn($q) => $q->where('unit', $user->unit))
                ->orderBy('name')->get(['id', 'name', 'role', 'unit']),
        ]);
    }

    public function statusHistory(User $user)
    {
        $this->authorizeManaged($user);
        return response()->json(
            \App\Models\UserStatusEvent::where('user_id', $user->id)->orderByDesc('id')->get()
        );
    }

    public function toggleActive(Request $request, User $user)
    {
        $this->authorizeManaged($user);
        $deactivating = (bool) $user->is_active;
        $kind = $deactivating ? 'deactivate' : 'reactivate';
        $list = $deactivating ? \App\Support\EmployeeAccountReasons::DEACTIVATE : \App\Support\EmployeeAccountReasons::REACTIVATE;

        abort_if($user->id === $request->user()->id && $deactivating, 422, 'You cannot deactivate your own account.');

        // B591: a reactivation reason must relate to how the account was
        // actually deactivated - e.g. it was deactivated for "End of
        // Contract / Term" can't then be reactivated as "Security Verified".
        $allowedReasonKeys = $deactivating
            ? array_keys($list)
            : \App\Support\EmployeeAccountReasons::allowedReactivationKeys($user->deactivation_reason);

        $validated = $request->validate([
            'reason'      => ['required', \Illuminate\Validation\Rule::in($allowedReasonKeys)],
            'note'        => ['nullable', 'string', 'max:500', 'required_if:reason,other', 'regex:/\p{L}/u'],
            'reassign_to' => ['nullable', 'integer', 'exists:users,id'],
        ], [
            'reason.required' => 'Please select a reason.',
            'reason.in'       => 'That reactivation reason does not match how this account was deactivated.',
            'note.required_if' => 'Please describe the reason when "Other" is selected.',
            'note.regex'      => 'Please explain in words, not numbers only.',
        ]);

        $label = \App\Support\EmployeeAccountReasons::label($kind, $validated['reason']);
        $note  = isset($validated['note']) ? trim($validated['note']) : null;
        $note  = $note === '' ? null : $note;
        $actor = $request->user();

        if ($deactivating) {
            $reassigned = ['referrals' => 0, 'cases' => 0, 'appointments' => 0];
            if (!empty($validated['reassign_to'])) {
                $target = User::where('id', $validated['reassign_to'])->where('is_active', true)->first();
                abort_if(!$target || $target->id === $user->id, 422, 'Choose an active staff member to take over.');
                [$referrals, $cases, $appointments] = $this->openWorkQuery($user);
                $reassigned['referrals']    = $referrals->update(['assigned_to_user_id' => $target->id]);
                $reassigned['cases']        = $cases->update(['primary_counselor_id' => $target->id]);
                $reassigned['appointments'] = $appointments->update(['staff_user_id' => $target->id]);
            }

            $user->update([
                'is_active'           => false,
                'deactivation_reason' => $label,
                'deactivation_note'   => $note,
                'deactivated_at'      => now(),
            ]);

            \App\Models\UserStatusEvent::create([
                'user_id' => $user->id, 'action' => 'deactivated', 'reason_key' => $validated['reason'],
                'reason_label' => $label, 'note' => $note, 'actor_id' => $actor->id, 'actor_name' => $actor->name,
                'created_at' => now(),
            ]);

            $msg = "Deactivated {$user->name} (account {$user->email}). Reason: {$label}." . ($note ? " Note: {$note}." : '');
            if (!empty($validated['reassign_to'])) {
                $msg .= " Reassigned {$reassigned['referrals']} referral(s), {$reassigned['cases']} case(s) and {$reassigned['appointments']} appointment(s) to {$target->name}.";
            }
            AuditLog::record('deactivated', $msg, $user, ['is_active' => true], ['is_active' => false, 'reason' => $label, 'note' => $note]);
        } else {
            $prevLabel = $user->deactivation_reason;
            $prevAt    = $user->deactivated_at;

            $update = [
                'is_active'           => true,
                'deactivation_reason' => null,
                'deactivation_note'   => null,
                'deactivated_at'      => null,
            ];
            // Every reactivation requires a new password at the next login (enforced by the
            // password.fresh middleware). The password itself is never logged.
            $update['must_change_password'] = true;
            $user->update($update);
            $user->tokens()->delete();

            \App\Models\UserStatusEvent::create([
                'user_id' => $user->id, 'action' => 'reactivated', 'reason_key' => $validated['reason'],
                'reason_label' => $label, 'note' => $note, 'previous_reason_label' => $prevLabel, 'previous_at' => $prevAt,
                'actor_id' => $actor->id, 'actor_name' => $actor->name, 'created_at' => now(),
            ]);

            $msg = "Reactivated {$user->name} (account {$user->email}). Reason: {$label}."
                . ($note ? " Note: {$note}." : '')
                . ($prevLabel ? " Originally deactivated for: {$prevLabel}." : '')
                . ' A password change is required at the next login.';
            AuditLog::record('reactivated', $msg, $user, ['is_active' => false], ['is_active' => true, 'reason' => $label, 'note' => $note]);
        }

        return response()->json($user->fresh());
    }

    public function resetPassword(Request $request, User $user)
    {
        $this->authorizeManaged($user);
        abort_if(!$user->is_active, 422, 'This account is deactivated. Reactivate it before resetting the password.');
        $newPassword = \App\Support\PasswordReset::perform($request, $user, 'employee');

        return response()->json([
            'message'       => 'Password reset successfully.',
            'temp_password' => $newPassword,
        ]);
    }

    public function passwordInfo(Request $request, User $user)
    {
        $this->authorizeManaged($user);
        return response()->json(['name' => $user->name] + \App\Support\PasswordReset::info($user));
    }

    // Admin-only: reports whether the account's temp password is still unused
    // ("pending"). B643 (10/30/2026): temp_password is now a hash, so the
    // original value can't be shown again here - it was only ever shown
    // once, in the create/reset response.
    public function viewTempPassword(User $user)
    {
        $this->authorizeManaged($user);
        if (!$user->must_change_password || !$user->temp_password) {
            return response()->json(['message' => 'This user has already set their own password.'], 404);
        }

        return response()->json(['pending' => true, 'message' => 'A temporary password is set but can only be shown once, at the time it was created or reset. If it was missed or lost, reset the password to generate a new one.']);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls',
        ]);

        $file = $request->file('file');
        $ext  = strtolower($file->getClientOriginalExtension());

        $headerMap = [
            'last name'        => 'last_name',
            'first name'       => 'first_name',
            'middle name'      => 'middle_name',
            'suffix'           => 'suffix',
            'email address'    => 'email',
            'email'            => 'email',
            'role'             => 'role',
            'employee id'      => 'employee_id',
            'college'          => 'college',
            'department'       => 'department',
            'contact number'   => 'contact_number',
        ];

        $rows = [];

        if (in_array($ext, ['xlsx', 'xls'])) {
            // Read values only (no styles) - the template is formatted down to
            // row 10000, and loading all of that formatting is what made valid
            // .xlsx files fail with "Failed to read file".
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($file->getRealPath());
            if (method_exists($reader, 'setReadDataOnly')) {
                $reader->setReadDataOnly(true);
            }
            $spreadsheet = $reader->load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $data  = $sheet->toArray(null, true, true, false);

            // Drop the unused rows at the bottom of the sheet.
            while (!empty($data) && count(array_filter(end($data), fn($v) => $v !== null && trim((string) $v) !== '')) === 0) {
                array_pop($data);
            }

            $rawHeader = array_map(fn($h) => strtolower(trim($h ?? '')), $data[0]);
            for ($i = 1; $i < count($data); $i++) {
                $rowAssoc = [];
                foreach ($rawHeader as $idx => $key) {
                    $mappedKey = $headerMap[$key] ?? $key;
                    $rowAssoc[$mappedKey] = $data[$i][$idx] ?? null;
                }
                $rows[] = $rowAssoc;
            }
        } else {
            $handle = fopen($file->getRealPath(), 'r');
            $rawHeader = array_map(fn($h) => strtolower(trim($h)), fgetcsv($handle));
            while (($data = fgetcsv($handle)) !== false) {
                $rowAssoc = [];
                foreach ($rawHeader as $idx => $key) {
                    $mappedKey = $headerMap[$key] ?? $key;
                    $rowAssoc[$mappedKey] = $data[$idx] ?? null;
                }
                $rows[] = $rowAssoc;
            }
            fclose($handle);
        }

        $validRoles = $this->assignableRoles();
        $created = 0;
        $skipped = 0;
        $errors  = [];
        $rowNum  = 1;
        $generatedPasswords = [];

        foreach ($rows as $rowData) {
            $rowNum++;
            $rowData['role'] = strtolower(trim($rowData['role'] ?? ''));

            if (empty($rowData['first_name']) || empty($rowData['last_name']) || empty($rowData['email']) || empty($rowData['role'])) {
                $errors[] = "Row {$rowNum}: missing required fields (Last Name, First Name, Email, Role).";
                $skipped++;
                continue;
            }

            if (!in_array($rowData['role'], $validRoles)) {
                $errors[] = "Row {$rowNum}: invalid role '{$rowData['role']}'.";
                $skipped++;
                continue;
            }

            $exists = \App\Support\AccountEmail::takenByOther($rowData['email']);
            if ($exists) {
                $errors[] = "Row {$rowNum}: email {$rowData['email']} is already used by another account - skipped.";
                $skipped++;
                continue;
            }

            $tempPassword = Str::random(10);
            User::create([
                'first_name'            => $rowData['first_name'],
                'middle_name'           => $rowData['middle_name'] ?? null,
                'last_name'             => $rowData['last_name'],
                'suffix'                => $rowData['suffix'] ?? null,
                'name'                  => trim($rowData['first_name'] . ' ' . $rowData['last_name']),
                'email'                 => $rowData['email'],
                'employee_id'           => $rowData['employee_id'] ?? null,
                'role'                  => $rowData['role'],
                'college'               => $rowData['college'] ?? null,
                'department'            => $rowData['department'] ?? null,
                'contact_number'        => $rowData['contact_number'] ?? null,
                'password'              => Hash::make($tempPassword),
                // B643: hashed - the plaintext below is the only place this
                // is shown, same as a single created account.
                'temp_password'         => Hash::make($tempPassword),
                'must_change_password'  => true,
                'is_active'             => true,
            ]);
            $created++;
            $generatedPasswords[] = [
                'email'         => $rowData['email'],
                'name'          => trim($rowData['first_name'] . ' ' . $rowData['last_name']),
                'temp_password' => $tempPassword,
            ];
        }

        AuditLog::record('imported', "Bulk imported {$created} employees, skipped {$skipped}.");

        return response()->json([
            'created'   => $created,
            'skipped'   => $skipped,
            'errors'    => $errors,
            'passwords' => $generatedPasswords,
        ]);
    }

    /**
     * Column-header map for the Faculty/Employee masterlist templates,
     * mirroring StudentController's studentHeaderMap() pattern.
     */
    protected function employeeHeaderMap(): array
    {
        return [
            'last name'      => 'last_name',
            'first name'     => 'first_name',
            'middle name'    => 'middle_name',
            'suffix'         => 'suffix',
            'email address'  => 'email',
            'email'          => 'email',
            'role'           => 'role',
            'employee id'    => 'employee_id',
            'college'        => 'college',
            'department'     => 'department',
            'contact number' => 'contact_number',
        ];
    }

    /**
     * Parses an uploaded masterlist file (xlsx/xls/csv/txt) into an array of
     * associative rows, keyed by the mapped field names in $headerMap.
     * Same parsing logic already used inline by import(), just reusable.
     */
    protected function parseFile($file, string $ext, array $headerMap): array
    {
        $rows = [];

        if (in_array($ext, ['xlsx', 'xls'])) {
            // Read values only (no styles) - the template is formatted down to
            // row 10000, and loading all of that formatting is what made valid
            // .xlsx files fail with "Failed to read file".
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($file->getRealPath());
            if (method_exists($reader, 'setReadDataOnly')) {
                $reader->setReadDataOnly(true);
            }
            $spreadsheet = $reader->load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $data  = $sheet->toArray(null, true, true, false);

            // Drop the unused rows at the bottom of the sheet.
            while (!empty($data) && count(array_filter(end($data), fn($v) => $v !== null && trim((string) $v) !== '')) === 0) {
                array_pop($data);
            }

            if (empty($data)) {
                return [];
            }

            $rawHeader = array_map(fn($h) => strtolower(trim($h ?? '')), $data[0]);
            for ($i = 1; $i < count($data); $i++) {
                $rowAssoc = [];
                foreach ($rawHeader as $idx => $key) {
                    $mappedKey = $headerMap[$key] ?? $key;
                    $rowAssoc[$mappedKey] = $data[$i][$idx] ?? null;
                }
                $rows[] = $rowAssoc;
            }
        } else {
            $handle = fopen($file->getRealPath(), 'r');
            $rawHeader = array_map(fn($h) => strtolower(trim($h)), fgetcsv($handle));
            while (($data = fgetcsv($handle)) !== false) {
                $rowAssoc = [];
                foreach ($rawHeader as $idx => $key) {
                    $mappedKey = $headerMap[$key] ?? $key;
                    $rowAssoc[$mappedKey] = $data[$idx] ?? null;
                }
                $rows[] = $rowAssoc;
            }
            fclose($handle);
        }

        // Unused rows at the bottom (blank cells) are not records.
        while (!empty($rows) && count(array_filter(end($rows), fn($v) => $v !== null && trim((string) $v) !== '')) === 0) {
            array_pop($rows);
        }

        return $rows;
    }

    /**
     * Validates a single parsed masterlist row for the Faculty/Employee import,
     * mirroring StudentController's validateImportRow() pattern.
     */
    protected function validateImportRow(array $row, array $validRoles): array
    {
        $reasons = [];

        if (empty($row['last_name'])) {
            $reasons[] = 'Last Name is required.';
        }

        if (empty($row['first_name'])) {
            $reasons[] = 'First Name is required.';
        }

        if (empty($row['email'])) {
            $reasons[] = 'Email is required.';
        } elseif (!filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
            $reasons[] = 'Email address is invalid.';
        } elseif (($o = \App\Support\AccountEmail::mappedOwner($row['email'])) && $o['type'] === 'student') {
            // Staff-vs-staff matches are the normal "update this employee" case; a STUDENT's email can never be reused.
            $reasons[] = 'Email address is already used by a student account.';
        }

        if (empty($row['role'])) {
            $reasons[] = 'Role is required.';
        } elseif (!in_array($row['role'], $validRoles)) {
            $reasons[] = "Invalid role '{$row['role']}'.";
        }

        return $reasons;
    }

    /**
     * Hook for subclasses (FacultyController) to force/validate each row
     * before it is previewed or saved. Returns [row, extraReasons].
     */
    protected function scopeImportRow(array $row, ?User $existing): array
    {
        // The TMDU Head can only bring in TMDU Staff, and can only update
        // accounts that already are TMDU Staff - never another role's account
        // that happens to share an email.
        if (request()->user()?->isTMDUHead()) {
            $reasons = [];
            if (($row['role'] ?? '') === '') {
                $row['role'] = 'tmdu_staff';
            }
            if (($row['role'] ?? '') !== 'tmdu_staff') {
                $reasons[] = 'The TMDU Head can only upload TMDU Staff (role: tmdu_staff).';
            }
            if ($existing && $existing->role !== 'tmdu_staff') {
                $reasons[] = 'This email belongs to another account that the TMDU Head cannot change.';
            }
            return [$row, $reasons];
        }
        // B589: a Dean's masterlist upload is scoped to their own college,
        // the same way the single "+ Add Employee" form is (see store()/
        // update() above) - role itself is already limited to faculty/
        // dept_chair by assignableRoles() via validateImportRow().
        if (request()->user()?->isDean()) {
            $reasons = [];
            $row['college'] = request()->user()->college;
            if ($existing && $existing->college !== $row['college']) {
                $reasons[] = 'This email belongs to an account in another college that this Dean cannot change.';
            }
            return [$row, $reasons];
        }
        return [$row, []];
    }

    /**
     * Step 1 of the Faculty/Employee masterlist upload: parse + validate
     * every row up front and return a preview (new/duplicate/invalid) without
     * writing anything to the database yet. Mirrors
     * StudentController::importPreview().
     */
    public function importPreview(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls',
        ]);

        $file = $request->file('file');
        $ext  = strtolower($file->getClientOriginalExtension());
        $rows = $this->parseFile($file, $ext, $this->employeeHeaderMap());

        if (empty($rows)) {
            return response()->json(['preview' => [], 'total' => 0, 'duplicates' => 0, 'token' => '']);
        }

        $validRoles = $this->assignableRoles();

        $emails = collect($rows)
            ->pluck('email')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $existingEmails = User::whereIn('email', $emails)->pluck('email')->flip();

        $preview = [];
        $duplicateCount = 0;

        $existingUsers = User::whereIn('email', $emails)->get()->keyBy('email');

        // Duplicate checks against existing accounts (Employee ID and full name)
        // and within the uploaded file itself.
        $fileIds = collect($rows)->pluck('employee_id')->filter(fn($v) => trim((string) $v) !== '')->map(fn($v) => trim((string) $v))->unique()->values()->all();
        $byEmployeeId = User::whereIn('employee_id', $fileIds)->get()->keyBy('employee_id');
        $firstNames = collect($rows)->map(fn($r) => mb_strtolower(trim((string) ($r['first_name'] ?? ''))))->filter()->unique()->values()->all();
        $nameKey = fn($f, $l) => mb_strtolower(trim((string) $f)) . '|' . mb_strtolower(trim((string) $l));
        $byFullName = User::whereIn(\DB::raw('lower(first_name)'), $firstNames)->get()
            ->groupBy(fn($u) => $nameKey($u->first_name, $u->last_name));
        $seenEmail = []; $seenId = []; $seenName = [];

        foreach ($rows as $i => $row) {
            $row['role']  = strtolower(trim($row['role'] ?? ''));
            [$row, $scopeReasons] = $this->scopeImportRow($row, $existingUsers->get($row['email'] ?? ''));
            $reasons      = array_merge($this->validateImportRow($row, $validRoles), $scopeReasons);
            $isDuplicate  = !empty($row['email']) && isset($existingEmails[$row['email']]);

            $rowEmail = mb_strtolower(trim((string) ($row['email'] ?? '')));
            $rowId    = trim((string) ($row['employee_id'] ?? ''));
            $rowName  = $nameKey($row['first_name'] ?? '', $row['last_name'] ?? '');
            $sameEmailUser = $existingUsers->get($row['email'] ?? '');

            if ($rowId !== '' && ($idUser = $byEmployeeId->get($rowId)) && (!$sameEmailUser || $sameEmailUser->id !== $idUser->id)) {
                $reasons[] = "Duplicate Employee ID: {$rowId} already belongs to {$idUser->name}.";
            }
            if ($rowName !== '|' && ($nameUsers = $byFullName->get($rowName)) && !$nameUsers->contains(fn($u) => $sameEmailUser && $u->id === $sameEmailUser->id)) {
                $reasons[] = 'Possible duplicate: full name matches existing employee ' . $nameUsers->first()->name . ' (' . $nameUsers->first()->email . ').';
            }
            if ($rowEmail !== '' && isset($seenEmail[$rowEmail])) {
                $reasons[] = "Duplicate Email Address within the file (same as row {$seenEmail[$rowEmail]}).";
            }
            if ($rowId !== '' && isset($seenId[$rowId])) {
                $reasons[] = "Duplicate Employee ID within the file (same as row {$seenId[$rowId]}).";
            }
            if ($rowName !== '|' && isset($seenName[$rowName])) {
                $reasons[] = "Duplicate Full Name within the file (same as row {$seenName[$rowName]}).";
            }
            if ($rowEmail !== '')    { $seenEmail[$rowEmail] ??= $i + 2; }
            if ($rowId !== '')       { $seenId[$rowId] ??= $i + 2; }
            if ($rowName !== '|')    { $seenName[$rowName] ??= $i + 2; }

            if ($isDuplicate) {
                $duplicateCount++;

                // Protect existing admin/system_admin accounts: a bulk
                // masterlist import must never be able to change the role
                // of a privileged account (e.g. an admin's email accidentally
                // reused as a "faculty" row would otherwise silently demote
                // them). Flag it invalid so it can't be actioned as update.
                $existingUser = $existingUsers->get($row['email']);
                if ($existingUser && in_array($existingUser->role, ['admin', 'system_admin'], true)) {
                    $reasons[] = "This email belongs to a protected {$existingUser->role} account and cannot be modified by import.";
                }
            }

            $preview[] = [
                'row'          => $i + 2,
                'email'        => $row['email'] ?? '',
                'name'         => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
                'role'         => $row['role'] ?? '',
                'is_duplicate' => $isDuplicate,
                'valid'        => empty($reasons),
                'reasons'      => $reasons,
            ];
        }

        $token = uniqid('import_');
        cache()->put($token, $rows, now()->addMinutes(15));

        return response()->json([
            'token'      => $token,
            'preview'    => $preview,
            'total'      => count($rows),
            'duplicates' => $duplicateCount,
        ]);
    }

    /**
     * Step 2 of the Faculty/Employee masterlist upload: re-fetch the cached,
     * already-validated rows by token and actually create/update/skip each
     * one according to the admin's per-row decisions. Mirrors
     * StudentController::importConfirm().
     */
    public function importConfirm(Request $request)
    {
        $request->validate([
            'token'     => 'required|string',
            'decisions' => 'required|array',
        ]);

        $rows = cache()->get($request->token);

        if (!$rows) {
            return response()->json(['message' => 'Import session expired. Please upload the file again.'], 422);
        }

        $decisions  = $request->decisions;
        $validRoles = $this->assignableRoles();

        $emails   = collect($rows)->pluck('email')->filter()->unique()->values()->all();
        $existing = User::whereIn('email', $emails)->get()->keyBy('email');

        $created   = 0;
        $updated   = 0;
        $skipped   = 0;
        $errors    = [];
        $passwords = [];

        foreach ($rows as $i => $row) {
            $decision = $decisions[$i] ?? 'skip';

            if ($decision === 'skip') {
                $skipped++;
                continue;
            }

            $row['role'] = strtolower(trim($row['role'] ?? ''));
            [$row, $scopeReasons] = $this->scopeImportRow($row, $existing->get($row['email'] ?? ''));
            $reasons = array_merge($this->validateImportRow($row, $validRoles), $scopeReasons);
            if (!empty($reasons)) {
                $skipped++;
                $errors[] = 'Row ' . ($i + 2) . ': ' . implode(' ', $reasons);
                continue;
            }

            $existingUser = $existing->get($row['email']);

            // Never let a bulk import modify a protected admin/system_admin
            // account, even if the frontend sent an 'update' decision for it
            // (e.g. importPreview already flagged this row invalid).
            if ($existingUser && in_array($existingUser->role, ['admin', 'system_admin'], true)) {
                $skipped++;
                $errors[] = 'Row ' . ($i + 2) . ": email {$row['email']} belongs to a protected {$existingUser->role} account and was not modified.";
                continue;
            }

            $payload = [
                'first_name'     => $row['first_name'],
                'middle_name'    => $row['middle_name'] ?? null,
                'last_name'      => $row['last_name'],
                'suffix'         => $row['suffix'] ?? null,
                'name'           => trim($row['first_name'] . ' ' . $row['last_name']),
                'email'          => $row['email'],
                'role'           => $row['role'],
                'employee_id'    => $row['employee_id'] ?? null,
                'college'        => $row['college'] ?? null,
                'department'     => $row['department'] ?? null,
                'contact_number' => $row['contact_number'] ?? null,
            ];

                        try {
                if ($existingUser) {
                    // decision is 'update' (or 'create' re-hitting an existing
                    // email, which is also treated as an update to avoid a
                    // duplicate-email database error).
                    $existingUser->update(array_filter($payload, fn($v) => $v !== null && $v !== ''));
                    $updated++;
                } else {
                    $tempPassword = Str::random(10);
                    $payload['password']             = Hash::make($tempPassword);
                    // B643: hashed - the plaintext below is the only place this is shown.
                    $payload['temp_password']        = Hash::make($tempPassword);
                    $payload['must_change_password'] = true;
                    $payload['is_active']            = true;

                    $user = User::create($payload);

                    $passwords[] = [
                        'email'         => $user->email,
                        'name'          => $user->name,
                        'temp_password' => $tempPassword,
                    ];
                    $created++;
                }
            } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                // A duplicate value on some other unique column (e.g.
                // employee_id reused across rows or already taken by an
                // existing account) must not crash the whole batch - skip
                // just this row and report it.
                $skipped++;
                $errors[] = 'Row ' . ($i + 2) . ": could not save {$row['email']} - a unique field (such as Employee ID) is already in use.";
            }
        }

        cache()->forget($request->token);

        AuditLog::record('imported', "Bulk imported {$created} employees, updated {$updated}, skipped {$skipped}.");

        return response()->json([
            'created'   => $created,
            'updated'   => $updated,
            'skipped'   => $skipped,
            'errors'    => $errors,
            'passwords' => $passwords,
        ]);
    }
}