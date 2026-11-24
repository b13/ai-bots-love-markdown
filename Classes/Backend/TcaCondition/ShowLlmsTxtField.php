<?php

declare(strict_types=1);

/*
 * This file is part of TYPO3 CMS-based extension "ai_bots_love_markdown" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace B13\AiBotsLoveMarkdown\Backend\TcaCondition;

use B13\AiBotsLoveMarkdown\LlmsTxt\LlmsTxtSettings;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\SiteFinder;

/**
 * TCA displayCond user function for `pages.llms_txt`.
 *
 * Only shown when llms.txt is enabled for the page's site. Then shown on
 * sysfolders (the value inherits down the tree, so folders are where
 * subtree decisions are made) and on every doktype that can produce Markdown.
 */
final readonly class ShowLlmsTxtField
{
    private const DOKTYPE_SYSFOLDER = 254;

    public function __construct(
        private HideOnExcludedDoktype $hideOnExcludedDoktype,
        private SiteFinder $siteFinder,
    ) {}

    /**
     * @param array{record: array<string, mixed>} $parameters
     */
    public function match(array $parameters): bool
    {
        $record = $parameters['record'] ?? [];
        // FormEngine passes select values as arrays (e.g. ['254'])
        $doktype = $record['doktype'] ?? 0;
        if (is_array($doktype)) {
            $doktype = reset($doktype);
        }
        $record['doktype'] = (int)$doktype;

        if (!$this->isLlmsTxtEnabled($record)) {
            return false;
        }

        if ($record['doktype'] === self::DOKTYPE_SYSFOLDER) {
            return true;
        }

        return $this->hideOnExcludedDoktype->match(['record' => $record]);
    }

    private function isLlmsTxtEnabled(array $record): bool
    {
        // Existing page: its own uid; new page: the parent pid.
        $pageId = (int)($record['uid'] ?? 0) ?: (int)($record['pid'] ?? 0);
        if ($pageId <= 0) {
            return false;
        }
        try {
            $site = $this->siteFinder->getSiteByPageId($pageId);
        } catch (SiteNotFoundException) {
            return false;
        }

        return LlmsTxtSettings::fromSite($site)->enabled;
    }
}
