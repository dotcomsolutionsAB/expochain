<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ClientWiseYearlySalesSummaryExport implements FromArray, WithHeadings, WithEvents, ShouldAutoSize
{
    protected array $rows;
    protected array $headings;
    protected int $percentageColIndex; // 1-based
    protected int $nameColIndex = 2;   // "Name" is 2nd column
    protected int $snColIndex   = 1;   // "SN" is 1st column
    protected array $numericColIndexes; // all numeric col indexes (right align)

    /**
     * @param array $rows       // array of associative rows (already numeric without % sign)
     * @param array $headings   // flat headings array for row 1
     * @param int   $percentageColIndex // 1-based index of percentage column
     * @param array $numericColIndexes  // 1-based indexes to right-align + number-format
     */
    public function __construct(array $rows, array $headings, int $percentageColIndex, array $numericColIndexes)
    {
        $this->rows = $rows;
        $this->headings = $headings;
        $this->percentageColIndex = $percentageColIndex;
        $this->numericColIndexes = $numericColIndexes;
    }

    public function array(): array
    {
        // Return rows without headings
        return $this->rows;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                /** @var Worksheet $sheet */
                $sheet = $event->sheet->getDelegate();

                $highestRow    = $sheet->getHighestRow();
                $highestColumn = $sheet->getHighestColumn();
                $fullRange     = "A1:{$highestColumn}{$highestRow}";

                // Header styling: bold + centered + vertical center
                $sheet->getStyle("A1:{$highestColumn}1")->applyFromArray([
                    'font' => ['bold' => true],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                // Borders for all cells (thin)
                $sheet->getStyle($fullRange)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                        ],
                    ],
                ]);

                // Freeze header
                $sheet->freezePane('A2');

                // Alignment rules:
                // SN center, Name left, all numeric right
                // Center SN
                $sheet->getStyleByColumnAndRow($this->snColIndex, 2, $this->snColIndex, $highestRow)
                      ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Left align Name
                $sheet->getStyleByColumnAndRow($this->nameColIndex, 2, $this->nameColIndex, $highestRow)
                      ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                // Right align numeric columns
                foreach ($this->numericColIndexes as $colIdx) {
                    $sheet->getStyleByColumnAndRow($colIdx, 2, $colIdx, $highestRow)
                          ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    // Format numbers to 2 decimals (no % symbol for percentage)
                    $sheet->getStyleByColumnAndRow($colIdx, 2, $colIdx, $highestRow)
                          ->getNumberFormat()->setFormatCode('0.00'); // you can switch to '0.##' if you want trimming
                }

                // Conditional font color for Percentage column (red if < 0, green otherwise)
                if ($this->percentageColIndex > 0 && $highestRow >= 2) {
                    for ($row = 2; $row <= $highestRow; $row++) {
                        $cell = $sheet->getCellByColumnAndRow($this->percentageColIndex, $row);
                        $val  = (float) $cell->getValue();

                        $sheet->getStyleByColumnAndRow($this->percentageColIndex, $row)->applyFromArray([
                            'font' => [
                                'color' => ['rgb' => $val < 0 ? 'FF0000' : '008000'] // red / green
                            ],
                        ]);
                    }
                }

                // Header row height
                $sheet->getRowDimension(1)->setRowHeight(22);
            },
        ];
    }
}