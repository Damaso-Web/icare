<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class BackupRecordFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type'                 => 'data',
            'trigger'              => 'manual',
            'status'               => 'completed',
            'verified'             => true,
            'file_path'            => 'backups/' . $this->faker->uuid() . '.json',
            'row_counts'           => [],
            'error_message'        => null,
            'initiated_by_user_id' => null,
        ];
    }
}