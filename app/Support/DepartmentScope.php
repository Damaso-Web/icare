<?php

namespace App\Support;

// A Department Chair's / Faculty member's "department" comes from the
// Management > Departments list (e.g. "Information Technology"), while a
// student's "program" comes from the Programs list (e.g. "Bachelor of Science
// in Information Technology"). The two lists are not linked, so an exact
// comparison never matched real data and the student lookup came back empty.
// A program belongs to a department when its name equals, or contains, the
// department's name.
class DepartmentScope
{
    public static function matches(?string $program, ?string $department): bool
    {
        $program    = mb_strtolower(trim((string) $program));
        $department = mb_strtolower(trim((string) $department));

        if ($program === '' || $department === '') {
            return false;
        }

        return $program === $department || str_contains($program, $department);
    }

    // Same rule as matches(), as a query condition on students.program.
    public static function whereProgramMatches($query, ?string $department)
    {
        $department = mb_strtolower(trim(str_replace(['%', '_'], '', (string) $department)));

        if ($department === '') {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereRaw('LOWER(program) LIKE ?', ['%' . $department . '%']);
    }
}