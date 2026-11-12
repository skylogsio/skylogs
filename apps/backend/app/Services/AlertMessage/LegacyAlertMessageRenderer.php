<?php

namespace App\Services\AlertMessage;

use App\Interfaces\Messageable;
use App\Models\AlertRule;
use App\Models\Notify;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

final class LegacyAlertMessageRenderer
{
    /**
     * @return array<string, string>
     */
    public static function buildContext(AlertRule $alertRule, Messageable $alert): array
    {
        $ctx = [];

        foreach (['name', 'state', 'fireCount', 'alertname', 'type'] as $field) {
            $v = $alertRule->$field ?? null;
            if ($v !== null && ! is_array($v) && ! is_object($v)) {
                $ctx[$field] = (string) $v;
            }
        }

        foreach (Arr::dot($alertRule->toArray()) as $key => $value) {
            $ctx['rule.'.$key] = self::scalarToString($value);
        }

        $alertArray = self::resolvePayload($alert);
        foreach (Arr::dot($alertArray) as $key => $value) {
            $ctx['alert.'.$key] = self::scalarToString($value);
        }

        return $ctx;
    }

    public static function render(AlertRule $alertRule, Messageable $alert, string $template): string
    {
        return self::replacePlaceholders($template, self::buildContext($alertRule, $alert));
    }

    /**
     * @return array<string, mixed>
     */
    public static function resolvePayload(Messageable $source): array
    {
        if ($source instanceof Notify) {
            $alert = $source->alert;

            return is_array($alert) ? $alert : [];
        }

        if ($source instanceof LegacyPayloadMessageable) {
            return $source->toArray();
        }

        if ($source instanceof Model) {
            return $source->toArray();
        }

        return (array) $source;
    }

    private static function scalarToString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_scalar($value)) {
            return (string) $value;
        }

        return Str::limit(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 2000, '…');
    }

    /**
     * @param  array<string, string>  $context
     */
    private static function replacePlaceholders(string $template, array $context): string
    {
        return (string) preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_.-]+)\s*\}\}/',
            function (array $matches) use ($context): string {
                return $context[$matches[1]] ?? '';
            },
            $template,
        );
    }
}
