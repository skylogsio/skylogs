<?php

use App\Enums\Constants;
use App\Services\OnCallPlanExcelImporter;
use Illuminate\Http\UploadedFile;
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

    it('parses one sheet per layer and resolves users by name', function () {
        $path = OnCallPlanTestData::workbook([
            [
                'title' => 'Layer 1',
                'rows' => [
                    ['Mon 00:00–08:00', $this->alice->name],
                    ['Mon 08:00-16:00', $this->bob->username],
                ],
            ],
            [
                'title' => 'Layer 2',
                'rows' => [
                    ['Sun 00:00–24:00', $this->alice->name],
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

    it('reports an unknown user with sheet and row', function () {
        $path = OnCallPlanTestData::workbook([
            [
                'title' => 'Layer 1',
                'rows' => [
                    ['Mon 00:00–08:00', 'Missing Person'],
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
            ->and($parsed['errors'][0]['message'])->toContain('Missing Person');

        @unlink($path);
    });
});
