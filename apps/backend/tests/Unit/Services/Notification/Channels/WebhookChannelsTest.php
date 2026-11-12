<?php

use App\Enums\EndpointType;
use App\Models\Endpoint;
use App\Services\Notification\Channels\DiscordChannel;
use App\Services\Notification\Channels\MatterMostChannel;
use App\Services\Notification\Channels\TeamsChannel;
use App\Services\Notification\Channels\WebhookChannel;
use App\Support\NotifyMessagePayload;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

dataset('webhook channels', [
    'teams' => [
        fn () => new TeamsChannel,
        EndpointType::TEAMS,
        fn (Request $request) => $request['attachments'][0]['content']['body'][0]['inlines'][0]['text'],
    ],
    'discord' => [
        fn () => new DiscordChannel,
        EndpointType::DISCORD,
        fn (Request $request) => $request['content'],
    ],
    'mattermost' => [
        fn () => new MatterMostChannel,
        EndpointType::MATTER_MOST,
        fn (Request $request) => $request['text'],
    ],
]);

describe('webhook channels', function () {
    beforeEach(function () {
        Http::preventStrayRequests();
    });

    it('posts the channel text to the webhook url', function (WebhookChannel $channel, EndpointType $type, Closure $text) {
        Http::fake(['hooks.example.test/*' => Http::response('', 200)]);

        $result = $channel->send(
            new Endpoint(['type' => $type->value, 'value' => 'https://hooks.example.test/abc']),
            NotifyMessagePayload::fromBody('Disk full'),
        );

        Http::assertSent(fn (Request $request) => $request->url() === 'https://hooks.example.test/abc'
            && $text($request) === 'Disk full');

        expect($result->isSent())->toBeTrue()
            ->and($result->response)->toBeNull();
    })->with('webhook channels');

    it('treats an empty 202 or 204 answer as sent', function (int $status) {
        Http::fake(['hooks.example.test/*' => Http::response('', $status)]);

        $result = (new TeamsChannel)->send(
            new Endpoint(['type' => 'teams', 'value' => 'https://hooks.example.test/abc']),
            NotifyMessagePayload::fromBody('x'),
        );

        expect($result->isSent())->toBeTrue()
            ->and($result->httpStatus)->toBe($status);
    })->with([202, 204]);

    it('stores the raw text body for inspection', function () {
        Http::fake(['hooks.example.test/*' => Http::response('1', 200)]);

        $result = (new TeamsChannel)->send(
            new Endpoint(['type' => 'teams', 'value' => 'https://hooks.example.test/abc']),
            NotifyMessagePayload::fromBody('x'),
        );

        expect($result->response)->toBe(1);
    });

    it('fails without retry on client errors', function (WebhookChannel $channel, EndpointType $type, Closure $text) {
        Http::fake(['hooks.example.test/*' => Http::response('Webhook not found', 404)]);

        $result = $channel->send(
            new Endpoint(['type' => $type->value, 'value' => 'https://hooks.example.test/abc']),
            NotifyMessagePayload::fromBody('x'),
        );

        expect($result->isSent())->toBeFalse()
            ->and($result->retryable)->toBeFalse()
            ->and($result->error)->toBe('HTTP 404')
            ->and($result->response)->toBe('Webhook not found');
    })->with('webhook channels');

    it('retries rate limits and server errors', function (int $status) {
        Http::fake(['hooks.example.test/*' => Http::response('', $status)]);

        $result = (new DiscordChannel)->send(
            new Endpoint(['type' => 'discord', 'value' => 'https://hooks.example.test/abc']),
            NotifyMessagePayload::fromBody('x'),
        );

        expect($result->retryable)->toBeTrue();
    })->with([429, 500, 503]);
});
