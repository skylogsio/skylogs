<?php

namespace App\Services;

use App\Models\GroupAlertRule;
use Illuminate\Support\Collection;

class GroupService
{
    public static function GetEndpointsByAlertId($alertRule)
    {
        $groups = GroupAlertRule::where('alert_ids', $alertRule->_id)->get();
        $endpointIds = collect();
        if ($groups->isNotEmpty()) {
            foreach ($groups as $group) {
                $endpointIds = $endpointIds->merge($group->endpoint_ids);
            }

        } else {

            $endpointIds = $alertRule->endpoint_ids ?? [];
        }
        if ($endpointIds instanceof Collection) {
            return $endpointIds->toArray();
        } else
            return $endpointIds;
    }


}
