<?php

namespace Board\PluginGithub\Mcp;

use Board\PluginGithub\GitHubClient;
use Board\PluginSdk\Contracts\PluginContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

/**
 * Lists recent commits of a repository, using the GitHub Power-Up's stored
 * token. Fully decoupled from the host: board access + the (encrypted) config
 * are resolved through the SDK's PluginContext, which the host binds.
 */
#[Description('List recent commits of a GitHub repository connected to a board through the GitHub Power-Up.')]
class GithubCommitsTool extends Tool
{
    public function handle(Request $request): Response
    {
        $request->validate([
            'board_id' => 'required|string',
            'repository' => 'required|string',
        ]);

        $context = app(PluginContext::class);
        $boardId = (string) $request->get('board_id');

        if (! $context->userCanAccessBoard($boardId)) {
            return Response::error('Board not found or access denied.');
        }

        $config = $context->boardPluginConfig($boardId, 'github');

        if ($config === null) {
            return Response::error('The GitHub Power-Up is not installed/active on this board.');
        }

        $commits = (new GitHubClient($config['token'] ?? null))
            ->recentCommits((string) $request->get('repository'), 20);

        return Response::json([
            'commits' => collect($commits)->map(fn (array $commit): array => [
                'sha' => $commit['sha'] ?? null,
                'message' => Str::of((string) data_get($commit, 'commit.message', ''))->explode("\n")->first(),
                'author' => data_get($commit, 'commit.author.name'),
                'date' => data_get($commit, 'commit.author.date'),
                'url' => $commit['html_url'] ?? null,
            ])->all(),
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'board_id' => $schema->string()->description('The board public id (ULID).')->required(),
            'repository' => $schema->string()->description('The repository, as owner/repo.')->required(),
        ];
    }
}
