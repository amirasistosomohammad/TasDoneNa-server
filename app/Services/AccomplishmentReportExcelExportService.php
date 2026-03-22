<?php

namespace App\Services;

use App\Models\AccomplishmentReport;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AccomplishmentReportExcelExportService
{
    public function templatePath(): string
    {
        $absolute = config('accomplishment_report_export.template_absolute');

        if (is_string($absolute) && $absolute !== '' && is_readable($absolute)) {
            return $absolute;
        }

        return storage_path('app/'.config('accomplishment_report_export.template_relative'));
    }

    public function templateExists(): bool
    {
        return is_readable($this->templatePath());
    }

    /**
     * Load the client template and fill it from a persisted report.
     *
     * @param  string|null  $certifiedByName  School Head name for “Certified by”; null leaves template text.
     * @param  string|null  $certifiedByDesignation  School Head designation; null leaves template text.
     */
    public function fill(
        AccomplishmentReport $report,
        ?string $certifiedByName = null,
        ?string $certifiedByDesignation = null
    ): Spreadsheet {
        $path = $this->templatePath();
        if (! is_readable($path)) {
            throw new \RuntimeException('Accomplishment Excel template is missing or not readable at: '.$path);
        }

        $spreadsheet = IOFactory::load($path);
        $sheet = $this->resolveSheet($spreadsheet);

        $cells = config('accomplishment_report_export.cells', []);
        $year = (int) $report->year;
        $month = (int) $report->month;

        $monthStart = Carbon::create($year, $month, 1)->startOfDay();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $monthName = $monthStart->format('F');

        $user = $report->user;

        if (! empty($cells['period_subtitle'])) {
            $sheet->setCellValue($cells['period_subtitle'], 'for the Month of '.$monthName.', '.$year);
        }
        if (! empty($cells['name'])) {
            $sheet->setCellValue($cells['name'], $user?->name ?? '');
        }
        if (! empty($cells['designation'])) {
            $sheet->setCellValue($cells['designation'], $user?->position ?? '');
        }
        if (! empty($cells['period_cover'])) {
            $sheet->setCellValue(
                $cells['period_cover'],
                $monthName.' 1–'.$monthEnd->day.', '.$year
            );
        }

        $rowsInsertedBelowKraBand = $this->fillDataRows($sheet, $report);

        if (! empty($cells['prepared_by_name'])) {
            $addr = $this->shiftCellRow($cells['prepared_by_name'], $rowsInsertedBelowKraBand);
            $sheet->setCellValue($addr, $user?->name ?? '');
        }
        if (! empty($cells['prepared_by_position'])) {
            $addr = $this->shiftCellRow($cells['prepared_by_position'], $rowsInsertedBelowKraBand);
            $sheet->setCellValue($addr, $user?->position ?? '');
        }

        $certName = $certifiedByName !== null ? trim($certifiedByName) : '';
        $certDes = $certifiedByDesignation !== null ? trim($certifiedByDesignation) : '';
        if ($certName !== '' && $certDes !== '') {
            $delta = $rowsInsertedBelowKraBand;
            if (! empty($cells['certified_by_name'])) {
                $sheet->setCellValue($this->shiftCellRow($cells['certified_by_name'], $delta), $certName);
            }
            if (! empty($cells['certified_by_position'])) {
                $sheet->setCellValue($this->shiftCellRow($cells['certified_by_position'], $delta), $certDes);
            }
            if (! empty($cells['certified_instruction_note_clear'])) {
                $sheet->setCellValue($this->shiftCellRow($cells['certified_instruction_note_clear'], $delta), '');
            }
        }

        return $spreadsheet;
    }

    /**
     * When rows are inserted before the footer, shift absolute template row numbers (e.g. C36 → C40).
     */
    protected function shiftCellRow(string $cellAddress, int $deltaRows): string
    {
        if ($deltaRows === 0) {
            return $cellAddress;
        }
        $cellAddress = str_replace('$', '', $cellAddress);
        [$col, $row] = Coordinate::coordinateFromString($cellAddress);

        return $col.((int) $row + $deltaRows);
    }

    protected function resolveSheet(Spreadsheet $spreadsheet): Worksheet
    {
        $name = config('accomplishment_report_export.sheet_name');
        if (is_string($name) && $name !== '') {
            $sheet = $spreadsheet->getSheetByName($name);
            if ($sheet !== null) {
                return $sheet;
            }
        }

        return $spreadsheet->getActiveSheet();
    }

    /**
     * @return list<string>
     */
    protected function buildAccomplishmentLines(AccomplishmentReport $report): array
    {
        $lines = [];
        $summary = $report->tasks_summary ?? [];

        foreach ($summary as $group) {
            $kra = isset($group['kra']) ? trim((string) $group['kra']) : '';
            foreach ($group['tasks'] ?? [] as $task) {
                $parts = [];
                if ($kra !== '') {
                    $parts[] = 'KRA: '.$kra;
                }
                if (! empty($task['title'])) {
                    $parts[] = (string) $task['title'];
                }
                if (! empty($task['objective'])) {
                    $parts[] = 'Objective: '.$task['objective'];
                }
                if (! empty($task['mfo'])) {
                    $parts[] = 'MFO: '.$task['mfo'];
                }
                $lines[] = $parts !== [] ? implode(' — ', $parts) : '—';
            }
        }

        if ($lines === []) {
            $lines[] = 'No completed tasks recorded for this period in the system.';
        }

        return $lines;
    }

    /**
     * @return int Rows inserted immediately above the footer (shifts “Prepared by” rows down by this amount).
     */
    protected function fillDataRows(Worksheet $sheet, AccomplishmentReport $report): int
    {
        $startRow = (int) config('accomplishment_report_export.data_start_row', 18);
        $bandEndRow = (int) config('accomplishment_report_export.template_data_last_row', 33);
        $rowsPerEntry = max(1, (int) config('accomplishment_report_export.rows_per_accomplishment', 2));
        $colStart = config('accomplishment_report_export.merge_from_column', 'A');
        $colEnd = config('accomplishment_report_export.merge_to_column', 'L');

        $lines = $this->buildAccomplishmentLines($report);
        $count = count($lines);

        $styleBottom = $startRow + $rowsPerEntry - 1;
        $styleRefRange = $colStart.$startRow.':'.$colEnd.$styleBottom;
        $baseStyle = $sheet->getStyle($styleRefRange);

        $this->unmergeBand($sheet, $startRow, $bandEndRow, $colStart, $colEnd);

        $rowsInBand = $bandEndRow - $startRow + 1;
        $pairCapacity = (int) ($rowsInBand / $rowsPerEntry);
        $pairsNeeded = $count;

        $rowsInserted = 0;
        if ($pairsNeeded > $pairCapacity) {
            $extraRows = $rowsPerEntry * ($pairsNeeded - $pairCapacity);
            $sheet->insertNewRowBefore($bandEndRow + 1, $extraRows);
            $bandEndRow += $extraRows;
            $rowsInserted = $extraRows;
        }

        $effectivePairCapacity = (int) (($bandEndRow - $startRow + 1) / $rowsPerEntry);

        for ($i = 0; $i < $count; $i++) {
            $top = $startRow + ($rowsPerEntry * $i);
            $bottom = $top + $rowsPerEntry - 1;
            $range = $colStart.$top.':'.$colEnd.$bottom;
            $this->safeUnmerge($sheet, $range);
            $sheet->mergeCells($range);
            $sheet->duplicateStyle($baseStyle, $range);
            $sheet->setCellValue($colStart.$top, $lines[$i]);
            $this->applyAccomplishmentCellAlignment($sheet, $range);
        }

        for ($j = $count; $j < $effectivePairCapacity; $j++) {
            $top = $startRow + ($rowsPerEntry * $j);
            $bottom = $top + $rowsPerEntry - 1;
            $range = $colStart.$top.':'.$colEnd.$bottom;
            $this->safeUnmerge($sheet, $range);
            $sheet->mergeCells($range);
            $sheet->duplicateStyle($baseStyle, $range);
            $sheet->setCellValue($colStart.$top, '');
            $this->applyAccomplishmentCellAlignment($sheet, $range);
        }

        return $rowsInserted;
    }

    protected function applyAccomplishmentCellAlignment(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getAlignment()
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setWrapText(true);
    }

    /**
     * Remove merged regions in the KRA table band so we can rebuild 2-row (or N-row) blocks cleanly.
     */
    protected function unmergeBand(Worksheet $sheet, int $minRow, int $maxRow, string $minCol, string $maxCol): void
    {
        $minCi = Coordinate::columnIndexFromString($minCol);
        $maxCi = Coordinate::columnIndexFromString($maxCol);

        foreach (array_keys($sheet->getMergeCells()) as $range) {
            $parts = Coordinate::splitRange($range)[0];
            if (count($parts) < 2) {
                continue;
            }

            $a = str_replace('$', '', $parts[0]);
            $b = str_replace('$', '', $parts[1]);
            [$c1, $r1] = Coordinate::coordinateFromString($a);
            [$c2, $r2] = Coordinate::coordinateFromString($b);
            $r1 = (int) $r1;
            $r2 = (int) $r2;
            $rLow = min($r1, $r2);
            $rHigh = max($r1, $r2);
            $ci1 = Coordinate::columnIndexFromString($c1);
            $ci2 = Coordinate::columnIndexFromString($c2);
            $cLow = min($ci1, $ci2);
            $cHigh = max($ci1, $ci2);

            if ($rHigh >= $minRow && $rLow <= $maxRow && $cHigh >= $minCi && $cLow <= $maxCi) {
                $this->safeUnmerge($sheet, $range);
            }
        }
    }

    protected function safeUnmerge(Worksheet $sheet, string $range): void
    {
        try {
            $sheet->unmergeCells($range);
        } catch (\Throwable) {
            // not merged or range does not match template merge exactly
        }
    }
}
