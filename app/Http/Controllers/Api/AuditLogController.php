<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
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

        $rows = $this->exportRows($this->filteredQuery($request)->latest('created_at')->limit(1000)->get());

        $pdf = Pdf::loadView('audit.export-pdf', [
            'rows'         => $rows,
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

    public const EXPORT_HEADERS = ['Timestamp', 'User', 'Role', 'Action', 'Description', 'IP Address'];

    private const ROLE_LABELS = [
        'admin'          => 'Admin / GCU Head',
        'gcu_staff'      => 'GCU Staff',
        'sdu_head'       => 'SDU Head',
        'tmdu_staff'     => 'TMDU Staff',
        'faculty'        => 'Faculty',
        'dean'           => 'Dean',
        'dept_chair'     => 'Department Chair',
        'dean_secretary' => "Dean's Secretary",
        'system_admin'   => 'System Admin',
        'student'        => 'Student',
    ];

    /**
     * The log rows as they are printed in both exports, in readable words:
     * roles and actions by name ("GCU Staff", "Login Failed") and field
     * names in descriptions without underscores ("first name").
     */
    private function exportRows($logs): array
    {
        $words = fn(?string $code) => $code ? ucwords(str_replace('_', ' ', $code)) : '';

        return $logs->map(fn($log) => [
            $log->created_at?->format('M j, Y g:i A') ?? '',
            $log->user_name ?: '-',
            self::ROLE_LABELS[$log->user_role] ?? $words($log->user_role),
            $words($log->action),
            // snake_case field names only - an email address keeps its underscores
            preg_replace_callback('/\b[a-z]+(?:_[a-z]+)+\b(?![\w.]*@)/', fn($m) => str_replace('_', ' ', $m[0]), (string) $log->description),
            $log->ip_address ?? '',
        ])->all();
    }

    public function exportExcel(Request $request)
    {
        $rows = $this->exportRows($this->filteredQuery($request)->latest('created_at')->limit(5000)->get());

        // Laid out like the PDF: letterhead, title, one table with the same
        // columns, landscape, fitted to the page width.
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Audit Trail');
        $sheet->getParent()->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);

        foreach (['A' => 20, 'B' => 24, 'C' => 18, 'D' => 20, 'E' => 70, 'F' => 16] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        $center = ['horizontal' => Alignment::HORIZONTAL_CENTER];
        $lines = [
            1 => ['BENGUET STATE UNIVERSITY', ['bold' => true, 'size' => 14]],
            2 => ['Office of Student Services', ['size' => 10]],
            4 => ['iCARE AUDIT TRAIL', ['bold' => true, 'size' => 12]],
            5 => ['Generated ' . now()->format('F j, Y g:i A') . ' · ' . count($rows) . ' record(s)', ['size' => 9, 'color' => ['rgb' => '555555']]],
        ];
        foreach ($lines as $r => [$text, $font]) {
            $sheet->mergeCells("A{$r}:F{$r}");
            $sheet->setCellValue("A{$r}", $text);
            $sheet->getStyle("A{$r}")->applyFromArray(['font' => $font, 'alignment' => $center]);
        }

        $head = 7;
        $sheet->fromArray(self::EXPORT_HEADERS, null, "A{$head}");
        $sheet->getStyle("A{$head}:F{$head}")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F5C3A']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($head)->setRowHeight(20);

        $first = $head + 1;
        if ($rows) {
            $sheet->fromArray($rows, null, "A{$first}", true);
            $last = $first + count($rows) - 1;
            for ($r = $first + 1; $r <= $last; $r += 2) {
                $sheet->getStyle("A{$r}:F{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F5F8F6');
            }
        } else {
            $last = $first;
            $sheet->mergeCells("A{$first}:F{$first}");
            $sheet->setCellValue("A{$first}", 'No audit logs found.');
            $sheet->getStyle("A{$first}")->applyFromArray(['font' => ['italic' => true, 'color' => ['rgb' => '888888']], 'alignment' => $center]);
        }

        $sheet->getStyle("A{$head}:F{$last}")->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('B9C2BC');
        $sheet->getStyle("A{$first}:F{$last}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        $sheet->getStyle("E{$first}:E{$last}")->getAlignment()->setWrapText(true);
        $sheet->getStyle("B{$first}:B{$last}")->getAlignment()->setWrapText(true);

        $sheet->freezePane('A' . $first);
        $sheet->setAutoFilter("A{$head}:F{$last}");
        $setup = $sheet->getPageSetup();
        $setup->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_A4)
              ->setFitToWidth(1)->setFitToHeight(0)->setHorizontalCentered(true)
              ->setRowsToRepeatAtTopByStartAndEnd($head, $head);
        $sheet->getPageMargins()->setTop(0.6)->setBottom(0.6)->setLeft(0.5)->setRight(0.5);
        $sheet->getHeaderFooter()->setOddFooter('&L&8iCARE Audit Trail&R&8Page &P of &N');

        $filename = 'iCARE-Audit-Trail-' . now()->format('Y-m-d') . '.xlsx';
        $tmpPath = storage_path('app/' . $filename);
        (new Xlsx($spreadsheet))->save($tmpPath);

        return response()->download($tmpPath, $filename)->deleteFileAfterSend(true);
    }
}