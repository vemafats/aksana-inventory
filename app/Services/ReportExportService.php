<?php

namespace App\Services;

use App\Models\Item;
use App\Models\Location;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportService
{
    /**
     * @return array{
     *   totalPenjualan: float,
     *   totalHpp: float,
     *   grossProfit: float,
     *   totalExpenses: float,
     *   totalDiscount: float,
     *   netProfit: float,
     *   netMargin: float
     * }
     */
    public function buildGrossProfitSummary(Collection $perItemProfit, float $totalExpenses): array
    {
        $totalPenjualan = (float) $perItemProfit->sum('total_revenue');
        $totalHpp = (float) $perItemProfit->sum('total_cost');
        $grossProfit = $totalPenjualan - $totalHpp;
        $totalDiscount = (float) $perItemProfit->sum('total_discount');
        $netProfit = $grossProfit - $totalExpenses;
        $netMargin = $totalPenjualan > 0 ? round(($netProfit / $totalPenjualan) * 100, 1) : 0.0;

        return [
            'totalPenjualan' => $totalPenjualan,
            'totalHpp' => $totalHpp,
            'grossProfit' => $grossProfit,
            'totalExpenses' => $totalExpenses,
            'totalDiscount' => $totalDiscount,
            'netProfit' => $netProfit,
            'netMargin' => $netMargin,
        ];
    }

    /**
     * @param  array<string, float|int|string>  $summary
     */
    public function exportGrossProfit(
        array $summary,
        Collection $perItemProfit,
        Collection $expensesByEvent,
        string $dateFrom,
        string $dateTo,
    ): StreamedResponse {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Gross Profit');

        $sheet->setCellValue('A1', 'LAPORAN GROSS PROFIT');
        $sheet->setCellValue('A2', "Periode: {$dateFrom} s/d {$dateTo}");
        $sheet->mergeCells('A1:I1');
        $sheet->mergeCells('A2:I2');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2')->getFont()->setSize(11)->setColor(new Color('666666'));

        $row = 4;
        $summaryLabels = ['Total Penjualan', 'Total HPP', 'Gross Profit', 'Total Biaya', 'Total Diskon', 'Net Profit', 'Net Margin %'];
        $summaryValues = [
            $summary['totalPenjualan'] ?? 0,
            $summary['totalHpp'] ?? 0,
            $summary['grossProfit'] ?? 0,
            $summary['totalExpenses'] ?? 0,
            $summary['totalDiscount'] ?? 0,
            $summary['netProfit'] ?? 0,
            ($summary['netMargin'] ?? 0).'%',
        ];

        foreach ($summaryLabels as $i => $label) {
            $col = chr(65 + $i);
            $sheet->setCellValue("{$col}{$row}", $label);
            $sheet->getStyle("{$col}{$row}")->getFont()->setBold(true)->setSize(10);
            $valRow = $row + 1;
            $val = $summaryValues[$i];

            if (is_numeric($val)) {
                $sheet->setCellValue("{$col}{$valRow}", $val);
                $sheet->getStyle("{$col}{$valRow}")->getNumberFormat()->setFormatCode('#,##0');
            } else {
                $sheet->setCellValue("{$col}{$valRow}", $val);
            }

            $sheet->getStyle("{$col}{$valRow}")->getFont()->setBold(true)->setSize(12);
        }

        $row = 8;
        $sheet->setCellValue("A{$row}", 'GROSS PROFIT PER ITEM');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
        $row++;

        $headers = ['Item', 'Barcode', 'Qty', 'Penjualan (Kotor)', 'Diskon', 'Net Penjualan', 'HPP', 'Profit', 'Margin %'];
        foreach ($headers as $i => $header) {
            $col = chr(65 + $i);
            $sheet->setCellValue("{$col}{$row}", $header);
        }

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1a1a2e']],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN]],
        ];
        $sheet->getStyle("A{$row}:I{$row}")->applyFromArray($headerStyle);
        $row++;

        foreach ($perItemProfit as $item) {
            $sheet->setCellValue("A{$row}", $item->item_name);
            $sheet->setCellValue("B{$row}", $item->barcode);
            $sheet->setCellValue("C{$row}", $item->total_qty);
            $sheet->setCellValue("D{$row}", $item->total_revenue_before_discount);
            $sheet->setCellValue("E{$row}", $item->total_discount);
            $sheet->setCellValue("F{$row}", $item->total_revenue);
            $sheet->setCellValue("G{$row}", $item->total_cost);
            $sheet->setCellValue("H{$row}", $item->profit);
            $sheet->setCellValue("I{$row}", $item->margin.'%');

            foreach (['D', 'E', 'F', 'G', 'H'] as $col) {
                $sheet->getStyle("{$col}{$row}")->getNumberFormat()->setFormatCode('#,##0');
            }

            $row++;
        }

        $sheet->setCellValue("A{$row}", 'TOTAL');
        $sheet->setCellValue("C{$row}", $perItemProfit->sum('total_qty'));
        $sheet->setCellValue("D{$row}", $perItemProfit->sum('total_revenue_before_discount'));
        $sheet->setCellValue("E{$row}", $perItemProfit->sum('total_discount'));
        $sheet->setCellValue("F{$row}", $perItemProfit->sum('total_revenue'));
        $sheet->setCellValue("G{$row}", $perItemProfit->sum('total_cost'));
        $totalProfit = $perItemProfit->sum('total_revenue') - $perItemProfit->sum('total_cost');
        $totalMargin = $perItemProfit->sum('total_revenue') > 0
            ? round(($totalProfit / $perItemProfit->sum('total_revenue')) * 100, 1)
            : 0;
        $sheet->setCellValue("H{$row}", $totalProfit);
        $sheet->setCellValue("I{$row}", $totalMargin.'%');
        $sheet->getStyle("A{$row}:I{$row}")->getFont()->setBold(true);

        foreach (['D', 'E', 'F', 'G', 'H'] as $col) {
            $sheet->getStyle("{$col}{$row}")->getNumberFormat()->setFormatCode('#,##0');
        }

        $row += 2;

        if ($expensesByEvent->isNotEmpty()) {
            $sheet->setCellValue("A{$row}", 'BIAYA PER EVENT');
            $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
            $row++;

            $expHeaders = ['Event', 'Lokasi', 'Jumlah Biaya', 'Total'];
            foreach ($expHeaders as $i => $header) {
                $col = chr(65 + $i);
                $sheet->setCellValue("{$col}{$row}", $header);
            }

            $sheet->getStyle("A{$row}:D{$row}")->applyFromArray($headerStyle);
            $row++;

            foreach ($expensesByEvent as $exp) {
                $sheet->setCellValue("A{$row}", $exp->event_name);
                $sheet->setCellValue("B{$row}", $exp->location_name);
                $sheet->setCellValue("C{$row}", $exp->expense_count);
                $sheet->setCellValue("D{$row}", (float) $exp->total_amount);
                $sheet->getStyle("D{$row}")->getNumberFormat()->setFormatCode('#,##0');
                $row++;
            }
        }

        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = "laporan-gross-profit-{$dateFrom}-{$dateTo}.xlsx";

        return $this->streamSpreadsheet($spreadsheet, $filename);
    }

    /**
     * @param  array{
     *   items: Collection<int, Item>,
     *   locations: Collection<int, Location>,
     *   totalUnits: int,
     *   totalDamaged: int,
     *   lowCount: int
     * }  $report
     */
    public function exportStock(array $report, ReportService $reportService): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Stok');

        $items = $report['items'];
        $locations = $report['locations'];
        $lastCol = $this->columnLetter(3 + $locations->count());

        $sheet->setCellValue('A1', 'LAPORAN STOK');
        $sheet->setCellValue('A2', 'Snapshot: '.now()->format('d M Y H:i'));
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2')->getFont()->setSize(11)->setColor(new Color('666666'));

        $row = 4;
        $summaryLabels = ['Total SKU', 'Total Unit', 'Rusak', 'Stok Kritis'];
        $summaryValues = [
            $items->count(),
            $report['totalUnits'],
            $report['totalDamaged'],
            $report['lowCount'],
        ];

        foreach ($summaryLabels as $i => $label) {
            $col = $this->columnLetter($i);
            $sheet->setCellValue("{$col}{$row}", $label);
            $sheet->getStyle("{$col}{$row}")->getFont()->setBold(true)->setSize(10);
            $sheet->setCellValue("{$col}".($row + 1), $summaryValues[$i]);
            $sheet->getStyle("{$col}".($row + 1))->getFont()->setBold(true)->setSize(12);
            $sheet->getStyle("{$col}".($row + 1))->getNumberFormat()->setFormatCode('#,##0');
        }

        $row = 8;
        $sheet->setCellValue("A{$row}", 'STOK PER ITEM PER LOKASI');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
        $row++;

        $headers = ['Item', 'Barcode'];
        foreach ($locations as $location) {
            $headers[] = $location->location_name;
        }
        $headers[] = 'Total';
        $headers[] = 'Status';

        foreach ($headers as $i => $header) {
            $col = $this->columnLetter($i);
            $sheet->setCellValue("{$col}{$row}", $header);
        }

        $headerStyle = $this->headerStyleArray();
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray($headerStyle);
        $row++;

        foreach ($items as $item) {
            $totalAvail = (int) ($item->total_available ?? 0);
            $sheet->setCellValue("A{$row}", $item->item_name);
            $sheet->setCellValue("B{$row}", $item->barcode);

            $colIndex = 2;
            foreach ($locations as $location) {
                $col = $this->columnLetter($colIndex);
                $qty = $reportService->itemAvailableQtyAtLocation($item, $location->id);
                $sheet->setCellValue("{$col}{$row}", $qty > 0 ? $qty : 0);
                $colIndex++;
            }

            $totalCol = $this->columnLetter($colIndex);
            $statusCol = $this->columnLetter($colIndex + 1);
            $sheet->setCellValue("{$totalCol}{$row}", $totalAvail);
            $sheet->setCellValue("{$statusCol}{$row}", $this->stockStatusLabel($totalAvail));
            $row++;
        }

        foreach (range(1, Coordinate::columnIndexFromString($lastCol)) as $colIndex) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($colIndex))->setAutoSize(true);
        }

        $filename = 'laporan-stok-'.now()->format('Y-m-d').'.xlsx';

        return $this->streamSpreadsheet($spreadsheet, $filename);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $salesByLocation
     */
    public function exportSales(
        Collection $salesByLocation,
        float $totalSales,
        int $totalTransactions,
        string $dateFrom,
        string $dateTo,
    ): StreamedResponse {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Penjualan');

        $sheet->setCellValue('A1', 'LAPORAN PENJUALAN');
        $sheet->setCellValue('A2', "Periode: {$dateFrom} s/d {$dateTo}");
        $sheet->mergeCells('A1:D1');
        $sheet->mergeCells('A2:D2');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2')->getFont()->setSize(11)->setColor(new Color('666666'));

        $row = 4;
        $summaryLabels = ['Total Omzet', 'Total Transaksi'];
        $summaryValues = [$totalSales, $totalTransactions];

        foreach ($summaryLabels as $i => $label) {
            $col = $this->columnLetter($i);
            $sheet->setCellValue("{$col}{$row}", $label);
            $sheet->getStyle("{$col}{$row}")->getFont()->setBold(true)->setSize(10);
            $valCol = "{$col}".($row + 1);
            $sheet->setCellValue($valCol, $summaryValues[$i]);
            $sheet->getStyle($valCol)->getFont()->setBold(true)->setSize(12);

            if ($i === 0) {
                $sheet->getStyle($valCol)->getNumberFormat()->setFormatCode('#,##0');
            } else {
                $sheet->getStyle($valCol)->getNumberFormat()->setFormatCode('#,##0');
            }
        }

        $row = 8;
        $sheet->setCellValue("A{$row}", 'PENJUALAN PER LOKASI');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
        $row++;

        $headers = ['Lokasi', 'Omzet', 'Transaksi', '% Kontribusi'];
        foreach ($headers as $i => $header) {
            $col = $this->columnLetter($i);
            $sheet->setCellValue("{$col}{$row}", $header);
        }

        $headerStyle = $this->headerStyleArray();
        $sheet->getStyle("A{$row}:D{$row}")->applyFromArray($headerStyle);
        $row++;

        foreach ($salesByLocation as $loc) {
            $locSales = (float) ($loc['total_sales'] ?? 0);
            $contribution = $totalSales > 0
                ? round(($locSales / $totalSales) * 100, 1)
                : (float) ($loc['sales_pct'] ?? 0);

            $sheet->setCellValue("A{$row}", $loc['location_name'] ?? '-');
            $sheet->setCellValue("B{$row}", $locSales);
            $sheet->setCellValue("C{$row}", (int) ($loc['transaction_count'] ?? 0));
            $sheet->setCellValue("D{$row}", $contribution.'%');
            $sheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode('#,##0');
            $row++;
        }

        if ($salesByLocation->isNotEmpty()) {
            $sheet->setCellValue('A'.$row, 'TOTAL');
            $sheet->setCellValue('B'.$row, $totalSales);
            $sheet->setCellValue('C'.$row, $totalTransactions);
            $sheet->setCellValue('D'.$row, $totalSales > 0 ? '100%' : '0%');
            $sheet->getStyle("A{$row}:D{$row}")->getFont()->setBold(true);
            $sheet->getStyle('B'.$row)->getNumberFormat()->setFormatCode('#,##0');
        }

        foreach (range('A', 'D') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = "laporan-penjualan-{$dateFrom}-{$dateTo}.xlsx";

        return $this->streamSpreadsheet($spreadsheet, $filename);
    }

    /**
     * @return array<string, mixed>
     */
    private function headerStyleArray(): array
    {
        return [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1a1a2e']],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN]],
        ];
    }

    private function columnLetter(int $zeroBasedIndex): string
    {
        return Coordinate::stringFromColumnIndex($zeroBasedIndex + 1);
    }

    private function stockStatusLabel(int $qty): string
    {
        if ($qty === 0) {
            return 'HABIS';
        }

        if ($qty <= 1) {
            return 'KRITIS';
        }

        return 'AMAN';
    }

    private function streamSpreadsheet(Spreadsheet $spreadsheet, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
