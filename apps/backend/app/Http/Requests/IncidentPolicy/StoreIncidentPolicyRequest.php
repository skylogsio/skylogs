<?php

namespace App\Http\Requests\IncidentPolicy;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class StoreIncidentPolicyRequest extends IncidentPolicyDefinitionRequest
{
    protected function nameUniqueRule(): Unique
    {
        return Rule::unique('incident_policies', 'name');
    }
}
