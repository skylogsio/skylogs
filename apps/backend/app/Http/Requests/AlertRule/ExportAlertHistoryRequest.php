<?php

namespace App\Http\Requests\AlertRule;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportAlertHistoryRequest extends FormRequest
{
    /**
     * Values at or above this are treated as Unix milliseconds (13-digit) and
     * converted to seconds. 1e12 ms ≈ 2001-09-09; seconds never reach this
     * until year ~33658.
     */
    private const MILLISECOND_TIMESTAMP_THRESHOLD = 1_000_000_000_000;

    protected function prepareForValidation(): void
    {
        $this->merge(array_filter([
            'from' => $this->normalizeUnixTimestamp($this->input('from')),
            'to' => $this->normalizeUnixTimestamp($this->input('to')),
        ], fn (mixed $value): bool => $value !== null));
    }

    /**
     * Accept seconds (10-digit) or milliseconds (13-digit); always store seconds.
     */
    private function normalizeUnixTimestamp(mixed $timestamp): ?int
    {
        if (! is_numeric($timestamp)) {
            return null;
        }

        $timestamp = (int) $timestamp;

        if ($timestamp >= self::MILLISECOND_TIMESTAMP_THRESHOLD) {
            return intdiv($timestamp, 1000);
        }

        return $timestamp;
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'from' => ['required', 'integer'],
            'to' => ['required', 'integer', 'gt:from'],
            'format' => ['sometimes', 'string', Rule::in(['xlsx', 'csv'])],
        ];
    }

    public function exportFormat(): string
    {
        return $this->validated('format') ?? 'xlsx';
    }
}
