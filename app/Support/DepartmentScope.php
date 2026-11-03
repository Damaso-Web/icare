<?php

namespace App\Support;

use App\Models\College;
use App\Models\Department;
use App\Models\Program;

// A Department Chair's / Faculty member's "department" comes from the
// Management > Departments list (e.g. "Information Technology"), while a
// student's "program" comes from the Programs list (e.g. "Bachelor of Science
// in Information Technology"). Management > Programs can now link a program
// to its department directly (10/27/2026) - when it has been, that link is
// authoritative. Programs added before the link existed, or never linked,
// fall back to the old rule: a program belongs to a department when its name
// equals, or contains, the department's name.
class DepartmentScope
{
    // Bug (11/3/2026): the student-side Refer/Complaint forms and
    // ComplaintController::file()'s auto-fill both looked up a student's
    // department with ONLY the direct Program->department link - unlike
    // every other department-scoping check in the app, they never fell back
    // to the equals-or-contains rule below. A program added before the link
    // existed (or never linked) showed "No matching department found" here
    // even though the main (staff) form's own, separate contains-matching
    // logic would have found one. Centralized that two-tier lookup here so
    // every caller resolves a department the same way. When $collegeName is
    // given, the fuzzy fallback is scoped to that college's departments -
    // same college a student's record already carries - so it can't match a
    // department belonging to a different college by name alone.
    public static function resolve(?string $program, ?string $collegeName = null): ?Department
    {
        $programName = trim((string) $program);
        if ($programName === '') {
            return null;
        }

        $linked = Program::where('name', $programName)->with('department')->first();
        if ($linked?->department) {
            return $linked->department;
        }

        $query = Department::query();
        $collegeName = trim((string) $collegeName);
        if ($collegeName !== '') {
            $college = College::where('name', $collegeName)->first();
            if ($college) {
                $query->where('college_id', $college->id);
            }
        }

        $normProgram = mb_strtolower($programName);
        $best = null;
        foreach ($query->get() as $d) {
            $normDept = mb_strtolower(trim($d->name));
            if ($normDept === '') {
                continue;
            }
            if ($normProgram === $normDept || str_contains($normProgram, $normDept)) {
                // Same tie-break as the main form's own client-side matcher:
                // the longest (most specific) department name wins.
                if (!$best || mb_strlen($normDept) > mb_strlen(mb_strtolower($best->name))) {
                    $best = $d;
                }
            }
        }
        return $best;
    }

    public static function matches(?string $program, ?string $department): bool
    {
        $programName    = trim((string) $program);
        $departmentName = trim((string) $department);

        if ($programName === '' || $departmentName === '') {
            return false;
        }

        $linked = Program::where('name', $programName)->with('department')->first();
        if ($linked?->department) {
            return mb_strtolower($linked->department->name) === mb_strtolower($departmentName);
        }

        $programName    = mb_strtolower($programName);
        $departmentName = mb_strtolower($departmentName);

        return $programName === $departmentName || str_contains($programName, $departmentName);
    }

    // Same rule as matches(), as a query condition on students.program.
    public static function whereProgramMatches($query, ?string $department)
    {
        $departmentName = trim((string) $department);

        if ($departmentName === '') {
            return $query->whereRaw('1 = 0');
        }

        $linkedProgramNames = Program::whereHas('department', fn ($d) =>
            $d->whereRaw('LOWER(name) = ?', [mb_strtolower($departmentName)])
        )->pluck('name')->all();

        $likeTerm = mb_strtolower(str_replace(['%', '_'], '', $departmentName));

        return $query->where(function ($q) use ($linkedProgramNames, $likeTerm) {
            if ($linkedProgramNames) {
                $q->whereIn('program', $linkedProgramNames);
            }
            $q->orWhereRaw('LOWER(program) LIKE ?', ['%' . $likeTerm . '%']);
        });
    }
}