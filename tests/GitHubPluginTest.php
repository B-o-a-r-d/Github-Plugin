<?php

use Board\PluginSdk\Contracts\DefinesActivities;
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


test('the create_issue automation action posts to the GitHub API with templates applied', function () {
    Http::fake(['api.github.com/*' => Http::response([
        'number' => 12,
        'html_url' => 'https://github.com/acme/app/issues/12',
    ], 201)]);

    /** @var \Board\PluginGithub\GitHubPlugin $plugin */
    $plugin = app(PluginRegistry::class)->get('github');

    expect($plugin)->toBeInstanceOf(\Board\PluginSdk\Contracts\ProvidesAutomationActions::class)
        ->and($plugin->automationActions()[0]['key'])->toBe('create_issue');

    $toast = $plugin->runAutomationAction(
        ['token' => 'gh-token'],
        'create_issue',
        ['title' => 'Payer la facture', 'board' => 'Compta', 'list' => 'À faire'],
        ['repo' => 'acme/app', 'title' => '[{board}] {card}', 'body' => 'Depuis la liste {list}', 'labels' => 'bug, board'],
    );

    expect($toast)->toBeInstanceOf(\Board\PluginSdk\PluginToast::class)
        ->and($toast->type)->toBe('success')
        ->and($toast->description)->toBe('acme/app#12')
        ->and($toast->duration)->toBe(8000)
        ->and($toast->actions)->toHaveCount(1)
        ->and($toast->actions[0]['url'])->toBe('https://github.com/acme/app/issues/12');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.github.com/repos/acme/app/issues'
            && $request->hasHeader('Authorization', 'Bearer gh-token')
            && $request['title'] === '[Compta] Payer la facture'
            && $request['body'] === 'Depuis la liste À faire'
            && $request['labels'] === ['bug', 'board'];
    });
});

test('the create_issue action requires a repository', function () {
    /** @var \Board\PluginGithub\GitHubPlugin $plugin */
    $plugin = app(PluginRegistry::class)->get('github');

    expect(fn () => $plugin->runAutomationAction([], 'create_issue', ['title' => 'X'], []))
        ->toThrow(RuntimeException::class);
});
