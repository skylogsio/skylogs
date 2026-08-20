<?php

namespace App\Services\IncidentPolicy;

class IncidentPolicyDslParseResult
{
    /**
     * @param  list<ParsedIncidentPolicy>  $policies
     * @param  list<IncidentPolicyDslError>  $errors
     */
    public function __construct(
        public readonly array $policies,
        public readonly array $errors,
    ) {}

    public function isValid(): bool
    {
        return $this->errors === [];
    }
}
