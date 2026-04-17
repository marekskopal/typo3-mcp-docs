<?php

declare(strict_types=1);

use MarekSkopal\MsMcpDocs\Controller\OAuthCallbackController;

return [
    'msmcpdocs_oauth_initiate' => [
        'path' => '/msmcpdocs/oauth/initiate',
        'target' => OAuthCallbackController::class . '::initiateAction',
    ],
    'msmcpdocs_oauth_callback' => [
        'path' => '/msmcpdocs/oauth/callback',
        'target' => OAuthCallbackController::class . '::callbackAction',
        'access' => 'public',
    ],
];
