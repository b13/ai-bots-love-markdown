<?php

declare(strict_types=1);

/*
 * This file is part of TYPO3 CMS-based extension "ai_bots_love_markdown" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

defined('TYPO3') or die();

call_user_func(static function (): void {
    $additionalColumns = [
        'markdown_version' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ai_bots_love_markdown/Resources/Private/Language/locallang_db.xlf:pages.markdown_version',
            // Hide on doktypes that are already in the site's `excludedDoktypes`
            // list — the toggle has no effect there, showing it would only confuse
            // editors. Site-aware lookup so projects can override the list.
            'displayCond' => 'USER:B13\\AiBotsLoveMarkdown\\Backend\\TcaCondition\\HideOnExcludedDoktype->match',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                // Field is named in the affirmative ("markdown_version") with
                // default-on, and the BE renders the toggle inverted so the
                // editor sees / sets "Disable Markdown version". Keeps the
                // PHP guard `!$row['markdown_version']` readable.
                'default' => 1,
                'items' => [
                    [
                        'label' => '',
                        'invertStateDisplay' => true,
                    ],
                ],
            ],
        ],
        'llms_txt' => [
            'exclude' => true,
            'label' => 'LLL:EXT:ai_bots_love_markdown/Resources/Private/Language/locallang_db.xlf:pages.llms_txt',
            'description' => 'LLL:EXT:ai_bots_love_markdown/Resources/Private/Language/locallang_db.xlf:pages.llms_txt.description',
            // Inclusion in llms.txt is a structural decision, same for all languages.
            'l10n_mode' => 'exclude',
            // Shown on sysfolders too: the value inherits down the page tree.
            'displayCond' => 'USER:B13\\AiBotsLoveMarkdown\\Backend\\TcaCondition\\ShowLlmsTxtField->match',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'default' => 0,
                'items' => [
                    ['label' => 'LLL:EXT:ai_bots_love_markdown/Resources/Private/Language/locallang_db.xlf:pages.llms_txt.inherit', 'value' => 0],
                    ['label' => 'LLL:EXT:ai_bots_love_markdown/Resources/Private/Language/locallang_db.xlf:pages.llms_txt.include', 'value' => 1],
                    ['label' => 'LLL:EXT:ai_bots_love_markdown/Resources/Private/Language/locallang_db.xlf:pages.llms_txt.optional', 'value' => 2],
                    ['label' => 'LLL:EXT:ai_bots_love_markdown/Resources/Private/Language/locallang_db.xlf:pages.llms_txt.exclude', 'value' => 3],
                ],
            ],
        ],
    ];

    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('pages', $additionalColumns);
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes(
        'pages',
        'markdown_version, llms_txt',
        '',
        'after:no_search',
    );
});
