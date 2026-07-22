<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name'        => 'System Administrator',
                'email'       => 'admin@bsu.edu.ph',
                'employee_id' => 'BSU-ADMIN-001',
                'role'        => 'admin',
                'unit'        => 'OSS',
                'password'    => Hash::make('Admin@iCARE2026'),
                'is_active'   => true,
            ],
            [
                'name'        => 'GCU Staff',
                'email'       => 'gcu@bsu.edu.ph',
                'employee_id' => 'BSU-GCU-001',
                'role'        => 'gcu_staff',
                'unit'        => 'GCU',
                'password'    => Hash::make('GCU@iCARE2026'),
                'is_active'   => true,
            ],
            [
                'name'        => 'SDU Head',
                'email'       => 'sdu@bsu.edu.ph',
                'employee_id' => 'BSU-SDU-001',
                'role'        => 'sdu_head',
                'unit'        => 'SDU',
                'password'    => Hash::make('SDU@iCARE2026'),
                'is_active'   => true,
            ],
            [
                'name'        => 'TMDU Staff',
                'email'       => 'tmdu@bsu.edu.ph',
                'employee_id' => 'BSU-TMDU-001',
                'role'        => 'tmdu_staff',
                'unit'        => 'TMDU',
                'password'    => Hash::make('TMDU@iCARE2026'),
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
                'password'    => Hash::make('Faculty@iCARE2026'),
                'is_active'   => true,
            ],
            [
                'name'        => 'Dean Secretary',
                'email'       => 'deansec@bsu.edu.ph',
                'employee_id' => 'BSU-DS-001',
                'role'        => 'dean_secretary',
                'unit'        => null,
                'college'     => 'CIT',
                'password'    => Hash::make('DeanSec@iCARE2026'),
                'is_active'   => true,
            ],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(['email' => $user['email']], $user);
        }

        $this->command->info('✓ Default users seeded successfully.');
    }
}