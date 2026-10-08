<?php

use App\Mcp\Servers\CarServer;
use Laravel\Mcp\Facades\Mcp;

// Car search for AI agents over the Model Context Protocol (read-only, public data)
Mcp::web('/mcp', CarServer::class)->middleware('throttle:60,1');
Mcp::local('kai-cars', CarServer::class);
