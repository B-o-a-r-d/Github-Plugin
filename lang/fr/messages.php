<?php

return [
    'description' => 'Listes en lecture seule des commits, pull requests et issues d\'un dépôt GitHub.',
    'mode' => [
        'commits' => 'Derniers commits',
        'pull_requests' => 'Pull requests ouvertes',
        'issues' => 'Issues ouvertes',
    ],
    'field' => [
        'repository' => 'Dépôt',
        'repository_help' => 'Format propriétaire/dépôt, ex. laravel/framework.',
        'repository_placeholder' => 'propriétaire/dépôt',
    ],
    'oauth' => [
        'client_id' => 'GitHub OAuth · Client ID',
        'client_id_help' => 'Depuis GitHub → Settings → Developer settings → OAuth Apps.',
        'client_secret' => 'GitHub OAuth · Client Secret',
        'client_secret_help' => 'Laissez vide pour conserver la valeur enregistrée.',
    ],
    'ref' => [
        'commit' => 'commit',
        'pull_request' => 'pull request',
        'issue' => 'issue',
    ],
    'activity' => [
        'tab' => 'GitHub',
        'linked' => 'a lié le/la :type « :title »',
    ],
    'automation' => [
        'create_issue' => 'Créer une issue GitHub',
        'repo' => 'Dépôt (owner/repo)',
        'title' => "Titre ({card}, {board}, {list} sont remplacés — vide = titre de la carte)",
        'body' => 'Description (mêmes gabarits, optionnel)',
        'labels' => 'Labels (séparés par des virgules, optionnel)',
        'issue_created' => 'Issue GitHub créée',
        'open_issue' => "Ouvrir l'issue",
    ],
];
