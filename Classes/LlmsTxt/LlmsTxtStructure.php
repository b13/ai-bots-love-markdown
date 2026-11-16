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

final readonly class LlmsTxtStructure
{
    /**
     * @param list<LlmsTxtSection> $sections
     * @param list<LlmsTxtEntry> $optional
     */
    public function __construct(
        public array $sections,
        public array $optional,
    ) {}
}
