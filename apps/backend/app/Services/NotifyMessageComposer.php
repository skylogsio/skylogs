<?php

namespace App\Services;

use App\Interfaces\Messageable;
use App\Models\AlertRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class NotifyMessageComposer
{
    /**
     * @return array<string, mixed>
     */
    public static function buildMessages(?AlertRule $alertRule, Messageable $alert): array
    {
        return self::fromMessageable($alert);
    }

    /**
     * Render one template string into all channel message keys.
     *
     * @return array<string, mixed>
     */
    public static function composeFromSingleTemplate(AlertRule $alertRule, Messageable $alert, string $template): array
    {
        $context = self::buildContext($alertRule, $alert);
        $body = self::replacePlaceholders($template, $context);

        return self::applyBodyToAllChannels($alert, $body);
    }

    /**
     * @return array<string, mixed>
     */
    public static function fromMessageable(Messageable $alert): array
    {
        return [
            'matterMostMessage' => $alert->matterMostMessage(),
            'telegram' => $alert->telegram(),
            'teamsMessage' => $alert->teamsMessage(),
            'emailMessage' => $alert->emailMessage(),
            'smsMessage' => $alert->smsMessage(),
            'discordMessage' => $alert->discordMessage(),
            'callMessage' => $alert->callMessage(),
            'defaultMessage' => $alert->defaultMessage(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function applyBodyToAllChannels(Messageable $alert, string $body): array
    {
        return [
            'matterMostMessage' => $body,
            'telegram' => self::applyTelegramBody($alert, $body),
            'teamsMessage' => $body,
            'emailMessage' => $body,
            'smsMessage' => $body,
            'discordMessage' => $body,
            'callMessage' => $body,
            'defaultMessage' => $body,
        ];
    }

    private static function applyTelegramBody(Messageable $alert, string $body): array|string
    {
        $base = $alert->telegram();

        if (is_array($base)) {
            $base['message'] = $body;

            return $base;
        }

        return $body;
    }

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
