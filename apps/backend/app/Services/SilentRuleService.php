<?php

namespace App\Services;

use App\Models\AlertRule;
use App\Models\SilentRule;
use Carbon\Carbon;

class SilentRuleService
{
    public static function getCurrentSilents()
    {
        $now = Carbon::now()->format('H:i:s');

        $silentRule = SilentRule::get();
        $alert_ids = collect();

        foreach ($silentRule as $silent) {
            if ($silent->periods[0]['from'] <= $now && $silent->periods[0]['to'] >= $now) {
                switch ($silent->type) {
                    case SilentRule::TYPES:

                        $query = AlertRule::query();

                        $query = $query->where('type', $silent->alert_rule_types);

                        $alerts = $query->get()->pluck('_id');
                        $alert_ids = $alert_ids->merge($alerts);

                        break;
                    case SilentRule::TAGS:

                        $query = AlertRule::query();

                        if (! empty($silent->tags)) {
                            $query = $query->whereIn('tags', $silent->tags);
                        } else {
                            break;
                        }

                        $alerts = $query->get()->pluck('_id');
                        $alert_ids = $alert_ids->merge($alerts);

                        break;
                    case SilentRule::ALERTNAME:
                        $alert_ids = $alert_ids->merge($silent->alertRuleIds);
                        break;
                }
            }
        }

        return $alert_ids->toArray();

    }
}
