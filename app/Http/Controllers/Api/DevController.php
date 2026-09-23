<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class DevController extends Controller
{
    // This whole controller is a testing convenience for one designated
    // account only - never trust the frontend's own gating on this, since
    // it lets an account jump into any staff role at will.
    private const TESTER_EMAIL = 'genrytester@bsu.edu.ph';

    private const ROLE_PROFILES = [
        'admin'          => ['unit' => 'OSS',  'college' => null,  'department' => null],
        'gcu_staff'      => ['unit' => 'GCU',  'college' => null,  'department' => null],
        'sdu_head'       => ['unit' => 'SDU',  'college' => null,  'department' => null],
        'tmdu_staff'     => ['unit' => 'TMDU', 'college' => null,  'department' => null],
        'faculty'        => ['unit' => null,   'college' => 'CIT', 'department' => 'Information Technology'],
        'dean_secretary' => ['unit' => null,   'college' => 'CIT', 'department' => null],
        'system_admin'   => ['unit' => null,   'college' => null,  'department' => null],
    ];

    private function authorizeTester(Request $request): void
    {
        $email = strtolower($request->user()->email ?? '');
        if ($email !== self::TESTER_EMAIL) {
            abort(403, 'This feature is only available on the designated tester account.');
        }
    }

    public function switchRole(Request $request)
    {
        $this->authorizeTester($request);

        $request->validate([
            'role' => 'required|in:' . implode(',', array_keys(self::ROLE_PROFILES)),
        ]);

        $user    = $request->user();
        $profile = self::ROLE_PROFILES[$request->role];

        $user->update([
            'role'       => $request->role,
            'unit'       => $profile['unit'],
            'college'    => $profile['college'],
            'department' => $profile['department'],
        ]);

        return response()->json($user->only([
            'id', 'name', 'first_name', 'middle_name', 'last_name',
            'email', 'role', 'unit', 'college', 'department',
            'contact_number', 'employee_id'
        ]));
    }

    public function switchToStudent(Request $request)
    {
        $this->authorizeTester($request);

        $student = Student::firstOrCreate(
            ['student_id' => 'DEV-TESTER-001'],
            [
                'first_name' => 'Genry',
                'last_name'  => 'Tester',
                'college'    => 'CIT',
                'program'    => 'Bachelor of Science in Information Technology',
                'year_level' => '4th Year',
                'section'    => 'A',
                'sex'        => 'Prefer not to say',
                'password'   => Hash::make('Shazam@iCARE2026'),
                'is_active'  => true,
            ]
        );

        $token = $student->createToken('dev-switch-student', ['student'])->plainTextToken;

        return response()->json([
            'token'   => $token,
            'student' => $student->only([
                'id', 'student_id', 'first_name', 'middle_name', 'last_name',
                'email', 'college', 'program', 'year_level', 'must_change_password'
            ]),
        ]);
    }
}