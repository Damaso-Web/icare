<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Builds the unit report workbooks (Reports > Export Excel) as documents fit
 * to print and submit: every sheet carries the university letterhead, the
 * report title and period, bordered tables with totals, and prints on A4.
 */
class ReportWorkbook
{
    private const GREEN = '1F5C3A';
    private const PALE  = 'EAF3EC';
    private const GRID  = 'BFC8C2';

    private Spreadsheet $book;
    private ?Worksheet $sheet = null;
    private int $row = 1;
    private int $cols = 2;

    public function __construct(
        private string $unitName,
        private string $reportTitle,
        private string $period,
        private string $generatedAt,
    ) {
        $this->book = new Spreadsheet();
        $this->book->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);
        $this->book->getProperties()->setTitle($reportTitle)->setCreator('iCARE - Benguet State University');
    }

    /** Starts a new sheet with the letterhead. $widths are the column widths, left to right. */
    public function sheet(string $tab, string $heading, array $widths): static
    {
        $this->sheet = $this->sheet === null ? $this->book->getActiveSheet() : $this->book->createSheet();
        $this->sheet->setTitle(mb_substr($tab, 0, 31));
        $this->cols = count($widths);
        $this->row = 1;

        foreach ($widths as $i => $width) {
            $this->sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i + 1))->setWidth($width);
        }

        $this->bannerLine('BENGUET STATE UNIVERSITY', 14, true);
        $this->bannerLine('Office of Student Services', 11, false);
        $this->bannerLine($this->unitName, 11, true);
        $this->row++;
        $this->bannerLine(mb_strtoupper($this->reportTitle), 13, true, self::GREEN);
        $this->bannerLine($heading, 11, true);
        $this->bannerLine($this->period, 10, false);
        $this->row++;

        $setup = $this->sheet->getPageSetup();
        $setup->setPaperSize(PageSetup::PAPERSIZE_A4)
              ->setOrientation(PageSetup::ORIENTATION_PORTRAIT)
              ->setFitToWidth(1)
              ->setFitToHeight(0);
        $setup->setHorizontalCentered(true);
        $this->sheet->getPageMargins()->setTop(0.6)->setBottom(0.7)->setLeft(0.5)->setRight(0.5);
        $this->sheet->getHeaderFooter()->setOddFooter(
            '&L&8iCARE - ' . $this->reportTitle . ' | Generated ' . $this->generatedAt . '&R&8Page &P of &N'
        );
        return $this;
    }

    public function landscape(): static
    {
        $this->sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);

        return $this;
    }

    /** A bold caption above a table. */
    public function section(string $title): static
    {
        $this->sheet->setCellValue("A{$this->row}", $title);
        $this->sheet->getStyle("A{$this->row}")->getFont()->setBold(true)->setSize(11)->getColor()->setRGB(self::GREEN);
        $this->row++;

        return $this;
    }

    /**
     * A bordered table. $rows are plain arrays in header order.
     *  - total:   column positions (0-based) to sum in a TOTAL row
     *  - percent: column positions holding 0..1 fractions, shown as percentages
     *  - center:  column positions to centre (numbers are right-aligned by default)
     *  - repeat:  repeat this table's header row on every printed page
     */
    public function table(array $headers, array $rows, array $options = []): static
    {
        $last = Coordinate::stringFromColumnIndex(count($headers));
        $headerRow = $this->row;

        $this->sheet->fromArray($headers, null, "A{$headerRow}");
        $this->sheet->getStyle("A{$headerRow}:{$last}{$headerRow}")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::GREEN]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $this->sheet->getRowDimension($headerRow)->setRowHeight(22);
        $this->row++;

        if (!$rows) {
            $this->sheet->setCellValue("A{$this->row}", 'No records for this period.');
            $this->sheet->mergeCells("A{$this->row}:{$last}{$this->row}");
            $this->sheet->getStyle("A{$this->row}")->getFont()->setItalic(true)->getColor()->setRGB('777777');
            $this->sheet->getStyle("A{$this->row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $this->row++;
        }

        $firstData = $this->row;
        foreach ($rows as $i => $values) {
            foreach (array_values($values) as $c => $value) {
                // Explicit strings keep codes such as "26-1" or leading-zero IDs as typed.
                $cell = Coordinate::stringFromColumnIndex($c + 1) . $this->row;
                is_int($value) || is_float($value)
                    ? $this->sheet->setCellValue($cell, $value)
                    : $this->sheet->setCellValueExplicit($cell, (string) ($value ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
            if ($i % 2 === 1) {
                $this->sheet->getStyle("A{$this->row}:{$last}{$this->row}")
                    ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F7FAF8');
            }
            $this->row++;
        }
        $lastData = $this->row - 1;

        if ($rows && !empty($options['total'])) {
            $this->sheet->setCellValue("A{$this->row}", 'TOTAL');
            foreach ($options['total'] as $c) {
                $col = Coordinate::stringFromColumnIndex($c + 1);
                $this->sheet->setCellValue("{$col}{$this->row}", "=SUM({$col}{$firstData}:{$col}{$lastData})");
            }
            $this->sheet->getStyle("A{$this->row}:{$last}{$this->row}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::PALE]],
            ]);
            $this->row++;
        }

        $end = $this->row - 1;
        $this->sheet->getStyle("A{$headerRow}:{$last}{$end}")->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::GRID);
        $this->sheet->getStyle("A{$firstData}:{$last}{$end}")->getAlignment()
            ->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);

        foreach ($options['percent'] ?? [] as $c) {
            $col = Coordinate::stringFromColumnIndex($c + 1);
            $this->sheet->getStyle("{$col}{$firstData}:{$col}{$end}")->getNumberFormat()->setFormatCode('0.0%');
        }
        foreach ($options['center'] ?? [] as $c) {
            $col = Coordinate::stringFromColumnIndex($c + 1);
            $this->sheet->getStyle("{$col}{$firstData}:{$col}{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
        if (!empty($options['repeat'])) {
            $this->sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($headerRow, $headerRow);
            $this->sheet->freezePane("A" . ($headerRow + 1));
        }

        $this->row++;

        return $this;
    }

    /**
     * The printed GCU report's table: a two-row header (first column | COURSE |
     * $groupTitle over MALE / FEMALE / TOTAL), the college written once and
     * merged down beside its courses, shaded count cells and a TOTAL row.
     * $groups: [['college' => 'CA', 'rows' => [['course' =>, 'male' =>, 'female' =>, 'total' =>], ...]], ...]
     */
    public function groupedTable(string $firstHeader, string $groupTitle, array $groups): static
    {
        $s = $this->sheet;
        $top = $this->row;
        $headerFill = ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D6DCE5']];

        $s->setCellValue("A{$top}", $firstHeader);
        $s->setCellValue("B{$top}", 'COURSE');
        $s->setCellValue("C{$top}", $groupTitle);
        $s->mergeCells("A{$top}:A" . ($top + 1));
        $s->mergeCells("B{$top}:B" . ($top + 1));
        $s->mergeCells("C{$top}:E{$top}");
        $s->fromArray(['MALE', 'FEMALE', 'TOTAL'], null, 'C' . ($top + 1));
        $s->getStyle("A{$top}:E" . ($top + 1))->applyFromArray([
            'font'      => ['bold' => true, 'size' => 10],
            'fill'      => $headerFill,
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $this->row = $top + 2;
        $first = $this->row;

        if (!array_filter($groups, fn($g) => !empty($g['rows']))) {
            $s->setCellValue("A{$this->row}", 'No records for this period.');
            $s->mergeCells("A{$this->row}:E{$this->row}");
            $s->getStyle("A{$this->row}")->getFont()->setItalic(true)->getColor()->setRGB('777777');
            $s->getStyle("A{$this->row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $this->row++;
        }

        foreach ($groups as $group) {
            if (empty($group['rows'])) continue;
            $start = $this->row;
            foreach ($group['rows'] as $r) {
                $s->setCellValueExplicit("B{$this->row}", (string) $r['course'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $s->setCellValue("C{$this->row}", (int) $r['male']);
                $s->setCellValue("D{$this->row}", (int) $r['female']);
                $s->setCellValue("E{$this->row}", (int) $r['total']);
                $this->row++;
            }
            $end = $this->row - 1;
            $s->setCellValue("A{$start}", $group['college']);
            if ($end > $start) $s->mergeCells("A{$start}:A{$end}");
            $s->getStyle("A{$start}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        }
        $last = $this->row - 1;

        if ($last >= $first) {
            $s->getStyle("C{$first}:D{$last}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FCE9C6');
            $s->getStyle("E{$first}:E{$last}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F6CF6E');
            $s->getStyle("C{$first}:E{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $s->getStyle("E{$first}:E{$last}")->getFont()->setBold(true);

            $s->setCellValue("B{$this->row}", 'TOTAL');
            foreach (['C', 'D', 'E'] as $col) $s->setCellValue("{$col}{$this->row}", "=SUM({$col}{$first}:{$col}{$last})");
            $s->getStyle("A{$this->row}:E{$this->row}")->applyFromArray([
                'font'      => ['bold' => true],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'C9D6C3']],
            ]);
            $s->getStyle("C{$this->row}:E{$this->row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $this->row++;
        }

        $s->getStyle("A{$top}:E" . ($this->row - 1))->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('8C8C8C');
        $this->row += 2;

        return $this;
    }

    /** A plain bold line (used for the printed report's two-line section headings). */
    public function line(string $text, bool $upper = false): static
    {
        $this->sheet->setCellValue("A{$this->row}", $upper ? mb_strtoupper($text) : $text);
        $this->sheet->getStyle("A{$this->row}")->getFont()->setBold(true)->setSize(11);
        $this->row++;

        return $this;
    }

    /** A small italic note under a table. */
    public function note(string $text): static
    {
        $last = Coordinate::stringFromColumnIndex($this->cols);
        $this->sheet->setCellValue("A{$this->row}", $text);
        $this->sheet->mergeCells("A{$this->row}:{$last}{$this->row}");
        $style = $this->sheet->getStyle("A{$this->row}");
        $style->getFont()->setItalic(true)->setSize(9)->getColor()->setRGB('666666');
        $style->getAlignment()->setWrapText(true);
        $this->sheet->getRowDimension($this->row)->setRowHeight(26);
        $this->row += 2;

        return $this;
    }

    /**
     * The printed report's services table: a shaded header band with the
     * value heading on the right, plain rows, and a gold TOTAL row.
     * $rows: [[label, number], ...]
     */
    public function servicesTable(string $valueHeader, array $rows): static
    {
        $s = $this->sheet;
        $top = $this->row;

        $s->setCellValue("B{$top}", $valueHeader);
        $s->getStyle("A{$top}:B{$top}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 10],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D6DCE5']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $this->row++;
        $first = $this->row;

        foreach ($rows as [$label, $value]) {
            $s->setCellValue("A{$this->row}", $label);
            $s->setCellValue("B{$this->row}", (int) $value);
            $this->row++;
        }
        $last = $this->row - 1;
        $s->getStyle("A{$first}:A{$last}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
        $s->getStyle("B{$first}:B{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $s->setCellValue("A{$this->row}", 'TOTAL');
        $s->setCellValue("B{$this->row}", $rows ? "=SUM(B{$first}:B{$last})" : 0);
        $s->getStyle("A{$this->row}:B{$this->row}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F6CF6E']],
        ]);
        $s->getStyle("A{$this->row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $s->getStyle("B{$this->row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $this->row++;

        $s->getStyle("A{$top}:B" . ($this->row - 1))->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('8C8C8C');
        $this->row += 2;

        return $this;
    }

    /**
     * The printed report's counseling matrix: first header over the college
     * and course columns, each category with MALE / FEMALE under it, and a
     * TOTAL column. Category cells are filled from $row['categories'][key]
     * when the data has them, and left blank when it does not.
     * $categories: ['academic' => 'ACADEMIC', ...]
     */
    public function counselingMatrix(string $firstHeader, string $collegeHeader, array $categories, array $groups): static
    {
        $s = $this->sheet;
        $top = $this->row;
        $col = fn(int $i) => Coordinate::stringFromColumnIndex($i);
        $totalCol = $col(3 + 2 * count($categories));

        $s->setCellValue("A{$top}", $firstHeader);
        $s->mergeCells("A{$top}:B{$top}");
        $s->setCellValue('A' . ($top + 1), $collegeHeader);
        $s->setCellValue('B' . ($top + 1), 'COURSES');
        $i = 3;
        foreach ($categories as $label) {
            $s->setCellValue($col($i) . $top, $label);
            $s->mergeCells($col($i) . $top . ':' . $col($i + 1) . $top);
            $s->setCellValue($col($i) . ($top + 1), 'MALE');
            $s->setCellValue($col($i + 1) . ($top + 1), 'FEMALE');
            $i += 2;
        }
        $s->setCellValue("{$totalCol}{$top}", 'TOTAL');
        $s->mergeCells("{$totalCol}{$top}:{$totalCol}" . ($top + 1));
        $s->getStyle("A{$top}:{$totalCol}" . ($top + 1))->applyFromArray([
            'font'      => ['bold' => true, 'size' => 9],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D6DCE5']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $s->getRowDimension($top)->setRowHeight(30);
        $this->row = $top + 2;
        $first = $this->row;

        $hasRows = (bool) array_filter($groups, fn($g) => !empty($g['rows']));
        if (!$hasRows) {
            $s->setCellValue("A{$this->row}", 'No records for this period.');
            $s->mergeCells("A{$this->row}:{$totalCol}{$this->row}");
            $s->getStyle("A{$this->row}")->getFont()->setItalic(true)->getColor()->setRGB('777777');
            $s->getStyle("A{$this->row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $this->row++;
        }

        foreach ($groups as $group) {
            if (empty($group['rows'])) continue;
            $start = $this->row;
            foreach ($group['rows'] as $r) {
                $s->setCellValueExplicit("B{$this->row}", (string) $r['course'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $i = 3;
                foreach (array_keys($categories) as $key) {
                    if (isset($r['categories'][$key])) {
                        $s->setCellValue($col($i) . $this->row, (int) ($r['categories'][$key]['male'] ?? 0));
                        $s->setCellValue($col($i + 1) . $this->row, (int) ($r['categories'][$key]['female'] ?? 0));
                    }
                    $i += 2;
                }
                $s->setCellValue("{$totalCol}{$this->row}", (int) $r['total']);
                $this->row++;
            }
            $end = $this->row - 1;
            $s->setCellValue("A{$start}", $group['college']);
            if ($end > $start) $s->mergeCells("A{$start}:A{$end}");
            $s->getStyle("A{$start}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        }
        $last = $this->row - 1;

        if ($hasRows) {
            $s->getStyle("C{$first}:{$totalCol}{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $s->getStyle("{$totalCol}{$first}:{$totalCol}{$last}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F6CF6E']],
            ]);
        }

        // TOTAL row: every category column and the TOTAL column summed.
        $s->setCellValue("B{$this->row}", 'TOTAL');
        // Category totals stay blank when no row carries categories, so they do not read as zero.
        $hasCategories = (bool) array_filter($groups, fn($g) => array_filter($g['rows'] ?? [], fn($r) => !empty($r['categories'])));
        for ($i = 3; $i <= 3 + 2 * count($categories); $i++) {
            $c = $col($i);
            if ($c !== $totalCol && !$hasCategories) continue;
            $s->setCellValue("{$c}{$this->row}", $hasRows ? "=SUM({$c}{$first}:{$c}{$last})" : 0);
        }
        $s->getStyle("A{$this->row}:{$totalCol}{$this->row}")->applyFromArray([
            'font'      => ['bold' => true],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'C9D6C3']],
        ]);
        $s->getStyle("C{$this->row}:{$totalCol}{$this->row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $this->row++;

        $s->getStyle("A{$top}:{$totalCol}" . ($this->row - 1))->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('8C8C8C');
        $this->row += 2;

        return $this;
    }

    /** "Prepared by / Noted by" lines at the foot of a sheet. */
    public function signatures(): static
    {
        $this->row += 2;
        $right = Coordinate::stringFromColumnIndex(max(2, $this->cols));

        $this->sheet->setCellValue("A{$this->row}", 'Prepared by:');
        $this->sheet->setCellValue("{$right}{$this->row}", 'Noted by:');
        $this->row += 3;
        $this->sheet->setCellValue("A{$this->row}", '______________________________');
        $this->sheet->setCellValue("{$right}{$this->row}", '______________________________');
        $this->row++;
        $this->sheet->setCellValue("A{$this->row}", 'Signature over printed name');
        $this->sheet->setCellValue("{$right}{$this->row}", 'Signature over printed name');
        $this->sheet->getStyle("A{$this->row}:{$right}{$this->row}")->getFont()->setSize(9)->setItalic(true)->getColor()->setRGB('666666');
        $this->row++;

        return $this;
    }

    /** Saves the workbook and returns its path. */
    public function save(string $filename): string
    {
        $this->book->setActiveSheetIndex(0);
        $path = storage_path('app/' . $filename);
        (new Xlsx($this->book))->save($path);

        return $path;
    }

    private function bannerLine(string $text, int $size, bool $bold, string $color = '1A1A1A'): void
    {
        $last = Coordinate::stringFromColumnIndex($this->cols);
        $this->sheet->setCellValue("A{$this->row}", $text);
        $this->sheet->mergeCells("A{$this->row}:{$last}{$this->row}");
        $style = $this->sheet->getStyle("A{$this->row}");
        $style->getFont()->setBold($bold)->setSize($size)->getColor()->setRGB($color);
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $this->row++;
    }
}
