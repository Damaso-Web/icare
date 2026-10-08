<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
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
            // Grouped, so the OR can't bypass the other filters.
            ->when($request->search,     fn($q) => $q->where(fn($sq) =>
                $sq->where('description', 'like', "%{$request->search}%")
                   ->orWhere('user_name', 'like', "%{$request->search}%")
            ));
    }

    /**
     * Choices for the Audit Trail filter dropdowns, taken from the log itself
     * so every action that has actually been recorded can be filtered on.
     */
    public function filterOptions()
    {
        return response()->json([
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
            'users'   => User::whereIn('id', AuditLog::query()->select('user_id')->whereNotNull('user_id'))
                            ->orderBy('name')
                            ->get(['id', 'name', 'role']),
        ]);
    }

    public function exportPdf(Request $request)
    {
        // A full 1,000-row PDF needs ~150 MB and ~20 s - over PHP's default
        // 128 MB / 30 s on the server, which made the export fail live.
        ini_set('memory_limit', '384M');
        set_time_limit(180);

        $logs = $this->filteredQuery($request)->latest('created_at')->limit(1000)->get();

        $pdf = Pdf::loadView('audit.export-pdf', [
            'logs'         => $logs,
            'generated_at' => now()->format('F j, Y g:i A'),
        ])->setPaper('a4', 'landscape');

        $dompdf = $pdf->getDomPDF();
        $dompdf->render();
        $canvas = $dompdf->getCanvas();
        $canvas->page_text($canvas->get_width() - 100, $canvas->get_height() - 28, 'Page {PAGE_NUM} of {PAGE_COUNT}',
            $dompdf->getFontMetrics()->getFont('Helvetica'), 7, [0.45, 0.45, 0.45]);

        return response($dompdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename=iCARE-Audit-Trail-' . now()->format('Y-m-d') . '.pdf',
        ]);
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