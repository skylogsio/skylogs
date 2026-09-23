<?php

namespace App\Services\Health\Contracts;

use App\Models\AlertRule;
use App\Services\Health\HealthProbe;

interface HealthTarget
{
    /**
     * Validation rules for the `target` object. Keys are relative to that object.
     *
     * @return array<string, mixed>
     */
    public function rules(): array;

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function attributes(array $validated): array;

    public function probe(AlertRule $rule): HealthProbe;
}
