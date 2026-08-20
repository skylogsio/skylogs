<?php

namespace App\Http\Requests\PostMortem;

use App\Enums\PostMortemStatus;
use App\Enums\RootCauseMethod;
use App\Http\Requests\Concerns\ValidatesMongoReferences;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePostMortemRequest extends FormRequest
{
    use ValidatesMongoReferences;

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
            'status' => ['nullable', Rule::enum(PostMortemStatus::class)],
            'summary' => ['required', 'string', 'max:10000'],
            'impact' => ['nullable', 'string', 'max:10000'],
            'detection' => ['nullable', 'string', 'max:10000'],
            'resolution' => ['nullable', 'string', 'max:10000'],

            'rootCause' => ['nullable', 'array'],
            'rootCause.method' => ['nullable', Rule::enum(RootCauseMethod::class)],
            'rootCause.whys' => ['nullable', 'array', 'max:10'],
            'rootCause.whys.*' => ['required', 'string', 'max:1000'],
            'rootCause.contributingFactors' => ['nullable', 'array', 'max:50'],
            'rootCause.contributingFactors.*' => ['required', 'string', 'max:1000'],
            'rootCause.statement' => ['nullable', 'string', 'max:5000'],

            'whatWentWell' => ['nullable', 'array', 'max:50'],
            'whatWentWell.*' => ['required', 'string', 'max:1000'],
            'whatWentWrong' => ['nullable', 'array', 'max:50'],
            'whatWentWrong.*' => ['required', 'string', 'max:1000'],
            'lessonsLearned' => ['nullable', 'array', 'max:50'],
            'lessonsLearned.*' => ['required', 'string', 'max:1000'],

            'authorId' => ['nullable', 'string', 'size:24'],
            'reviewerIds' => ['nullable', 'array', 'max:20'],
            'reviewerIds.*' => ['required', 'string', 'size:24'],
            'dueAt' => ['nullable', 'date'],
        ];
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $this->assertReferencesExist($validator, 'authorId', User::class, 'User', $this->input('authorId'));
                $this->assertReferencesExist($validator, 'reviewerIds', User::class, 'User', $this->input('reviewerIds'));
            },
        ];
    }
}
