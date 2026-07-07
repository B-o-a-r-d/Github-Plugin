<?php

namespace Board\PluginGithub;

use Board\PluginSdk\Contracts\Plugin;
use Board\PluginSdk\Contracts\ProvidesListSource;
use Board\PluginSdk\PluginListItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * GitHub Power-Up: turns a board list into a read-only feed of a repository's
 * recent commits, open pull requests or open issues.
 */
class GitHubPlugin implements Plugin, ProvidesListSource
{
    public static function key(): string
    {
        return 'github';
    }

    public function label(): string
    {
        return 'GitHub';
    }

    public function description(): string
    {
        return 'Listes en lecture seule des commits, pull requests et issues d\'un dépôt GitHub.';
    }

    public function icon(): string
    {
        return 'github-logo';
    }

    public function requiresOAuth(): bool
    {
        return true;
    }

    public function oauthProvider(): ?string
    {
        return 'github';
    }

    /**
     * The OAuth app credentials — entered from the host's plugin config modal
     * (never from environment files), stored encrypted on the instance and used
     * to drive the OAuth flow.
     */
    public function configFields(array $config = []): array
    {
        return [
            [
                'key' => 'client_id',
                'label' => 'GitHub OAuth · Client ID',
                'type' => 'text',
                'placeholder' => 'Iv1.xxxxxxxxxxxx',
                'help' => 'Depuis GitHub → Settings → Developer settings → OAuth Apps.',
            ],
            [
                'key' => 'client_secret',
                'label' => 'GitHub OAuth · Client Secret',
                'type' => 'password',
                'help' => 'Laissez vide pour conserver la valeur enregistrée.',
            ],
        ];
    }

    public function sourceModes(): array
    {
        return [
            ['key' => 'commits', 'label' => 'Derniers commits'],
            ['key' => 'pull_requests', 'label' => 'Pull requests ouvertes'],
            ['key' => 'issues', 'label' => 'Issues ouvertes'],
        ];
    }

    public function listConfigFields(array $config = []): array
    {
        $repos = $this->client($config)->listRepos();

        // Dynamic repository picker when the token can enumerate repos;
        // otherwise degrade to a free-text "owner/repo" field.
        if ($repos !== []) {
            return [[
                'key' => 'repository',
                'label' => 'Dépôt',
                'type' => 'select',
                'options' => array_map(fn (array $repo): array => [
                    'value' => $repo['full_name'],
                    'label' => $repo['full_name'].($repo['private'] ? ' 🔒' : ''),
                ], $repos),
            ]];
        }

        return [[
            'key' => 'repository',
            'label' => 'Dépôt',
            'type' => 'text',
            'placeholder' => 'propriétaire/dépôt',
            'help' => 'Format propriétaire/dépôt, ex. laravel/framework.',
        ]];
    }

    public function items(array $config, string $mode, array $sourceConfig): Collection
    {
        $repo = trim((string) ($sourceConfig['repository'] ?? ''));

        if ($repo === '') {
            return collect();
        }

        $client = $this->client($config);

        return match ($mode) {
            'pull_requests' => $this->mapPullRequests($client->openPullRequests($repo)),
            'issues' => $this->mapIssues($client->openIssues($repo)),
            default => $this->mapCommits($client->recentCommits($repo)),
        };
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function client(array $config): GitHubClient
    {
        return new GitHubClient($config['token'] ?? null);
    }

    /**
     * @param  array<int, array<string, mixed>>  $commits
     * @return Collection<int, PluginListItem>
     */
    private function mapCommits(array $commits): Collection
    {
        return collect($commits)->map(function (array $commit): PluginListItem {
            $sha = (string) ($commit['sha'] ?? '');
            $message = (string) data_get($commit, 'commit.message', '');
            $author = (string) (data_get($commit, 'commit.author.name') ?? data_get($commit, 'author.login') ?? '—');

            return new PluginListItem(
                externalRef: $sha,
                title: Str::of($message)->explode("\n")->first() ?: $sha,
                subtitle: $author.' · '.Str::substr($sha, 0, 7),
                url: (string) ($commit['html_url'] ?? ''),
                icon: 'git-commit',
                timestamp: (string) (data_get($commit, 'commit.author.date') ?? ''),
            );
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $pulls
     * @return Collection<int, PluginListItem>
     */
    private function mapPullRequests(array $pulls): Collection
    {
        return collect($pulls)->map(function (array $pull): PluginListItem {
            $number = (int) ($pull['number'] ?? 0);
            $isDraft = (bool) ($pull['draft'] ?? false);

            return new PluginListItem(
                externalRef: (string) $number,
                title: (string) ($pull['title'] ?? ''),
                subtitle: '#'.$number.' · '.(string) data_get($pull, 'user.login', '—'),
                url: (string) ($pull['html_url'] ?? ''),
                badge: $isDraft ? 'draft' : 'open',
                badgeColor: $isDraft ? 'neutral' : 'green',
                icon: 'git-pull-request',
                timestamp: (string) ($pull['updated_at'] ?? ''),
            );
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $issues
     * @return Collection<int, PluginListItem>
     */
    private function mapIssues(array $issues): Collection
    {
        return collect($issues)->map(function (array $issue): PluginListItem {
            $number = (int) ($issue['number'] ?? 0);
            $comments = (int) ($issue['comments'] ?? 0);

            return new PluginListItem(
                externalRef: (string) $number,
                title: (string) ($issue['title'] ?? ''),
                subtitle: '#'.$number.' · '.(string) data_get($issue, 'user.login', '—'),
                url: (string) ($issue['html_url'] ?? ''),
                badge: $comments > 0 ? $comments.' 💬' : null,
                badgeColor: 'neutral',
                icon: 'circle-dashed',
                timestamp: (string) ($issue['updated_at'] ?? ''),
            );
        });
    }
}
