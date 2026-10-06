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
