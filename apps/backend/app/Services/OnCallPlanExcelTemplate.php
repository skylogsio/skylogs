<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class OnCallPlanExcelTemplate
{
    public const DAY_HEADERS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    public const SHIFT_TIMES = [
        '00:00–08:00',
        '08:00–16:00',
        '16:00–24:00',
    ];

    /**
     * @var array<string, array{fill: string, font: string}>
     */
    public const PERSON_COLORS = [
        'alice' => ['fill' => '3D5A80', 'font' => 'FFFFFF'],
        'bob' => ['fill' => 'EE6C4D', 'font' => 'FFFFFF'],
        'carol' => ['fill' => '2A9D8F', 'font' => 'FFFFFF'],
    ];

    public function blank(): Spreadsheet
    {
        return $this->make([
            [
                'title' => 'Layer 1',
                'subtitle' => 'Primary — paged first',
                'banner' => '1B2A4A',
            ],
            [
                'title' => 'Layer 2',
                'subtitle' => 'Backup — paged after the layer 1 delay',
                'banner' => '2A9D8F',
            ],
        ]);
    }

    /**
     * @param  list<string>  $usernames
     */
    public function sample(array $usernames = ['alice', 'bob', 'carol']): Spreadsheet
    {
        $people = array_values(array_filter(
            $usernames,
            fn (mixed $name): bool => trim((string) $name) !== '',
        ));

        if ($people === []) {
            $people = ['alice'];
        }

        return $this->make([
            [
                'title' => 'Layer 1',
                'banner' => '1B2A4A',
                'assignments' => $this->weekAssignments($people),
            ],
            [
                'title' => 'Layer 2',
                'banner' => '2A9D8F',
                'assignments' => $this->weekAssignments(array_reverse($people)),
            ],
        ], withGuideSheets: false);
    }

    /**
     * @param  list<array{title: string, subtitle?: string, banner?: string, assignments?: array<string, list<?string>>}>  $layers
     */
    public function make(array $layers, bool $withGuideSheets = true): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()
            ->setCreator('Skylogs')
            ->setTitle('On-call plan')
            ->setDescription('Weekly on-call calendar. One roster sheet per escalation layer.');

        $spreadsheet->removeSheetByIndex(0);

        $sheetIndex = 0;

        if ($withGuideSheets) {
            $this->writeInstructions($spreadsheet->createSheet($sheetIndex));
            $sheetIndex++;
        }

        foreach ($layers as $layer) {
            $this->writeLayer($spreadsheet->createSheet($sheetIndex), $layer, withChrome: $withGuideSheets);
            $sheetIndex++;
        }

        if ($withGuideSheets) {
            $this->writeLegend($spreadsheet->createSheet($sheetIndex), withSamplePeople: false);
        }

        $spreadsheet->setActiveSheetIndex($withGuideSheets ? 1 : 0);

        return $spreadsheet;
    }

    public function save(Spreadsheet $spreadsheet, string $path): void
    {
        (new Xlsx($spreadsheet))->save($path);
    }

    private function writeInstructions(Worksheet $sheet): void
    {
        $sheet->setTitle('Instructions');
        $sheet->getTabColor()->setRGB('1B2A4A');
        $this->applyPageSetup($sheet);
        $sheet->getColumnDimension('A')->setWidth(92);
        $sheet->getRowDimension(1)->setRowHeight(36);
        $sheet->getRowDimension(2)->setRowHeight(22);

        $sheet->mergeCells('A1:H1');
        $sheet->setCellValue('A1', 'Skylogs on-call plan');
        $sheet->getStyle('A1')->applyFromArray($this->bannerStyle('1B2A4A'));

        $sheet->mergeCells('A2:H2');
        $sheet->setCellValue('A2', 'Fill the Layer sheets like a weekly wall calendar. Upload the file when you create or replace a team plan.');
        $sheet->getStyle('A2')->applyFromArray($this->hintStyle());

        $lines = [
            '1. Each Layer sheet is one escalation layer. Sheet order is the page order (Layer 1, then Layer 2, …). Duplicate a Layer sheet to add another backup.',
            '2. Columns are days (Monday → Sunday). Rows are shifts. The default grid is three 8-hour blocks; change the times in column A if you need different windows.',
            '3. Type a team member’s display name or username in each cell. Matching is case-insensitive. If two people share a display name, use the username.',
            '4. Leave a cell blank when nobody covers that shift. Two names cannot share a cell.',
            '5. Merge cells (same person across several hours or days) — the importer expands merges. Or insert extra time rows (00:00, 01:00, …); consecutive identical names collapse into one window.',
            '6. Overnight wrap is not supported. Split 22:00–06:00 into 22:00–24:00 and 00:00–06:00 on the next day.',
            '7. Times are 24-hour (H:MM or HH:MM). 24:00 is allowed as an end time. Column A may be a start (“08:00”) or a range (“08:00–16:00”).',
            '8. These sheets are ignored: Instructions, Legend, People, Readme, Notes. Do not put the roster on them.',
            '9. Timezone and layer delays are not in this file — send them with the upload (timezone, layerDelays).',
            '10. Colors are visual only. The importer reads the text in each cell.',
        ];

        $row = 4;

        foreach ($lines as $line) {
            $sheet->mergeCells("A{$row}:H{$row}");
            $sheet->setCellValue("A{$row}", $line);
            $sheet->getStyle("A{$row}")->applyFromArray([
                'font' => ['name' => 'Calibri', 'size' => 12, 'color' => ['rgb' => '1B2A4A']],
                'alignment' => [
                    'wrapText' => true,
                    'vertical' => Alignment::VERTICAL_TOP,
                    'indent' => 1,
                ],
            ]);
            $sheet->getRowDimension($row)->setRowHeight(48);
            $row++;
        }

        $sheet->setCellValue("A{$row}", 'Example (Layer 1, 8-hour shifts)');
        $sheet->getStyle("A{$row}")->applyFromArray([
            'font' => ['name' => 'Calibri', 'size' => 13, 'bold' => true, 'color' => ['rgb' => '1B2A4A']],
        ]);
        $row += 2;

        $exampleStart = $row;
        $sheet->fromArray([
            ['Time', ...self::DAY_HEADERS],
            ['00:00–08:00', 'alice', 'bob', 'alice', 'bob', 'alice', 'carol', 'carol'],
            ['08:00–16:00', 'bob', 'alice', 'bob', 'alice', 'bob', 'carol', 'carol'],
            ['16:00–24:00', 'alice', 'bob', 'alice', 'bob', 'alice', 'carol', 'carol'],
        ], null, "A{$row}");

        $this->styleGrid($sheet, $exampleStart, withPeople: true);
        $sheet->setSelectedCell('A1');
    }

    /**
     * @param  array{title: string, subtitle?: string, banner?: string, assignments?: array<string, list<?string>>}  $layer
     */
    private function writeLayer(Worksheet $sheet, array $layer, bool $withChrome = true): void
    {
        $title = $layer['title'];
        $subtitle = $layer['subtitle'] ?? 'Type a team member name in each cell. Merge cells for a longer shift.';
        $banner = $layer['banner'] ?? '1B2A4A';
        $assignments = $layer['assignments'] ?? [];
        $gridStart = $withChrome ? 4 : 1;

        $sheet->setTitle($title);
        $sheet->getTabColor()->setRGB($banner);
        $this->applyPageSetup($sheet);
        $sheet->freezePane('B'.($gridStart + 1));

        if ($withChrome) {
            $sheet->getRowDimension(1)->setRowHeight(32);
            $sheet->getRowDimension(2)->setRowHeight(28);
            $sheet->mergeCells('A1:H1');
            $sheet->setCellValue('A1', $title.'  ·  weekly on-call');
            $sheet->getStyle('A1')->applyFromArray($this->bannerStyle($banner));
            $sheet->mergeCells('A2:H2');
            $sheet->setCellValue('A2', $subtitle);
            $sheet->getStyle('A2')->applyFromArray($this->hintStyle());
        }

        $sheet->getRowDimension($gridStart)->setRowHeight(24);
        $sheet->fromArray([['Time', ...self::DAY_HEADERS]], null, "A{$gridStart}");

        foreach (self::SHIFT_TIMES as $offset => $shift) {
            $row = $gridStart + 1 + $offset;
            $names = $assignments[$shift] ?? array_fill(0, 7, null);
            $sheet->fromArray([$shift, ...$names], null, "A{$row}");
            $sheet->getRowDimension($row)->setRowHeight(56);
        }

        $this->styleGrid($sheet, $gridStart, withPeople: $assignments !== []);

        if ($withChrome) {
            $sheet->getComment('B'.($gridStart + 1))->getText()->createTextRun(
                'Type a display name or username. Leave blank if this shift is uncovered.',
            );
        }

        $sheet->setSelectedCell('B'.($gridStart + 1));
    }

    private function writeLegend(Worksheet $sheet, bool $withSamplePeople): void
    {
        $sheet->setTitle('Legend');
        $sheet->getTabColor()->setRGB('E9C46A');
        $this->applyPageSetup($sheet);
        $sheet->getColumnDimension('A')->setWidth(22);
        $sheet->getColumnDimension('B')->setWidth(28);
        $sheet->getColumnDimension('C')->setWidth(48);
        $sheet->getRowDimension(1)->setRowHeight(32);

        $sheet->mergeCells('A1:C1');
        $sheet->setCellValue('A1', 'Legend');
        $sheet->getStyle('A1')->applyFromArray($this->bannerStyle('264653'));

        $sheet->fromArray([
            ['Swatch', 'What to type', 'Role in the sample file'],
            ['alice', 'alice', 'Layer 1 nights / evenings; Layer 2 weekend'],
            ['bob', 'bob', 'Layer 1 days'],
            ['carol', 'carol', 'Layer 1 weekend; Layer 2 weekday backup'],
        ], null, 'A3');

        $sheet->getStyle('A3:C3')->applyFromArray([
            'font' => ['name' => 'Calibri', 'size' => 11, 'bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1B2A4A']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        foreach (['alice' => 4, 'bob' => 5, 'carol' => 6] as $name => $row) {
            $colors = self::PERSON_COLORS[$name];
            $sheet->getStyle("A{$row}")->applyFromArray($this->personStyle($colors['fill'], $colors['font']));
            $sheet->getRowDimension($row)->setRowHeight(28);
        }

        $sheet->getStyle('A3:C6')->applyFromArray($this->borderStyle());

        if (! $withSamplePeople) {
            $sheet->setCellValue('A8', 'The blank template has no names filled in. Use this sheet only as a color key if you copy the sample.');
            $sheet->getStyle('A8')->applyFromArray($this->hintStyle());
            $sheet->mergeCells('A8:C8');
        }

        $sheet->setCellValue('A10', 'This sheet is not imported.');
        $sheet->getStyle('A10')->applyFromArray([
            'font' => ['name' => 'Calibri', 'size' => 11, 'italic' => true, 'color' => ['rgb' => '6C757D']],
        ]);
        $sheet->setSelectedCell('A1');
    }

    private function styleGrid(Worksheet $sheet, int $headerRow, bool $withPeople): void
    {
        $lastRow = $headerRow + count(self::SHIFT_TIMES);
        $range = "A{$headerRow}:H{$lastRow}";

        $sheet->getStyle($range)->applyFromArray([
            'font' => ['name' => 'Calibri', 'size' => 12],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            ...$this->borderStyle(),
        ]);

        $sheet->getStyle("A{$headerRow}:H{$headerRow}")->applyFromArray([
            'font' => ['name' => 'Calibri', 'size' => 11, 'bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1B2A4A']],
        ]);

        foreach (['G', 'H'] as $weekend) {
            $sheet->getStyle($weekend.$headerRow)->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'C9A227']],
                'font' => ['name' => 'Calibri', 'size' => 11, 'bold' => true, 'color' => ['rgb' => '1B2A4A']],
            ]);
        }

        $sheet->getStyle('A'.($headerRow + 1).":A{$lastRow}")->applyFromArray([
            'font' => ['name' => 'Calibri', 'size' => 11, 'bold' => true, 'color' => ['rgb' => '1B2A4A']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8EEF4']],
        ]);

        for ($row = $headerRow + 1; $row <= $lastRow; $row++) {
            for ($col = 2; $col <= 8; $col++) {
                $cell = Coordinate::stringFromColumnIndex($col).$row;
                $name = strtolower(trim((string) $sheet->getCell($cell)->getValue()));

                if ($withPeople && $name !== '') {
                    $colors = $this->colorForName($name);
                    $sheet->getStyle($cell)->applyFromArray($this->personStyle($colors['fill'], $colors['font']));

                    continue;
                }

                $fill = in_array($col, [7, 8], true) ? 'FFF8E8' : 'F8FAFC';
                $sheet->getStyle($cell)->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $fill]],
                    'font' => ['name' => 'Calibri', 'size' => 12, 'color' => ['rgb' => '1B2A4A']],
                ]);
            }
        }

        $sheet->getColumnDimension('A')->setWidth(16);

        for ($col = 2; $col <= 8; $col++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col))->setWidth(22);
        }
    }

    /**
     * @param  list<string>  $people
     * @return array<string, list<string>>
     */
    private function weekAssignments(array $people): array
    {
        $assignments = [];

        foreach (self::SHIFT_TIMES as $shiftIndex => $shift) {
            $row = [];

            for ($day = 0; $day < 7; $day++) {
                $row[] = $people[($shiftIndex + $day) % count($people)];
            }

            $assignments[$shift] = $row;
        }

        return $assignments;
    }

    /**
     * @return array{fill: string, font: string}
     */
    private function colorForName(string $name): array
    {
        if (isset(self::PERSON_COLORS[$name])) {
            return self::PERSON_COLORS[$name];
        }

        $palette = array_values(self::PERSON_COLORS);

        return $palette[abs(crc32($name)) % count($palette)];
    }

    /**
     * @return array<string, mixed>
     */
    private function bannerStyle(string $rgb): array
    {
        return [
            'font' => ['name' => 'Calibri', 'size' => 18, 'bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $rgb]],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
                'indent' => 1,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function hintStyle(): array
    {
        return [
            'font' => ['name' => 'Calibri', 'size' => 11, 'italic' => true, 'color' => ['rgb' => '445566']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EEF3F8']],
            'alignment' => [
                'wrapText' => true,
                'vertical' => Alignment::VERTICAL_CENTER,
                'indent' => 1,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function personStyle(string $fill, string $font): array
    {
        return [
            'font' => ['name' => 'Calibri', 'size' => 12, 'bold' => true, 'color' => ['rgb' => $font]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $fill]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function borderStyle(): array
    {
        $border = [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => 'C5CDD6'],
        ];

        return [
            'borders' => [
                'allBorders' => $border,
            ],
        ];
    }

    private function applyPageSetup(Worksheet $sheet): void
    {
        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setFitToPage(true)
            ->setFitToWidth(1)
            ->setFitToHeight(1)
            ->setPaperSize(PageSetup::PAPERSIZE_A4);

        $sheet->getPageMargins()->setTop(0.4);
        $sheet->getPageMargins()->setBottom(0.4);
        $sheet->getPageMargins()->setLeft(0.4);
        $sheet->getPageMargins()->setRight(0.4);
        $sheet->getHeaderFooter()->setOddHeader('&LSkylogs&ROn-call plan');
        $sheet->getSheetView()->setZoomScale(120);
        $sheet->getStyle('A1')->getFont()->setName('Calibri');
    }
}
