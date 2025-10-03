<?php

namespace App\Enums;


enum FlowEndpointStepType: string
{
    case WAIT = "wait";
    case ENDPOINT = "endpoint";

}
