<?php

namespace App\Services;

use App\Interfaces\Messageable;
use App\Models\AlertRule;
use App\Services\AlertMessage\AlertMessageTemplateRenderer;
use App\Services\Notification\ChannelRegistry;
use App\Support\NotifyMessagePayload;

class NotifyMessageComposer
{
    public static function buildMessages(?AlertRule $alertRule, Messageable $alert): array
    {
        return self::fromMessageable($alert)->toArray();
    }

    public static function fromMessageable(Messageable $alert): NotifyMessagePayload
    {
        return NotifyMessagePayload::fromMessageable($alert);
    }

    /**
     * The rendered template replaces the text on every channel. Chat content
     * (an array with a message key, e.g. the Acknowledge button) keeps its
     * metadata; plain channel text is dropped so the template wins.
     */
    public static function composeFromSingleTemplate(AlertRule $alertRule, Messageable $alert, string $template): NotifyMessagePayload
    {
        $body = AlertMessageTemplateRenderer::make()->render($alertRule, $alert, $template);

        $overrides = [];

        foreach (app(ChannelRegistry::class)->types() as $type) {
            $content = $alert->messageFor($type);

            if (is_array($content) && array_key_exists('message', $content)) {
                $content['message'] = $body;
                $overrides[$type->value] = $content;
            }
        }

        return NotifyMessagePayload::fromBody($body, $overrides);
    }
}
