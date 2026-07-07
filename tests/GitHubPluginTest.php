<?php

use Board\PluginSdk\Contracts\DefinesActivities;
use Board\PluginSdk\Contracts\EnrichesCards;
use Board\PluginSdk\Contracts\ProvidesListSource;
use Board\PluginSdk\Contracts\ProvidesMcpTools;
use Board\PluginSdk\Contracts\ProvidesOAuth;
use Board\PluginSdk\PluginRegistry;
use Illuminate\Support\Facades\Http;

function githubPlugin(): ProvidesListSource
{
    return app(PluginRegistry::class)->get('github');
}

test('the plugin auto-registers into the host registry via its provider', function () {
    $plugin = app(PluginRegistry::class)->get('github');

    expect($plugin)->not->toBeNull()
        ->and($plugin->label())->toBe('GitHub')
        ->and($plugin->requiresOAuth())->toBeTrue()
        ->and($plugin->oauthProvider())->toBe('github')
        ->and($plugin)->toBeInstanceOf(ProvidesListSource::class)
        ->and($plugin)->toBeInstanceOf(DefinesActivities::class)
        ->and($plugin)->toBeInstanceOf(EnrichesCards::class)
        ->and($plugin)->toBeInstanceOf(ProvidesMcpTools::class)
        ->and($plugin)->toBeInstanceOf(ProvidesOAuth::class);
});

test('the plugin ships its own file translations', function () {
    $plugin = githubPlugin();

    app()->setLocale('en');
    expect($plugin->description())->toBe('Read-only lists of a GitHub repository\'s commits, pull requests and issues.')
        ->and(trans('github::messages.mode.commits'))->toBe('Recent commits');

    app()->setLocale('fr');
    expect(trans('github::messages.mode.commits'))->toBe('Derniers commits');
});

test('it declares the github oauth endpoints and reads back the account', function () {
    $plugin = githubPlugin();

    expect($plugin->authorizeUrl())->toBe('https://github.com/login/oauth/authorize')
        ->and($plugin->tokenUrl())->toBe('https://github.com/login/oauth/access_token')
        ->and($plugin->scopes())->toBe(['repo', 'read:org'])
        ->and($plugin->authorizeParameters())->toBe(['allow_signup' => 'false']);

    Http::fake(['api.github.com/user' => Http::response(['login' => 'octocat'])]);

    expect($plugin->resolveAccount('gho_token'))->toBe('octocat');
});

test('it maps recent commits into read-only list items', function () {
    Http::fake([
        'api.github.com/repos/*/commits*' => Http::response([
            [
                'sha' => 'abc1234567890',
                'html_url' => 'https://github.com/o/r/commit/abc1234',
                'commit' => ['message' => "Fix the widget\n\nbody", 'author' => ['name' => 'Octo Cat', 'date' => '2026-07-07T10:00:00Z']],
            ],
        ]),
    ]);

    $items = githubPlugin()->items(['token' => 't'], 'commits', ['repository' => 'o/r', 'limit' => 15]);

    expect($items)->toHaveCount(1)
        ->and($items->first()->title)->toBe('Fix the widget')
        ->and($items->first()->subtitle)->toBe('Octo Cat · abc1234')
        ->and($items->first()->externalRef)->toBe('abc1234567890');
});

test('it resolves a commit ref into a card enrichment payload', function () {
    Http::fake([
        'api.github.com/repos/*/commits/*' => Http::response([
            'sha' => 'abc1234567890',
            'html_url' => 'https://github.com/o/r/commit/abc1234',
            'commit' => ['message' => "Fix the bug\n\nbody", 'author' => ['name' => 'Octo', 'date' => '2026-07-07T10:00:00Z']],
        ]),
    ]);

    $payload = githubPlugin()->resolveCardRef(['token' => 't'], 'commit', 'o/r@abc1234567890');

    expect($payload)->not->toBeNull()
        ->and($payload['ref_id'])->toBe('abc1234567890')
        ->and($payload['title'])->toBe('Fix the bug');
});

test('an unrecognized ref resolves to null without a request', function () {
    Http::fake(['api.github.com/*' => Http::response([], 404)]);

    expect(githubPlugin()->resolveCardRef(['token' => 't'], 'commit', 'garbage-input'))->toBeNull();
});
