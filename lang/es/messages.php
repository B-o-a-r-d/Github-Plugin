<?php

return [
    'description' => 'Listas de solo lectura de los commits, pull requests e issues de un repositorio de GitHub.',
    'mode' => [
        'commits' => 'Últimos commits',
        'pull_requests' => 'Pull requests abiertas',
        'issues' => 'Issues abiertas',
    ],
    'field' => [
        'repository' => 'Repositorio',
        'repository_help' => 'Formato propietario/repo, p. ej. laravel/framework.',
        'repository_placeholder' => 'propietario/repo',
    ],
    'oauth' => [
        'client_id' => 'GitHub OAuth · Client ID',
        'client_id_help' => 'Desde GitHub → Settings → Developer settings → OAuth Apps.',
        'client_secret' => 'GitHub OAuth · Client Secret',
        'client_secret_help' => 'Déjalo vacío para conservar el valor guardado.',
    ],
    'ref' => [
        'commit' => 'commit',
        'pull_request' => 'pull request',
        'issue' => 'issue',
    ],
    'activity' => [
        'tab' => 'GitHub',
        'linked' => 'vinculó el/la :type «:title»',
    ],
];
