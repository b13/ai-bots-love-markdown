<?php

declare(strict_types=1);

/*
 * This file is part of TYPO3 CMS-based extension "ai_bots_love_markdown" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace B13\AiBotsLoveMarkdown\Tests\Unit\Routing;

use B13\AiBotsLoveMarkdown\Routing\PageTypeMap;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class PageTypeMapTest extends UnitTestCase
{
    #[Test]
    public function offKeepsExistingBehaviour(): void
    {
        self::assertSame(
            ['sitemap.xml' => 1533906435, 'ai-bots-love.md' => 2026],
            PageTypeMap::apply(['sitemap.xml' => 1533906435], []),
        );
    }

    #[Test]
    public function offDoesNotAddWhenTypeNumAlreadyMapped(): void
    {
        self::assertSame(
            ['custom.md' => 2026],
            PageTypeMap::apply(['custom.md' => 2026], []),
        );
    }

    #[Test]
    public function onPutsDotMdFirst(): void
    {
        $map = PageTypeMap::apply(
            ['sitemap.xml' => 1533906435],
            ['ai_bots_love_markdown.llmsTxt.enabled' => true],
        );

        self::assertSame(['.md', 'sitemap.xml', 'ai-bots-love.md', 'llms.txt'], array_keys($map));
        self::assertSame([2026, 1533906435, 2026, 2027], array_values($map));
    }

    #[Test]
    public function onKeepsIntegratorSuffix(): void
    {
        $map = PageTypeMap::apply(
            ['ai-bots-love.md' => 2026, '.md' => 2026, 'llms.txt' => 2027],
            ['ai_bots_love_markdown.llmsTxt.enabled' => true],
        );

        self::assertSame(['.md' => 2026, 'ai-bots-love.md' => 2026, 'llms.txt' => 2027], $map);
    }

    #[Test]
    public function readsNestedSettings(): void
    {
        $map = PageTypeMap::apply([], [
            'ai_bots_love_markdown' => [
                'pageTypeTypeNum' => 4000,
                'llmsTxt' => ['enabled' => true, 'typeNum' => 3000],
            ],
        ]);

        self::assertSame(['.md' => 4000, 'ai-bots-love.md' => 4000, 'llms.txt' => 3000], $map);
    }

    #[Test]
    public function stringFalseKeepsFeatureOff(): void
    {
        self::assertFalse(PageTypeMap::isLlmsTxtEnabled(['ai_bots_love_markdown.llmsTxt.enabled' => 'false']));
        self::assertTrue(PageTypeMap::isLlmsTxtEnabled(['ai_bots_love_markdown.llmsTxt.enabled' => '1']));
    }
}
