<?php

declare(strict_types=1);

use MarekSkopal\MsMcpDocs\Controller\McpDocsController;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') or die();

ExtensionUtility::configurePlugin(
    'MsMcpDocs',
    'McpDocs',
    [McpDocsController::class => 'list'],
    [],
    ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT,
);

$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1713350000] = [
    'nodeName' => 'oauthStatus',
    'priority' => 40,
    'class' => \MarekSkopal\MsMcpDocs\Form\Element\OAuthStatusElement::class,
];

$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['msmcpdocs'] ??= [
    'frontend' => \TYPO3\CMS\Core\Cache\Frontend\VariableFrontend::class,
    'backend' => \TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend::class,
    'options' => [
        'defaultLifetime' => 86400,
    ],
];
