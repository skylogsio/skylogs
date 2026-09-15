<?php

namespace App\Services;

use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\IOFactory;

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

    public function __construct(private readonly OnCallPlanService $onCallPlanService) {}

    /**
     * @return array{layers: list<array<string, mixed>>, errors: list<array{sheet: string, row: int|null, message: string}>}
     */
    public function parse(UploadedFile $file, Team $team): array
    {
        $spreadsheet = IOFactory::load($file->getPathname());
        $members = User::query()
            ->whereIn('_id', $this->onCallPlanService->teamMemberIds($team))
            ->get();

        $layers = [];
        $errors = [];

        foreach ($spreadsheet->getAllSheets() as $index => $sheet) {
            $sheetName = $sheet->getTitle();
            $rows = $sheet->toArray(null, true, true, false);
            $parsed = $this->parseSheet($sheetName, $rows, $members, $index + 1);

            $layers[] = $parsed['layer'];
            $errors = [...$errors, ...$parsed['errors']];
        }

        if ($layers === [] && $errors === []) {
            $errors[] = [
                'sheet' => '',
                'row' => null,
                'message' => 'The workbook has no sheets.',
            ];
        }

        return [
            'layers' => $errors === [] ? $layers : [],
            'errors' => $errors,
        ];
    }

    /**
     * @param  list<list<mixed>>  $rows
     * @param  Collection<int, User>  $members
     * @return array{layer: array<string, mixed>, errors: list<array{sheet: string, row: int|null, message: string}>}
     */
    private function parseSheet(string $sheetName, array $rows, Collection $members, int $level): array
    {
        $errors = [];
        $entriesByUser = [];

        if ($rows === []) {
            return [
                'layer' => ['level' => $level, 'entries' => []],
                'errors' => [[
                    'sheet' => $sheetName,
                    'row' => null,
                    'message' => 'The sheet is empty.',
                ]],
            ];
        }

        $header = array_map(fn (mixed $value): string => mb_strtolower(trim((string) $value)), $rows[0] ?? []);
        $timeIndex = array_search('time', $header, true);
        $userIndex = array_search('user', $header, true);

        if ($timeIndex === false || $userIndex === false) {
            return [
                'layer' => ['level' => $level, 'entries' => []],
                'errors' => [[
                    'sheet' => $sheetName,
                    'row' => 1,
                    'message' => 'The first row must contain Time and User columns.',
                ]],
            ];
        }

        foreach (array_slice($rows, 1) as $offset => $row) {
            $rowNumber = $offset + 2;
            $time = trim((string) ($row[$timeIndex] ?? ''));
            $userLabel = trim((string) ($row[$userIndex] ?? ''));

            if ($time === '' && $userLabel === '') {
                continue;
            }

            if ($time === '' || $userLabel === '') {
                $errors[] = [
                    'sheet' => $sheetName,
                    'row' => $rowNumber,
                    'message' => 'Both Time and User are required.',
                ];

                continue;
            }

            $window = $this->parseTimeWindow($time);

            if ($window === null) {
                $errors[] = [
                    'sheet' => $sheetName,
                    'row' => $rowNumber,
                    'message' => "Could not parse time '{$time}'. Use 'Mon 00:00–08:00'.",
                ];

                continue;
            }

            $resolved = $this->resolveUser($userLabel, $members);

            if (is_string($resolved)) {
                $errors[] = [
                    'sheet' => $sheetName,
                    'row' => $rowNumber,
                    'message' => $resolved,
                ];

                continue;
            }

            $userId = (string) $resolved->id;

            if (! isset($entriesByUser[$userId])) {
                $entriesByUser[$userId] = [];
            }

            $entriesByUser[$userId][] = $window;
        }

        $entries = [];

        foreach ($entriesByUser as $userId => $windows) {
            $entries[] = [
                'userId' => $userId,
                'windows' => $windows,
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
     * @return array{daysOfWeek: list<int>, startTime: string, endTime: string}|null
     */
    private function parseTimeWindow(string $value): ?array
    {
        if (! preg_match('/^([A-Za-z]+)\s+(\d{1,2}:\d{2})\s*[–—-]\s*(\d{1,2}:\d{2})$/u', trim($value), $matches)) {
            return null;
        }

        $day = self::DAY_NUMBERS[mb_strtolower($matches[1])] ?? null;
        $start = $this->normalizeClock($matches[2]);
        $end = $this->normalizeClock($matches[3]);

        if ($day === null || $start === null || $end === null) {
            return null;
        }

        return [
            'daysOfWeek' => [$day],
            'startTime' => $start,
            'endTime' => $end,
        ];
    }

    private function normalizeClock(string $time): ?string
    {
        if ($time === '24:00') {
            return '24:00';
        }

        if (! preg_match('/^(\d{1,2}):([0-5]\d)$/', $time, $matches)) {
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

    private function resolveUser(string $label, Collection $members): User|string
    {
        $needle = mb_strtolower($label);
        $byName = $members->filter(fn (User $user) => mb_strtolower((string) $user->name) === $needle)->values();

        if ($byName->count() === 1) {
            return $byName->first();
        }

        if ($byName->count() > 1) {
            return "User '{$label}' is ambiguous. Use a unique name or username.";
        }

        $byUsername = $members->filter(fn (User $user) => mb_strtolower((string) $user->username) === $needle)->values();

        if ($byUsername->count() === 1) {
            return $byUsername->first();
        }

        if ($byUsername->count() > 1) {
            return "User '{$label}' is ambiguous. Use a unique name or username.";
        }

        return "User '{$label}' was not found on this team.";
    }
}
