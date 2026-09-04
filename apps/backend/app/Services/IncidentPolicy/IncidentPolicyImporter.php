<?php

namespace App\Services\IncidentPolicy;

use App\Enums\IncidentPolicySource;
use App\Models\IncidentPolicy;
use App\Models\User;

/**
 * Applies a YAML definition to the policy collection.
 *
 * Nothing is written unless every document in the input is valid, and re-applying an
 * unchanged file is a no-op rather than a version bump.
 */
class IncidentPolicyImporter
{
    /**
     * Fields that decide whether a re-import is a real change.
     *
     * @var list<string>
     */
    private const TRACKED_FIELDS = ['description', 'enabled', 'ownerId', 'teamIds', 'match', 'grouping', 'incident', 'rules'];

    public function __construct(
        private readonly IncidentPolicyDslParser $parser,
        private readonly IncidentPolicyReferenceResolver $resolver,
    ) {}

    public function import(User $user, string $yaml, bool $dryRun = false): IncidentPolicyImportResult
    {
        $parsed = $this->parser->parse($yaml);

        if (! $parsed->isValid()) {
            return IncidentPolicyImportResult::invalid($parsed->errors, $dryRun);
        }

        $errors = [];
        $pending = [];

        foreach ($parsed->policies as $policy) {
            $resolution = $this->resolver->resolve($policy);

            if ($resolution['errors'] !== []) {
                $errors = [...$errors, ...$resolution['errors']];

                continue;
            }

            $pending[] = $resolution['attributes'];
        }

        if ($errors !== []) {
            return IncidentPolicyImportResult::invalid($errors, $dryRun);
        }

        $created = [];
        $updated = [];
        $unchanged = [];

        foreach ($pending as $attributes) {
            $existing = IncidentPolicy::query()->where('name', $attributes['name'])->first();

            if ($existing === null) {
                $created[] = $this->create($user, $attributes, $dryRun);

                continue;
            }

            if (! $this->hasChanges($existing, $attributes)) {
                $unchanged[] = $this->summarize($existing->name, $existing->id, (int) $existing->version);

                continue;
            }

            $updated[] = $this->update($user, $existing, $attributes, $dryRun);
        }

        return new IncidentPolicyImportResult($dryRun, $created, $updated, $unchanged, []);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{name: string, id: string|null, version: int}
     */
    private function create(User $user, array $attributes, bool $dryRun): array
    {
        if ($dryRun) {
            return $this->summarize($attributes['name'], null, 1);
        }

        $policy = IncidentPolicy::create([
            ...$attributes,
            'version' => 1,
            'source' => IncidentPolicySource::Yaml,
            'createdBy' => $user->id,
            'updatedBy' => $user->id,
        ]);

        return $this->summarize($policy->name, $policy->id, (int) $policy->version);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{name: string, id: string|null, version: int}
     */
    private function update(User $user, IncidentPolicy $existing, array $attributes, bool $dryRun): array
    {
        $version = (int) $existing->version + 1;

        if (! $dryRun) {
            $existing->update([
                ...$attributes,
                'version' => $version,
                'source' => IncidentPolicySource::Yaml,
                'updatedBy' => $user->id,
            ]);
        }

        return $this->summarize($existing->name, $existing->id, $version);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function hasChanges(IncidentPolicy $existing, array $attributes): bool
    {
        foreach (self::TRACKED_FIELDS as $field) {
            if ($this->canonical($existing->getAttribute($field)) !== $this->canonical($attributes[$field])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Key order in a stored document is irrelevant, list order is not.
     */
    private function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $isList = array_is_list($value);
        $value = array_map(fn (mixed $item) => $this->canonical($item), $value);

        if (! $isList) {
            ksort($value);
        }

        return $value;
    }

    /**
     * @return array{name: string, id: string|null, version: int}
     */
    private function summarize(string $name, ?string $id, int $version): array
    {
        return [
            'name' => $name,
            'id' => $id,
            'version' => $version,
        ];
    }
}
