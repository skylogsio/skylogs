<?php

it('rejects unauthenticated requests to the skylogs mcp endpoint', function () {
    config(['mcp.api_token' => 'test-mcp-token']);

    $this->postJson('/mcp/skylogs', [])
        ->assertUnauthorized();
});

it('accepts authenticated requests to the skylogs mcp endpoint', function () {
    config(['mcp.api_token' => 'test-mcp-token']);

    $this->withToken('test-mcp-token')
        ->postJson('/mcp/skylogs', [])
        ->assertSuccessful();
});

it('returns service unavailable when the mcp api token is not configured', function () {
    config(['mcp.api_token' => null]);

    $this->withToken('any-token')
        ->postJson('/mcp/skylogs', [])
        ->assertServiceUnavailable();
});
