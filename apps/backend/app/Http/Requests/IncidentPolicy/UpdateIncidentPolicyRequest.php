<?php

namespace App\Http\Requests\IncidentPolicy;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class UpdateIncidentPolicyRequest extends IncidentPolicyDefinitionRequest
{
    protected function nameUniqueRule(): Unique
    {
        return Rule::unique('incident_policies', 'name')->ignore($this->route('id'), '_id');
    }
}
