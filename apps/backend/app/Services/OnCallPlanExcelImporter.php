<?php

namespace App\Services;

use App\Models\Team;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OnCallPlanExcelImporter
{
    /**
     * @var array<string, int>
     */
    private const DAY_NUMBERS = [
        'mon' => 1,
        'monday' => 1,
        'tue' => 2,
        'tues' => 2,
        'tuesday' => 2,
        'wed' => 3,
        'wednesday' => 3,
        'thu' => 4,
        'thur' => 4,
        'thurs' => 4,
        'thursday' => 4,
        'fri' => 5,
        'friday' => 5,
        'sat' => 6,
        'saturday' => 6,
        'sun' => 7,
        'sunday' => 7,
    ];

    /**
     * @var list<string>
     */
    private const SKIP_SHEET_TITLES = [
        'instructions',
        'instruction',
        'legend',
        'people',
        'readme',
        'how to',
        'cover',
        'notes',
    ];

    /**
     * @var list<string>
     */
    private const TIME_HEADERS = ['', 'time', 'hour', 'hours', 'slot', 'shift', 'start'];

    /**
     * @var list<string>
     */
    private const STOP_LABELS = ['legend', 'people', 'notes', 'instructions', 'readme'];

    public function __construct(private readonly OnCallPlanService $onCallPlanService) {}

    /**
     * @return array{layers: list<array<string, mixed>>, errors: list<array{sheet: string, row: int|null, column: string|null, message: string}>}
     */
    public function parse(UploadedFile $file, Team $team): array
    {
        $spreadsheet = IOFactory::load($file->getPathname());
        $members = User::query()
            ->whereIn('_id', $this->onCallPlanService->teamMemberIds($team))
            ->get();

        $layers = [];
        $errors = [];
        $level = 1;

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            if ($this->shouldSkipSheet($sheet->getTitle())) {
                continue;
            }

            $parsed = $this->parseSheet($sheet, $members, $level);
            $layers[] = $parsed['layer'];
            $errors = [...$errors, ...$parsed['errors']];
            $level++;
        }

        if ($layers === [] && $errors === []) {
            $errors[] = $this->error('', null, null, 'The workbook has no roster sheets.');
        }

        return [
            'layers' => $errors === [] ? $layers : [],
            'errors' => $errors,
        ];
    }

    /**
     * @param  Collection<int, User>  $members
     * @return array{layer: array<string, mixed>, errors: list<array{sheet: string, row: int|null, column: string|null, message: string}>}
     */
    private function parseSheet(Worksheet $sheet, Collection $members, int $level): array
    {
        $sheetName = $sheet->getTitle();
        $matrix = $this->cellMatrix($sheet);

        if ($matrix === []) {
            return [
                'layer' => ['level' => $level, 'entries' => []],
                'errors' => [$this->error($sheetName, null, null, 'The sheet is empty.')],
            ];
        }

        $header = $this->findHeader($matrix);

        if ($header === null) {
            return [
                'layer' => ['level' => $level, 'entries' => []],
                'errors' => [$this->error(
                    $sheetName,
                    1,
                    null,
                    'Could not find a calendar header. Put Monday–Sunday (or Mon–Sun) on one row, with Time in the first column.',
                )],
            ];
        }

        $slots = [];
        $errors = [];

        foreach ($matrix as $rowNumber => $cells) {
            if ($rowNumber <= $header['rowNumber']) {
                continue;
            }

            $timeLabel = $this->displayValue($cells[$header['timeColumn']] ?? null);

            if (in_array(Str::lower($timeLabel), self::STOP_LABELS, true)) {
                break;
            }

            $dayValues = [];

            foreach ($header['dayColumns'] as $col => $day) {
                $dayValues[$col] = $this->displayValue($cells[$col] ?? null);
            }

            $hasNames = collect($dayValues)->contains(fn (string $value): bool => $value !== '');

            if ($timeLabel === '' && ! $hasNames) {
                continue;
            }

            $window = $this->parseSlotTime($cells[$header['timeColumn']] ?? null, $timeLabel);

            if ($window === null) {
                if (! $hasNames) {
                    break;
                }

                $errors[] = $this->error(
                    $sheetName,
                    $rowNumber,
                    Coordinate::stringFromColumnIndex($header['timeColumn']),
                    "Could not parse time '{$timeLabel}'. Use '08:00' or '08:00–16:00'.",
                );

                continue;
            }

            if (! $hasNames) {
                $slots[] = ['row' => $rowNumber, 'window' => $window, 'users' => []];

                continue;
            }

            $users = [];

            foreach ($header['dayColumns'] as $col => $day) {
                $label = $dayValues[$col];

                if ($label === '') {
                    continue;
                }

                $resolved = $this->resolveUser($label, $members);

                if (is_string($resolved)) {
                    $errors[] = $this->error(
                        $sheetName,
                        $rowNumber,
                        Coordinate::stringFromColumnIndex($col),
                        $resolved,
                    );

                    continue;
                }

                $users[$day] = $resolved;
            }

            $slots[] = [
                'row' => $rowNumber,
                'window' => $window,
                'users' => $users,
            ];
        }

        $entriesByUser = [];

        foreach ($this->materializeSlots($slots) as $slot) {
            foreach ($slot['users'] as $day => $user) {
                $userId = (string) $user->id;

                if (! isset($entriesByUser[$userId])) {
                    $entriesByUser[$userId] = [];
                }

                $entriesByUser[$userId][] = [
                    'daysOfWeek' => [$day],
                    'startTime' => $slot['startTime'],
                    'endTime' => $slot['endTime'],
                ];
            }
        }

        $entries = [];

        foreach ($entriesByUser as $userId => $windows) {
            $entries[] = [
                'userId' => $userId,
                'windows' => $this->compactWindows($windows),
            ];
        }

        return [
            'layer' => [
                'level' => $level,
                'entries' => $entries,
            ],
            'errors' => $errors,
        ];
    }

    /**
     * @param  array<int, array<int, mixed>>  $matrix
     * @return array{rowNumber: int, timeColumn: int, dayColumns: array<int, int>}|null
     */
    private function findHeader(array $matrix): ?array
    {
        foreach ($matrix as $rowNumber => $cells) {
            $dayColumns = [];
            $timeColumn = null;

            foreach ($cells as $col => $value) {
                $label = Str::of($this->displayValue($value))->lower()->replace('.', ' ')->squish()->toString();
                $day = $this->parseDayLabel($label);

                if ($day !== null) {
                    $dayColumns[$col] = $day;

                    continue;
                }

                if ($timeColumn === null && in_array($label, self::TIME_HEADERS, true)) {
                    $timeColumn = $col;
                }
            }

            if ($dayColumns === []) {
                continue;
            }

            return [
                'rowNumber' => $rowNumber,
                'timeColumn' => $timeColumn ?? 1,
                'dayColumns' => $dayColumns,
            ];
        }

        return null;
    }

    /**
     * @return array{start: string, end: string|null}|null
     */
    private function parseSlotTime(mixed $raw, string $label): ?array
    {
        if ($raw instanceof DateTimeInterface) {
            return ['start' => $raw->format('H:i'), 'end' => null];
        }

        if (is_int($raw) || (is_float($raw) && $raw == (int) $raw)) {
            $hour = (int) $raw;

            if ($hour >= 0 && $hour <= 23) {
                return ['start' => sprintf('%02d:00', $hour), 'end' => null];
            }

            if ($hour === 24) {
                return ['start' => '24:00', 'end' => null];
            }
        }

        if (is_numeric($raw) && (float) $raw >= 0 && (float) $raw <= 1) {
            $clock = $this->clockFromDayFraction((float) $raw);

            if ($clock !== null) {
                return ['start' => $clock, 'end' => null];
            }
        }

        $text = Str::of($label)->replace('.', ':')->squish()->toString();

        if ($text === '') {
            return null;
        }

        if (preg_match('/(\d{1,2}:\d{2})\s*[–—-]\s*(\d{1,2}:\d{2})/u', $text, $matches) === 1) {
            $start = $this->normalizeClock($matches[1]);
            $end = $this->normalizeClock($matches[2]);

            if ($start === null || $end === null) {
                return null;
            }

            return ['start' => $start, 'end' => $end];
        }

        if (preg_match('/\b(\d{1,2}:\d{2})\b/u', $text, $matches) === 1) {
            $start = $this->normalizeClock($matches[1]);

            return $start === null ? null : ['start' => $start, 'end' => null];
        }

        return null;
    }

    /**
     * @param  list<array{row: int, window: array{start: string, end: string|null}, users: array<int, User>}>  $slots
     * @return list<array{startTime: string, endTime: string, users: array<int, User>}>
     */
    private function materializeSlots(array $slots): array
    {
        $materialized = [];

        foreach ($slots as $index => $slot) {
            $start = $slot['window']['start'];
            $end = $slot['window']['end'];

            if ($end === null) {
                $next = $slots[$index + 1]['window']['start'] ?? '24:00';
                $end = $next;
            }

            if ($start === '24:00' || $this->clockToMinutes($start) >= $this->clockToMinutes($end)) {
                continue;
            }

            $materialized[] = [
                'startTime' => $start,
                'endTime' => $end,
                'users' => $slot['users'],
            ];
        }

        return $materialized;
    }

    /**
     * @param  list<array{daysOfWeek: list<int>, startTime: string, endTime: string}>  $windows
     * @return list<array{daysOfWeek: list<int>, startTime: string, endTime: string}>
     */
    private function compactWindows(array $windows): array
    {
        $byDay = [];

        foreach ($windows as $window) {
            foreach ($window['daysOfWeek'] as $day) {
                $byDay[$day][] = [
                    'start' => $window['startTime'],
                    'end' => $window['endTime'],
                ];
            }
        }

        $compacted = [];

        foreach ($byDay as $day => $ranges) {
            usort($ranges, fn (array $left, array $right): int => $this->clockToMinutes($left['start']) <=> $this->clockToMinutes($right['start']));

            $current = null;

            foreach ($ranges as $range) {
                if ($current === null) {
                    $current = $range;

                    continue;
                }

                if ($current['end'] === $range['start']) {
                    $current['end'] = $range['end'];

                    continue;
                }

                $compacted[] = [
                    'daysOfWeek' => [$day],
                    'startTime' => $current['start'],
                    'endTime' => $current['end'],
                ];
                $current = $range;
            }

            if ($current !== null) {
                $compacted[] = [
                    'daysOfWeek' => [$day],
                    'startTime' => $current['start'],
                    'endTime' => $current['end'],
                ];
            }
        }

        return $compacted;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function cellMatrix(Worksheet $sheet): array
    {
        $highestRow = (int) $sheet->getHighestDataRow();
        $highestColumn = $sheet->getHighestDataColumn();
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

        if ($highestRow < 1 || $highestColumnIndex < 1) {
            return [];
        }

        $matrix = [];

        for ($row = 1; $row <= $highestRow; $row++) {
            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $cell = $sheet->getCell(Coordinate::stringFromColumnIndex($col).$row);
                $value = $cell->getCalculatedValue();

                if (ExcelDate::isDateTime($cell) && is_numeric($value)) {
                    $value = ExcelDate::excelToDateTimeObject((float) $value);
                }

                $matrix[$row][$col] = $value;
            }
        }

        foreach ($sheet->getMergeCells() as $range) {
            [$start, $end] = Coordinate::rangeBoundaries($range);
            $origin = $matrix[$start[1]][$start[0]] ?? null;

            for ($col = (int) $start[0]; $col <= (int) $end[0]; $col++) {
                for ($row = (int) $start[1]; $row <= (int) $end[1]; $row++) {
                    $matrix[$row][$col] = $origin;
                }
            }
        }

        return $matrix;
    }

    private function parseDayLabel(string $label): ?int
    {
        $token = Str::of($label)->lower()->before(' ')->trim()->toString();

        return self::DAY_NUMBERS[$token] ?? null;
    }

    private function shouldSkipSheet(string $title): bool
    {
        return in_array(Str::lower(trim($title)), self::SKIP_SHEET_TITLES, true);
    }

    private function displayValue(mixed $value): string
    {
        if ($value instanceof RichText) {
            return trim($value->getPlainText());
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('H:i');
        }

        if (is_bool($value) || $value === null) {
            return '';
        }

        return trim((string) $value);
    }

    private function normalizeClock(string $time): ?string
    {
        if ($time === '24:00') {
            return '24:00';
        }

        if (preg_match('/^(\d{1,2}):([0-5]\d)$/', $time, $matches) !== 1) {
            return null;
        }

        $hour = (int) $matches[1];
        $minute = $matches[2];

        if ($hour === 24 && $minute === '00') {
            return '24:00';
        }

        if ($hour > 23) {
            return null;
        }

        return sprintf('%02d:%s', $hour, $minute);
    }

    private function clockFromDayFraction(float $fraction): ?string
    {
        $minutes = (int) round($fraction * 24 * 60);

        if ($minutes === 24 * 60) {
            return '24:00';
        }

        if ($minutes < 0 || $minutes >= 24 * 60) {
            return null;
        }

        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    private function clockToMinutes(string $time): int
    {
        if ($time === '24:00') {
            return 24 * 60;
        }

        [$hour, $minute] = array_map('intval', explode(':', $time));

        return ($hour * 60) + $minute;
    }

    private function resolveUser(string $label, Collection $members): User|string
    {
        $needle = Str::lower($label);
        $byName = $members->filter(fn (User $user) => Str::lower((string) $user->name) === $needle)->values();

        if ($byName->count() === 1) {
            return $byName->first();
        }

        if ($byName->count() > 1) {
            return "User '{$label}' is ambiguous. Use a unique name or username.";
        }

        $byUsername = $members->filter(fn (User $user) => Str::lower((string) $user->username) === $needle)->values();

        if ($byUsername->count() === 1) {
            return $byUsername->first();
        }

        if ($byUsername->count() > 1) {
            return "User '{$label}' is ambiguous. Use a unique name or username.";
        }

        return "User '{$label}' was not found on this team.";
    }

    /**
     * @return array{sheet: string, row: int|null, column: string|null, message: string}
     */
    private function error(string $sheet, ?int $row, ?string $column, string $message): array
    {
        return [
            'sheet' => $sheet,
            'row' => $row,
            'column' => $column,
            'message' => $message,
        ];
    }
}
