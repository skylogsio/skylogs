<?php

namespace App\Enums;


enum HealthAlertType:string
{
    case AGENT_CLUSTER = "agentCluster";
    case SOURCE_CLUSTER = "sourceCluster";

    case DATASOURCE = "datasource";

}
