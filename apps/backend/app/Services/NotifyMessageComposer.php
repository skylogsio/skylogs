<?php

namespace App\Services;

use App\Interfaces\Messageable;
use App\Models\AlertRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Builds per-channel notify text from optional AlertRule.notifyTemplates.
 *
 * Store on AlertRule (Mongo) a document like:
 * {
 *   "matterMost": "…",
 *   "telegram": "…",
 *   "teams": "…",
 *   "email": "…",
 *   "sms": "…",
 *   "discord": "…",
 *   "call": "…",
 *   "default": "…"
 * }
 *
 * Placeholders use double braces. Keys are merged from:
 * - Top-level shortcuts: name, state, fireCount, alertname, type (from the alert rule)
 * - rule.* — dotted paths from the alert rule document
 * - alert.* — dotted paths from the firing payload model/array
 */
class NotifyMessageComposer
{
    private const CHANNEL_KEYS = [
        'matterMostMessage' => 'matterMost',
        'telegram' => 'telegram',
        'teamsMessage' => 'teams',
        'emailMessage' => 'email',
        'smsMessage' => 'sms',
        'discordMessage' => 'discord',
        'callMessage' => 'call',
        'defaultMessage' => 'default',
    ];

    public static function buildMessages(?AlertRule $alertRule, Messageable $alert): array
    {
        if ($alertRule === null) {
            return self::fromMessageable($alert);
        }

        $templates = $alertRule->notifyTemplates ?? null;
        if (! is_array($templates) || $templates === []) {
            return self::fromMessageable($alert);
        }

        $context = self::buildContext($alertRule, $alert);

        $messages = [];
        foreach (self::CHANNEL_KEYS as $messageKey => $templateKey) {
            $template = $templates[$templateKey] ?? null;
            if (! is_string($template) || $template === '') {
                $messages[$messageKey] = self::channelFallback($alert, $messageKey);

                continue;
            }

            if ($messageKey === 'telegram') {
                $messages[$messageKey] = self::renderTelegram($alert, $template, $context);

                continue;
            }

            $messages[$messageKey] = self::replacePlaceholders($template, $context);
        }

        return $messages;
    }

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

    private static function channelFallback(Messageable $alert, string $messageKey): mixed
    {
        return match ($messageKey) {
            'matterMostMessage' => $alert->matterMostMessage(),
            'telegram' => $alert->telegram(),
            'teamsMessage' => $alert->teamsMessage(),
            'emailMessage' => $alert->emailMessage(),
            'smsMessage' => $alert->smsMessage(),
            'discordMessage' => $alert->discordMessage(),
            'callMessage' => $alert->callMessage(),
            'defaultMessage' => $alert->defaultMessage(),
            default => $alert->defaultMessage(),
        };
    }

    /**
     * @param  array<string, string>  $context
     */
    private static function renderTelegram(Messageable $alert, string $template, array $context): array|string
    {
        $base = $alert->telegram();
        $body = self::replacePlaceholders($template, $context);

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
