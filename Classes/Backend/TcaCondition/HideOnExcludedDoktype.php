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

use B13\AiBotsLoveMarkdown\Service\MarkdownExclusionService;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\SiteFinder;

/**
 * TCA displayCond user function: hide a field on pages whose doktype is in
 * the site's `ai_bots_love_markdown.excludedDoktypes` list.
 *
 * Used by the `pages.no_markdown_version` toggle so editors don't see a
 * "Disable Markdown version" switch on doktypes that never produce markdown
 * anyway (cart, wishlist, mountpoint, sysfolder, recycler, ...).
 */
final readonly class HideOnExcludedDoktype
{
    public function __construct(
        private MarkdownExclusionService $exclusionService,
        private SiteFinder $siteFinder,
    ) {}

    /**
     * @param array{record: array<string, mixed>} $parameters
     */
    public function match(array $parameters): bool
    {
        $record = $parameters['record'] ?? [];
        $doktype = (int)($record['doktype'] ?? 0);
        if ($doktype === 0) {
            return true;
        }

        // For an existing page, $record['uid'] is the page itself; for a new
        // page-to-be the uid is still missing, so fall back to the parent pid
        // to locate the surrounding site.
        $pageIdForSiteLookup = (int)($record['uid'] ?? 0);
        if ($pageIdForSiteLookup === 0) {
            $pageIdForSiteLookup = (int)($record['pid'] ?? 0);
        }
        if ($pageIdForSiteLookup === 0) {
            return true;
        }

        try {
            $site = $this->siteFinder->getSiteByPageId($pageIdForSiteLookup);
        } catch (SiteNotFoundException) {
            return true;
        }

        // Show the field only when the doktype is NOT in the exclusion list.
        // We synthesize a minimal record so the service can run its plain
        // doktype check without re-reading the page.
        return !$this->exclusionService->isExcluded(
            ['doktype' => $doktype],
            $site,
        );
    }
}
