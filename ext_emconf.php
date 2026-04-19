<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'MCP Docs',
    'description' => 'MCP documentation plugin for TYPO3 CMS',
    'category' => 'plugin',
    'author' => 'Marek Skopal',
    'author_email' => 'skopal.marek@gmail.com',
    'state' => 'stable',
    'version' => '1.1.1',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-14.4.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
