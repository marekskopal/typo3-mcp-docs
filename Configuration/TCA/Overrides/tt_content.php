<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') or die;

$pluginSignature = ExtensionUtility::registerPlugin('MsMcpDocs', 'McpDocs', 'MCP Docs');
ExtensionManagementUtility::addPiFlexFormValue('*', 'FILE:EXT:ms_mcp_docs/Configuration/FlexForms/Flexform.xml', $pluginSignature);
ExtensionManagementUtility::addToAllTCAtypes(
    'tt_content',
    '--div--;LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:tabs.plugin, pi_flexform',
    $pluginSignature,
    'after:palette:headers',
);
