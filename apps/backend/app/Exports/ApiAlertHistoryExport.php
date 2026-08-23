<?php

namespace App\Exports;

use App\Helpers\Utilities;
use App\Models\AlertRule;
use App\Models\ApiAlertHistory;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ApiAlertHistoryExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        private readonly AlertRule $alertRule,
        private readonly Carbon $from,
        private readonly Carbon $to,
    ) {}

    public function collection(): Collection
    {
        return ApiAlertHistory::query()
            ->where('alertRuleId', $this->alertRule->id)
            ->where('createdAt', '>=', $this->from)
            ->where('createdAt', '<=', $this->to)
            ->latest()
            ->get();
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'Alert Rule',
            'Instance',
            'Status',
            'Description',
            'Summary',
            'Created At (UTC)',
            'Created At (Jalali)',
        ];
    }

    /**
     * @param  ApiAlertHistory  $history
     * @return list<string>
     */
    public function map($history): array
    {
        return [
            (string) ($history->alertRuleName ?? $this->alertRule->name),
            (string) ($history->instance ?? ''),
            (string) $history->status,
            (string) ($history->description ?? ''),
            (string) ($history->summary ?? ''),
            $history->createdAt?->timezone('UTC')->toIso8601String() ?? '',
            $history->createdAt ? Utilities::ConvertUTCTimeTOJalali($history->createdAt) : '',
        ];
    }
}
