<?php

namespace App\Http\Requests\PostMortem;

use App\Http\Requests\Concerns\ValidatesPostMortemPayload;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePostMortemRequest extends FormRequest
{
    use ValidatesPostMortemPayload;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->postMortemFieldRules();
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $this->assertPostMortemReferences($validator);
            },
        ];
    }
}
