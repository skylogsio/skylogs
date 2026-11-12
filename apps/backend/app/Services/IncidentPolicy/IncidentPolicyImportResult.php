<?php

namespace App\Services\IncidentPolicy;

/**
 * What an import did, or would do when running as a dry run.
 */
class IncidentPolicyImportResult
{
    /**
     * @param  list<array{name: string, id: string|null, version: int}>  $created
     * @param  list<array{name: string, id: string|null, version: int}>  $updated
     * @param  list<array{name: string, id: string|null, version: int}>  $unchanged
     * @param  list<IncidentPolicyDslError>  $errors
     */
    public function __construct(
        public readonly bool $dryRun,
        public readonly array $created,
        public readonly array $updated,
        public readonly array $unchanged,
        public readonly array $errors,
    ) {}

    /**
     * @param  list<IncidentPolicyDslError>  $errors
     */
    public static function invalid(array $errors, bool $dryRun): self
    {
        return new self($dryRun, [], [], [], $errors);
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'valid' => $this->isValid(),
            'dryRun' => $this->dryRun,
            'created' => $this->created,
            'updated' => $this->updated,
            'unchanged' => $this->unchanged,
            'errors' => array_map(fn (IncidentPolicyDslError $error) => $error->toArray(), $this->errors),
        ];
    }
}
