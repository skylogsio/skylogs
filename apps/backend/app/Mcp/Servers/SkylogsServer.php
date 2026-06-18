<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\GetFiredAlertsTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Skylogs Server')]
#[Version('0.0.1')]
#[Instructions('Skylogs monitoring MCP server for incident investigation. Use get-fired-alerts without parameters to discover all currently critical alert rules and their firing alert instances. Pass alertRuleId when you already know which alert rule to inspect.')]
class SkylogsServer extends Server
{
    protected array $tools = [
        GetFiredAlertsTool::class,
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];
}
