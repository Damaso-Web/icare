<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Appointment;
use App\Models\Referral;
use App\Support\ManagementGuard;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ManagementAcademicYearController extends Controller
{
    public function index()
    {
        return AcademicYear::orderByDesc('start_year')->get();
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, null);
        $ay = AcademicYear::create($data);
        // The first term ever added becomes the current one.
        if (!AcademicYear::where('is_current', true)->exists()) {
            $ay->forceFill(['is_current' => true])->save();
        }
        ManagementGuard::audit('school calendar', 'created', $ay->label(), $ay, [], $data);
        return response()->json($ay->fresh(), 201);
    }

    public function update(Request $request, AcademicYear $academicYear)
    {
        ManagementGuard::assertEditable($academicYear);
        $data = $this->validated($request, $academicYear->id);
        $old = $academicYear->only(array_keys($data));
        $academicYear->update($data);
        ManagementGuard::audit('school calendar', 'updated', $academicYear->label(), $academicYear, array_map(fn($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v, $old), $data);
        return response()->json($academicYear);
    }

    /** Only one term is current at a time. */
    public function setCurrent(Request $request, AcademicYear $academicYear)
    {
        ManagementGuard::assertUnchanged($request, $academicYear);
        abort_if(($academicYear->status ?: 'active') !== 'active', 422, 'Only an active term can be set as current.');
        abort_if($academicYear->is_current, 422, 'This term is already the current term.');

        $previous = AcademicYear::where('is_current', true)->first();
        AcademicYear::where('is_current', true)->update(['is_current' => false]);
        $academicYear->forceFill(['is_current' => true])->save();

        ManagementGuard::audit('school calendar', 'updated', $academicYear->label(), $academicYear,
            ['current term' => $previous?->label() ?? 'none'], ['current term' => $academicYear->label()]);
        return response()->json($academicYear->fresh());
    }

    /** Appointments and referrals dated inside the term. */
    public static function links(AcademicYear $ay): array
    {
        $from = $ay->year_start->format('Y-m-d');
        $to   = $ay->year_end->format('Y-m-d');
        return [
            'appointments' => Appointment::whereBetween('appointment_date', [$from, $to])->count(),
            'referrals'    => Referral::whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to)->count(),
        ];
    }

    public function usage(AcademicYear $academicYear)
    {
        return response()->json(self::links($academicYear));
    }

    public function destroy(Request $request, AcademicYear $academicYear)
    {
        [$reason, $note] = ManagementGuard::requireDeleteReason($request, 'calendar');
        ManagementGuard::assertUnchanged($request, $academicYear);

        abort_if($academicYear->is_current, 422, 'This is the current term. Set another term as current first.');
        ManagementGuard::blockIfLinked('term', self::links($academicYear), 'Archive it instead to keep the history.');

        $old = ['term' => $academicYear->label()];
        $name = $academicYear->label();
        $academicYear->delete();
        ManagementGuard::audit('school calendar', 'deleted', $name, $academicYear, $old, [], $reason, $note);
        return response()->json(['message' => 'Academic year deleted.']);
    }

    private function validated(Request $request, ?int $ignoreId): array
    {
        $rules = [
            'start_year' => ['required', 'integer', 'between:2000,2100', 'unique:academic_years,start_year' . ($ignoreId ? ",$ignoreId" : '')],
        ];
        foreach (AcademicYear::DATE_FIELDS as $f) {
            $rules[$f] = ['required', 'date_format:Y-m-d'];
        }
        $d = $request->validate($rules);

        // Each start must be on or before its end, the three terms must sit
        // inside the academic year, and terms must not overlap each other.
        $errors = [];
        $pairs = [
            'year'    => ['year_start', 'year_end', 'Academic year'],
            'first'   => ['first_sem_start', 'first_sem_end', '1st Semester'],
            'second'  => ['second_sem_start', 'second_sem_end', '2nd Semester'],
            'midyear' => ['midyear_start', 'midyear_end', 'Midyear'],
        ];
        foreach ($pairs as [$s, $e, $label]) {
            if ($d[$e] < $d[$s]) $errors[$e] = ["$label end date must be on or after its start date."];
        }
        foreach (['first', 'second', 'midyear'] as $k) {
            [$s, $e, $label] = $pairs[$k];
            if ($d[$s] < $d['year_start'] || $d[$e] > $d['year_end']) {
                $errors[$s] = ["$label must fall within the academic year."];
            }
        }
        if ($d['second_sem_start'] <= $d['first_sem_end']) $errors['second_sem_start'] = ['2nd Semester must start after the 1st Semester ends.'];
        if ($d['midyear_start'] <= $d['second_sem_end'])   $errors['midyear_start']    = ['Midyear must start after the 2nd Semester ends.'];

        // The whole academic year may not overlap another (non-archived) one.
        if (!$errors) {
            $clash = AcademicYear::where('status', '!=', 'archived')
                ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
                ->where('year_start', '<=', $d['year_end'])
                ->where('year_end', '>=', $d['year_start'])
                ->first();
            if ($clash) {
                $errors['year_start'] = ['These dates overlap A.Y. ' . $clash->label() . ' (' . $clash->year_start->format('M j, Y') . ' - ' . $clash->year_end->format('M j, Y') . '). Change the dates or archive that term first.'];
            }
        }

        if ($errors) throw ValidationException::withMessages($errors);
        return $d;
    }
}