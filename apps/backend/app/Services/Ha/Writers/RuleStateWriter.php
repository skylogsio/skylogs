<?php

namespace App\Services\Ha\Writers;

use App\Models\AlertRule;
use App\Services\Ha\AlertStateKey;
use App\Services\Ha\AlertStateValue;

/**
 * Sentry and Metabase keep no check document: their webhooks write straight
 * onto the alert rule, so the rule is the slot and the applier's own aggregate
 * write is the entire apply.
 *
 * No history is written here. Their timelines are built from the raw webhook
 * documents, which carry the provider's payload; synthesising one from the
 * replicated state would put invented alert content in front of an operator.
 */
final class RuleStateWriter implements StateWriter
{
    public function localState(AlertRule $alertRule, AlertStateValue $value): string
    {
        return $alertRule->state ?? AlertRule::UNKNOWN;
    }

    public function write(AlertRule $alertRule, AlertStateValue $value): void {}

    public function writeHistory(AlertRule $alertRule, AlertStateValue $value): void {}

    public function remove(AlertRule $alertRule, AlertStateKey $key): void {}
}
