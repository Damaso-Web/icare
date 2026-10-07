<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
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
        return response()->json(AcademicYear::create($data), 201);
    }

    public function update(Request $request, AcademicYear $academicYear)
    {
        $data = $this->validated($request, $academicYear->id);
        $academicYear->update($data);
        return response()->json($academicYear);
    }

    public function destroy(AcademicYear $academicYear)
    {
        $academicYear->delete();
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

        if ($errors) throw ValidationException::withMessages($errors);
        return $d;
    }
}