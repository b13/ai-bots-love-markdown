<?php

declare(strict_types=1);

/*
 * This file is part of TYPO3 CMS-based extension "ai_bots_love_markdown" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace B13\AiBotsLoveMarkdown;

use TYPO3\CMS\Core\Site\Entity\Site;

final class MarkdownPageType
{
    public const TYPE_NUM = 2026;
    public const SUFFIX = 'ai-bots-love.md';

    public function getSuffix(Site $site): string
    {
        return $site->getSettings()->get('ai_bots_love_markdown.pageTypeSuffix', self::SUFFIX);
    }

    public function getTypeNum(Site $site): int
    {
        return $site->getSettings()->get('ai_bots_love_markdown.pageTypeTypeNum', self::TYPE_NUM);
    }
}
