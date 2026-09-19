<?php

use App\Enums\Constants;
use App\Services\OnCallPlanExcelImporter;
use App\Services\OnCallPlanExcelTemplate;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Support\OnCallPlanTestData;
use Tests\Support\TeamTestData;

describe('OnCallPlanExcelImporter', function () {
    beforeEach(function () {
        $this->alice = TeamTestData::createUser(Constants::ROLE_MEMBER);
        $this->bob = TeamTestData::createUser(Constants::ROLE_MEMBER);
        $this->alice->update(['name' => 'Alice-'.uniqid(), 'username' => 'alice-'.uniqid()]);
        $this->bob->update(['name' => 'Bob-'.uniqid(), 'username' => 'bob-'.uniqid()]);
        $this->team = TeamTestData::createTeam($this->alice, [$this->alice->id, $this->bob->id]);
    });

    afterEach(function () {
        TeamTestData::deleteTeam($this->team);
        TeamTestData::deleteUser($this->alice);
        TeamTestData::deleteUser($this->bob);
    });

    it('parses a weekly calendar with one sheet per layer', function () {
        $path = OnCallPlanTestData::workbook([
            [
                'title' => 'Layer 1',
                'rows' => [
                    ['00:00–08:00', $this->alice->name],
                    ['08:00-16:00', $this->bob->username],
                ],
            ],
            [
                'title' => 'Layer 2',
                'rows' => [
                    ['00:00–24:00', '', '', '', '', '', '', $this->alice->name],
                ],
            ],
        ]);

        $parsed = app(OnCallPlanExcelImporter::class)->parse(
            new UploadedFile($path, 'oncall.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
            $this->team,
        );

        expect($parsed['errors'])->toBe([])
            ->and($parsed['layers'])->toHaveCount(2)
            ->and($parsed['layers'][0]['level'])->toBe(1)
            ->and($parsed['layers'][0]['entries'])->toHaveCount(2)
            ->and($parsed['layers'][1]['entries'][0]['windows'][0]['endTime'])->toBe('24:00')
            ->and($parsed['layers'][1]['entries'][0]['windows'][0]['daysOfWeek'])->toBe([7]);

        @unlink($path);
    });

    it('reports an unknown user with sheet, row, and column', function () {
        $path = OnCallPlanTestData::workbook([
            [
                'title' => 'Layer 1',
                'rows' => [
                    ['00:00–08:00', 'Missing Person'],
                ],
            ],
        ]);

        $parsed = app(OnCallPlanExcelImporter::class)->parse(
            new UploadedFile($path, 'oncall.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
            $this->team,
        );

        expect($parsed['layers'])->toBe([])
            ->and($parsed['errors'][0]['sheet'])->toBe('Layer 1')
            ->and($parsed['errors'][0]['row'])->toBe(2)
            ->and($parsed['errors'][0]['column'])->toBe('B')
            ->and($parsed['errors'][0]['message'])->toContain('Missing Person');

        @unlink($path);
    });

    it('skips instruction sheets and reads the styled calendar template', function () {
        $path = tempnam(sys_get_temp_dir(), 'oncall').'.xlsx';
        $spreadsheet = app(OnCallPlanExcelTemplate::class)->make([
            [
                'title' => 'Layer 1',
                'assignments' => [
                    '00:00–08:00' => [$this->alice->username, null, null, null, null, null, null],
                    '08:00–16:00' => [$this->bob->username, null, null, null, null, null, null],
                    '16:00–24:00' => [$this->alice->username, null, null, null, null, null, null],
                ],
            ],
            [
                'title' => 'Layer 2',
                'banner' => '2A9D8F',
                'assignments' => [
                    '00:00–08:00' => array_fill(0, 7, $this->bob->username),
                    '08:00–16:00' => array_fill(0, 7, $this->bob->username),
                    '16:00–24:00' => array_fill(0, 7, $this->bob->username),
                ],
            ],
        ]);
        app(OnCallPlanExcelTemplate::class)->save($spreadsheet, $path);

        $parsed = app(OnCallPlanExcelImporter::class)->parse(
            new UploadedFile($path, 'oncall.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
            $this->team,
        );

        $aliceMonday = collect($parsed['layers'][0]['entries'])
            ->firstWhere('userId', (string) $this->alice->id)['windows'];

        expect($parsed['errors'])->toBe([])
            ->and($parsed['layers'])->toHaveCount(2)
            ->and($spreadsheet->getSheetCount())->toBe(4)
            ->and($aliceMonday)->toHaveCount(2)
            ->and($aliceMonday[0])->toMatchArray(['daysOfWeek' => [1], 'startTime' => '00:00', 'endTime' => '08:00'])
            ->and($aliceMonday[1])->toMatchArray(['daysOfWeek' => [1], 'startTime' => '16:00', 'endTime' => '24:00'])
            ->and($parsed['layers'][1]['entries'][0]['windows'][0]['endTime'])->toBe('24:00');

        @unlink($path);
    });

    it('expands merged cells and collapses consecutive hour slots', function () {
        $path = OnCallPlanTestData::workbook([
            [
                'title' => 'Layer 1',
                'rows' => [
                    ['00:00', $this->alice->name],
                    ['01:00'],
                    ['02:00'],
                    ['03:00'],
                    ['04:00'],
                ],
                'merge' => ['B2:B5'],
            ],
        ]);

        $parsed = app(OnCallPlanExcelImporter::class)->parse(
            new UploadedFile($path, 'oncall.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
            $this->team,
        );

        expect($parsed['errors'])->toBe([])
            ->and($parsed['layers'][0]['entries'][0]['windows'][0])->toMatchArray([
                'daysOfWeek' => [1],
                'startTime' => '00:00',
                'endTime' => '04:00',
            ]);

        @unlink($path);
    });

    it('requires a day-of-week header row', function () {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([
            ['Nope', 'Wrong'],
            ['00:00', $this->alice->name],
        ]);
        $path = tempnam(sys_get_temp_dir(), 'oncall').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        $parsed = app(OnCallPlanExcelImporter::class)->parse(
            new UploadedFile($path, 'oncall.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
            $this->team,
        );

        expect($parsed['layers'])->toBe([])
            ->and($parsed['errors'][0]['message'])->toContain('calendar header');

        @unlink($path);
    });
});

describe('OnCallPlanExcelTemplate', function () {
    it('builds a blank workbook with instructions, two layers, and a legend', function () {
        $spreadsheet = app(OnCallPlanExcelTemplate::class)->blank();

        expect($spreadsheet->getSheetNames())->toBe(['Instructions', 'Layer 1', 'Layer 2', 'Legend'])
            ->and($spreadsheet->getSheetByName('Layer 1')->getCell('A4')->getValue())->toBe('Time')
            ->and($spreadsheet->getSheetByName('Layer 1')->getCell('B4')->getValue())->toBe('Monday')
            ->and($spreadsheet->getSheetByName('Layer 1')->getCell('A5')->getValue())->toBe('00:00–08:00')
            ->and($spreadsheet->getSheetByName('Layer 1')->getCell('B5')->getValue())->toBeNull();
    });

    it('builds a sample workbook with only filled roster sheets', function () {
        $spreadsheet = app(OnCallPlanExcelTemplate::class)->sample();
        $layer1 = $spreadsheet->getSheetByName('Layer 1');

        expect($spreadsheet->getSheetNames())->toBe(['Layer 1', 'Layer 2'])
            ->and($layer1->getCell('A1')->getValue())->toBe('Time')
            ->and($layer1->getCell('B1')->getValue())->toBe('Monday')
            ->and($layer1->getCell('A2')->getValue())->toBe('00:00–08:00')
            ->and($layer1->getCell('B2')->getValue())->toBe('alice')
            ->and($layer1->getCell('C2')->getValue())->toBe('bob')
            ->and($spreadsheet->getSheetByName('Layer 2')->getCell('B2')->getValue())->toBe('carol');
    });

    it('fills the sample grid with a single username', function () {
        $spreadsheet = app(OnCallPlanExcelTemplate::class)->sample(['me@example.com']);

        expect($spreadsheet->getSheetByName('Layer 1')->getCell('B2')->getValue())->toBe('me@example.com')
            ->and($spreadsheet->getSheetByName('Layer 1')->getCell('H4')->getValue())->toBe('me@example.com')
            ->and($spreadsheet->getSheetByName('Layer 2')->getCell('B2')->getValue())->toBe('me@example.com');
    });
});
