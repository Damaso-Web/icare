<?php

namespace App\Console\Commands;

use App\Models\BackupRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RunBackup extends Command
{
    protected $signature = 'backup:run {--type=all : data|config|all} {--trigger=manual : scheduled|manual} {--user=}';

    protected $description = 'Back up referral/appointment/case data and/or system configuration to storage.';

    private const DATA_TABLES = [
        'cases', 'referrals', 'appointments', 'case_handoffs',
        'session_notes', 'testing_records', 'documents', 'case_interventions',
    ];

    private const CONFIG_TABLES = ['users', 'staff_availability'];

    public function handle(): int
    {
        $type = $this->option('type');
        $types = $type === 'all' ? ['data', 'config'] : [$type];

        foreach ($types as $t) {
            $this->runOne($t);
        }

        return self::SUCCESS;
    }

    private function runOne(string $type): void
    {
        $tables = $type === 'config' ? self::CONFIG_TABLES : self::DATA_TABLES;
        $timestamp = now()->format('Ymd_His');
        $userId = $this->option('user') ?: null;

        $record = BackupRecord::create([
            'type'                 => $type,
            'trigger'              => $this->option('trigger'),
            'status'               => 'pending',
            'initiated_by_user_id' => $userId,
        ]);

        try {
            $payload = [];
            $rowCounts = [];
            foreach ($tables as $table) {
                $rows = DB::table($table)->get()->map(fn ($row) => (array) $row)->toArray();
                $payload[$table] = $rows;
                $rowCounts[$table] = count($rows);
            }

            $filename = "backups/{$type}_{$timestamp}.json";
            Storage::disk('local')->put($filename, json_encode($payload, JSON_PRETTY_PRINT));

            // Verify by re-reading the file back and checking every table's
            // row count round-trips exactly - only verified backups are ever
            // eligible for restore.
            $reread = json_decode(Storage::disk('local')->get($filename), true);
            $verified = true;
            foreach ($tables as $table) {
                if (!isset($reread[$table]) || count($reread[$table]) !== $rowCounts[$table]) {
                    $verified = false;
                    break;
                }
            }

            $record->update([
                'status'     => 'completed',
                'verified'   => $verified,
                'file_path'  => $filename,
                'row_counts' => $rowCounts,
            ]);

            $this->info("Backup ({$type}) completed: {$filename} - " . ($verified ? 'verified' : 'NOT verified'));
        } catch (\Throwable $e) {
            $record->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            $this->error("Backup ({$type}) failed: {$e->getMessage()}");
        }
    }
}
