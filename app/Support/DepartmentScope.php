<?php

namespace App\Support;

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