<?php

namespace App\Services;

use Generator;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PlatformPaymentHistoryExport extends StringValueBinder implements FromGenerator, WithCustomValueBinder, WithHeadings, WithStrictNullComparison, WithStyles, WithTitle
{
    /**
     * @param  list<string>  $headers
     * @param  Generator<int, list<string>>  $rows
     */
    public function __construct(private readonly array $headers, private readonly Generator $rows) {}

    public function generator(): Generator
    {
        yield from $this->rows;
    }

    /** @return list<string> */
    public function headings(): array
    {
        return $this->headers;
    }

    /** @return array<int, array<string, mixed>> */
    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('A2');
        $sheet->getDefaultColumnDimension()->setWidth(24);
        $sheet->getRowDimension(1)->setRowHeight(45);

        return [1 => ['font' => ['bold' => true], 'alignment' => ['wrapText' => true]]];
    }

    public function title(): string
    {
        return 'Payment history';
    }
}
