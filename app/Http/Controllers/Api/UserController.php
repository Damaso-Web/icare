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

    // Decision (11/3/2026): a Dean's college is forced everywhere below, but
    // nothing checked that the department they typed/uploaded actually
    // belongs to that college (e.g. a CTE Dean could end up with a faculty
    // record stamped College=CTE, Department="Computer Science"). Checked
    // against the real Department/College DB relationship (seeded by
    // ManagementDataSeeder, not a hardcoded list here) so this stays correct
    // if departments are ever added/renamed later. Shared with the subclass
    // FacultyController, whose own Dean-scoping goes through the same check.
    // B#, 11/4/2026: called once per row from scopeImportRow() during a Dean's
    // Faculty masterlist upload - with no caching, that meant up to 2 fresh
    // DB queries PER ROW (college lookup + department exists check). Fine
    // for one row on the "+ Add Employee" form, but on a real multi-hundred
    // or multi-thousand row masterlist that's thousands of extra remote DB
    // round trips in a single request, reported as uploads "taking a long
    // time". A Dean's own upload always forces the SAME college on every
    // row (scopeImportRow sets $row['college'] = the actor's own college,
    // never the file's), so the valid-department list for that college is
    // identical across every row - loaded and cached here once per college
    // name per request instead of once per row.
    protected static array $departmentsByCollegeCache = [];

    protected function departmentBelongsToCollege(?string $department, ?string $collegeName): bool
    {
        if (empty($department) || empty($collegeName)) {
            return false;
        }

        $cacheKey = strtolower(trim($collegeName));
        if (!array_key_exists($cacheKey, self::$departmentsByCollegeCache)) {
            $college = \App\Models\College::where('name', $collegeName)->first();
            // Nothing seeded to check against (e.g. test fixtures/legacy data
            // using a bare college name that doesn't match any real College
            // row) - don't block on a check we have no real data for.
            self::$departmentsByCollegeCache[$cacheKey] = (!$college || !$college->departments()->exists())
                ? true
                : $college->departments()->pluck('name')->map(fn($n) => strtolower(trim($n)))->all();
        }

        $cached = self::$departmentsByCollegeCache[$cacheKey];
        if ($cached === true) {
            return true;
        }
        return in_array(strtolower(trim($department)), $cached, true);
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

    // B#, 11/4/2026: lets the Add/Edit User form (User Management - staff
    // accounts, not Faculty/Students, which have their own equivalent
    // checks) check Employee ID, Name, and Email for a duplicate as the
    // admin types, before they submit - the same checks store()/update()
    // enforce below (employeeIdUnique(), UniqueAccountEmail), plus a name
    // check this form never had at all. Not scoped by college/department
    // (unlike Faculty's own checkDuplicate()) - User Management spans
    // every office (GCU, TMDU, SDU, admin), not one college.
    public function checkDuplicate(Request $request)
    {
        $employeeId = trim((string) $request->query('employee_id', ''));
        $firstName  = trim((string) $request->query('first_name', ''));
        $middleName = trim((string) $request->query('middle_name', ''));
        $lastName   = trim((string) $request->query('last_name', ''));
        $email      = trim((string) $request->query('email', ''));
        $ignoreId   = $request->query('ignore_id');

        $employeeIdExists = $employeeId !== '' && User::where('employee_id', $employeeId)
            ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        $nameExists = $firstName !== '' && $lastName !== '' && User::query()
            ->whereRaw('LOWER(first_name) = LOWER(?)', [$firstName])
            ->whereRaw('LOWER(last_name) = LOWER(?)', [$lastName])
            ->when($middleName !== '', fn($q) => $q->whereRaw('LOWER(middle_name) = LOWER(?)', [$middleName]))
            ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        $emailExists = $email !== '' && User::whereRaw('LOWER(email) = LOWER(?)', [$email])
            ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        return response()->json([
            'employee_id_exists' => $employeeIdExists,
            'name_exists'        => $nameExists,
            'email_exists'       => $emailExists,
        ]);
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

        // B#, 11/4/2026: matches Students' own page size (StudentController
        // already defaults to 10) instead of its own, different 20.
        return response()->json($query->paginate(10));
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
            // Only a Dean's department is actually unverified here - a plain
            // Admin picks college freely too, so there's nothing to check it
            // against (no college/actor relationship is being forced).
            if ($request->user()->isDean() && !$this->departmentBelongsToCollege($validated['department'], $validated['college'])) {
                abort(422, "'{$validated['department']}' is not a department under {$validated['college']}.");
            }
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
        if (array_key_exists('department', $validated) && !empty($validated['department'])
            && !$this->departmentBelongsToCollege($validated['department'], $user->college)) {
            abort(422, "'{$validated['department']}' is not a department under {$user->college}.");
        }
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

            // B#, 11/4/2026: same fix as importConfirm() below - one hash,
            // reused for both columns, at the reduced cost already
            // established for a bulk masterlist row (see the longer note on
            // importConfirm()'s version of this).
            $tempPassword = Str::random(10);
            $tempPasswordHash = Hash::make($tempPassword, ['rounds' => 4]);
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
                'password'              => $tempPasswordHash,
                // B643: hashed - the plaintext below is the only place this
                // is shown, same as a single created account.
                'temp_password'         => $tempPasswordHash,
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
        //
        // B#, 11/4/2026: this used to FORCE $row['college'] to the Dean's
        // own college unconditionally, no matter what the uploaded file
        // actually said. That meant uploading the wrong file - a different
        // college's masterlist, by mistake or otherwise - was silently
        // "corrected" instead of refused: every row got quietly relabeled
        // into this Dean's college, which could then fail the department
        // check below in confusing ways (or, worse, succeed and create
        // faculty records under the wrong college with no signal anything
        // was off). A safeguard that silently rewrites bad input instead of
        // rejecting it isn't really a safeguard. Reported directly: a CIS
        // masterlist uploaded to a CTE Dean's account came back relabeled
        // CTE for every row instead of being flagged.
        //
        // Now: a blank College column (the normal case - the official
        // template doesn't even include one, since every row is assumed to
        // already be this Dean's college) still defaults to the Dean's own
        // college, that's a convenience default, not a cross-college claim.
        // A College value that already matches the Dean's own (case/spacing
        // aside) is normalized to the canonical string, which isn't an
        // override either - it's the same college. Only a GENUINELY
        // different College value is rejected, and it is kept exactly as
        // the file wrote it (not silently swapped) so the preview shows the
        // real mismatch instead of a "fixed" value that hides it.
        if (request()->user()?->isDean()) {
            $reasons = [];
            $deanCollege = request()->user()->college;
            $fileCollege = trim((string) ($row['college'] ?? ''));

            if ($fileCollege === '' || strcasecmp($fileCollege, $deanCollege) === 0) {
                $row['college'] = $deanCollege;
            } else {
                $reasons[] = "This row's College ('{$fileCollege}') does not match your own college ({$deanCollege}). A Dean can only upload faculty for their own college - double-check this is the right file.";
                return [$row, $reasons];
            }

            if ($existing && $existing->college !== $row['college']) {
                $reasons[] = 'This email belongs to an account in another college that this Dean cannot change.';
            }
            // Department isn't required here (an existing upload flow relies
            // on that), but if one WAS given, it must actually belong to the
            // Dean's own college.
            if (!empty($row['department']) && !$this->departmentBelongsToCollege($row['department'], $row['college'])) {
                $reasons[] = "'{$row['department']}' is not a department under {$row['college']}.";
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

        // B#, 11/4/2026: uploading a STUDENT masterlist here (wrong page)
        // used to just fail every single row with "Role is required" - true,
        // but not useful, since the real problem is "wrong file", not
        // "missing data". employeeHeaderMap() has no mapping for Student-only
        // columns (Student ID, Year Level, Section, Guardian ...), so they
        // pass through parseFile() unmapped, under their own raw header text
        // - if a couple of those show up as keys and not one row has Role
        // filled in, that's the Student template, not this one. Caught here,
        // before generating a wall of per-row "Role is required" errors.
        $studentOnlyMarkers = ['student id', 'year level', 'section', 'guardian last name', 'guardian first name'];
        $hasStudentMarkers = count(array_intersect($studentOnlyMarkers, array_keys($rows[0] ?? []))) >= 2;
        $anyRoleFilled = collect($rows)->contains(fn($r) => trim((string) ($r['role'] ?? '')) !== '');
        if ($hasStudentMarkers && !$anyRoleFilled) {
            return response()->json([
                'message' => 'This file looks like a Student masterlist, not an Employee masterlist - it has Student-only columns (Student ID, Year Level, etc.) and no Role column. Please upload it from the Students page instead.',
            ], 422);
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
        // B#, 11/4/2026: this used to key on first+last only, with no middle
        // name - two genuinely different employees who happen to share a
        // common first+last name combination (not unusual with common
        // Filipino names on a large masterlist) got hard-blocked as
        // "Possible duplicate" against each other, reported as "any part of
        // their full name" triggering a false match. Middle name is now
        // part of the key, same fix as StudentController::importPreview()'s
        // equivalent name_matches logic - two rows/records only match when
        // all three agree.
        $nameKey = fn($f, $m, $l) => mb_strtolower(trim((string) $f)) . '|' . mb_strtolower(trim((string) $m)) . '|' . mb_strtolower(trim((string) $l));
        $byFullName = User::whereIn(\DB::raw('lower(first_name)'), $firstNames)->get()
            ->groupBy(fn($u) => $nameKey($u->first_name, $u->middle_name, $u->last_name));
        $seenEmail = []; $seenId = []; $seenName = [];

        foreach ($rows as $i => $row) {
            $row['role']  = strtolower(trim($row['role'] ?? ''));
            [$row, $scopeReasons] = $this->scopeImportRow($row, $existingUsers->get($row['email'] ?? ''));
            $reasons      = array_merge($this->validateImportRow($row, $validRoles), $scopeReasons);
            $isDuplicate  = !empty($row['email']) && isset($existingEmails[$row['email']]);

            $rowEmail = mb_strtolower(trim((string) ($row['email'] ?? '')));
            $rowId    = trim((string) ($row['employee_id'] ?? ''));
            $rowName  = $nameKey($row['first_name'] ?? '', $row['middle_name'] ?? '', $row['last_name'] ?? '');
            $sameEmailUser = $existingUsers->get($row['email'] ?? '');

            if ($rowId !== '' && ($idUser = $byEmployeeId->get($rowId)) && (!$sameEmailUser || $sameEmailUser->id !== $idUser->id)) {
                $reasons[] = "Duplicate Employee ID: {$rowId} already belongs to {$idUser->name}.";
            }
            if ($rowName !== '||' && ($nameUsers = $byFullName->get($rowName)) && !$nameUsers->contains(fn($u) => $sameEmailUser && $u->id === $sameEmailUser->id)) {
                $reasons[] = 'Possible duplicate: full name matches existing employee ' . $nameUsers->first()->name . ' (' . $nameUsers->first()->email . ').';
            }
            if ($rowEmail !== '' && isset($seenEmail[$rowEmail])) {
                $reasons[] = "Duplicate Email Address within the file (same as row {$seenEmail[$rowEmail]}).";
            }
            if ($rowId !== '' && isset($seenId[$rowId])) {
                $reasons[] = "Duplicate Employee ID within the file (same as row {$seenId[$rowId]}).";
            }
            if ($rowName !== '||' && isset($seenName[$rowName])) {
                $reasons[] = "Duplicate Full Name within the file (same as row {$seenName[$rowName]}).";
            }
            if ($rowEmail !== '')    { $seenEmail[$rowEmail] ??= $i + 2; }
            if ($rowId !== '')       { $seenId[$rowId] ??= $i + 2; }
            if ($rowName !== '||')   { $seenName[$rowName] ??= $i + 2; }

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
                // B#, 11/4/2026: kept alongside first/middle/last below for
                // anything still reading the combined form - the preview
                // table itself now shows the three separately (see
                // users/Index.vue and faculty/Index.vue) so staff can tell
                // apart two people who share a first+last name.
                'name'         => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
                'first_name'   => $row['first_name'] ?? '',
                'middle_name'  => $row['middle_name'] ?? '',
                'last_name'    => $row['last_name'] ?? '',
                'role'         => $row['role'] ?? '',
                // Added so the preview table can show what college/department
                // each row will actually be saved with - scopeImportRow()
                // above may have forced or rejected these, so this reflects
                // the real outcome, not just what the uploaded file said.
                'college'      => $row['college'] ?? '',
                'department'   => $row['department'] ?? '',
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
        // B#, 11/4/2026: new rows used to go through User::create() one at a
        // time - each one its own INSERT round-trip to the (remote, Aiven)
        // database. That's fine for a handful of rows, but a 2500-row Faculty/
        // Employee masterlist meant 2500 separate round-trips, which is what
        // made clicking "Confirm" on a big upload feel like it hung. New rows
        // are now collected here and bulk-inserted in chunks after the loop
        // (StudentController::importConfirm() already does this). Existing
        // rows still go through ->update() one at a time below - updates are
        // normally a small minority of any upload, so that round-trip cost
        // isn't the bottleneck the way thousands of new rows is.
        $inserts   = [];
        $now       = now();

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

            if ($existingUser) {
                // decision is 'update' (or 'create' re-hitting an existing
                // email, which is also treated as an update to avoid a
                // duplicate-email database error).
                $existingUser->update(array_filter($payload, fn($v) => $v !== null && $v !== ''));
                $updated++;
                continue;
            }

            // B#, 11/4/2026: this used to call Hash::make() twice at the
            // full ~10-12 round cost for every new row - 300 new
            // employees meant 600 full-cost bcrypt hashes, which is what
            // made a big masterlist upload take minutes. password and
            // temp_password are hashes of the exact same random string,
            // so hash it once and reuse the result for both columns.
            // Cost is also dropped to 4 rounds, same as
            // StudentController::importConfirm()'s already-established
            // fix for the same slowdown on the Student masterlist: this
            // hash only ever needs to verify ONE login, because
            // must_change_password forces a real (full-cost) password
            // immediately after, so a cheaper cost here trades nothing
            // meaningful for the huge speedup on a big upload.
            $tempPassword = Str::random(10);
            $tempPasswordHash = Hash::make($tempPassword, ['rounds' => 4]);
            // Raw insert() below bypasses Eloquent events, so the 'name'
            // computed by User::booted()'s saving() listener (first + middle
            // + last + suffix, trimmed and filtered) is reproduced here by
            // hand, rather than the first+last-only version in $payload above
            // (kept as-is for the ->update() path, where the listener still
            // runs and recomputes it anyway).
            $inserts[] = array_merge($payload, [
                'name'                  => implode(' ', array_filter(
                    array_map('trim', [$row['first_name'] ?? '', $row['middle_name'] ?? '', $row['last_name'] ?? '', $row['suffix'] ?? '']),
                    'strlen'
                )),
                'password'              => $tempPasswordHash,
                // B643: hashed - the plaintext below is the only place this is shown.
                'temp_password'         => $tempPasswordHash,
                'must_change_password'  => true,
                'is_active'             => true,
                'created_at'            => $now,
                'updated_at'            => $now,
            ]);
            $passwords[] = [
                'email'         => $row['email'],
                'name'          => $inserts[array_key_last($inserts)]['name'],
                'temp_password' => $tempPassword,
            ];
            $created++;
        }

        // Bulk insert - one query per 500 rows instead of one per row. A
        // chunk that fails (e.g. two rows in this same file share an
        // Employee ID neither of us caught yet) falls back to inserting
        // that chunk one row at a time, so only the actual offending row(s)
        // are skipped instead of losing the whole chunk.
        if (!empty($inserts)) {
            foreach (array_chunk($inserts, 500) as $chunk) {
                try {
                    \Illuminate\Support\Facades\DB::transaction(function () use ($chunk) {
                        User::insert($chunk);
                    });
                } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                    foreach ($chunk as $insertRow) {
                        try {
                            User::insert([$insertRow]);
                        } catch (\Illuminate\Database\UniqueConstraintViolationException $e2) {
                            $created--;
                            $skipped++;
                            $errors[] = "Could not save {$insertRow['email']} - a unique field (such as Employee ID) is already in use.";
                            $passwords = array_values(array_filter($passwords, fn($p) => $p['email'] !== $insertRow['email']));
                        }
                    }
                }
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