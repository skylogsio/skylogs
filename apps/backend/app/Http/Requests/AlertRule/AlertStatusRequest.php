<?php

namespace App\Http\Requests\AlertRule;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AlertStatusRequest extends FormRequest
{
    /**
     * Values at or above this are treated as Unix milliseconds (13-digit) and
     * converted to seconds. 1e12 ms ≈ 2001-09-09; seconds never reach this
     * until year ~33658.
     */
    private const MILLISECOND_TIMESTAMP_THRESHOLD = 1_000_000_000_000;

    protected function prepareForValidation(): void
    {
        $normalizedAlertRuleIds = $this->normalizeAlertRuleIds($this->input('alertRuleIds'));

        if ($normalizedAlertRuleIds !== null) {
            $this->merge(['alertRuleIds' => $normalizedAlertRuleIds]);
        }

        $this->merge(array_filter([
            'fromTime' => $this->normalizeUnixTimestamp($this->input('fromTime')),
            'toTime' => $this->normalizeUnixTimestamp($this->input('toTime')),
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

    /**
     * @return list<string>|null
     */
    private function normalizeAlertRuleIds(mixed $alertRuleIds): ?array
    {
        if (is_array($alertRuleIds)) {
            return array_values(array_filter(
                $alertRuleIds,
                fn (mixed $id): bool => is_string($id) && $id !== '',
            ));
        }

        if (! is_string($alertRuleIds)) {
            return null;
        }

        $trimmed = trim($alertRuleIds);

        if ($trimmed === '') {
            return [];
        }

        if (str_starts_with($trimmed, '[')) {
            $decoded = json_decode($trimmed, true);

            if (is_array($decoded)) {
                return array_values(array_filter(
                    $decoded,
                    fn (mixed $id): bool => is_string($id) && $id !== '',
                ));
            }
        }

        if (str_contains($trimmed, ',')) {
            return array_values(array_filter(
                array_map(trim(...), explode(',', $trimmed)),
                fn (string $id): bool => $id !== '',
            ));
        }

        return [$trimmed];
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * Per-alert-rule access is enforced in AlertRuleService::getAlertsStatusHistory().
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'alertRuleIds' => ['required', 'array', 'min:1'],
            'alertRuleIds.*' => ['string', 'regex:/^[0-9a-fA-F]{24}$/'],
            'fromTime' => ['required', 'integer'],
            'toTime' => ['required', 'integer', 'gt:fromTime'],
        ];
    }
}
