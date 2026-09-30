<?php

namespace App\Enums;

enum HealthAlertType: string
{
    case DATASOURCE = 'datasource';
    case HTTP = 'http';

    /**
     * Legacy cluster checks. Existing alert rules may still store these, so
     * they must stay castable, but new rules cannot use them.
     */
    case AGENT_CLUSTER = 'agentCluster';
    case SOURCE_CLUSTER = 'sourceCluster';

    /**
     * @return list<self>
     */
    public static function creatable(): array
    {
        return [
            self::DATASOURCE,
            self::HTTP,
        ];
    }
}
