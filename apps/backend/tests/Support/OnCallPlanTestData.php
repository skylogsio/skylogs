<?php

namespace Tests\Support;

use App\Models\OnCallPlan;
use App\Models\Team;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class OnCallPlanTestData
{
    /**
     * @param  list<array{title: string, rows: list<array{0: string, 1: string}>}>  $sheets
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    public static function request(array $sheets, array $fields = []): array
    {
        $path = self::workbook($sheets);

        return array_replace([
            'name' => 'oncall-plan',
            'timezone' => 'UTC',
            'file' => new UploadedFile(
                $path,
                'oncall.xlsx',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                null,
                true,
            ),
        ], $fields);
    }

    /**
     * @param  list<array{title: string, rows: list<array{0: string, 1: string}>}>  $sheets
     */
    public static function workbook(array $sheets): string
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->removeSheetByIndex(0);

        foreach ($sheets as $index => $sheet) {
            $worksheet = $spreadsheet->createSheet($index);
            $worksheet->setTitle($sheet['title']);
            $worksheet->fromArray([
                ['Time', 'User'],
                ...$sheet['rows'],
            ]);
        }

        $path = tempnam(sys_get_temp_dir(), 'oncall').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function unlinkFile(array $payload): void
    {
        $file = $payload['file'] ?? null;

        if ($file instanceof UploadedFile) {
            @unlink($file->getPathname());
        }
    }

    public static function deletePlan(?OnCallPlan $plan): void
    {
        if ($plan === null) {
            return;
        }

        OnCallPlan::query()->where('_id', $plan->id)->delete();
    }

    public static function deleteForTeam(Team $team): void
    {
        OnCallPlan::query()->where('teamId', (string) $team->id)->delete();
    }
}
