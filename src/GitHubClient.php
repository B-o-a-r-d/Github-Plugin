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
    public function recentCommits(string $repo, int $perPage = 15): array
    {
        return $this->collection("/repos/{$repo}/commits", ['per_page' => $perPage]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function openPullRequests(string $repo, int $perPage = 15): array
    {
        return $this->collection("/repos/{$repo}/pulls", ['state' => 'open', 'per_page' => $perPage]);
    }

    /**
     * Open issues, excluding pull requests (the issues endpoint returns both).
     *
     * @return array<int, array<string, mixed>>
     */
    public function openIssues(string $repo, int $perPage = 15): array
    {
        return collect($this->collection("/repos/{$repo}/issues", ['state' => 'open', 'per_page' => $perPage]))
            ->reject(fn (array $issue): bool => isset($issue['pull_request']))
            ->values()
            ->all();
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
