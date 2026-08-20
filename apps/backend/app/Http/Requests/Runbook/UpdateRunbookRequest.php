<?php

namespace App\Http\Requests\Runbook;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class UpdateRunbookRequest extends RunbookRequest
{
    protected function slugUniqueRule(): Unique
    {
        return Rule::unique('runbooks', 'slug')->ignore($this->route('id'), '_id');
    }
}
