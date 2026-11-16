<?php

declare(strict_types=1);

/*
 * This file is part of TYPO3 CMS-based extension "ai_bots_love_markdown" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace B13\AiBotsLoveMarkdown\Tests\Unit\LlmsTxt;

use B13\AiBotsLoveMarkdown\LlmsTxt\LlmsTxtController;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class LlmsTxtControllerTest extends UnitTestCase
{
    #[Test]
    public function servesOnSiteRoot(): void
    {
        self::assertTrue(LlmsTxtController::isSiteRootRequest(1, 1, null));
    }

    #[Test]
    public function servesOnShortcutTargetOfSiteRoot(): void
    {
        // Root is a shortcut: TYPO3 resolves it and redirects /llms.txt to /<target>/llms.txt
        self::assertTrue(LlmsTxtController::isSiteRootRequest(5, 1, 5));
    }

    #[Test]
    public function refusesOtherPages(): void
    {
        self::assertFalse(LlmsTxtController::isSiteRootRequest(7, 1, null));
        self::assertFalse(LlmsTxtController::isSiteRootRequest(7, 1, 5));
    }
}
