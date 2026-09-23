<?php

namespace App\Services\Health;

use App\Enums\HealthAlertType;
use App\Models\AlertRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class HealthRuleInput
{
    /**
     * @param  array<string, mixed>  $target
     */
    public function __construct(
        public HealthAlertType $checkType,
        public int $threshold,
        public int $intervalSeconds,
        public array $target,
    ) {}

    public static function validate(Request $request): self
    {
        Validator::make($request->all(), [
            'checkType' => ['required', Rule::enum(HealthAlertType::class)->only(HealthAlertType::creatable())],
            'threshold' => ['nullable', 'integer', 'min:1', 'max:20'],
            'intervalSeconds' => ['nullable', 'integer', 'min:10', 'max:3600'],
            'target' => ['required', 'array'],
        ])->validate();

        $checkType = HealthAlertType::from($request->string('checkType')->toString());
        $validatedTarget = Validator::make(
            $request->input('target', []),
            app(HealthTargetRegistry::class)->for($checkType)->rules(),
        )->validate();

        return new self(
            checkType: $checkType,
            threshold: (int) ($request->input('threshold') ?? 3),
            intervalSeconds: (int) ($request->input('intervalSeconds') ?? 30),
            target: app(HealthTargetRegistry::class)->for($checkType)->attributes($validatedTarget),
        );
    }

    public function resetsCheck(AlertRule $rule): bool
    {
        $currentType = $rule->checkType instanceof HealthAlertType
            ? $rule->checkType->value
            : (string) $rule->checkType;

        return $currentType !== $this->checkType->value
            || (int) $rule->threshold !== $this->threshold
            || (int) ($rule->intervalSeconds ?? 30) !== $this->intervalSeconds
            || self::normalize(is_array($rule->target) ? $rule->target : []) !== self::normalize($this->target);
    }

    /**
     * @param  array<mixed>  $value
     */
    private static function normalize(array $value): string
    {
        return json_encode(self::sortKeys($value)) ?: '';
    }

    /**
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    private static function sortKeys(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = self::sortKeys($item);
            }
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }
}
