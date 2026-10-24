<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Referral;
use App\Models\Student;
use App\Models\StudentStatusEvent;
use App\Support\StudentAccountReasons;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class StudentController extends Controller
{
    // Shared character-restriction patterns for plain <input> fields (never
    // applied to free-text <textarea>-backed fields like notes/remarks,
    // which need normal punctuation). Letters include basic Latin-1/Latin
    // Extended-A so accented names (e.g. "Peña", "Dela Cruz") still work.
    private const NAME_REGEX  = '/^[a-zA-Z\x{00C0}-\x{024F}\'\-\.\s]+$/u';
    private const TEXT_REGEX  = '/^[a-zA-Z0-9\x{00C0}-\x{024F}\'\"\-\.\,\&\(\)\/\#\:\;\!\?\@\%\+\_\s]+$/u';
    private const PHONE_REGEX = '/^[0-9\+\-\s]+$/';
    private const ID_REGEX    = '/^[A-Za-z0-9\-]+$/';

    public function index(Request $request)
    {
        // "name_only" is opt-in: the Complaint form's complainee search passes
        // this so staff can't look a student up (or see anyone else's match)
        // by student ID there. Every other caller (Students list, Referral
        // Create, etc.) is unaffected and still searches by ID too.
        $nameOnly = filter_var($request->name_only, FILTER_VALIDATE_BOOLEAN);

        $query = Student::query()
            ->when($request->search, function ($q) use ($request, $nameOnly) {
                $terms = preg_split('/\s+/', trim($request->search), -1, PREG_SPLIT_NO_EMPTY);
                $q->where(function ($outer) use ($terms, $nameOnly) {
                    foreach ($terms as $term) {
                        $outer->where(function ($t) use ($term, $nameOnly) {
                            $t->where('first_name', 'like', "%{$term}%")
                              ->orWhere('middle_name', 'like', "%{$term}%")
                              ->orWhere('last_name', 'like', "%{$term}%")
                              ->when(!$nameOnly, fn($iq) => $iq->orWhere('student_id', 'like', "%{$term}%"));
                        });
                    }
                });
            })
            // B590: a Dean's student lookup (Refer Student, File a Complaint)
            // is scoped to their own college - they act for their college,
            // not the whole university, so any ?college= they pass is
            // ignored in favor of their own. (A real cross-college referral/
            // complaint still needs its own approval workflow - that's a
            // separate feature and isn't built here; this only closes the
            // "search any student" gap.)
            ->when($request->user()?->isDean(), fn($q) => $q->whereIn('college', \App\Support\CollegeName::equivalents($request->user()->college)))
            // Bug (10/14/2026): same reasoning as the Dean's college scope
            // above, one level further - a Dept Chair's lookup (Refer a
            // Student, File a Complaint) is scoped to their own department,
            // so any ?college=/?program= they pass is ignored in favor of it.
            ->when($request->user()?->isDeptChair(), fn($q) => $q
                ->whereIn('college', \App\Support\CollegeName::equivalents($request->user()->college))
                ->whereRaw('LOWER(program) = LOWER(?)', [(string) $request->user()->department]))
            // Bug (10/25/2026, B626): a plain Faculty member's student lookup
            // (the "+ Refer Student" search) could search and select ANY
            // student university-wide - same reasoning as the Dean (B590)
            // and Dept Chair scopes above, one level further down: Faculty
            // is scoped to their own department within their own college.
            ->when($request->user()?->role === 'faculty', fn($q) => $q
                ->whereIn('college', \App\Support\CollegeName::equivalents($request->user()->college))
                ->whereRaw('LOWER(program) = LOWER(?)', [(string) $request->user()->department]))
            ->when(!$request->user()?->isDean() && !$request->user()?->isDeptChair() && $request->user()?->role !== 'faculty' && $request->college, fn($q) => $q->where('college', $request->college))
            ->when($request->year_level, fn($q) => $q->where('year_level', $request->year_level))
            ->when($request->has('is_active'), fn($q) => $q->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN)));

        $sortBy  = in_array($request->sort_by, ['created_at', 'student_id', 'last_name']) ? $request->sort_by : 'created_at';
        $sortDir = $request->sort_dir === 'asc' ? 'asc' : 'desc';
        if ($sortBy === 'last_name') {
            $query->orderBy('last_name', $sortDir)->orderBy('first_name', $sortDir);
        } else {
            $query->orderBy($sortBy, $sortDir);
        }

        $results = $query->paginate($request->per_page ?? 10);

        if ($nameOnly) {
            $results->getCollection()->transform(fn($s) => collect($s->toArray())->except('student_id')->all());
        }

        return response()->json($results);
    }

    public function store(Request $request)
    {
        // No two accounts (student or staff) may share an email address.
        if ($request->filled('email') && \App\Support\AccountEmail::takenByOther($request->email)) {
            return response()->json(['message' => \App\Support\AccountEmail::MESSAGE, 'errors' => ['email' => [\App\Support\AccountEmail::MESSAGE]]], 422);
        }

        $validated = $request->validate([
            'student_id'             => ['required', 'string', 'regex:' . self::ID_REGEX, 'unique:students,student_id'],
            'first_name'             => ['required', 'string', 'min:2', 'max:255', 'regex:' . self::NAME_REGEX],
            'last_name'              => ['required', 'string', 'min:2', 'max:255', 'regex:' . self::NAME_REGEX],
            'middle_name'            => ['nullable', 'string', 'min:2', 'max:255', 'regex:' . self::NAME_REGEX],
            'suffix'                 => ['nullable', 'string', 'max:20', 'regex:' . self::NAME_REGEX],
            'sex'                    => 'nullable|in:Male,Female,Prefer not to say',
            'email'                  => 'nullable|email|regex:/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/',
            'contact_number'         => ['nullable', 'string', 'max:11', 'regex:' . self::PHONE_REGEX],
            'college'                => 'nullable|string',
            'program'                => 'nullable|string',
            'year_level'             => 'nullable|string',
            'section'                => ['nullable', 'string', 'max:1', 'regex:' . self::ID_REGEX],
            'guardian_first_name'    => ['nullable', 'string', 'min:2', 'max:255', 'regex:' . self::NAME_REGEX],
            'guardian_middle_name'   => ['nullable', 'string', 'min:2', 'max:255', 'regex:' . self::NAME_REGEX],
            'guardian_last_name'     => ['nullable', 'string', 'min:2', 'max:255', 'regex:' . self::NAME_REGEX],
            'guardian_contact'       => ['nullable', 'string', 'max:11', 'regex:' . self::PHONE_REGEX],
            'guardian_relationship'  => ['nullable', 'string', 'max:255', 'regex:' . self::NAME_REGEX],
        ]);

        $tempPassword = Str::random(10);

        $student = Student::create([
            ...$validated,
            'password'              => Hash::make($tempPassword),
            'temp_password'         => $tempPassword,
            'must_change_password'  => true,
            'is_active'             => true,
        ]);

        AuditLog::record('created', "Created student account {$student->student_id} ({$student->first_name} {$student->last_name}). Created by " . ($request->user()->name ?? 'unknown') . '.', $student);

        return response()->json([
            ...$student->toArray(),
            'temp_password' => $tempPassword,
        ], 201);
    }

    // TMDU may open a student's record only when that student has been referred
    // to TMDU: a TMDU-owned referral, a referral for psychological testing (even
    // before its testing record exists), a testing record, a TMDU appointment,
    // or a case that is with TMDU or was handed to it.
    private function authorizeTmduScope(Student $student): void
    {
        $user = request()->user();
        if (!$user?->isTMDUStaff()) return;

        $referred = $student->referrals()->tmduOwned()->exists()
            || \App\Models\TestingRecord::where('student_id', $student->id)->exists()
            || $student->referrals()->where('referral_type', 'psychological_testing')->exists()
            || $student->appointments()->where('unit', 'TMDU')->exists()
            || \App\Models\CaseFile::where('student_id', $student->id)
                ->where(fn ($q) => $q->where('current_unit', 'TMDU')->orWhereHas('handoffs', fn ($h) => $h->where('to_unit', 'TMDU')))
                ->exists();

        if (!$referred) {
            abort(403, 'This student has not been referred to TMDU.');
        }
    }

    public function show(Student $student)
    {
        $this->authorizeTmduScope($student);
        return response()->json($student);
    }

    public function update(Request $request, Student $student)
    {
        $validated = $request->validate([
            'student_id'             => ['sometimes', 'required', 'string', 'regex:' . self::ID_REGEX, 'unique:students,student_id,' . $student->id],
            'first_name'             => ['sometimes', 'required', 'string', 'min:2', 'max:255', 'regex:' . self::NAME_REGEX],
            'last_name'              => ['sometimes', 'required', 'string', 'min:2', 'max:255', 'regex:' . self::NAME_REGEX],
            'middle_name'            => ['nullable', 'string', 'min:2', 'max:255', 'regex:' . self::NAME_REGEX],
            'suffix'                 => ['nullable', 'string', 'max:20', 'regex:' . self::NAME_REGEX],
            'sex'                    => 'nullable|in:Male,Female,Prefer not to say',
            'email'                  => 'nullable|email|regex:/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/',
            'contact_number'         => ['nullable', 'string', 'max:11', 'regex:' . self::PHONE_REGEX],
            'college'                => 'sometimes|required|string',
            'program'                => 'nullable|string',
            'year_level'             => 'sometimes|required|string',
            'section'                => ['nullable', 'string', 'max:1', 'regex:' . self::ID_REGEX],
            'guardian_first_name'    => ['nullable', 'string', 'min:2', 'max:255', 'regex:' . self::NAME_REGEX],
            'guardian_middle_name'   => ['nullable', 'string', 'min:2', 'max:255', 'regex:' . self::NAME_REGEX],
            'guardian_last_name'     => ['nullable', 'string', 'min:2', 'max:255', 'regex:' . self::NAME_REGEX],
            'guardian_contact'       => ['nullable', 'string', 'max:11', 'regex:' . self::PHONE_REGEX],
            'guardian_relationship'  => ['nullable', 'string', 'max:255', 'regex:' . self::NAME_REGEX],
        ]);

        // Nullable fields only ever mean "leave as-is" when submitted blank - an
        // edit form re-sends the whole record, so an empty value here is never a
        // deliberate clear, just a field the admin didn't touch.
        foreach ([
            'middle_name', 'suffix', 'sex', 'email', 'contact_number',
            'college', 'program', 'year_level', 'section',
            'guardian_first_name', 'guardian_middle_name', 'guardian_last_name',
            'guardian_contact', 'guardian_relationship',
        ] as $optionalField) {
            if (array_key_exists($optionalField, $validated) && $validated[$optionalField] === '') {
                unset($validated[$optionalField]);
            }
        }

        // Student ID, email and sex identify the account: after it is created
        // only the Admin may change them.
        if ($request->user()->role !== 'admin') {
            foreach (['student_id' => 'Student ID', 'email' => 'email address', 'sex' => 'sex'] as $field => $label) {
                if (array_key_exists($field, $validated) && (string) $validated[$field] !== (string) $student->{$field}) {
                    abort(403, "Only the Admin can change a student's {$label}.");
                }
            }
        }

        // A changed email must not belong to another account (student or staff).
        if (isset($validated['email']) && $validated['email'] !== $student->email
            && \App\Support\AccountEmail::takenByOther($validated['email'], 'student', $student->id)) {
            return response()->json(['message' => \App\Support\AccountEmail::MESSAGE, 'errors' => ['email' => [\App\Support\AccountEmail::MESSAGE]]], 422);
        }

        $old = $student->toArray();
        $student->update($validated);

        AuditLog::record('updated', "Updated student profile for {$student->first_name} {$student->last_name}.", $student, $old, $student->toArray());

        return response()->json($student);
    }

    public function destroy(Student $student)
    {
        $student->delete();
        AuditLog::record('deleted', "Deleted student profile for {$student->first_name} {$student->last_name}.");
        return response()->json(['message' => 'Student deleted.']);
    }

    /** GCU Staff / GCU Head: remind a student to fill out the forms in My Account. */
    public function remindForms(Student $student)
    {
        if (!$student->is_active) {
            return response()->json(['message' => 'This student account is deactivated, so it cannot receive the reminder.'], 422);
        }

        $student->notify(new \App\Notifications\FillOutFormsNotification(reminder: true));
        AuditLog::record('reminder_sent', "Reminded student {$student->student_id} to fill out the forms in My Account.", $student);

        return response()->json(['message' => 'The student has been notified to fill out the forms in My Account.']);
    }

    /**
     * Reactivates a student account. A reason is required (B414/B415); the
     * original deactivation reason is kept in the history and the Audit Trail.
     */
    public function toggleActive(Request $request, Student $student)
    {
        abort_if($student->is_active, 422, 'This account is already active. Use Deactivate to turn it off.');

        $validated = $request->validate([
            'reason' => ['required', \Illuminate\Validation\Rule::in(array_keys(StudentAccountReasons::REACTIVATE))],
            'note'   => ['nullable', 'string', 'max:500', 'required_if:reason,other', 'regex:/\p{L}/u'],
        ], [
            'reason.required'  => 'Please select a reason for reactivation.',
            'note.required_if' => 'Please describe the reason when "Other" is selected.',
            'note.regex'       => 'Please explain in words, not numbers only.',
        ]);

        $label = StudentAccountReasons::reactivateLabel($validated['reason']);
        $note  = isset($validated['note']) ? trim($validated['note']) : null;
        $note  = $note === '' ? null : $note;
        $actor = $request->user();

        $originalLabel = $student->deactivation_reason ? StudentAccountReasons::deactivateLabel($student->deactivation_reason) : null;
        $originalNote  = $student->deactivation_notes;
        $originalAt    = $student->deactivated_at;

        // Every reactivation requires a new password at the next login (enforced by the
        // password.fresh middleware). The password itself is never logged or shown.
        $student->update(['is_active' => true, 'must_change_password' => true]);
        $student->tokens()->delete();

        StudentStatusEvent::create([
            'student_id'            => $student->id,
            'action'                => 'reactivated',
            'reason_key'            => $validated['reason'],
            'reason_label'          => $label,
            'note'                  => $note,
            'previous_reason_label' => $originalLabel,
            'previous_at'           => $originalAt,
            'actor_id'              => $actor->id,
            'actor_name'            => $actor->name,
            'created_at'            => now(),
        ]);

        $text = "Reactivated student {$student->student_id} ({$student->first_name} {$student->last_name}). "
            . ($originalLabel ? "Originally deactivated: {$originalLabel}" . ($originalAt ? ' on ' . $originalAt->format('M j, Y') : '') . ($originalNote ? " ({$originalNote})" : '') . '. ' : '')
            . "Reason for reactivation: {$label}" . ($note ? " - {$note}" : '') . '.'
            . ' A password change is required at the next login.';
        AuditLog::record('reactivated', $text, $student);

        return response()->json($student->fresh());
    }

    /** Deactivation / reactivation timeline of one student, newest first. */
    public function statusHistory(Student $student)
    {
        return response()->json(
            StudentStatusEvent::where('student_id', $student->id)->orderByDesc('id')->get()
        );
    }

    public function graduate(Request $request, Student $student)
    {
        $validated = $request->validate([
            'deactivation_reason' => ['required', \Illuminate\Validation\Rule::in(array_merge(array_keys(StudentAccountReasons::DEACTIVATE), array_keys(StudentAccountReasons::LEGACY)))],
            'deactivation_notes'  => ['nullable', 'string', 'max:255', 'required_if:deactivation_reason,other', 'regex:' . self::TEXT_REGEX],
        ], [
            'deactivation_reason.required' => 'Please select a reason for deactivation.',
            'deactivation_notes.required_if' => 'Please describe the reason when "Other" is selected.',
        ]);

        // B303: deactivating a student also closes their SIF, even when the case
        // is still open or has an ongoing referral - this is the second way (besides
        // the case status dropdown) to close a SIF.
        $closedCases = $student->cases()->whereNotIn('status', ['closed', 'resolved'])->get();
        foreach ($closedCases as $case) {
            $case->update(['status' => 'closed', 'closed_date' => today()]);
            AuditLog::record('closed', "Closed case {$case->case_number} because the student was deactivated.", $case);
        }

        $label = StudentAccountReasons::deactivateLabel($validated['deactivation_reason']);
        $note  = isset($validated['deactivation_notes']) ? trim($validated['deactivation_notes']) : null;
        $note  = $note === '' ? null : $note;
        $actor = $request->user();

        $student->update([
            'is_active'            => false,
            'deactivation_reason'  => $validated['deactivation_reason'],
            'deactivation_notes'   => $note,
            'deactivated_at'       => now(),
        ]);

        StudentStatusEvent::create([
            'student_id'   => $student->id,
            'action'       => 'deactivated',
            'reason_key'   => $validated['deactivation_reason'],
            'reason_label' => $label,
            'note'         => $note,
            'actor_id'     => $actor->id,
            'actor_name'   => $actor->name,
            'created_at'   => now(),
        ]);

        AuditLog::record('graduated', "Deactivated student {$student->student_id} ({$student->first_name} {$student->last_name}). Reason: {$label}" . ($note ? " - {$note}" : '') . '.', $student);
        Referral::archiveActive(['student_id' => $student->id], 'student account deactivated');
        return response()->json($student);
    }

        public function history(Student $student)
    {
        // The Student Profile page always loads this, and it is what returns
        // the student's referrals, cases, appointments and testing records -
        // so this is where opening a profile is logged (once per open).
        AuditLog::record('viewed', "Viewed student profile and records for {$student->first_name} {$student->last_name} ({$student->student_id}).", $student);

        $user = request()->user();
        $this->authorizeTmduScope($student);

        // TMDU works from Testing Records and its own Case Referral Slips -
        // never the SIF (cases) or GCU's referrals/appointments. GCU in turn
        // never sees TMDU's referrals, appointments or testing records.
        if ($user?->isTMDUStaff()) {
            return response()->json([
                'cases'           => [],
                'referrals'       => $student->referrals()->tmduOwned()->with('case:id,status')->latest()->get(),
                'appointments'    => $student->appointments()->where('unit', 'TMDU')->with('staff')->latest()->get(),
                'testing_records' => \App\Models\TestingRecord::where('student_id', $student->id)->latest()->get(),
            ]);
        }

        return response()->json([
            'cases'           => $student->cases()->with(['counselor', 'previousCounselor'])->latest()->get(),
            'referrals'       => $student->referrals()->notTmduOwned()->with('case:id,status')->latest()->get(),
            'appointments'    => $student->appointments()->where('unit', '!=', 'TMDU')->with('staff')->latest()->get(),
            'testing_records' => [],
        ]);
    }

    // Admin-only: view current temp password if the student hasn't changed it yet
    public function viewTempPassword(Student $student)
    {
        if (!$student->must_change_password || !$student->temp_password) {
            return response()->json(['message' => 'This student has already set their own password.'], 404);
        }

        return response()->json(['temp_password' => $student->temp_password]);
    }

    // Last resort for a student who cannot use "Forgot password": needs a reason from the list.
    public function resetPassword(Request $request, Student $student)
    {
        abort_if(!$student->is_active, 422, 'This account is deactivated. Reactivate it before resetting the password.');
        $newPassword = \App\Support\PasswordReset::perform($request, $student, 'student');

        return response()->json([
            'message'       => 'Password reset successfully.',
            'temp_password' => $newPassword,
        ]);
    }

    public function passwordInfo(Student $student)
    {
        return response()->json(['name' => trim($student->first_name . ' ' . $student->last_name)] + \App\Support\PasswordReset::info($student));
    }

    // ==========================
    // Bulk Import (direct, no preview)
    // ==========================
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls',
        ]);

        $file = $request->file('file');
        $ext  = strtolower($file->getClientOriginalExtension());
        $headerMap = $this->studentHeaderMap();
        $rows = $this->parseFile($file, $ext, $headerMap);

        $created = 0;
        $skipped = 0;
        $errors  = [];
        $rowNum  = 1;

        foreach ($rows as $rowData) {
            $rowNum = $rowData['__row'] ?? $rowNum + 1;

            if (empty($rowData['student_id']) || empty($rowData['first_name']) || empty($rowData['last_name'])) {
                $errors[] = "Row {$rowNum}: missing required fields (Student ID, First Name, Last Name).";
                $skipped++;
                continue;
            }

            $exists = Student::where('student_id', $rowData['student_id'])->exists();
            if ($exists) {
                $errors[] = "Row {$rowNum}: Student ID {$rowData['student_id']} already exists - skipped.";
                $skipped++;
                continue;
            }

            if (!empty($rowData['email']) && \App\Support\AccountEmail::takenByOther($rowData['email'])) {
                $errors[] = "Row {$rowNum}: email {$rowData['email']} is already used by another account - skipped.";
                $skipped++;
                continue;
            }

            Student::create([
                'student_id'             => $rowData['student_id'],
                'first_name'             => $rowData['first_name'],
                'last_name'              => $rowData['last_name'],
                'middle_name'            => $rowData['middle_name'] ?? null,
                'suffix'                 => $rowData['suffix'] ?? null,
                'sex'                    => $rowData['sex'] ?? null,
                'email'                  => $rowData['email'] ?? null,
                'contact_number'         => $rowData['contact_number'] ?? null,
                'college'                => $rowData['college'] ?? null,
                'program'                => $rowData['program'] ?? null,
                'year_level'             => $rowData['year_level'] ?? null,
                'section'                => $rowData['section'] ?? null,
                'guardian_first_name'    => $rowData['guardian_first_name'] ?? null,
                'guardian_middle_name'   => $rowData['guardian_middle_name'] ?? null,
                'guardian_last_name'     => $rowData['guardian_last_name'] ?? null,
                'guardian_contact'       => $rowData['guardian_contact'] ?? null,
                'guardian_relationship'  => $rowData['guardian_relationship'] ?? null,
                'is_active'              => true,
            ]);
            $created++;
        }

        AuditLog::record('imported', "Bulk imported {$created} students, skipped {$skipped}.");

        return response()->json([
            'created' => $created,
            'skipped' => $skipped,
            'errors'  => $errors,
        ]);
    }

    // Bulk Import with Preview + Duplicate handling
    public function importPreview(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls',
        ]);

        $file = $request->file('file');
        $ext  = strtolower($file->getClientOriginalExtension());
        $headerMap = $this->studentHeaderMap();
        $rows = $this->parseFile($file, $ext, $headerMap);

        $colleges = [
            'College of Agriculture (CA)',
            'College of Arts and Humanities (CAH)',
            'College of Engineering (CE)',
            'College of Forestry (CF)',
            'College of Human Ecology (CHE)',
            'College of Human Kinetics (CHK)',
            'College of Information Sciences (CIS)',
            'College of Medicine (CM)',
            'College of Natural Sciences (CNS)',
            'College of Numeracy and Applied Sciences (CNAS)',
            'College of Nursing (CN)',
            'College of Public Administration and Governance (CPAG)',
            'College of Social Sciences (CSS)',
            'College of Teacher Education (CTE)',
            'College of Veterinary Medicine (CVM)',
        ];

        // ONE query: fetch every existing student_id that appears in the file
        $existingIds = collect($rows)
            ->pluck('student_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $existingSet = empty($existingIds)
            ? collect()
            : Student::whereIn('student_id', $existingIds)->pluck('student_id')->flip();

        // Students already in the system with the same first and last name as a
        // row (ignoring case), so a re-typed ID can't quietly create a twin (B410).
        $nameKey = fn($first, $last) => strtolower(trim((string) $first)) . '|' . strtolower(trim((string) $last));
        $fileNames = collect($rows)->map(fn($r) => $nameKey($r['first_name'] ?? '', $r['last_name'] ?? ''))->filter(fn($k) => $k !== '|')->unique()->values();
        // Narrowed with LOWER/TRIM (supported by both MySQL and the sqlite
        // connection used in tests) instead of CONCAT, which sqlite lacks;
        // the exact pairing is then matched in PHP via nameKey/only().
        $firstNamesLower = collect($rows)->map(fn($r) => strtolower(trim((string) ($r['first_name'] ?? ''))))->filter()->unique()->values();
        $lastNamesLower  = collect($rows)->map(fn($r) => strtolower(trim((string) ($r['last_name'] ?? ''))))->filter()->unique()->values();
        $byName = $fileNames->isEmpty() ? collect() : Student::whereIn(DB::raw('LOWER(TRIM(first_name))'), $firstNamesLower->all())
            ->whereIn(DB::raw('LOWER(TRIM(last_name))'), $lastNamesLower->all())
            ->get(['student_id', 'first_name', 'last_name'])
            ->groupBy(fn($st) => $nameKey($st->first_name, $st->last_name))
            ->only($fileNames->all());

        $preview = [];
        $seenEmails = [];
        foreach ($rows as $i => $rowData) {
            $isDuplicate = !empty($rowData['student_id']) && $existingSet->has($rowData['student_id']);
            $reasons = $this->validateImportRow($rowData, $colleges);
            // The same email twice in one file.
            $em = strtolower(trim((string) ($rowData['email'] ?? '')));
            if ($em !== '' && isset($seenEmails[$em])) {
                $reasons[] = "Email Address is repeated in the file (same as row {$seenEmails[$em]})";
            }
            if ($em !== '') { $seenEmails[$em] ??= $rowData['__row'] ?? ($i + 2); }
            $nameMatches = $isDuplicate ? [] : ($byName[$nameKey($rowData['first_name'] ?? '', $rowData['last_name'] ?? '')] ?? collect())
                ->filter(fn($st) => $st->student_id !== ($rowData['student_id'] ?? null))
                ->map(fn($st) => ['student_id' => $st->student_id, 'name' => trim($st->first_name . ' ' . $st->last_name)])
                ->values()->all();

            $preview[] = [
                'name_matches' => $nameMatches,
                'row'          => $rowData['__row'] ?? ($i + 2),
                'student_id'   => $rowData['student_id'] ?? null,
                'is_duplicate' => $isDuplicate,
                'valid'        => empty($reasons),
                'reasons'      => $reasons,
            ];
        }

        $token = uniqid('import_');
        cache()->put("import_preview_{$token}", $rows, now()->addMinutes(15));

        return response()->json([
            'token'      => $token,
            'preview'    => $preview,
            'total'      => count($preview),
            'duplicates' => collect($preview)->where('is_duplicate', true)->count(),
        ]);
    }

    private function validateImportRow(array $row, array $colleges): array
    {
        $reasons = [];
        $val = fn($k) => trim((string) ($row[$k] ?? ''));

        // Student ID
        $sid = $val('student_id');
        if ($sid === '') {
            $reasons[] = 'Student ID is required';
        } elseif (!ctype_digit($sid)) {
            $reasons[] = 'Student ID must contain only numbers';
        } elseif (strlen($sid) > 15) {
            $reasons[] = 'Student ID is too long (max 15 digits)';
        }

        // Names
        if ($val('first_name') === '') $reasons[] = 'First Name is required';
        if ($val('last_name')  === '') $reasons[] = 'Last Name is required';

        // Sex
        $sex = $val('sex');
        if ($sex === '') {
            $reasons[] = 'Sex is required';
        } elseif (!in_array($sex, ['Male', 'Female'], true)) {
            $reasons[] = 'Sex must be Male or Female';
        }

        // Email
        $email = $val('email');
        if ($email === '') {
            $reasons[] = 'Email Address is required';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $reasons[] = 'Email Address is not valid';
        } elseif (($o = \App\Support\AccountEmail::mappedOwner($email)) && !($o['type'] === 'student' && $o['student_id'] === $sid)) {
            $reasons[] = $o['type'] === 'staff'
                ? 'Email Address is already used by a staff account'
                : 'Email Address is already used by another student';
        }

        // Contact Number
        $contact = $val('contact_number');
        if ($contact === '') {
            $reasons[] = 'Contact Number is required';
        } elseif (!preg_match('/^09\d{9}$/', $contact)) {
            $reasons[] = 'Contact Number must start with 09 and be 11 digits';
        }

        // College
        $college = $val('college');
        if ($college === '') {
            $reasons[] = 'College is required';
        } elseif (!in_array($college, $colleges, true)) {
            $reasons[] = 'College name does not match any in the system';
        }

        // Year Level
        $year = $val('year_level');
        if ($year === '') {
            $reasons[] = 'Year Level is required';
        } elseif (!in_array($year, ['1','2','3','4','5','6','7','8','9','10'], true)) {
            $reasons[] = 'Year Level must be a number from 1 to 10';
        }

        // Guardian Contact
        $gcontact = $val('guardian_contact');
        if ($gcontact === '') {
            $reasons[] = 'Guardian Contact is required';
        } elseif (!preg_match('/^09\d{9}$/', $gcontact)) {
            $reasons[] = 'Guardian Contact must start with 09 and be 11 digits';
        } elseif ($gcontact === $contact) {
            $reasons[] = "Guardian Contact can't be the same as the student's contact";
        }

        return $reasons;
    }

    public function importConfirm(Request $request)
    {
        $request->validate([
            'token'     => 'required|string',
            'decisions' => 'required|array',
        ]);

        $rows = cache()->get("import_preview_{$request->token}");
        if (!$rows) {
            return response()->json(['message' => 'Import session expired. Please re-upload the file.'], 422);
        }

        // ONE query: fetch every existing student matching any ID in the file
        $existingIds = collect($rows)
            ->pluck('student_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $existing = empty($existingIds)
            ? collect()
            : Student::whereIn('student_id', $existingIds)->get()->keyBy('student_id');

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors  = [];
        $inserts = [];
        $now     = now();

        $confirmSeen = [];
        foreach ($rows as $i => $rowData) {
            $decision = $request->decisions[$i] ?? 'create';

            if ($decision === 'skip') {
                $skipped++;
                continue;
            }

            if (empty($rowData['student_id']) || empty($rowData['first_name']) || empty($rowData['last_name'])) {
                $errors[] = "Row " . ($rowData['__row'] ?? $i + 2) . ": missing required fields.";
                $skipped++;
                continue;
            }

            // Never save an email that another account already uses (or that repeats in this file).
            $em = strtolower(trim((string) ($rowData['email'] ?? '')));
            if ($em !== '') {
                $o = \App\Support\AccountEmail::mappedOwner($em);
                if (($o && !($o['type'] === 'student' && $o['student_id'] === (string) $rowData['student_id'])) || isset($confirmSeen[$em])) {
                    $errors[] = "Row " . ($rowData['__row'] ?? $i + 2) . ": email {$rowData['email']} is already used by another account - skipped.";
                    $skipped++;
                    continue;
                }
                $confirmSeen[$em] = true;
            }

            $payload = [
                'first_name'             => $rowData['first_name'],
                'last_name'              => $rowData['last_name'],
                'middle_name'            => $rowData['middle_name'] ?? null,
                'suffix'                 => $rowData['suffix'] ?? null,
                'sex'                    => $rowData['sex'] ?? null,
                'email'                  => $rowData['email'] ?? null,
                'contact_number'         => $rowData['contact_number'] ?? null,
                'college'                => $rowData['college'] ?? null,
                'program'                => $rowData['program'] ?? null,
                'year_level'             => $rowData['year_level'] ?? null,
                'section'                => $rowData['section'] ?? null,
                'guardian_first_name'    => $rowData['guardian_first_name'] ?? null,
                'guardian_middle_name'   => $rowData['guardian_middle_name'] ?? null,
                'guardian_last_name'     => $rowData['guardian_last_name'] ?? null,
                'guardian_contact'       => $rowData['guardian_contact'] ?? null,
                'guardian_relationship'  => $rowData['guardian_relationship'] ?? null,
                'updated_at'             => $now,
            ];

            // Duplicate → update (or skip) using the pre-fetched collection
            if ($existing->has($rowData['student_id'])) {
                if ($decision === 'update') {
                    $existingStudent = $existing->get($rowData['student_id']);
                    $updatePayload = $payload;
                    foreach ($updatePayload as $key => $value) {
                        if ($value === null) {
                            unset($updatePayload[$key]);
                        }
                    }
                    $existingStudent->update($updatePayload);
                    $updated++;
                } else {
                    $skipped++;
                }
                continue;
            }

            // New row - build the insert payload
            // Each new student gets their own random temporary password (never
            // the Student ID). Hashed at cost 4 so a big masterlist stays fast;
            // the password is random, and the student must change it at first
            // login (which is then hashed at the full cost).
            $tempPassword = Str::random(10);
            $inserts[] = array_merge($payload, [
                'student_id'            => $rowData['student_id'],
                'password'              => Hash::make($tempPassword, ['rounds' => 4]),
                'temp_password'         => $tempPassword,
                'must_change_password'  => true,
                'is_active'             => true,
                'created_at'            => $now,
            ]);
            $created++;
        }

        // Bulk insert - one query per 500 rows
        if (!empty($inserts)) {
            DB::transaction(function () use ($inserts) {
                foreach (array_chunk($inserts, 500) as $chunk) {
                    Student::insert($chunk);
                }
            });
        }

        cache()->forget("import_preview_{$request->token}");
        AuditLog::record('imported', "Bulk imported students: {$created} created, {$updated} updated, {$skipped} skipped.");

        return response()->json([
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors'  => $errors,
        ]);
    }

    private function studentHeaderMap(): array
    {
        return [
            'student id'              => 'student_id',
            'last name'               => 'last_name',
            'first name'              => 'first_name',
            'middle name'             => 'middle_name',
            'suffix'                  => 'suffix',
            'sex'                     => 'sex',
            'email address'           => 'email',
            'email'                   => 'email',
            'contact number'          => 'contact_number',
            'college'                 => 'college',
            'program'                 => 'program',
            'year level'              => 'year_level',
            'section'                 => 'section',
            'guardian last name'      => 'guardian_last_name',
            'guardian first name'     => 'guardian_first_name',
            'guardian middle name'    => 'guardian_middle_name',
            'guardian contact'        => 'guardian_contact',
            'guardian relationship'   => 'guardian_relationship',
        ];
    }

    // A row with nothing typed in any column.
    private function isBlankRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) ($value ?? '')) !== '') return false;
        }
        return true;
    }

    private function parseFile($file, $ext, $headerMap)
    {
        $rows = [];

        if (in_array($ext, ['xlsx', 'xls'])) {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            // Read only the part of the sheet that actually holds values.
            $lastCol = $sheet->getHighestDataColumn();
            $lastRow = $sheet->getHighestDataRow();
            $data  = $sheet->rangeToArray("A1:{$lastCol}{$lastRow}", null, true, true, false);

            $rawHeader = array_map(fn($h) => strtolower(trim($h ?? '')), $data[0]);
            for ($i = 1; $i < count($data); $i++) {
                $rowAssoc = [];
                foreach ($rawHeader as $idx => $key) {
                    $mappedKey = $headerMap[$key] ?? $key;
                    $rowAssoc[$mappedKey] = $data[$i][$idx] ?? null;
                }
                if ($this->isBlankRow($rowAssoc)) continue;   // B409: empty rows don't count
                $rowAssoc['__row'] = $i + 1;
                $rows[] = $rowAssoc;
            }
        } else {
            $handle = fopen($file->getRealPath(), 'r');
            $rawHeader = array_map(fn($h) => strtolower(trim($h)), fgetcsv($handle));
            $line = 1;
            while (($data = fgetcsv($handle)) !== false) {
                $line++;
                $rowAssoc = [];
                foreach ($rawHeader as $idx => $key) {
                    $mappedKey = $headerMap[$key] ?? $key;
                    $rowAssoc[$mappedKey] = $data[$idx] ?? null;
                }
                if ($this->isBlankRow($rowAssoc)) continue;   // B409: empty rows don't count
                $rowAssoc['__row'] = $line;
                $rows[] = $rowAssoc;
            }
            fclose($handle);
        }

        return $rows;
    }

    public function checkDuplicateName(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string',
            'last_name'  => 'required|string',
        ]);

        $existing = Student::where('first_name', $request->first_name)
            ->where('last_name', $request->last_name)
            ->when($request->middle_name, fn($q) => $q->where('middle_name', $request->middle_name))
            ->first();

        return response()->json([
            'duplicate_found' => (bool) $existing,
            'existing_student' => $existing,
        ]);
    }

    // Student Information Sheet Ctrl No. - the client count at the end of
    // "26-1-______". Blank until GCU fills it in; editable any time.
    // 6 digits (not 4) so the count can accommodate the ~12,000 student body.
    public function updateClientNo(Request $request, Student $student)
    {
        $data = $request->validate([
            'sif_client_no' => ['nullable', 'integer', 'min:1', 'max:999999'],
        ]);

        // An archived file (deactivated student, or a closed case) is view only.
        $latest = $student->cases()->latest('id')->first();
        if ($student->is_active === false || ($latest && $latest->status === 'closed')) {
            return response()->json(['message' => 'This file is archived and view only. The Control Number can no longer be changed.'], 422);
        }

        // One client number per student (B404).
        if (!empty($data['sif_client_no'])) {
            $taken = Student::where('sif_client_no', $data['sif_client_no'])->where('id', '!=', $student->id)->first();
            if ($taken) {
                $msg = 'Client number ' . str_pad((string) $data['sif_client_no'], 6, '0', STR_PAD_LEFT) . " is already used by another student ({$taken->student_id}).";
                return response()->json(['message' => $msg, 'errors' => ['sif_client_no' => [$msg]]], 422);
            }
        }

        $old = $student->sif_client_no;
        $student->sif_client_no = $data['sif_client_no'] ?? null;
        $student->save();

        AuditLog::record('updated', "Set SIF client no. for {$student->student_id} from " . ($old ?? 'blank') . ' to ' . ($student->sif_client_no ?? 'blank') . '.', $student);

        return response()->json(['sif_client_no' => $student->sif_client_no]);
    }
}