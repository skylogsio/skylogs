<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;

/**
 * Existence checks for MongoDB id references inside a request body.
 *
 * The `exists` rule is avoided on purpose: these documents are addressed by `_id`, and
 * reporting the offending id per path reads better in a form than a generic failure.
 */
trait ValidatesMongoReferences
{
    /**
     * @param  class-string<Model>  $modelClass
     * @param  mixed  $value  A single id, or a list of ids reported per index
     */
    protected function assertReferencesExist(
        Validator $validator,
        string $path,
        string $modelClass,
        string $label,
        mixed $value,
    ): void {
        if ($value === null || $value === '' || $value === []) {
            return;
        }

        $references = is_array($value) ? $value : [$value];
        $ids = array_values(array_filter($references, 'is_string'));

        if ($ids === []) {
            return;
        }

        $existing = $modelClass::query()
            ->whereIn('_id', $ids)
            ->get()
            ->map(fn (Model $model) => (string) $model->getKey())
            ->all();

        foreach ($references as $index => $reference) {
            if (! is_string($reference) || in_array($reference, $existing, true)) {
                continue;
            }

            $validator->errors()->add(
                is_array($value) ? $path.'.'.$index : $path,
                "{$label} '{$reference}' not found.",
            );
        }
    }
}
