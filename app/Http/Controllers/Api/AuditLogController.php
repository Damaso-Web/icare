<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $query = $this->filteredQuery($request);

        return response()->json($query->latest('created_at')->paginate(50));
    }

    public function show(AuditLog $auditLog)
    {
        return response()->json($auditLog->load('user'));
    }

    /**
     * Same filters as index(), without pagination - shared by both exports
     * so "Export PDF"/"Export Excel" always match whatever the Audit Trail
     * page's current filters show.
     */
    private function filteredQuery(Request $request)
    {
        return AuditLog::with('user')
            ->when($request->action,     fn($q) => $q->where('action', $request->action))
            ->when($request->user_id,    fn($q) => $q->where('user_id', $request->user_id))
            ->when($request->model_type, fn($q) => $q->where('model_type', $request->model_type))
            ->when($request->date_from,  fn($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->date_to,    fn($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->when($request->search,     fn($q) =>
                $q->where('description', 'like', "%{$request->search}%")
                  ->orWhere('user_name', 'like', "%{$request->search}%")
            );
    }

    public function exportPdf(Request $request)
    {
        $logs = $this->filteredQuery($request)->latest('created_at')->limit(1000)->get();

        $pdf = Pdf::loadView('audit.export-pdf', [
            'logs'         => $logs,
            'generated_at' => now()->format('F j, Y g:i A'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('iCARE-Audit-Trail-' . now()->format('Y-m-d') . '.pdf');
    }

    public function exportExcel(Request $request)
    {
        $logs = $this->filteredQuery($request)->latest('created_at')->limit(5000)->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Audit Trail');
        $sheet->fromArray(['Timestamp', 'User', 'Role', 'Action', 'Description', 'IP Address'], null, 'A1');

        $row = 2;
        foreach ($logs as $log) {
            $sheet->setCellValue("A{$row}", (string) $log->created_at);
            $sheet->setCellValue("B{$row}", $log->user_name);
            $sheet->setCellValue("C{$row}", $log->user_role);
            $sheet->setCellValue("D{$row}", $log->action);
            $sheet->setCellValue("E{$row}", $log->description);
            $sheet->setCellValue("F{$row}", $log->ip_address);
            $row++;
        }

        $filename = 'iCARE-Audit-Trail-' . now()->format('Y-m-d') . '.xlsx';
        $tmpPath = storage_path('app/' . $filename);
        (new Xlsx($spreadsheet))->save($tmpPath);

        return response()->download($tmpPath, $filename)->deleteFileAfterSend(true);
    }
}