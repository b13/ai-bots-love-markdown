<?php

declare(strict_types=1);

/*
 * This file is part of TYPO3 CMS-based extension "ai_bots_love_markdown" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace B13\AiBotsLoveMarkdown\Service;

use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Decides whether a page is excluded from producing a Markdown alternate response.
 *
 * Two mechanisms, both checked:
 *  - Site setting `ai_bots_love_markdown.excludedDoktypes` (comma-separated doktype IDs)
 *  - TCA field `pages.markdown_version` (per-page editorial opt-out — affirmative
 *    field with default 1; the BE renders the toggle inverted as "Disable Markdown
 *    version")
 */
final readonly class MarkdownExclusionService
{
    private const SETTING_KEY = 'ai_bots_love_markdown.excludedDoktypes';

    public function isExcluded(array $pageRecord, ?Site $site): bool
    {
        // Per-page opt-out: when `markdown_version` is explicitly 0 the editor
        // turned the toggle on ("Disable Markdown version" because the BE renders
        // it inverted). Default-on means missing-or-1 keeps markdown enabled.
        if (array_key_exists('markdown_version', $pageRecord)
            && (int)$pageRecord['markdown_version'] === 0
        ) {
            return true;
        }

        $doktype = (int)($pageRecord['doktype'] ?? 0);
        if ($doktype === 0) {
            return false;
        }

        return in_array($doktype, $this->resolveExcludedDoktypes($site), true);
    }

    /**
     * @return int[]
     */
    private function resolveExcludedDoktypes(?Site $site): array
    {
        if ($site === null) {
            return [];
        }

        $raw = trim((string)$site->getSettings()->get(self::SETTING_KEY, ''));
        if ($raw === '') {
            return [];
        }

        $ids = [];
        foreach (explode(',', $raw) as $part) {
            $trimmed = trim($part);
            if ($trimmed !== '' && ctype_digit($trimmed)) {
                $ids[] = (int)$trimmed;
            }
        }

        return $ids;
    }
}
