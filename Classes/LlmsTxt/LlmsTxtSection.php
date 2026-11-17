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

final readonly class LlmsTxtSection
{
    /**
     * @param list<LlmsTxtEntry> $entries
     */
    public function __construct(
        public string $title,
        public array $entries,
    ) {}
}
