<?php

namespace App\Services\IncidentPolicy;

/**
 * A single problem found in a policy definition, addressed by its path in the document.
 */
class IncidentPolicyDslError
{
    public function __construct(
        public readonly string $path,
        public readonly string $message,
    ) {}

    /**
     * @return array{path: string, message: string}
     */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'message' => $this->message,
        ];
    }
}
