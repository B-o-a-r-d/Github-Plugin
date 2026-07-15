<?php

namespace Board\PluginGithub;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Thin GitHub REST client. Uses Laravel's HTTP client with conservative
 * timeouts (mirrors the host app's outbound-HTTP conventions). All calls are
 * read-only and fail soft: on any error they return an empty array so a list
 * degrades to "no items" rather than throwing.
 */
class GitHubClient
{
    private const BASE = 'https://api.github.com';

    public function __construct(private readonly ?string $token) {}

    private function request(): PendingRequest
    {
        $request = Http::baseUrl(self::BASE)
            ->acceptJson()
            ->withHeaders([
                'Accept' => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
                'User-Agent' => 'BoardBot/1.0 (+plugin-github)',
            ])
            ->connectTimeout(3)
            ->timeout(8);

        return $this->token ? $request->withToken($this->token) : $request;
    }

    /**
     * Repositories the connected account can access (for the dynamic picker).
     *
     * @return array<int, array{full_name: string, private: bool}>
     */
    public function listRepos(): array
    {
        $response = $this->request()->get('/user/repos', [
            'per_page' => 100,
            'sort' => 'updated',
            'affiliation' => 'owner,collaborator,organization_member',
        ]);

        if (! $response->successful()) {
            return [];
        }

        return collect($response->json())
            ->map(fn (array $repo): array => [
                'full_name' => (string) ($repo['full_name'] ?? ''),
                'private' => (bool) ($repo['private'] ?? false),
            ])
            ->filter(fn (array $repo): bool => $repo['full_name'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recentCommits(string $repo, int $limit = 15): array
    {
        return $this->paged("/repos/{$repo}/commits", [], $limit);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function openPullRequests(string $repo, int $limit = 15): array
    {
        return $this->paged("/repos/{$repo}/pulls", ['state' => 'open'], $limit);
    }

    /**
     * Open issues, excluding pull requests (the issues endpoint returns both).
     *
     * @return array<int, array<string, mixed>>
     */
    public function openIssues(string $repo, int $limit = 15): array
    {
        return $this->paged(
            "/repos/{$repo}/issues",
            ['state' => 'open'],
            $limit,
            fn (array $issue): bool => ! isset($issue['pull_request']),
        );
    }

    /**
     * Fetch up to $limit items across pages (GitHub caps per_page at 100),
     * optionally keeping only those passing $keep. Stops at the last page.
     *
     * @param  array<string, mixed>  $query
     * @param  (callable(array<string, mixed>): bool)|null  $keep
     * @return array<int, array<string, mixed>>
     */
    private function paged(string $path, array $query, int $limit, ?callable $keep = null): array
    {
        $limit = max(1, $limit);
        $perPage = min(100, $limit);
        $items = [];

        for ($page = 1; $page <= 20 && count($items) < $limit; $page++) {
            $batch = $this->collection($path, array_merge($query, ['per_page' => $perPage, 'page' => $page]));

            if ($batch === []) {
                break;
            }

            foreach ($batch as $item) {
                if ($keep === null || $keep($item)) {
                    $items[] = $item;
                }
            }

            if (count($batch) < $perPage) {
                break;
            }
        }

        return array_slice($items, 0, $limit);
    }

    /**
     * A single commit, or null when missing.
     *
     * @return array<string, mixed>|null
     */
    public function commit(string $repo, string $sha): ?array
    {
        return $this->one("/repos/{$repo}/commits/{$sha}");
    }

    /**
     * A single pull request, or null when missing.
     *
     * @return array<string, mixed>|null
     */
    public function pullRequest(string $repo, int $number): ?array
    {
        return $this->one("/repos/{$repo}/pulls/{$number}");
    }

    /**
     * A single issue, or null when missing.
     *
     * @return array<string, mixed>|null
     */
    public function issue(string $repo, int $number): ?array
    {
        return $this->one("/repos/{$repo}/issues/{$number}");
    }

    /**
     * @return array<string, mixed>|null
     */
    private function one(string $path): ?array
    {
        $response = $this->request()->get($path);

        return $response->successful() && is_array($response->json())
            ? $response->json()
            : null;
    }

    /**
     * Create an issue. Throws on HTTP failure so the host's automation
     * pipeline can count and journal the error.
     *
     * @param  array<int, string>  $labels
     * @return array<string, mixed>
     */
    public function createIssue(string $repo, string $title, string $body = '', array $labels = []): array
    {
        return $this->request()
            ->post("/repos/{$repo}/issues", array_filter([
                'title' => $title,
                'body' => $body !== '' ? $body : null,
                'labels' => $labels !== [] ? $labels : null,
            ]))
            ->throw()
            ->json();
    }

    /**
     * The authenticated account, or null if the token is missing/invalid.
     *
     * @return array{login: string, avatar_url?: string}|null
     */
    public function account(): ?array
    {
        $response = $this->request()->get('/user');

        return $response->successful() && isset($response->json()['login'])
            ? $response->json()
            : null;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<int, array<string, mixed>>
     */
    private function collection(string $path, array $query): array
    {
        $response = $this->request()->get($path, $query);

        return $response->successful() && is_array($response->json())
            ? $response->json()
            : [];
    }
}
