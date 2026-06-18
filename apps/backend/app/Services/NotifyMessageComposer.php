<?php

namespace App\Services;

use App\Interfaces\Messageable;
use App\Models\AlertRule;
use App\Services\AlertMessage\AlertMessageTemplateRenderer;
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

    public static function composeFromSingleTemplate(AlertRule $alertRule, Messageable $alert, string $template): NotifyMessagePayload
    {
        $body = AlertMessageTemplateRenderer::make()->render($alertRule, $alert, $template);

        $overrides = [];
        $telegramBase = $alert->telegram();

        if (is_array($telegramBase)) {
            $telegramBase['message'] = $body;
            $overrides['telegram'] = $telegramBase;
        }

        $baleBase = $alert->baleMessage();

        if (is_array($baleBase)) {
            $baleBase['message'] = $body;
            $overrides['bale'] = $baleBase;
        }

        return NotifyMessagePayload::fromBody($body, $overrides);
    }
}
