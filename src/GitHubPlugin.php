<?php

namespace Board\PluginGithub;

use Board\PluginGithub\Mcp\GithubCommitsTool;
use Board\PluginSdk\Contracts\DefinesActivities;
use Board\PluginSdk\Contracts\EnrichesCards;
use Board\PluginSdk\Contracts\Plugin;
use Board\PluginSdk\Contracts\ProvidesListSource;
use Board\PluginSdk\Contracts\ProvidesMcpTools;
use Board\PluginSdk\Contracts\ProvidesOAuth;
use Board\PluginSdk\PluginListItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * GitHub Power-Up: read-only lists (commits/PRs/issues), card enrichment
 * (link a commit/PR/issue to a card), a dedicated activity tab, and an MCP
 * tool. All user-facing strings come from this package's `github::` translations.
 */
class GitHubPlugin implements DefinesActivities, EnrichesCards, Plugin, ProvidesListSource, ProvidesMcpTools, ProvidesOAuth
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
        return __('github::messages.description');
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

    public function configFields(array $config = []): array
    {
        return [
            [
                'key' => 'client_id',
                'label' => __('github::messages.oauth.client_id'),
                'type' => 'text',
                'placeholder' => 'Iv1.xxxxxxxxxxxx',
                'help' => __('github::messages.oauth.client_id_help'),
            ],
            [
                'key' => 'client_secret',
                'label' => __('github::messages.oauth.client_secret'),
                'type' => 'password',
                'help' => __('github::messages.oauth.client_secret_help'),
            ],
        ];
    }

    // --- ProvidesOAuth --------------------------------------------------------

    public function authorizeUrl(): string
    {
        return 'https://github.com/login/oauth/authorize';
    }

    public function tokenUrl(): string
    {
        return 'https://github.com/login/oauth/access_token';
    }

    public function scopes(): array
    {
        return ['repo', 'read:org'];
    }

    public function authorizeParameters(): array
    {
        return ['allow_signup' => 'false'];
    }

    public function resolveAccount(string $accessToken): ?string
    {
        return $this->client(['token' => $accessToken])->account()['login'] ?? null;
    }

    // --- ProvidesListSource ---------------------------------------------------

    public function sourceModes(): array
    {
        return [
            ['key' => 'commits', 'label' => __('github::messages.mode.commits')],
            ['key' => 'pull_requests', 'label' => __('github::messages.mode.pull_requests')],
            ['key' => 'issues', 'label' => __('github::messages.mode.issues')],
        ];
    }

    public function listConfigFields(array $config = []): array
    {
        $repos = $this->client($config)->listRepos();

        if ($repos !== []) {
            return [[
                'key' => 'repository',
                'label' => __('github::messages.field.repository'),
                'type' => 'select',
                'options' => array_map(fn (array $repo): array => [
                    'value' => $repo['full_name'],
                    'label' => $repo['full_name'].($repo['private'] ? ' 🔒' : ''),
                ], $repos),
            ]];
        }

        return [[
            'key' => 'repository',
            'label' => __('github::messages.field.repository'),
            'type' => 'text',
            'placeholder' => __('github::messages.field.repository_placeholder'),
            'help' => __('github::messages.field.repository_help'),
        ]];
    }

    public function items(array $config, string $mode, array $sourceConfig): Collection
    {
        $repo = trim((string) ($sourceConfig['repository'] ?? ''));

        if ($repo === '') {
            return collect();
        }

        $limit = max(1, (int) ($sourceConfig['limit'] ?? 15));
        $client = $this->client($config);

        return match ($mode) {
            'pull_requests' => $this->mapPullRequests($client->openPullRequests($repo, $limit)),
            'issues' => $this->mapIssues($client->openIssues($repo, $limit)),
            default => $this->mapCommits($client->recentCommits($repo, $limit)),
        };
    }

    // --- EnrichesCards --------------------------------------------------------

    public function cardRefTypes(): array
    {
        return [
            ['key' => 'commit', 'label' => __('github::messages.ref.commit')],
            ['key' => 'pull_request', 'label' => __('github::messages.ref.pull_request')],
            ['key' => 'issue', 'label' => __('github::messages.ref.issue')],
        ];
    }

    public function resolveCardRef(array $config, string $type, string $rawRef): ?array
    {
        [$repo, $ref] = $this->parseRef($rawRef);

        if ($repo === null || $ref === null) {
            return null;
        }

        $client = $this->client($config);

        return match ($type) {
            'pull_request' => $this->mapPullRef($client->pullRequest($repo, (int) $ref)),
            'issue' => $this->mapIssueRef($client->issue($repo, (int) $ref)),
            default => $this->mapCommitRef($client->commit($repo, $ref)),
        };
    }

    // --- DefinesActivities ----------------------------------------------------

    public function activityTab(): array
    {
        return ['key' => 'github', 'label' => __('github::messages.activity.tab')];
    }

    public function activityTypes(): array
    {
        return ['github.ref_linked'];
    }

    public function describeActivity(string $type, array $properties): ?string
    {
        if ($type !== 'github.ref_linked') {
            return null;
        }

        return __('github::messages.activity.linked', [
            'type' => __('github::messages.ref.'.($properties['ref_type'] ?? 'commit')),
            'title' => $properties['title'] ?? ($properties['ref_id'] ?? ''),
        ]);
    }

    // --- ProvidesMcpTools -----------------------------------------------------

    public function mcpTools(): array
    {
        return [GithubCommitsTool::class];
    }

    // --- internals ------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $config
     */
    private function client(array $config): GitHubClient
    {
        return new GitHubClient($config['token'] ?? null);
    }

    /**
     * Parse "owner/repo@sha", "owner/repo#123" or a github.com URL into
     * [repo, ref]. Returns [null, null] when unrecognized.
     *
     * @return array{0: string|null, 1: string|null}
     */
    private function parseRef(string $raw): array
    {
        $raw = trim($raw);

        if (preg_match('#github\.com/([^/]+)/([^/]+)/(?:commit|pull|issues)/([A-Za-z0-9]+)#', $raw, $m)) {
            return [$m[1].'/'.$m[2], $m[3]];
        }

        if (preg_match('#^([^/\s]+/[^/\s@\#]+)@([A-Za-z0-9]+)$#', $raw, $m)) {
            return [$m[1], $m[2]];
        }

        if (preg_match('#^([^/\s]+/[^/\s@\#]+)\#(\d+)$#', $raw, $m)) {
            return [$m[1], $m[2]];
        }

        return [null, null];
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

    /**
     * @param  array<string, mixed>|null  $commit
     * @return array<string, mixed>|null
     */
    private function mapCommitRef(?array $commit): ?array
    {
        if ($commit === null || empty($commit['sha'])) {
            return null;
        }

        $sha = (string) $commit['sha'];

        return [
            'ref_id' => $sha,
            'title' => Str::of((string) data_get($commit, 'commit.message', ''))->explode("\n")->first() ?: $sha,
            'subtitle' => (string) (data_get($commit, 'commit.author.name') ?? '—').' · '.Str::substr($sha, 0, 7),
            'url' => (string) ($commit['html_url'] ?? ''),
            'icon' => 'git-commit',
            'timestamp' => (string) data_get($commit, 'commit.author.date', ''),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $pull
     * @return array<string, mixed>|null
     */
    private function mapPullRef(?array $pull): ?array
    {
        if ($pull === null || empty($pull['number'])) {
            return null;
        }

        $merged = ! empty($pull['merged_at']);
        $state = (string) ($pull['state'] ?? 'open');

        return [
            'ref_id' => (string) $pull['number'],
            'title' => (string) ($pull['title'] ?? ''),
            'subtitle' => '#'.$pull['number'].' · '.(string) data_get($pull, 'user.login', '—'),
            'url' => (string) ($pull['html_url'] ?? ''),
            'badge' => $merged ? 'merged' : $state,
            'badge_color' => $merged ? 'indigo' : ($state === 'closed' ? 'red' : 'green'),
            'icon' => 'git-pull-request',
            'timestamp' => (string) ($pull['updated_at'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $issue
     * @return array<string, mixed>|null
     */
    private function mapIssueRef(?array $issue): ?array
    {
        if ($issue === null || empty($issue['number'])) {
            return null;
        }

        $state = (string) ($issue['state'] ?? 'open');

        return [
            'ref_id' => (string) $issue['number'],
            'title' => (string) ($issue['title'] ?? ''),
            'subtitle' => '#'.$issue['number'].' · '.(string) data_get($issue, 'user.login', '—'),
            'url' => (string) ($issue['html_url'] ?? ''),
            'badge' => $state,
            'badge_color' => $state === 'closed' ? 'red' : 'green',
            'icon' => 'circle-dashed',
            'timestamp' => (string) ($issue['updated_at'] ?? ''),
        ];
    }
}
