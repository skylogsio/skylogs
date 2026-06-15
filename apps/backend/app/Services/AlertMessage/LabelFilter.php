<?php

namespace App\Services\AlertMessage;

final class LabelFilter
{
    /**
     * @param  list<string>|null  $include
     * @param  list<string>|null  $exclude
     */
    public function __construct(
        public readonly ?array $include = null,
        public readonly ?array $exclude = null,
    ) {}

    public static function fromDirective(string $directive): self
    {
        $directive = trim($directive);

        if ($directive === '' || $directive === '*') {
            return new self(include: null, exclude: null);
        }

        if (preg_match('/^\*\s+exclude=(.+)$/i', $directive, $matches)) {
            return new self(include: null, exclude: self::splitKeys($matches[1]));
        }

        return new self(include: self::splitKeys($directive), exclude: null);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    public function keys(array $data): array
    {
        if ($this->include !== null) {
            return array_values(array_filter(
                $this->include,
                fn (string $key): bool => ! empty($data[$key]),
            ));
        }

        $keys = array_keys(array_filter(
            $data,
            fn (mixed $value): bool => $value !== null && $value !== '',
        ));

        if ($this->exclude === null) {
            return $keys;
        }

        return array_values(array_diff($keys, $this->exclude));
    }

    /**
     * @return list<string>
     */
    private static function splitKeys(string $value): array
    {
        return array_values(array_filter(array_map(
            trim(...),
            explode(',', $value),
        )));
    }
}
