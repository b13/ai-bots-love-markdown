<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'AI Bots Love Markdown',
    'description' => 'Serve TYPO3 pages as Markdown for AI crawlers',
    'category' => 'fe',
    'author' => 'b13 GmbH',
    'author_email' => 'typo3@b13.com',
    'state' => 'stable',
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-14.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
