<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BackupRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BackupController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            BackupRecord::with('initiatedBy')
                ->when($request->type, fn ($q) => $q->where('type', $request->type))
                ->latest()
                ->paginate(20)
        );
    }

    public function store(Request $request)
    {
        $request->validate(['type' => 'required|in:data,config,all']);

        Artisan::call('backup:run', [
            '--type'    => $request->type,
            '--trigger' => 'manual',
            '--user'    => $request->user()->id,
        ]);

        AuditLog::record('backup_run', "Manually triggered a {$request->type} backup.");

        return response()->json([
            'message' => 'Backup completed.',
            'backups' => BackupRecord::latest()->take($request->type === 'all' ? 2 : 1)->get(),
        ]);
    }

    public function restoreData(Request $request)
    {
        return $this->restoreWithTruncate($request, 'data', [
            // Child tables first so FK-checks-disabled truncation/reinsert
            // order doesn't matter, but keep it sane either way.
            'case_handoffs', 'session_notes', 'testing_records', 'documents',
            'case_interventions', 'appointments', 'referrals', 'cases',
        ]);
    }

    public function restoreConfig(Request $request)
    {
        // Config restore intentionally never truncates `users` - this table
        // is referenced everywhere (auth tokens, every FK in the system) and
        // truncating it on a live shared database would log everyone out and
        // risk orphaning/mismatching every other table's foreign keys. We
        // reconcile by upserting each backed-up row by its original id
        // instead, which is safe to run against a live system.
        $backup = BackupRecord::where('type', 'config')
            ->where('status', 'completed')
            ->where('verified', true)
            ->latest()
            ->first();

        if (!$backup) {
            abort(404, 'No verified configuration backup is available to restore from.');
        }

        $this->checkConfirmation($request, $backup);

        $payload = json_decode(Storage::disk('local')->get($backup->file_path), true);

        DB::transaction(function () use ($payload) {
            foreach ($payload as $table => $rows) {
                foreach ($rows as $row) {
                    DB::table($table)->updateOrInsert(['id' => $row['id']], $row);
                }
            }
        });

        AuditLog::record('backup_restored', "Restored configuration from backup #{$backup->id} ({$backup->file_path}).");

        return response()->json(['message' => "Configuration restored from backup #{$backup->id}."]);
    }

    private function restoreWithTruncate(Request $request, string $type, array $tableOrder)
    {
        $backup = BackupRecord::where('type', $type)
            ->where('status', 'completed')
            ->where('verified', true)
            ->latest()
            ->first();

        if (!$backup) {
            abort(404, "No verified {$type} backup is available to restore from.");
        }

        $this->checkConfirmation($request, $backup);

        if (!Storage::disk('local')->exists($backup->file_path)) {
            abort(404, 'Backup file no longer exists on disk.');
        }

        $payload = json_decode(Storage::disk('local')->get($backup->file_path), true);

        DB::beginTransaction();
        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            foreach ($tableOrder as $table) {
                if (!isset($payload[$table])) {
                    continue;
                }
                DB::table($table)->truncate();
                foreach (array_chunk($payload[$table], 500) as $chunk) {
                    if (!empty($chunk)) {
                        DB::table($table)->insert($chunk);
                    }
                }
            }

            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            DB::commit();
        } catch (\Throwable $e) {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            DB::rollBack();
            abort(500, 'Restore failed: ' . $e->getMessage());
        }

        AuditLog::record('backup_restored', "Restored {$type} from backup #{$backup->id} ({$backup->file_path}).");

        return response()->json(['message' => ucfirst($type) . " restored from backup #{$backup->id}."]);
    }

    private function checkConfirmation(Request $request, BackupRecord $backup): void
    {
        $request->validate(['confirm' => 'required|string']);

        $expected = 'RESTORE-' . $backup->id;
        if ($request->confirm !== $expected) {
            abort(422, "Confirmation text does not match. Expected: {$expected}");
        }
    }
}
