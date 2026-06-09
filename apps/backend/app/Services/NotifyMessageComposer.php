<?php

namespace App\Services;

use App\Interfaces\Messageable;
use App\Models\AlertRule;
use App\Support\NotifyMessagePayload;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class NotifyMessageComposer
{
    /**
     * @return array{body: string, overrides: array<string, mixed>}
     */
    public static function buildMessages(?AlertRule $alertRule, Messageable $alert): array
    {
        return self::fromMessageable($alert)->toArray();
    }

    public static function fromMessageable(Messageable $alert): NotifyMessagePayload
    {
        return NotifyMessagePayload::fromMessageable($alert);
    }

    public static function composeFromSingleTemplate(AlertRule $alertRule, Messageable $alert, string $template): NotifyMessagePayload
    {
        $context = self::buildContext($alertRule, $alert);
        $body = self::replacePlaceholders($template, $context);

        $overrides = [];
        $telegramBase = $alert->telegram();

        if (is_array($telegramBase)) {
            $telegramBase['message'] = $body;
            $overrides['telegram'] = $telegramBase;
        }

        return NotifyMessagePayload::fromBody($body, $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildContext(AlertRule $alertRule, Messageable $alert): array
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

        $alertArray = $alert instanceof Model ? $alert->toArray() : (array) $alert;
        foreach (Arr::dot($alertArray) as $key => $value) {
            $ctx['alert.'.$key] = self::scalarToString($value);
        }

        return $ctx;
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
            function (array $m) use ($context) {
                $key = $m[1];

                return $context[$key] ?? '';
            },
            $template
        );
    }
}
