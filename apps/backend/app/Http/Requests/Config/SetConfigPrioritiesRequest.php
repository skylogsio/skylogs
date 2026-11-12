<?php

namespace App\Http\Requests\Config;

use App\Http\Requests\Concerns\ValidatesMongoReferences;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

abstract class SetConfigPrioritiesRequest extends FormRequest
{
    use ValidatesMongoReferences;

    /**
     * @return class-string<Model>
     */
    abstract protected function modelClass(): string;

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
            'ids' => ['present', 'array'],
            'ids.*' => ['string', 'distinct', 'regex:/^[0-9a-fA-F]{24}$/'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $this->assertReferencesExist(
                    $validator,
                    'ids',
                    $this->modelClass(),
                    'Config',
                    $this->input('ids'),
                );
            },
        ];
    }
}
