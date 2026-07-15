<?php

return [
    'description' => 'Read-only lists of a GitHub repository\'s commits, pull requests and issues.',
    'mode' => [
        'commits' => 'Recent commits',
        'pull_requests' => 'Open pull requests',
        'issues' => 'Open issues',
    ],
    'field' => [
        'repository' => 'Repository',
        'repository_help' => 'Format owner/repo, e.g. laravel/framework.',
        'repository_placeholder' => 'owner/repo',
    ],
    'oauth' => [
        'client_id' => 'GitHub OAuth · Client ID',
        'client_id_help' => 'From GitHub → Settings → Developer settings → OAuth Apps.',
        'client_secret' => 'GitHub OAuth · Client Secret',
        'client_secret_help' => 'Leave blank to keep the stored value.',
    ],
    'ref' => [
        'commit' => 'commit',
        'pull_request' => 'pull request',
        'issue' => 'issue',
    ],
    'activity' => [
        'tab' => 'GitHub',
        'linked' => 'linked the :type ":title"',
    ],
    'automation' => [
        'create_issue' => 'Create a GitHub issue',
        'repo' => 'Repository (owner/repo)',
        'title' => 'Title ({card}, {board}, {list} are replaced — empty = card title)',
        'body' => 'Description (same placeholders, optional)',
        'labels' => 'Labels (comma-separated, optional)',
        'issue_created' => 'GitHub issue created',
        'open_issue' => 'Open the issue',
    ],
];
