<?php

use App\Mcp\Servers\SkylogsServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::local('skylogs', SkylogsServer::class);

Mcp::web('/mcp/skylogs', SkylogsServer::class)
    ->middleware('mcpAuth');
