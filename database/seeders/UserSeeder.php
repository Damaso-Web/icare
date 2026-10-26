<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // No passwords live in the code. Set SEED_DEFAULT_PASSWORD in your local .env,
        // or leave it empty and a random one is generated and shown once below.
        $password = env('SEED_DEFAULT_PASSWORD') ?: Str::random(20);

        $users = [
            [
                'name'        => 'System Administrator',
                'email'       => 'sysadmin@bsu.edu.ph',
                'employee_id' => 'BSU-SYS-001',
                'role'        => 'system_admin',
                'unit'        => 'OSS',
                'password'    => Hash::make($password),
                'is_active'   => true,
            ],
            [
                'name'        => 'Administrator',
                'email'       => 'admin@bsu.edu.ph',
                'employee_id' => 'BSU-ADMIN-001',
                'role'        => 'admin',
                'unit'        => 'OSS',
                'password'    => Hash::make($password),
                'is_active'   => true,
            ],
            [
                'name'        => 'GCU Staff',
                'email'       => 'gcu@bsu.edu.ph',
                'employee_id' => 'BSU-GCU-001',
                'role'        => 'gcu_staff',
                'unit'        => 'GCU',
                'password'    => Hash::make($password),
                'is_active'   => true,
            ],
            [
                'name'        => 'SDU Head',
                'email'       => 'sdu@bsu.edu.ph',
                'employee_id' => 'BSU-SDU-001',
                'role'        => 'sdu_head',
                'unit'        => 'SDU',
                'password'    => Hash::make($password),
                'is_active'   => true,
            ],
            [
                'name'        => 'TMDU Head',
                'email'       => 'tmduhead@bsu.edu.ph',
                'employee_id' => 'BSU-TMDU-000',
                'role'        => 'tmdu_head',
                'unit'        => 'TMDU',
                'password'    => Hash::make($password),
                'is_active'   => true,
            ],
            [
                'name'        => 'TMDU Staff',
                'email'       => 'tmdu@bsu.edu.ph',
                'employee_id' => 'BSU-TMDU-001',
                'role'        => 'tmdu_staff',
                'unit'        => 'TMDU',
                'password'    => Hash::make($password),
                'is_active'   => true,
            ],
            [
                'name'        => 'Dean',
                'email'       => 'dean@bsu.edu.ph',
                'employee_id' => 'BSU-DEAN-001',
                'role'        => 'dean',
                'unit'        => null,
                'college'     => 'CIT',
                'password'    => Hash::make($password),
                'is_active'   => true,
            ],
            [
                'name'        => 'Department Chair',
                'email'       => 'deptchair@bsu.edu.ph',
                'employee_id' => 'BSU-DC-001',
                'role'        => 'dept_chair',
                'unit'        => null,
                'college'     => 'CIT',
                'department'  => 'Information Technology',
                'password'    => Hash::make($password),
                'is_active'   => true,
            ],
            [
                'name'        => 'Faculty Member',
                'email'       => 'faculty@bsu.edu.ph',
                'employee_id' => 'BSU-FAC-001',
                'role'        => 'faculty',
                'unit'        => null,
                'college'     => 'CIT',
                'department'  => 'Information Technology',
                'password'    => Hash::make($password),
                'is_active'   => true,
            ],
            // Older account type: it now works exactly as Dean (see User::isDean()).
            [
                'name'        => 'Dean Secretary',
                'email'       => 'deansec@bsu.edu.ph',
                'employee_id' => 'BSU-DS-001',
                'role'        => 'dean_secretary',
                'unit'        => null,
                'college'     => 'CIT',
                'password'    => Hash::make($password),
                'is_active'   => true,
            ],
            [
                'name'        => 'Genry Tester',
                'email'       => 'genrytester@bsu.edu.ph',
                'employee_id' => 'BSU-DEV-001',
                'role'        => 'admin',
                'unit'        => 'OSS',
                'password'    => Hash::make($password),
                'is_active'   => true,
            ],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(['email' => $user['email']], $user);
        }

        $this->command->info('✓ Default users seeded successfully.');
        $this->command->warn("Password for all seeded accounts: {$password}");
        $this->command->warn('Change it after first login. Do not re-run this seeder on a live database.');
    }
}