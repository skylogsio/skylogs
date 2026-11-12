<?php

namespace App\Http\Requests\Concerns;

use App\Enums\PostMortemStatus;
use App\Enums\RootCauseMethod;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

trait ValidatesPostMortemPayload
{
    use ValidatesMongoReferences;

    /**
     * @return array<string, mixed>
     */
    protected function postMortemFieldRules(string $prefix = ''): array
    {
        $summaryRequired = $prefix === '' ? 'required' : 'required_with:'.$prefix;

        return [
            $this->prefixed($prefix, 'status') => ['nullable', Rule::enum(PostMortemStatus::class)],
            $this->prefixed($prefix, 'summary') => [$summaryRequired, 'string', 'max:10000'],
            $this->prefixed($prefix, 'impact') => ['nullable', 'string', 'max:10000'],
            $this->prefixed($prefix, 'detection') => ['nullable', 'string', 'max:10000'],
            $this->prefixed($prefix, 'resolution') => ['nullable', 'string', 'max:10000'],

            $this->prefixed($prefix, 'rootCause') => ['nullable', 'array'],
            $this->prefixed($prefix, 'rootCause.method') => ['nullable', Rule::enum(RootCauseMethod::class)],
            $this->prefixed($prefix, 'rootCause.whys') => ['nullable', 'array', 'max:10'],
            $this->prefixed($prefix, 'rootCause.whys.*') => ['required', 'string', 'max:1000'],
            $this->prefixed($prefix, 'rootCause.contributingFactors') => ['nullable', 'array', 'max:50'],
            $this->prefixed($prefix, 'rootCause.contributingFactors.*') => ['required', 'string', 'max:1000'],
            $this->prefixed($prefix, 'rootCause.statement') => ['nullable', 'string', 'max:5000'],

            $this->prefixed($prefix, 'whatWentWell') => ['nullable', 'array', 'max:50'],
            $this->prefixed($prefix, 'whatWentWell.*') => ['required', 'string', 'max:1000'],
            $this->prefixed($prefix, 'whatWentWrong') => ['nullable', 'array', 'max:50'],
            $this->prefixed($prefix, 'whatWentWrong.*') => ['required', 'string', 'max:1000'],
            $this->prefixed($prefix, 'lessonsLearned') => ['nullable', 'array', 'max:50'],
            $this->prefixed($prefix, 'lessonsLearned.*') => ['required', 'string', 'max:1000'],

            $this->prefixed($prefix, 'authorId') => ['nullable', 'string', 'size:24'],
            $this->prefixed($prefix, 'reviewerIds') => ['nullable', 'array', 'max:20'],
            $this->prefixed($prefix, 'reviewerIds.*') => ['required', 'string', 'size:24'],
            $this->prefixed($prefix, 'dueAt') => ['nullable', 'date'],
        ];
    }

    protected function assertPostMortemReferences(Validator $validator, string $prefix = ''): void
    {
        $authorPath = $this->prefixed($prefix, 'authorId');
        $reviewerPath = $this->prefixed($prefix, 'reviewerIds');

        $this->assertReferencesExist($validator, $authorPath, User::class, 'User', data_get($this->all(), $authorPath));
        $this->assertReferencesExist($validator, $reviewerPath, User::class, 'User', data_get($this->all(), $reviewerPath));
    }

    protected function prefixed(string $prefix, string $field): string
    {
        return $prefix === '' ? $field : $prefix.'.'.$field;
    }
}
