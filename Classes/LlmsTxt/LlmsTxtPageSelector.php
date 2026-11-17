<?php

declare(strict_types=1);

/*
 * This file is part of TYPO3 CMS-based extension "ai_bots_love_markdown" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace B13\AiBotsLoveMarkdown\LlmsTxt;

use B13\AiBotsLoveMarkdown\Service\MarkdownExclusionService;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Decides which pages end up in llms.txt and in which section.
 *
 * - Sections are the level-1 pages below the site root (tree order).
 * - All listed descendants of a level-1 page (any depth) go into its section,
 *   depth-first in tree order.
 * - `pages.llms_txt` inherits down the tree (root = Include).
 * - Hard rules (doktype lists, no_search, no_index, markdown disabled, not
 *   available in the requested language) apply per page, cannot be
 *   overridden and do not affect children.
 * - Below a backend user section or the recycler, children inherit "Exclude".
 */
final readonly class LlmsTxtPageSelector
{
    /**
     * Set to true on a page record by the tree loader when the page is not
     * available in the requested language (no translation, l18n_cfg, ...).
     */
    public const UNAVAILABLE_IN_LANGUAGE = '_llmsTxtUnavailableInLanguage';

    private const DOKTYPES_HIDING_SUBTREE = [
        PageRepository::DOKTYPE_BE_USER_SECTION,
        255, // recycler (constant removed in TYPO3 v14)
    ];

    public function __construct(
        private MarkdownExclusionService $exclusionService,
    ) {}

    /**
     * @param list<array<string, mixed>> $level1Pages page records with a `children` key (same shape, tree order)
     */
    public function select(array $level1Pages, Site $site, LlmsTxtSettings $settings): LlmsTxtStructure
    {
        $sections = [];
        $optional = [];

        foreach ($level1Pages as $level1Page) {
            $entries = [];
            $this->walk($level1Page, LlmsTxtMode::Include, $site, $settings, $entries, $optional);
            if ($entries !== []) {
                $sections[] = new LlmsTxtSection((string)($level1Page['title'] ?? ''), $entries);
            }
        }

        return new LlmsTxtStructure($sections, $optional);
    }

    /**
     * @param list<LlmsTxtEntry> $entries
     * @param list<LlmsTxtEntry> $optional
     */
    private function walk(
        array $page,
        LlmsTxtMode $inherited,
        Site $site,
        LlmsTxtSettings $settings,
        array &$entries,
        array &$optional,
    ): void {
        $own = LlmsTxtMode::fromRecordValue($page['llms_txt'] ?? null);
        $effective = $own === LlmsTxtMode::Inherit ? $inherited : $own;

        if ($effective !== LlmsTxtMode::Exclude && !$this->isHardExcluded($page, $site, $settings)) {
            $entry = new LlmsTxtEntry(
                (int)($page['uid'] ?? 0),
                (string)($page['title'] ?? ''),
                $this->note($page),
            );
            if ($effective === LlmsTxtMode::Optional) {
                $optional[] = $entry;
            } else {
                $entries[] = $entry;
            }
        }

        // Pages below a backend user section or the recycler are not meant for
        // frontend visitors: their subtree inherits "Exclude" unless a subpage
        // explicitly sets another value.
        $inheritedByChildren = in_array((int)($page['doktype'] ?? 0), self::DOKTYPES_HIDING_SUBTREE, true)
            ? LlmsTxtMode::Exclude
            : $effective;

        foreach ($page['children'] ?? [] as $child) {
            $this->walk($child, $inheritedByChildren, $site, $settings, $entries, $optional);
        }
    }

    private function isHardExcluded(array $page, Site $site, LlmsTxtSettings $settings): bool
    {
        return (bool)($page[self::UNAVAILABLE_IN_LANGUAGE] ?? false)
            || $this->exclusionService->isExcluded($page, $site)
            || in_array((int)($page['doktype'] ?? 0), $settings->excludedDoktypes, true)
            || (bool)($page['no_search'] ?? false)
            || (bool)($page['no_index'] ?? false);
    }

    private function note(array $page): string
    {
        $description = trim((string)($page['description'] ?? ''));
        if ($description !== '') {
            return $description;
        }

        return trim((string)($page['abstract'] ?? ''));
    }
}
